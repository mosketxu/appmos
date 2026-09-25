<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Contabilidad → Facturas OCR (25-sep-2026): facturas recibidas en PDF de una
 * carpeta → plantilla PluginFacturas.xlsx de SAGE, revisando cada una a mano.
 * El trabajo lo hace Contabilidad/FacturasOcr/facturas_ocr.py (ver su
 * Doc_y_Config/PLAN.md); aquí solo se eligen los parámetros, se lanza y se
 * revisa/corrige lo propuesto con el PDF al lado.
 *
 * Toca OneDrive (renombra y mueve los PDF), así que solo se ejecuta en los PCs
 * autorizados, como Procesos FIQ (config contabilidad.ejecucion_local).
 *
 * Una carpeta por cliente en FacturasOcr con cliente.json (entidad_id, nif,
 * carpeta_recibidas con {AAAA}/{MM}). El ciclo del IVA sale de la entidad
 * (Ciclo Impuesto) y si está vacío se graba allí al elegirlo.
 */
class FacturasOcr extends Component
{
    use WithFileUploads;

    public string $cliente = '';
    public string $carpeta = '';
    public string $ciclo = '';          // 'M' mensual, 'T' trimestral
    public string $presentado = '0';    // IVA del periodo anterior ya presentado
    public string $desde = '';          // cierre mensual: no registrar antes de AAAA-MM
    public bool $analitica = false;
    public string $salida = '';

    /** Factura en revisión (id) y sus datos editables. */
    public string $sel = '';
    public array $form = [];
    public string $motivo = '';

    public string $vista = 'revisar';   // revisar | historico
    public string $filtro = '';
    public string $filtroMes = '';

    /** Explorador de carpetas para elegir la de entrada. */
    public bool $explorando = false;

    /** Ficheros base subidos (listado de proveedores / mayor). */
    public array $subidas = [];

    public function mount(): void
    {
        $this->cliente = $this->clientes()[0] ?? '';
        $this->cargarCliente();
    }

