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
    public string $periodo = '';        // periodo fiscal en el que entran: '2026-3T' o '2026-09'
    public string $cierre = '';         // cierre mensual: no registrar antes de AAAA-MM (dentro del periodo)
    public bool $analitica = false;
    public string $salida = '';

    /** Factura en revisión (id) y sus datos editables. */
    public string $sel = '';
    public array $form = [];
    public string $motivo = '';

    public string $vista = 'revisar';   // revisar | historico | duplicadas | proveedores
    public string $filtro = '';
    public string $filtroMes = '';

    // Pestaña Proveedores: buscar y editar lo contable de cada proveedor (va a patrones.json)
    public string $filtroProv = '';
    public string $provSel = '';
    public array $provForm = [];

    /** Hay cambios en el formulario de la factura abierta: se guardan al final de la petición. */
    protected bool $sucio = false;

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
        // El de storage/app (disco de Linux, lo crea el hook de sincronización) arranca ~10 veces más
        // rápido que el .venv de la carpeta en /mnt/e o /mnt/f: se nota en cada Validar
        $rapido = storage_path('app/venv-facturasocr/bin/python');
        return config('contabilidad.facturasocr_python') ?: (is_executable($rapido) ? $rapido : $this->baseDir().'/.venv/bin/python');
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

    /**
     * Carpeta compartida del cliente (OneDrive, "datos" en cliente.json): estado, aprendido, textos,
     * Excel y ficheros base, para que todos los PCs vean lo mismo (26-sep-2026). Misma regla que
     * dir_datos() de facturas_base.py.
     */
    public static function rutaDatos(string $dirCliente): string
    {
        $cfg = json_decode((string) @file_get_contents($dirCliente.'/cliente.json'), true) ?: [];
        $d = (string) ($cfg['datos'] ?? '');
        if (str_starts_with($d, '{OneDrive}')) {
            foreach (['e', 'f', 'd', 'c', 'g'] as $u) {
                if (is_dir("/mnt/{$u}/OneDrive")) {
                    return "/mnt/{$u}/OneDrive".substr($d, strlen('{OneDrive}'));
                }
            }
        }
        return $d !== '' ? $d : $dirCliente;
    }

    protected function dirDatos(): string
    {
        return self::rutaDatos($this->dirCliente());
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
        return json_decode((string) @file_get_contents($this->dirDatos().'/facturas.json'), true) ?: ['facturas' => []];
    }

    protected function cargarCliente(): void
    {
        $this->sel = '';
        $this->propuestaCif = null;
        $this->form = [];
        if (! $this->clienteValido()) {
            return;
        }
        if (realpath($this->dirDatos()) !== realpath($this->dirCliente()) && config('contabilidad.ejecucion_local')
            && (is_file($this->dirCliente().'/facturas.json') || ! is_dir($this->dirDatos()))) {
            // Queda estado en la carpeta local (de antes de usar OneDrive): facturas_base.py lo pasa allí
            Process::path($this->baseDir())->timeout(300)->run([$this->pythonBin(), 'facturas_base.py', $this->cliente]);
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
        $this->periodo = (string) ($ult['periodo'] ?? '');
        if (! array_key_exists($this->periodo, $this->periodos())) {
            $this->periodo = $this->periodoActual();
        }
        $this->cierre = (string) ($ult['cierre'] ?? '');
        if (! array_key_exists($this->cierre, $this->mesesCierre())) {
            $this->cierre = '';
        }
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
        if (! array_key_exists($this->periodo, $this->periodos())) {
            $this->periodo = $this->periodoActual();
            $this->cierre = '';
        }
        $this->recalcularFechas();
    }

    public function updatedPeriodo(): void
    {
        if (! array_key_exists($this->cierre, $this->mesesCierre())) {
            $this->cierre = '';
        }
        $this->recalcularFechas();
    }

    public function updatedCierre(): void
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

    protected function periodoActual(): string
    {
        $h = now();
        return $this->ciclo === 'M' ? $h->format('Y-m') : $h->year.'-'.$h->quarter.'T';
    }

    /** Periodos fiscales para el combo: el actual, el siguiente y los anteriores (último año). */
    protected function periodos(): array
    {
        $out = [];
        if ($this->ciclo === 'M') {
            $m = now()->startOfMonth()->addMonth();
            for ($i = 0; $i < 14; $i++) {
                $out[$m->format('Y-m')] = ucfirst($m->locale('es')->isoFormat('MMMM YYYY'));
                $m->subMonth();
            }
        } elseif ($this->ciclo === 'T') {
            $m = now()->firstOfQuarter()->addMonths(3);
            for ($i = 0; $i < 6; $i++) {
                $out[$m->year.'-'.$m->quarter.'T'] = $m->quarter.'T '.$m->year;
                $m->subMonths(3);
            }
        }
        return $out;
    }

    /** [primer día, último día] del periodo elegido. */
    protected function rangoPeriodo(): ?array
    {
        if (preg_match('/^(\d{4})-([1-4])T$/', $this->periodo, $m)) {
            $ini = \Carbon\Carbon::create((int) $m[1], 3 * (int) $m[2] - 2, 1)->startOfDay();
            return [$ini, $ini->copy()->addMonths(2)->endOfMonth()];
        }
        if (preg_match('/^\d{4}-\d{2}$/', $this->periodo)) {
            $ini = \Carbon\Carbon::createFromFormat('Y-m-d', $this->periodo.'-01')->startOfDay();
            return [$ini, $ini->copy()->endOfMonth()];
        }
        return null;
    }

    /** Meses del periodo para el cierre mensual (solo con IVA trimestral). */
    protected function mesesCierre(): array
    {
        $r = $this->ciclo === 'T' ? $this->rangoPeriodo() : null;
        if (! $r) {
            return [];
        }
        $out = [];
        for ($m = $r[0]->copy(); $m->lte($r[1]); $m->addMonth()) {
            $out[$m->format('Y-m')] = ucfirst($m->locale('es')->isoFormat('MMMM YYYY'));
        }
        return $out;
    }

    /** Primer día en que se registra: el del periodo o, con cierre mensual, el primero de ese mes. */
    protected function primeraAbierta(): ?\Carbon\Carbon
    {
        $r = $this->rangoPeriodo();
        if (! $r) {
            return null;
        }
        if (array_key_exists($this->cierre, $this->mesesCierre())) {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $this->cierre.'-01')->startOfDay();
        }
        return $r[0];
    }

    /** Se puede pegar la ruta tal cual la copia el Explorador de Windows (E:\OneDrive\...). */
    public function updatedCarpeta(): void
    {
        $c = trim($this->carpeta, " \t\"'");
        if (preg_match('/^[A-Za-z]:/', $c)) {
            $c = $this->aLinux($c);
        }
        $this->carpeta = rtrim($c, '/') ?: $c;
    }

    protected function pdfsEnCarpeta(): int
    {
        if (! is_dir($this->carpeta)) {
            return 0;
        }
        return count(array_filter(scandir($this->carpeta) ?: [], fn ($f) => preg_match('/\.pdf$/i', $f) && is_file($this->carpeta.'/'.$f)));
    }

    // ------------------------------------------------------------ diálogos de Windows

    /** /mnt/e/x/y -> E:\x\y */
    protected function aWindows(string $p): string
    {
        return preg_match('#^/mnt/([a-z])(/.*)?$#', $p, $m) ? strtoupper($m[1]).':'.str_replace('/', '\\', $m[2] ?? '\\') : '';
    }

    /** E:\x\y -> /mnt/e/x/y */
    protected function aLinux(string $p): string
    {
        return preg_match('/^([A-Za-z]):\\\\?(.*)$/', trim($p), $m) ? '/mnt/'.strtolower($m[1]).'/'.str_replace('\\', '/', $m[2]) : '';
    }

    /** Abre un diálogo nativo de Windows (dialogo_windows.ps1) y devuelve la ruta elegida, o ''. */
    protected function dialogo(array $args): string
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida = '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            return '';
        }
        $ps = '/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe';
        // Sin argumentos vacíos: al pasar a Windows se pierden y descolocan los demás. Se quita también
        // su nombre (-Inicial ''), o PowerShell se queja de que al parámetro le falta el valor
        $limpios = [];
        for ($i = 0; $i < count($args); $i++) {
            if (str_starts_with($args[$i], '-') && ($args[$i + 1] ?? null) === '') {
                $i++;
            } elseif ($args[$i] !== '') {
                $limpios[] = $args[$i];
            }
        }
        $args = $limpios;
        $cmd = array_merge([is_file($ps) ? $ps : 'powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-STA',
            '-File', $this->aWindows($this->baseDir().'/dialogo_windows.ps1')], $args);
        $t0 = microtime(true);
        try {
            $r = Process::path($this->baseDir())->env($this->entornoWindows())->timeout(600)->run($cmd);
        } catch (\Throwable $e) {
            $this->salida = '⚠️ No se pudo abrir el diálogo de Windows: '.$e->getMessage();
            Log::error('FacturasOcr: diálogo de Windows', ['cmd' => $cmd, 'error' => $e->getMessage()]);
            return '';
        }
        // Los errores de PowerShell llegan en la página de códigos de la consola (850), no en UTF-8
        $utf8 = fn ($t) => mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'CP850');
        $salida = trim(preg_replace('/^Exception: ios_base::clear.*$/m', '', $utf8($r->output())));
        $err = trim(preg_replace('/^Exception: ios_base::clear.*$/m', '', $utf8($r->errorOutput())));
        if (! $r->successful() || ($salida === '' && microtime(true) - $t0 < 2)) {
            // Salió sin que diera tiempo a elegir nada: no se ha llegado a ver la ventana
            $this->salida = '⚠️ No se pudo abrir el diálogo de Windows (código '.$r->exitCode().'): '.($err ?: 'sin mensaje')
                ."\nMientras tanto puedes pegar la ruta copiada de la barra del Explorador (E:\\OneDrive\\...).";
            Log::error('FacturasOcr: diálogo de Windows', ['cmd' => $cmd, 'codigo' => $r->exitCode(), 'salida' => $salida, 'error' => $err]);
        }
        return $salida;
    }

    /** Elegir la carpeta de entrada con el diálogo del Explorador de Windows. */
    public function elegirCarpeta(): void
    {
        $inicial = is_dir($this->carpeta) ? $this->aWindows($this->carpeta) : '';
        $win = $this->dialogo(['-Modo', 'carpeta', '-Inicial', $inicial]);
        $lin = $win !== '' ? $this->aLinux($win) : '';
        if ($lin !== '' && is_dir($lin)) {
            $this->carpeta = $lin;
            $this->resetErrorBag('carpeta');
        } elseif ($win !== '') {
            $this->addError('carpeta', "No puedo usar {$win} desde aquí.");
        }
    }

    /**
     * Botón final del proceso: pregunta dónde guardar el Excel para SAGE (ventana de Windows), lo copia allí
     * y lo cierra (queda en Output/Guardados); lo que se valide después va a un Excel nuevo.
     */
    public function guardarExcel(): void
    {
        if (! $this->clienteValido() || ! is_file($this->dirDatos().'/Output/'.self::EXCEL)) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay Excel pendiente de guardar.');
            return;
        }
        $ult = $this->estado()['ultimo_guardado'] ?? '';
        $nombre = 'PluginFacturas_Recibidas_'.$this->cliente.'_'.date('Y-m-d').'.xlsx';
        $this->salida = '';
        $win = $this->dialogo(['-Modo', 'guardar', '-Inicial', $ult ? $this->aWindows($ult) : '', '-Nombre', $nombre]);
        if ($win === '') {
            if (trim($this->salida) !== '') {
                $this->dispatch('proceso-terminado', mensaje: trim($this->salida));   // no se abrió la ventana: decir por qué
            }
            return;
        }
        $destino = $this->aLinux($win);
        $this->salida = '';
        if ($destino !== '' && $this->ejecutar(['guardar_excel', '--destino', $destino], 60, 'Guardar el Excel', false)) {
            $this->modificarEstado(fn (array $e) => array_merge($e, ['ultimo_guardado' => dirname($destino)]));
            $this->dispatch('proceso-terminado', mensaje: "✅ Excel guardado en\n{$win}\n".trim($this->salida));
        }
    }

    /** Rechazadas, no legibles y duplicadas fuera de la lista (una o, sin id, todas). No se borra nada. */
    public function quitarDeLista(?string $id = null): void
    {
        $this->modificarEstado(function (array $e) use ($id) {
            foreach ($e['facturas'] as &$f) {
                if (($id === null || $f['id'] === $id) && in_array($f['estado'], ['rechazada', 'ilegible', 'duplicada'], true)) {
                    $f['oculta'] = date('Y-m-d H:i');
                }
            }
            return $e;
        });
        if ($id !== null && $this->sel === $id) {
            $this->cerrar();
        }
    }

    // ------------------------------------------------------------ guardado del borrador

    /** Lee, cambia y graba facturas.json con bloqueo (lo comparte con facturas_ocr.py). */
    protected function modificarEstado(callable $cambio): void
    {
        $f = $this->dirDatos().'/facturas.json';
        $fh = fopen($f, 'c+');
        if (! $fh) {
            return;
        }
        flock($fh, LOCK_EX);
        $e = json_decode(stream_get_contents($fh), true) ?: ['facturas' => []];
        $e = $cambio($e);
        $e['ultimo_cambio'] = ['pc' => gethostname(), 'fecha' => date('Y-m-d H:i:s')];
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($e, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    public function updatedForm(): void
    {
        $this->sucio = true;
    }

    /** Al final de cada petición: lo tocado en la factura abierta queda guardado (se puede cerrar Appmos). */
    public function dehydrate(): void
    {
        if (! $this->sucio || ! $this->sel || ! $this->clienteValido()) {
            return;
        }
        $this->sucio = false;
        $datos = $this->form;
        $sel = $this->sel;
        $this->modificarEstado(function (array $e) use ($sel, $datos) {
            foreach ($e['facturas'] as &$f) {
                if ($f['id'] === $sel && ! in_array($f['estado'], ['validada', 'validando'], true)) {
                    $f['datos'] = array_merge($f['datos'] ?? [], $datos);
                    $f['editada'] = date('Y-m-d H:i');
                }
            }
            return $e;
        });
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
        $this->ejecutar(array_merge(['analizar', '--carpeta', $this->carpeta], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']),
            1800, 'Leer facturas');
    }

    protected function parametros(): array
    {
        $p = ['--ciclo', $this->ciclo ?: 'T', '--periodo', $this->periodo ?: $this->periodoActual()];
        if (array_key_exists($this->cierre, $this->mesesCierre())) {
            array_push($p, '--cierre', $this->cierre);
        }
        return $p;
    }

    public function recalcularFechas(): void
    {
        if (! $this->clienteValido() || ! in_array($this->ciclo, ['M', 'T'], true) || ! is_file($this->dirDatos().'/facturas.json')) {
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

    /**
     * Bajo Apache falta WSL_INTEROP y los programas de Windows (powershell.exe del OCR y de los
     * diálogos) fallan en silencio (rc=1, sin salida): mismo arreglo que Procesos::windowsEnv().
     */
    protected function entornoWindows(): array
    {
        return getenv('WSL_INTEROP') ? [] : ['WSL_INTEROP' => '/run/WSL/1_interop'];
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
            $r = Process::path($this->baseDir())->env($this->entornoWindows())->timeout($timeout)->run($cmd);
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
        $fs = array_values(array_filter($this->estado()['facturas'], fn ($f) => empty($f['oculta']) && ! in_array($f['estado'], ['validada', 'duplicada', 'validando'], true)));
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
        // Proveedor que no se reconoció al leer la carpeta: puede que ya se haya aprendido (p.ej. se acaba
        // de validar otra factura suya con cuenta nueva). Se vuelve a proponer con lo aprendido, si no se
        // ha tocado a mano. Espera a que la cola termine la validación en curso (bloqueo de Python).
        // Igual si se está validando otra del mismo proveedor: lo que se le haya corregido (contrapartida,
        // cód. transacción, clave de operación...) vale ya para esta.
        $cta = (string) ($f['datos']['cuenta'] ?? '');
        $mismoEnCola = $cta !== '' && collect($this->estado()['facturas'])
            ->contains(fn ($o) => $o['estado'] === 'validando' && (string) ($o['datos']['cuenta'] ?? '') === $cta);
        if ($f['estado'] === 'pendiente' && empty($f['editada']) && config('contabilidad.ejecucion_local')
            && (($f['confianza']['proveedor'] ?? '') !== 'ok' || ! empty($f['datos']['proveedor_nuevo']) || $mismoEnCola)) {
            $salida = $this->salida;
            if ($this->ejecutar(array_merge(['reproponer', $id], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 120, 'Volver a proponer', false)) {
                $f = $this->factura($id) ?? $f;
            }
            $this->salida = $salida;
        }
        $this->resetErrorBag();
        $this->sel = $id;
        $this->propuestaCif = null;
        $this->motivo = (string) ($f['motivo_rechazo'] ?? '');
        $d = $f['datos'] ?? [];
        $lineas = array_values($d['lineas'] ?? []);
        while (count($lineas) < 3) {
            $lineas[] = ['base' => '', 'pct' => '', 'cuota' => ''];
        }
        $d['lineas'] = array_map(fn ($l) => array_map(fn ($v) => $v === null ? '' : (string) $v, $l), $lineas);
        if (empty($d['contrapartida']) && ! empty($d['cuenta'])) {
            // La de su ficha (o la última que se le puso aquí), si al leer la factura no se propuso
            $d['contrapartida'] = (string) ($this->patrones()[$d['cuenta']]['contrapartida'] ?? ($this->proveedores()[$d['cuenta']]['contrapartida'] ?? ''));
        }
        foreach (['cuenta', 'nombre_fichero', 'proveedor', 'cif', 'serie', 'su_factura', 'fecha_expedicion', 'fecha_operacion',
            'fecha_registro', 'contrapartida', 'codigo_transaccion', 'clave_operacion', 'total', 'codigo_retencion', 'base_retencion',
            'pct_retencion', 'cuota_retencion', 'canal', 'comentario', 'cp', 'cod_provincia', 'provincia'] as $k) {
            $d[$k] = isset($d[$k]) && $d[$k] !== null ? (string) $d[$k] : '';
        }
        $this->form = $d;
    }

    public function cerrar(): void
    {
        $this->sel = '';
        $this->propuestaCif = null;
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

    /**
     * Siguiente pendiente tras validar/rechazar (o cerrar si no quedan). $antes = ids de la cola antes
     * de cambiar el estado de la actual: se sigue desde su posición (las que se saltaron sin validar
     * quedan para la vuelta), no desde la primera pendiente.
     */
    protected function siguiente(array $antes = []): void
    {
        $pendientes = array_column(array_filter($this->cola(), fn ($f) => $f['estado'] === 'pendiente'), 'id');
        $i = array_search($this->sel, $antes, true);
        if ($i !== false) {
            foreach (array_merge(array_slice($antes, $i + 1), array_slice($antes, 0, $i)) as $id) {
                if (in_array($id, $pendientes, true)) {
                    $this->abrir($id);
                    return;
                }
            }
        }
        foreach ($pendientes as $id) {
            $this->abrir($id);
            return;
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
        return json_decode((string) @file_get_contents($this->dirDatos().'/patrones.json'), true) ?: [];
    }

    /**
     * Cuentas de proveedor creadas en Bancos (web) que aún no están en SAGE: copia que guarda
     * facturas_ocr.py al consultar la web (cuentas_bancos.json). [cuenta => {nombre, cif, cp, origen}]
     */
    protected function cuentasBancos(): array
    {
        $datos = json_decode((string) @file_get_contents($this->dirDatos().'/cuentas_bancos.json'), true);
        return array_diff_key($datos['cuentas'] ?? [], $this->proveedores());
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
                $this->form['codigo_transaccion'] = (string) ($pat['codigo_transaccion'] ?? ($this->form['codigo_transaccion'] ?? ''));
                $this->form['clave_operacion'] = (string) ($pat['clave_operacion'] ?? ($this->form['clave_operacion'] ?? ''));
                $this->form['nombre_fichero'] = $pat['nombre_fichero'] ?? '';
            } elseif ($b = $this->cuentasBancos()[$cta] ?? null) {
                // Creada en Bancos (a veces aún sin CIF)
                $this->form['proveedor'] = $b['nombre'] ?? '';
                $this->form['cif'] = $b['cif'] ?? '';
                $this->ponerCp($b['cp'] ?? '');
            }
            return;
        }
        $this->form['proveedor'] = $p['razon'] ?? '';
        $this->form['cif'] = ($p['cif_europeo'] ?? '') ?: (($p['sigla'] ?? '').($p['nif'] ?? ''));
        $this->form['contrapartida'] = $pat['contrapartida'] ?? ($p['contrapartida'] ?? '');
        $this->form['codigo_transaccion'] = (string) (($pat['codigo_transaccion'] ?? '') !== '' ? $pat['codigo_transaccion'] : ($p['transaccion'] ?? ''));
        $this->form['clave_operacion'] = (string) ($pat['clave_operacion'] ?? '');
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
        // Las creadas en Bancos tampoco se repiten (mismo CIF -> esa)
        foreach ($this->cuentasBancos() as $k => $b) {
            if ($cif !== '' && strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $b['cif'] ?? '')) === $cif) {
                $this->form['cuenta'] = (string) $k;
                $this->updatedFormCuenta();
                return;
            }
            $usadas[] = (string) $k;
        }
        $nums = array_map('intval', array_filter($usadas, fn ($k) => preg_match('/^410\d{3}$/', $k)));
        $this->form['cuenta'] = (string) ($nums ? max($nums) + 1 : 410001);
    }

    /** CP y, si es español, código y nombre de provincia (las dos primeras cifras). */
    protected function ponerCp(string $cp): void
    {
        $cp = trim($cp);
        $this->form['cp'] = $cp;
        if (preg_match('/^\d{5}$/', $cp) && ($prov = self::PROVINCIAS[substr($cp, 0, 2)] ?? null)) {
            $this->form['cod_provincia'] = substr($cp, 0, 2);
            $this->form['provincia'] = $prov;
        }
    }

    /** Propuesta de internet para el proveedor de la factura abierta (facturas_ocr.py buscar_cif). */
    public ?array $propuestaCif = null;

    public function buscarCif(): void
    {
        $this->propuestaCif = null;
        if (! $this->sel || trim($this->form['proveedor'] ?? '') === '') {
            $this->propuestaCif = ['error' => 'Pon primero el nombre del proveedor.'];
            return;
        }
        if (! config('contabilidad.ejecucion_local')) {
            return;
        }
        $cmd = [$this->pythonBin(), 'facturas_ocr.py', $this->cliente, 'buscar_cif', $this->sel, '--nombre', trim($this->form['proveedor'])];
        try {
            $r = Process::path($this->baseDir())->env($this->entornoWindows())->timeout(200)->run($cmd);
            $datos = json_decode($r->output(), true);
            $this->propuestaCif = is_array($datos) ? $datos : ['error' => trim($r->output()."\n".$r->errorOutput())];
        } catch (\Throwable $e) {
            $this->propuestaCif = ['error' => $e->getMessage()];
        }
    }

    public function aceptarCif(): void
    {
        $p = $this->propuestaCif ?? [];
        if (! empty($p['cif'])) {
            $this->form['cif'] = $p['cif'];
        }
        if (! empty($p['cp'])) {
            $this->ponerCp($p['cp']);
        }
        if (trim($this->form['proveedor'] ?? '') === '' && ! empty($p['nombre_oficial'])) {
            $this->form['proveedor'] = $p['nombre_oficial'];
        }
        $this->propuestaCif = null;
        $this->sucio = true;
    }

    public const PROVINCIAS = [
        '01' => 'Araba/Álava', '02' => 'Albacete', '03' => 'Alicante/Alacant', '04' => 'Almería', '05' => 'Ávila', '06' => 'Badajoz',
        '07' => 'Illes Balears', '08' => 'Barcelona', '09' => 'Burgos', '10' => 'Cáceres', '11' => 'Cádiz', '12' => 'Castellón/Castelló',
        '13' => 'Ciudad Real', '14' => 'Córdoba', '15' => 'A Coruña', '16' => 'Cuenca', '17' => 'Girona', '18' => 'Granada',
        '19' => 'Guadalajara', '20' => 'Gipuzkoa', '21' => 'Huelva', '22' => 'Huesca', '23' => 'Jaén', '24' => 'León', '25' => 'Lleida',
        '26' => 'La Rioja', '27' => 'Lugo', '28' => 'Madrid', '29' => 'Málaga', '30' => 'Murcia', '31' => 'Navarra', '32' => 'Ourense',
        '33' => 'Asturias', '34' => 'Palencia', '35' => 'Las Palmas', '36' => 'Pontevedra', '37' => 'Salamanca',
        '38' => 'Santa Cruz de Tenerife', '39' => 'Cantabria', '40' => 'Segovia', '41' => 'Sevilla', '42' => 'Soria', '43' => 'Tarragona',
        '44' => 'Teruel', '45' => 'Toledo', '46' => 'Valencia/València', '47' => 'Valladolid', '48' => 'Bizkaia', '49' => 'Zamora',
        '50' => 'Zaragoza', '51' => 'Ceuta', '52' => 'Melilla',
    ];

    /**
     * Recuadro dibujado en el visor sobre el PDF: se lee lo que hay dentro (texto del PDF u OCR,
     * también en vertical) y va al campo. Se guarda la zona para leerla ahí en las siguientes
     * facturas de ese proveedor (al validar, en patrones.json).
     */
    public function leerZona(string $campo, int $pagina, float $x0, float $y0, float $x1, float $y1): void
    {
        $this->resetErrorBag('zona');
        if (! $this->sel || ! in_array($campo, ['cif', 'su_factura', 'fecha', 'total'], true)) {
            return;
        }
        $rect = array_map(fn ($v) => round(max(0, min(1, $v)), 4), [min($x0, $x1), min($y0, $y1), max($x0, $x1), max($y0, $y1)]);
        $this->salida = '';
        if (! $this->ejecutar(['zona', $this->sel, '--pagina', (string) $pagina, '--rect', implode(',', $rect), '--campo', $campo], 120, 'Leer recuadro', false)) {
            $this->addError('zona', trim($this->salida));
            return;
        }
        $lineas = array_filter(explode("\n", trim($this->salida)));
        $r = json_decode((string) end($lineas), true) ?: [];
        $this->salida = '';
        $valor = (string) ($r['valor'] ?? '');
        if ($valor === '') {
            $this->addError('zona', 'No he sabido sacar el dato del recuadro. Leído: '.mb_substr((string) ($r['texto'] ?? ''), 0, 120));
            return;
        }
        if ($campo === 'fecha') {
            $this->form['fecha_expedicion'] = $valor;
            $this->form['fecha_operacion'] = $valor;
            $pa = $this->primeraAbierta();
            $this->form['fecha_registro'] = $pa && $valor < $pa->format('Y-m-d') ? $pa->format('Y-m-d') : $valor;
        } else {
            $this->form[$campo] = $valor;
        }
        if ($campo === 'cif' && ! empty($r['cuenta']) && ($r['cuenta'] !== ($this->form['cuenta'] ?? ''))) {
            $this->form['cuenta'] = (string) $r['cuenta'];
            $this->updatedFormCuenta();
            $this->form['cif'] = $valor;
        }
        if ($campo === 'su_factura' && ($this->form['proveedor'] ?? '') !== '') {
            $this->form['comentario'] = mb_substr(trim('Fra '.$valor.' '.$this->form['proveedor']), 0, 40);
        }
        $zonas = is_array($this->form['_zonas'] ?? null) ? $this->form['_zonas'] : [];
        $zonas[$campo] = ['pagina' => $pagina, 'rect' => $rect];
        $this->form['_zonas'] = $zonas;
        $this->sucio = true;
    }

    /** Listas para los buscadores del formulario (se piden una vez desde el navegador). */
    public function lista(string $cual): array
    {
        if (! $this->clienteValido()) {
            return [];
        }
        $f = $this->dirCliente().'/Base/proveedores.json';
        $d = json_decode((string) @file_get_contents($f), true) ?: [];
        $error = '';
        if (config('contabilidad.ejecucion_local')) {
            // Al día con el listado y el mayor de este PC (proveedores.json no va por git); si ya lo está, no hace nada
            $r = Process::path($this->baseDir())->timeout(300)->run([$this->pythonBin(), 'facturas_base.py', $this->cliente]);
            if (! $r->successful()) {
                $error = trim($r->errorOutput()."\n".$r->output());
                Log::warning('FacturasOcr: no se pudo rehacer proveedores.json', ['salida' => $error]);
            }
        }
        if ($cual === 'cuentas') {
            $c = json_decode((string) @file_get_contents($f), true)['cuentas'] ?? [];
            if (! $c) {
                // Que se vea en el propio desplegable por qué está vacío
                return [['', '⚠️ No hay cuentas de contrapartida: '.($error !== '' ? mb_substr(preg_replace('/\s+/', ' ', $error), -300)
                    : (is_file($f) ? 'proveedores.json sin cuentas (falta el mayor en '.$this->cliente.'?)' : 'no existe '.$f))]];
            }
            return array_map(fn ($k, $v) => [(string) $k, (string) $v], array_keys($c), $c);
        }
        $out = [];
        foreach ($this->proveedores() as $cta => $p) {
            $out[] = [(string) $cta, trim(($p['razon'] ?? '').' · '.($p['nif'] ?? ''), ' ·')];
        }
        foreach ($this->patrones() as $cta => $pat) {
            if (! empty($pat['nuevo'])) {
                $out[] = [(string) $cta, trim(($pat['proveedor'] ?? '').' · '.($pat['cif'] ?? '').' (nuevo)', ' ·')];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------ proveedores

    /**
     * Proveedores del listado de SAGE y nuevos de aquí, con lo que se propondrá en sus facturas:
     * lo aprendido / editado aquí manda sobre la ficha de SAGE (misma regla que propuesta() de Python).
     */
    protected function listaProveedores(): array
    {
        $pats = $this->patrones();
        $validadas = [];
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['estado'] === 'validada') {
                $c = (string) ($f['datos']['cuenta'] ?? '');
                $validadas[$c]['n'] = ($validadas[$c]['n'] ?? 0) + 1;
                $validadas[$c]['ultima'] = max($validadas[$c]['ultima'] ?? '', $f['validada_el'] ?? '');
            }
        }
        $fila = function (string $cta, array $p, array $pat, bool $nuevo) use ($validadas) {
            $trans = (string) (($pat['codigo_transaccion'] ?? '') !== '' ? $pat['codigo_transaccion'] : ($p['transaccion'] ?? ''));
            $ret = (string) (($pat['codigo_retencion'] ?? '') !== '' ? $pat['codigo_retencion'] : ($p['retencion'] ?? ''));
            return [
                'cuenta' => $cta, 'nuevo' => $nuevo,
                'nombre' => $nuevo ? ($pat['proveedor'] ?? '') : ($p['razon'] ?? ''),
                'cif' => $nuevo ? ($pat['cif'] ?? '') : ((($p['cif_europeo'] ?? '') ?: (($p['sigla'] ?? '').($p['nif'] ?? '')))),
                'contrapartida' => (string) (($pat['contrapartida'] ?? '') ?: ($p['contrapartida'] ?? '')),
                'contrapartida_aqui' => ($pat['contrapartida'] ?? '') !== '',
                'contrapartida_sage' => (string) ($p['contrapartida_listado'] ?? ''),
                'codigo_transaccion' => $trans, 'transaccion_aqui' => ($pat['codigo_transaccion'] ?? '') !== '',
                'transaccion_sage' => (string) ($p['transaccion'] ?? ''),
                'clave_operacion' => (string) ($pat['clave_operacion'] ?? ''),
                'codigo_retencion' => $ret, 'retencion_aqui' => ($pat['codigo_retencion'] ?? '') !== '',
                'validadas' => $validadas[$cta]['n'] ?? 0, 'ultima' => $validadas[$cta]['ultima'] ?? '',
            ];
        };
        $out = [];
        foreach ($this->proveedores() as $cta => $p) {
            $out[(string) $cta] = $fila((string) $cta, $p, $pats[$cta] ?? [], false);
        }
        foreach ($pats as $cta => $pat) {
            if (! empty($pat['nuevo']) && ! isset($out[(string) $cta])) {
                $out[(string) $cta] = $fila((string) $cta, [], $pat, true);
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    public function abrirProveedor(string $cta): void
    {
        $p = $this->listaProveedores()[$cta] ?? null;
        if (! $p) {
            return;
        }
        $this->resetErrorBag('proveedor');
        $this->provSel = $cta;
        $this->provForm = array_intersect_key($p, array_flip(['nombre', 'cif', 'contrapartida', 'codigo_transaccion', 'clave_operacion', 'codigo_retencion']));
    }

    public function guardarProveedor(): void
    {
        $this->resetErrorBag('proveedor');
        if ($this->provSel === '') {
            return;
        }
        $f = $this->provForm;
        $datos = [
            'proveedor' => trim((string) ($f['nombre'] ?? '')), 'cif' => trim((string) ($f['cif'] ?? '')),
            'contrapartida' => trim((string) ($f['contrapartida'] ?? '')), 'codigo_transaccion' => trim((string) ($f['codigo_transaccion'] ?? '')),
            'clave_operacion' => strtoupper(trim((string) ($f['clave_operacion'] ?? ''))), 'codigo_retencion' => trim((string) ($f['codigo_retencion'] ?? '')),
        ];
        foreach (['contrapartida', 'codigo_transaccion', 'codigo_retencion'] as $k) {
            if ($datos[$k] !== '' && ! ctype_digit($datos[$k])) {
                $this->addError('proveedor', 'Solo números en '.str_replace('_', ' ', $k).'.');
                return;
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'focr-prov-');
        file_put_contents($tmp, json_encode($datos, JSON_UNESCAPED_UNICODE));
        $this->salida = '';
        $ok = $this->ejecutar(['proveedor', $this->provSel, '--datos', $tmp], 300, 'Guardar proveedor');
        @unlink($tmp);
        if ($ok) {
            $this->provSel = '';
            $this->provForm = [];
        } else {
            $this->addError('proveedor', trim($this->salida));
        }
    }

    /** El listado tal cual se ve (con el filtro), en CSV para Excel. */
    public function descargarProveedores()
    {
        $provs = $this->listaProveedores();
        if ($this->filtroProv !== '') {
            $q = mb_strtolower($this->filtroProv);
            $provs = array_filter($provs, fn ($p) => str_contains(mb_strtolower($p['cuenta'].' '.$p['nombre'].' '.$p['cif'].' '.$p['contrapartida']), $q));
        }
        $nombres = json_decode((string) @file_get_contents($this->dirCliente().'/Base/proveedores.json'), true)['cuentas'] ?? [];
        return response()->streamDownload(function () use ($provs, $nombres) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cuenta', 'Proveedor', 'CIF', 'Nuevo', 'Contrapartida', 'Nombre contrapartida', 'Contrapartida SAGE',
                'Cód. transacción', 'Cód. transacción SAGE', 'Clave operación', 'Cód. retención', 'Validadas aquí', 'Última'], ';');
            foreach ($provs as $p) {
                fputcsv($out, [$p['cuenta'], $p['nombre'], $p['cif'], $p['nuevo'] ? 'sí' : '', $p['contrapartida'], $nombres[$p['contrapartida']] ?? '',
                    $p['contrapartida_sage'], $p['codigo_transaccion'], $p['transaccion_sage'], $p['clave_operacion'], $p['codigo_retencion'],
                    $p['validadas'] ?: '', $p['ultima']], ';');
            }
            fclose($out);
        }, 'Proveedores_'.$this->cliente.'_'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function cerrarProveedor(): void
    {
        $this->provSel = '';
        $this->provForm = [];
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

    /** Líneas de IVA cuya cuota no es base × % (la factura o lo tecleado está mal): [nº línea => texto]. */
    protected function lineasMal(): array
    {
        $mal = [];
        foreach ($this->form['lineas'] ?? [] as $k => $l) {
            $b = $this->num($l['base'] ?? '');
            $t = $this->num($l['pct'] ?? '');
            $c = $this->num($l['cuota'] ?? '');
            if ($b !== null && $t !== null && $c !== null && abs(round($b * $t / 100, 2) - $c) > 0.02) {
                $mal[$k] = 'Línea '.($k + 1).': '.number_format($b, 2, ',', '.').' × '.rtrim(rtrim(number_format($t, 2, ',', ''), '0'), ',')
                    .' % = '.number_format(round($b * $t / 100, 2), 2, ',', '.').' y pone '.number_format($c, 2, ',', '.');
            }
        }
        $b = $this->num($this->form['base_retencion'] ?? '');
        $t = $this->num($this->form['pct_retencion'] ?? '');
        $c = $this->num($this->form['cuota_retencion'] ?? '');
        if ($b !== null && $t !== null && $c !== null && abs(round($b * $t / 100, 2) - $c) > 0.02) {
            $mal['ret'] = 'Retención: '.number_format($b, 2, ',', '.').' × '.$t.' % = '.number_format(round($b * $t / 100, 2), 2, ',', '.').' y pone '.number_format($c, 2, ',', '.');
        }
        return $mal;
    }

    /** Otra factura de este proveedor con el mismo nº: en SAGE, validada aquí o pendiente (misma regla que duplicados() en Python). */
    protected function duplicados(): array
    {
        $this->gemelas = [];
        $a = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($this->form['su_factura'] ?? '')));
        $cta = (string) ($this->form['cuenta'] ?? '');
        if ($a === '' || $cta === '') {
            return [];
        }
        $norm = fn ($v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));
        $out = [];
        foreach ($this->proveedores()[$cta]['facturas'] ?? [] as $f) {
            if ($norm($f['num'] ?? '') === $a) {
                $out[] = "Ya contabilizada en SAGE: nº {$f['num']} del {$f['fecha']} ({$f['total']} €)";
                break;
            }
        }
        $pendientes = [];
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['id'] === $this->sel || (string) ($f['datos']['cuenta'] ?? '') !== $cta || $norm($f['datos']['su_factura'] ?? '') !== $a) {
                continue;
            }
            if (in_array($f['estado'], ['validada', 'validando'], true)) {
                $out[] = 'Ya validada aquí el '.($f['validada_el'] ?? 'ahora').' ('.basename($f['ruta']).')';
            } elseif ($f['estado'] === 'pendiente') {
                $pendientes[] = basename($f['ruta']);
            }
        }
        // Varias pendientes con el mismo nº: la primera de la lista es la buena y las demás sus duplicadas
        if ($pendientes) {
            usort($pendientes, 'strnatcasecmp');
            if (strnatcasecmp(basename($this->factura($this->sel)['ruta'] ?? ''), $pendientes[0]) < 0) {
                $this->gemelas = $pendientes;
            } else {
                $out[] = 'Es copia de '.$pendientes[0].', pendiente, que va antes en la lista';
            }
        }
        return $out;
    }

    /** Pendientes con el mismo nº que la abierta cuando la abierta es la primera: se avisa, no es duplicada. */
    protected array $gemelas = [];

    public function validar(bool $forzar = false): void
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
        // Lo que Python comprobaría: si falta algo, se dice ya y no se encola
        $faltan = array_filter(['cuenta', 'su_factura', 'fecha_expedicion', 'fecha_registro', 'contrapartida', 'total'],
            fn ($k) => ($datos[$k] ?? '') === '' || $datos[$k] === null);
        if (! isset($this->proveedores()[(string) ($datos['cuenta'] ?? '')])) {
            $faltan = array_merge($faltan, array_filter(['cif', 'proveedor'], fn ($k) => trim((string) ($datos[$k] ?? '')) === ''));
        }
        if ($faltan) {
            $this->addError('validar', 'Faltan datos: '.implode(', ', $faltan));
            return;
        }
        if (! config('contabilidad.ejecucion_local')) {
            $this->addError('validar', 'Opción no válida. Solo ejecutable desde un terminal autorizado.');
            return;
        }
        $antes = array_column($this->cola(), 'id');
        // Se pasa ya a la siguiente factura; el Excel, mover el PDF y aprender lo hace la cola en segundo plano
        $cola = $this->dirDatos().'/_cola';
        @mkdir($cola, 0777, true);
        file_put_contents($cola.'/'.date('Ymd-His').'-'.substr((string) hrtime(true), -6).'-'.$this->sel.'.json',
            json_encode(['id' => $this->sel, 'datos' => $datos, 'forzar' => $forzar], JSON_UNESCAPED_UNICODE));
        $sel = $this->sel;
        $this->modificarEstado(function (array $e) use ($sel, $datos) {
            foreach ($e['facturas'] as &$f) {
                if ($f['id'] === $sel) {
                    $f['estado'] = 'validando';
                    $f['datos'] = array_merge($f['datos'] ?? [], $datos);
                    unset($f['error_validar']);
                }
            }
            return $e;
        });
        $this->lanzarCola();
        $this->siguiente($antes);
    }

    /** Arranca (si no está ya) el proceso que valida en segundo plano lo encolado. */
    protected function lanzarCola(): void
    {
        $env = '';
        foreach ($this->entornoWindows() as $k => $v) {
            $env .= $k.'='.escapeshellarg($v).' ';
        }
        $log = $this->dirDatos().'/_cola/cola.log';
        $cmd = 'cd '.escapeshellarg($this->baseDir()).' && '.$env.'nohup setsid '.escapeshellarg($this->pythonBin())
            .' facturas_ocr.py '.escapeshellarg($this->cliente).' cola >> '.escapeshellarg($log).' 2>&1 < /dev/null &';
        Process::run(['bash', '-c', $cmd]);
    }

    /** Confirmada como duplicada: el PDF va a la subcarpeta Duplicadas y sale de la cola. */
    public function marcarDuplicada(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        $antes = array_column($this->cola(), 'id');
        if ($this->ejecutar(['duplicada', $this->sel, '--motivo', $this->motivo], 60, 'Duplicada', false)) {
            $this->siguiente($antes);
        } else {
            $this->addError('validar', trim($this->salida));
        }
    }

    public function rechazar(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        $antes = array_column($this->cola(), 'id');
        if ($this->ejecutar(['rechazar', $this->sel, '--motivo', $this->motivo], 60, 'Rechazar', false)) {
            $this->siguiente($antes);
        }
    }

    public function reabrir(string $id): void
    {
        $this->salida = '';
        $validada = ($this->factura($id)['estado'] ?? '') === 'validada';
        if (! $this->ejecutar(['reabrir', $id], 60, 'Reabrir', false)) {
            $this->addError('validar', trim($this->salida));
        } elseif ($validada && $this->sel === $id) {
            // Validada que se vuelve a pendiente para corregirla: se sigue en ella, ya editable
            $this->vista = 'revisar';
            $this->abrir($id);
        }
    }

    /** Vuelve a proponer los datos con el texto ya leído (sin OCR): tras mejoras del programa o tras aprender. */
    public function reproponer(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        if ($this->ejecutar(array_merge(['reproponer', $this->sel], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 120, 'Volver a proponer', false)) {
            $this->abrir($this->sel);
        } else {
            $this->addError('validar', trim($this->salida));
        }
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
        $dir = $this->dirDatos().'/Base';
        @mkdir($dir, 0775, true);
        foreach ($this->subidas as $f) {
            $nombre = $f->getClientOriginalName();
            if (! preg_match('/lisProveedores|^Mayor|plan/i', $nombre) || ! preg_match('/\.xlsx$/i', $nombre)) {
                $this->salida .= "⚠️ {$nombre}: no es el listado de proveedores (…lisProveedores….xlsx), un mayor (Mayor….xlsx) ni el plan de cuentas (…Plan….xlsx).\n";
                continue;
            }
            copy($f->getRealPath(), $dir.'/'.$nombre);
            $this->salida .= "Guardado Base/{$nombre}.\n";
        }
        $this->subidas = [];
        $this->dispatch('focr-listas');   // que los combos vuelvan a pedir las listas
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
        $raiz = realpath($this->dirDatos());
        $ruta = realpath($raiz.'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta)) {
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    /** Excel del proceso en curso (lo validado desde el último guardado); lo mismo en facturas_ocr.py. */
    public const EXCEL = 'PluginFacturas_Recibidas.xlsx';

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
        // Las quitadas de la lista (rechazadas/duplicadas ya vistas) no cuentan en ninguna pestaña
        $todas = array_values(array_filter($estado['facturas'], fn ($f) => empty($f['oculta'])));
        $validadas = array_values(array_filter($todas, fn ($f) => in_array($f['estado'], ['validada', 'validando'], true)));
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
        $provs = [];
        if ($valido && $this->vista === 'proveedores') {
            $provs = $this->listaProveedores();
            if ($this->filtroProv !== '') {
                $q = mb_strtolower($this->filtroProv);
                $provs = array_filter($provs, fn ($p) => str_contains(mb_strtolower($p['cuenta'].' '.$p['nombre'].' '.$p['cif'].' '.$p['contrapartida']), $q));
            }
        }
        $cola = $valido ? $this->cola() : [];

        // Otro PC ha tocado estas facturas hace poco (OneDrive puede no haberlo traído aún) o hay copias en conflicto
        $otroPc = null;
        $uc = $estado['ultimo_cambio'] ?? null;
        if ($uc && ($uc['pc'] ?? '') !== gethostname() && strtotime($uc['fecha'] ?? '') > time() - 900) {
            $otroPc = $uc;
        }
        $conflictos = $valido ? array_map('basename', array_filter(glob($this->dirDatos().'/{facturas,patrones}*.json', GLOB_BRACE) ?: [],
            fn ($f) => ! in_array(basename($f), ['facturas.json', 'patrones.json'], true))) : [];

        return view('livewire.contabilidad.facturas-ocr', [
            'otroPc' => $otroPc,
            'guardando' => count(array_filter($todas, fn ($f) => $f['estado'] === 'validando')),
            'fallidas' => array_values(array_filter($todas, fn ($f) => ! empty($f['error_validar']) && $f['estado'] === 'pendiente')),
            'conflictos' => $conflictos,
            'dirDatos' => $valido ? $this->dirDatos() : '',
            'clientes' => $this->clientes(),
            'cola' => $cola,
            'cuenta' => array_count_values(array_column($todas, 'estado')),
            'validadas' => $validadas,
            'provs' => $provs,
            'esNuevoProv' => $this->provSel !== '' && ! isset($this->proveedores()[$this->provSel]),
            // Factura abierta con proveedor que no está en SAGE: se puede buscar su CIF/CP en internet
            'provFueraSage' => $this->sel !== '' && ! isset($this->proveedores()[$this->form['cuenta'] ?? '']),
            'nombresCuentas' => $this->vista === 'proveedores' && $valido
                ? (json_decode((string) @file_get_contents($this->dirCliente().'/Base/proveedores.json'), true)['cuentas'] ?? []) : [],
            'mesesReg' => $mesesReg,
            'actual' => $actual,
            'posicion' => $actual ? array_search($this->sel, array_column($cola, 'id'), true) : false,
            'isp' => $this->sel ? $this->esIsp() : false,
            'esNuevo' => $this->sel && ($this->form['cuenta'] ?? '') !== '' && ! isset($this->proveedores()[$this->form['cuenta']]),
            'primeraAbierta' => $this->primeraAbierta(),
            'periodos' => $this->periodos(),
            'mesesCierre' => $this->mesesCierre(),
            'pdfs' => $valido ? $this->pdfsEnCarpeta() : 0,
            'enExcel' => count(array_filter($todas, fn ($f) => ($f['excel'] ?? '') === self::EXCEL && in_array($f['estado'], ['validada', 'validando'], true))),
            'ultimoExcel' => $estado['ultimo_excel'] ?? null,
            'quitables' => count(array_filter($todas, fn ($f) => empty($f['oculta']) && in_array($f['estado'], ['rechazada', 'ilegible', 'duplicada'], true))),
            'base' => $valido ? $this->base() : [],
            'descuadre' => $this->sel ? $this->descuadre() : null,
            'lineasMal' => $this->sel ? $this->lineasMal() : [],
            'duplicados' => $this->sel ? $this->duplicados() : [],
            'gemelas' => $this->gemelas,
            'duplicadas' => array_values(array_filter($todas, fn ($f) => $f['estado'] === 'duplicada')),
            'totalNum' => $this->sel ? ($this->num($this->form['total'] ?? '') ?? 0) : 0,
            'entidad' => $valido ? $this->entidad() : null,
            'hayAnalitica' => $this->hayColumnaAnalitica(),
        ]);
    }
}