    // ------------------------------------------------------------ cliente

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.facturasocr_dir'), '/');
    }

    protected function pythonBin(): string
    {
        return config('contabilidad.facturasocr_python') ?: $this->baseDir().'/.venv/bin/python';
    }

    /** Carpetas de cliente que el usuario puede ver (mismo criterio que Bancos: cliente.json → entidad). */
    protected function clientes(): array
    {
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        $dirs = [];
        foreach (glob($this->baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $nombre = basename($d);
            if ($nombre === 'Doc_y_Config' || str_starts_with($nombre, '.') || str_starts_with($nombre, '_') || ! is_file($d.'/cliente.json')) {
                continue;
            }
            if ($permitidas !== null) {
                $cfg = json_decode((string) @file_get_contents($d.'/cliente.json'), true);
                if (! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true)) {
                    continue;
                }
            }
            $dirs[] = $nombre;
        }
        natcasesort($dirs);
        return array_values($dirs);
    }

    protected function clienteValido(): bool
    {
        return $this->cliente !== '' && in_array($this->cliente, $this->clientes(), true);
    }

    protected function dirCliente(): string
    {
        return $this->baseDir().'/'.$this->cliente;
    }

    protected function cfg(): array
    {
        return json_decode((string) @file_get_contents($this->dirCliente().'/cliente.json'), true) ?: [];
    }

    protected function entidad(): ?Entidad
    {
        $id = (int) ($this->cfg()['entidad_id'] ?? 0);
        return $id ? Entidad::find($id) : null;
    }

    protected function hayColumnaAnalitica(): bool
    {
        static $hay = null;
        return $hay ??= Schema::hasColumn('entidades', 'contabilidad_analitica');
    }

    protected function estado(): array
    {
        return json_decode((string) @file_get_contents($this->dirCliente().'/facturas.json'), true) ?: ['facturas' => []];
    }

    protected function cargarCliente(): void
    {
        $this->sel = '';
        $this->form = [];
        if (! $this->clienteValido()) {
            return;
        }
        $e = $this->entidad();
        $this->ciclo = match ((int) ($e->cicloimpuesto_id ?? 0)) {
            1 => 'M',
            3, 13 => 'T',
            default => '',
        };
        $this->analitica = $e && $this->hayColumnaAnalitica() ? (bool) $e->contabilidad_analitica : (bool) ($this->cfg()['analitica'] ?? false);
        $ult = $this->estado()['ultimo_analisis'] ?? [];
        $this->carpeta = $ult['carpeta'] ?? ($this->cfg()['carpeta_entrada'] ?? '');
        $this->presentado = ! empty($ult['presentado']) ? '1' : '0';
        $this->desde = (string) ($ult['desde'] ?? '');
    }

    public function updatedCliente(): void
    {
        $this->cargarCliente();
    }

    /** Si la entidad no tiene Ciclo Impuesto, se le graba el elegido aquí. */
    public function updatedCiclo(): void
    {
        $e = $this->entidad();
        if ($e && (int) $e->cicloimpuesto_id === 0 && in_array($this->ciclo, ['M', 'T'], true)) {
            $e->cicloimpuesto_id = $this->ciclo === 'M' ? 1 : 3;
            $e->save();
            $this->salida = "Ciclo del IVA grabado en la entidad {$e->entidad}: ".($this->ciclo === 'M' ? 'Mensual' : 'Trimestral').".\n";
        }
        $this->recalcularFechas();
    }

    public function updatedPresentado(): void
    {
        $this->recalcularFechas();
    }

    public function updatedDesde(): void
    {
        $this->recalcularFechas();
    }

    public function updatedAnalitica(): void
    {
        $e = $this->entidad();
        if ($e && $this->hayColumnaAnalitica()) {
            $e->contabilidad_analitica = $this->analitica;
            $e->save();
        }
    }

    /** Primer día en que se puede registrar hoy (misma regla que primera_fecha_abierta() de facturas_ocr.py). */
    protected function primeraAbierta(): ?\Carbon\Carbon
    {
        if (! in_array($this->ciclo, ['M', 'T'], true)) {
            return null;
        }
        $hoy = now()->startOfDay();
        $ini = $this->ciclo === 'M' ? $hoy->copy()->startOfMonth() : $hoy->copy()->firstOfQuarter();
        $ant = $ini->copy()->subMonthsNoOverflow($this->ciclo === 'M' ? 1 : 3);
        $tope = $ini->copy()->day($this->ciclo === 'M' ? 15 : ($ini->month === 1 ? 30 : 20));
        $abierta = ($this->presentado !== '1' && $hoy->lte($tope)) ? $ant : $ini;
        if (preg_match('/^\d{4}-\d{2}$/', $this->desde)) {
            $d = \Carbon\Carbon::createFromFormat('Y-m-d', $this->desde.'-01')->startOfDay();
            if ($d->gt($abierta)) {
                $abierta = $d;
            }
        }
        return $abierta;
    }

    /** Meses para el combo "registrar a partir de": los últimos meses y el actual. */
    protected function mesesDesde(): array
    {
        $out = [];
        $m = now()->startOfMonth()->subMonths(5);
        for ($i = 0; $i < 7; $i++) {
            $out[$m->format('Y-m')] = ucfirst($m->locale('es')->isoFormat('MMMM YYYY'));
            $m->addMonth();
        }
        return $out;
    }

    // ------------------------------------------------------------ carpeta de entrada

    public function explorar(): void
    {
        $this->explorando = ! $this->explorando;
        if ($this->explorando && ! is_dir($this->carpeta)) {
            $this->carpeta = is_dir('/mnt/e/OneDrive') ? '/mnt/e/OneDrive' : '/';
        }
    }

    public function entrar(string $sub): void
    {
        $nueva = $sub === '..' ? dirname($this->carpeta) : rtrim($this->carpeta, '/').'/'.$sub;
        if (is_dir($nueva)) {
            $this->carpeta = $nueva;
        }
    }

    protected function subcarpetas(): array
    {
        if (! $this->explorando || ! is_dir($this->carpeta)) {
            return [];
        }
        $out = [];
        foreach (scandir($this->carpeta) ?: [] as $f) {
            if ($f[0] !== '.' && is_dir($this->carpeta.'/'.$f)) {
                $out[] = $f;
            }
        }
        natcasesort($out);
        return array_values($out);
    }

    protected function pdfsEnCarpeta(): int
    {
        if (! is_dir($this->carpeta)) {
            return 0;
        }
        return count(array_filter(scandir($this->carpeta) ?: [], fn ($f) => preg_match('/\.pdf$/i', $f) && is_file($this->carpeta.'/'.$f)));
    }

    // ------------------------------------------------------------ procesos

    public function analizar(): void
    {
        $this->salida = '';
        if (! $this->clienteValido()) {
            return;
        }
        if (! in_array($this->ciclo, ['M', 'T'], true)) {
            $this->addError('ciclo', 'Elige si el IVA es mensual o trimestral antes de leer las facturas.');
            return;
        }
        if (! is_dir($this->carpeta)) {
            $this->addError('carpeta', 'No existe la carpeta.');
            return;
        }
        $this->explorando = false;
        $this->ejecutar(array_merge(['analizar', '--carpeta', $this->carpeta], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']),
            1800, 'Leer facturas');
    }

    protected function parametros(): array
    {
        $p = ['--ciclo', $this->ciclo ?: 'T', '--presentado', $this->presentado];
        if (preg_match('/^\d{4}-\d{2}$/', $this->desde)) {
            array_push($p, '--desde', $this->desde);
        }
        return $p;
    }

    public function recalcularFechas(): void
    {
        if (! $this->clienteValido() || ! in_array($this->ciclo, ['M', 'T'], true) || ! is_file($this->dirCliente().'/facturas.json')) {
            return;
        }
        $this->ejecutar(array_merge(['fechas'], $this->parametros()), 60, 'Fechas de registro', false);
        if ($this->sel) {
            $f = $this->factura($this->sel);
            if ($f && isset($f['datos']['fecha_registro'])) {
                $this->form['fecha_registro'] = $f['datos']['fecha_registro'];
            }
        }
    }

    /** Lanza facturas_ocr.py <cliente> ...; devuelve si terminó bien. */
    protected function ejecutar(array $args, int $timeout, string $etiqueta, bool $avisar = true): bool
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            return false;
        }
        $cmd = array_merge([$this->pythonBin(), 'facturas_ocr.py', $this->cliente], $args);
        try {
            $r = Process::path($this->baseDir())->timeout($timeout)->run($cmd);
            $texto = trim($r->output()."\n".$r->errorOutput());
            $this->salida .= $texto."\n";
            if (! $r->successful()) {
                Log::warning("Contabilidad/FacturasOcr: {$etiqueta} salió con código {$r->exitCode()}", ['comando' => $cmd, 'salida' => $texto]);
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\n".$texto);
                return false;
            }
            if ($avisar) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\n".$texto);
            }
            return true;
        } catch (\Throwable $e) {
            $this->salida .= "\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n".get_class($e).': '.$e->getMessage()."\ncomando: ".implode(' ', $cmd);
            report($e);
            return false;
        }
    }

    // ------------------------------------------------------------ revisión

    protected function factura(string $id): ?array
    {
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['id'] === $id) {
                return $f;
            }
        }
        return null;
    }

    /** Cola de revisión: pendientes primero; rechazadas e ilegibles al final. */
    protected function cola(): array
    {
        $fs = array_values(array_filter($this->estado()['facturas'], fn ($f) => $f['estado'] !== 'validada'));
        $orden = ['pendiente' => 0, 'rechazada' => 1, 'ilegible' => 2];
        usort($fs, fn ($a, $b) => ($orden[$a['estado']] ?? 3) <=> ($orden[$b['estado']] ?? 3)
            ?: strnatcasecmp(basename($a['ruta']), basename($b['ruta'])));
        return $fs;
    }

    public function abrir(string $id): void
    {
        $f = $this->factura($id);
        if (! $f) {
            return;
        }
        $this->resetErrorBag();
        $this->sel = $id;
        $this->motivo = (string) ($f['motivo_rechazo'] ?? '');
        $d = $f['datos'] ?? [];
        $lineas = array_values($d['lineas'] ?? []);
        while (count($lineas) < 3) {
            $lineas[] = ['base' => '', 'pct' => '', 'cuota' => ''];
        }
        $d['lineas'] = array_map(fn ($l) => array_map(fn ($v) => $v === null ? '' : (string) $v, $l), $lineas);
        foreach (['cuenta', 'nombre_fichero', 'proveedor', 'cif', 'serie', 'su_factura', 'fecha_expedicion', 'fecha_operacion',
            'fecha_registro', 'contrapartida', 'codigo_transaccion', 'total', 'codigo_retencion', 'base_retencion',
            'pct_retencion', 'cuota_retencion', 'canal', 'comentario', 'cp', 'cod_provincia', 'provincia'] as $k) {
            $d[$k] = isset($d[$k]) && $d[$k] !== null ? (string) $d[$k] : '';
        }
        $this->form = $d;
    }

    public function cerrar(): void
    {
        $this->sel = '';
        $this->form = [];
    }

    public function mover(int $paso): void
    {
        $ids = array_column($this->cola(), 'id');
        $i = array_search($this->sel, $ids, true);
        $j = $i === false ? 0 : $i + $paso;
        if (isset($ids[$j])) {
            $this->abrir($ids[$j]);
        }
    }

    /** Siguiente pendiente tras validar/rechazar (o cerrar si no quedan). */
    protected function siguiente(): void
    {
        foreach ($this->cola() as $f) {
            if ($f['estado'] === 'pendiente') {
                $this->abrir($f['id']);
                return;
            }
        }
        $this->cerrar();
        $this->dispatch('proceso-terminado', mensaje: '✅ No quedan facturas pendientes de revisar.');
    }

    protected function proveedores(): array
    {
        static $cache = [];
        $f = $this->dirCliente().'/Base/proveedores.json';
        return $cache[$f] ??= (json_decode((string) @file_get_contents($f), true)['proveedores'] ?? []);
    }

    protected function patrones(): array
    {
        return json_decode((string) @file_get_contents($this->dirCliente().'/patrones.json'), true) ?: [];
    }

    /** Al cambiar la cuenta se rellenan nombre, CIF, contrapartida... del listado de proveedores. */
    public function updatedFormCuenta(): void
    {
        $cta = trim(explode(' ', trim($this->form['cuenta'] ?? ''))[0]);
        $this->form['cuenta'] = $cta;
        $p = $this->proveedores()[$cta] ?? null;
        $pat = $this->patrones()[$cta] ?? [];
        if (! $p) {
            // Proveedor nuevo ya dado de alta aquí con su 410xxx (aún no está en el listado de SAGE)
            if (! empty($pat['nuevo'])) {
                $this->form['proveedor'] = $pat['proveedor'] ?? '';
                $this->form['cif'] = $pat['cif'] ?? '';
                $this->form['contrapartida'] = $pat['contrapartida'] ?? ($this->form['contrapartida'] ?? '');
                $this->form['nombre_fichero'] = $pat['nombre_fichero'] ?? '';
            }
            return;
        }
        $this->form['proveedor'] = $p['razon'] ?? '';
        $this->form['cif'] = ($p['cif_europeo'] ?? '') ?: (($p['sigla'] ?? '').($p['nif'] ?? ''));
        $this->form['contrapartida'] = $pat['contrapartida'] ?? ($p['contrapartida'] ?? '');
        $this->form['codigo_transaccion'] = (string) ($p['transaccion'] ?? '');
        $this->form['cp'] = $p['cp'] ?? '';
        $this->form['canal'] = $this->analitica ? ($p['canal'] ?? '') : '';
        $this->form['nombre_fichero'] = $pat['nombre_fichero'] ?? '';
        if ($this->form['su_factura'] ?? '') {
            $this->form['comentario'] = mb_substr(trim('Fra '.$this->form['su_factura'].' '.$this->form['proveedor']), 0, 40);
        }
    }

    /**
     * Proveedor nuevo: la siguiente cuenta 410xxx libre (la más alta + 1) contando el listado de SAGE,
     * los nuevos ya validados aquí y los propuestos en otras facturas pendientes (misma regla que
     * cuenta_nueva() de facturas_ocr.py). Si ese CIF ya tiene cuenta nueva, la misma.
     */
    public function cuentaNueva(): void
    {
        $cif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->form['cif'] ?? ''));
        $usadas = array_filter(array_map('strval', array_keys($this->proveedores())), fn ($k) => preg_match('/^410\d{3}$/', $k));
        foreach ($this->patrones() as $k => $pat) {
            if (! empty($pat['nuevo'])) {
                if ($cif !== '' && in_array($cif, $pat['nifs'] ?? [], true)) {
                    $this->form['cuenta'] = (string) $k;
                    $this->updatedFormCuenta();
                    return;
                }
                $usadas[] = (string) $k;
            }
        }
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['id'] !== $this->sel && ! empty($f['datos']['proveedor_nuevo']) && ! empty($f['datos']['cuenta'])) {
                $usadas[] = (string) $f['datos']['cuenta'];
            }
        }
        $nums = array_map('intval', array_filter($usadas, fn ($k) => preg_match('/^410\d{3}$/', $k)));
        $this->form['cuenta'] = (string) ($nums ? max($nums) + 1 : 410001);
    }

    /** Recalcula la cuota de una línea con su base y tipo. */
    public function cuota(int $i): void
    {
        $b = $this->num($this->form['lineas'][$i]['base'] ?? '');
        $t = $this->num($this->form['lineas'][$i]['pct'] ?? '');
        if ($b !== null && $t !== null) {
            $this->form['lineas'][$i]['cuota'] = number_format(round($b * $t / 100, 2), 2, '.', '');
        }
    }

    protected function num($v): ?float
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }

    protected function esIsp(): bool
    {
        $cod = (string) ($this->proveedores()[$this->form['cuenta'] ?? '']['codigo_iva'] ?? '');
        return in_array($cod, ['910', '921', '810', '821'], true);
    }

    /** Total − (bases + cuotas − retención); con inversión del sujeto pasivo el total es la base. */
    protected function descuadre(): ?float
    {
        $total = $this->num($this->form['total'] ?? '');
        if ($total === null) {
            return null;
        }
        $isp = $this->esIsp();
        $suma = 0;
        foreach ($this->form['lineas'] ?? [] as $l) {
            $suma += ($this->num($l['base'] ?? '') ?? 0) + ($isp ? 0 : ($this->num($l['cuota'] ?? '') ?? 0));
        }
        $suma -= $this->num($this->form['cuota_retencion'] ?? '') ?? 0;
        return round($total - $suma, 2);
    }

    public function validar(): void
    {
        $this->resetErrorBag();
        if (! $this->sel) {
            return;
        }
        $datos = $this->form;
        foreach (['total', 'base_retencion', 'pct_retencion', 'cuota_retencion'] as $k) {
            $datos[$k] = $this->num($datos[$k] ?? '');
        }
        $datos['lineas'] = array_values(array_filter(array_map(fn ($l) => [
            'base' => $this->num($l['base'] ?? ''), 'pct' => $this->num($l['pct'] ?? ''), 'cuota' => $this->num($l['cuota'] ?? ''),
        ], $datos['lineas'] ?? []), fn ($l) => $l['base'] !== null));
        $tmp = storage_path('app/facturasocr_'.uniqid().'.json');
        file_put_contents($tmp, json_encode($datos, JSON_UNESCAPED_UNICODE));
        $this->salida = '';
        $ok = $this->ejecutar(['validar', $this->sel, '--datos', $tmp], 120, 'Validar', false);
        @unlink($tmp);
        if (! $ok) {
            $this->addError('validar', trim($this->salida));
            return;
        }
        $this->siguiente();
    }

    public function rechazar(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        if ($this->ejecutar(['rechazar', $this->sel, '--motivo', $this->motivo], 60, 'Rechazar', false)) {
            $this->siguiente();
        }
    }

    public function reabrir(string $id): void
    {
        $this->salida = '';
        $this->ejecutar(['reabrir', $id], 60, 'Reabrir', false);
    }

    public function releerOcr(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        if ($this->ejecutar(array_merge(['ocr', $this->sel], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 300, 'Leer con OCR', false)) {
            $this->abrir($this->sel);
        } else {
            $this->addError('validar', trim($this->salida));
        }
    }

    // ------------------------------------------------------------ ficheros

    /** Listado de proveedores o mayor de SAGE: se guardan en <Cliente>/Base y se rehace proveedores.json. */
    public function updatedSubidas(): void
    {
        if (! $this->clienteValido()) {
            return;
        }
        $this->salida = '';
        $dir = $this->dirCliente().'/Base';
        @mkdir($dir, 0775, true);
        foreach ($this->subidas as $f) {
            $nombre = $f->getClientOriginalName();
            if (! preg_match('/lisProveedores|^Mayor/i', $nombre) || ! preg_match('/\.xlsx$/i', $nombre)) {
                $this->salida .= "⚠️ {$nombre}: no es ni el listado de proveedores (…lisProveedores….xlsx) ni un mayor (Mayor….xlsx).\n";
                continue;
            }
            copy($f->getRealPath(), $dir.'/'.$nombre);
            $this->salida .= "Guardado Base/{$nombre}.\n";
        }
        $this->subidas = [];
        if (config('contabilidad.ejecucion_local')) {
            $r = Process::path($this->baseDir())->timeout(300)->run([$this->pythonBin(), 'facturas_base.py', $this->cliente, '--forzar']);
            $this->salida .= trim($r->output()."\n".$r->errorOutput())."\n";
        }
    }

    public function descargar(string $relativa)
    {
        if (! $this->clienteValido()) {
            return null;
        }
        $raiz = realpath($this->dirCliente());
        $ruta = realpath($raiz.'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta)) {
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    protected function excels(): array
    {
        $out = [];
        foreach (glob($this->dirCliente().'/Output/*.xlsx') ?: [] as $f) {
            if (! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        rsort($out);
        return $out;
    }

    protected function base(): array
    {
        $f = $this->dirCliente().'/Base/proveedores.json';
        if (! is_file($f)) {
            return [];
        }
        $d = json_decode((string) file_get_contents($f), true) ?: [];
        return ['generado' => $d['generado'] ?? '', 'origen' => array_keys($d['origen'] ?? []), 'n' => count($d['proveedores'] ?? []),
            'difieren' => count($d['contrapartida_difiere'] ?? [])];
    }

    public function render()
    {
        $valido = $this->clienteValido();
        $estado = $valido ? $this->estado() : ['facturas' => []];
        $todas = $estado['facturas'];
        $validadas = array_values(array_filter($todas, fn ($f) => $f['estado'] === 'validada'));
        if ($this->filtro !== '') {
            $q = mb_strtolower($this->filtro);
            $validadas = array_values(array_filter($validadas, fn ($f) => str_contains(mb_strtolower(
                ($f['datos']['proveedor'] ?? '').' '.($f['datos']['cuenta'] ?? '').' '.($f['datos']['su_factura'] ?? '').' '.basename($f['ruta'])), $q)));
        }
        if ($this->filtroMes !== '') {
            $validadas = array_values(array_filter($validadas, fn ($f) => str_starts_with($f['datos']['fecha_registro'] ?? '', $this->filtroMes)));
        }
        usort($validadas, fn ($a, $b) => strcmp($b['validada_el'] ?? '', $a['validada_el'] ?? ''));
        $mesesReg = array_values(array_unique(array_map(fn ($f) => substr($f['datos']['fecha_registro'] ?? '', 0, 7),
            array_filter($todas, fn ($f) => $f['estado'] === 'validada'))));
        rsort($mesesReg);
        $actual = $this->sel ? $this->factura($this->sel) : null;
        $cola = $valido ? $this->cola() : [];

        return view('livewire.contabilidad.facturas-ocr', [
            'clientes' => $this->clientes(),
            'cola' => $cola,
            'cuenta' => array_count_values(array_column($todas, 'estado')),
            'validadas' => $validadas,
            'mesesReg' => $mesesReg,
            'actual' => $actual,
            'posicion' => $actual ? array_search($this->sel, array_column($cola, 'id'), true) : false,
            'proveedores' => $valido && $this->sel ? $this->proveedores() : [],
            'isp' => $this->sel ? $this->esIsp() : false,
            'esNuevo' => $this->sel && ($this->form['cuenta'] ?? '') !== '' && ! isset($this->proveedores()[$this->form['cuenta']]),
            'primeraAbierta' => $this->primeraAbierta(),
            'mesesDesde' => $this->mesesDesde(),
            'subcarpetas' => $this->subcarpetas(),
            'pdfs' => $valido ? $this->pdfsEnCarpeta() : 0,
            'excels' => $valido ? $this->excels() : [],
            'base' => $valido ? $this->base() : [],
            'descuadre' => $this->sel ? $this->descuadre() : null,
            'entidad' => $valido ? $this->entidad() : null,
            'hayAnalitica' => $this->hayColumnaAnalitica(),
        ]);
    }
}
