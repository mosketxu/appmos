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
    // Web: el OCR de las facturas escaneadas lo hace un PC con el OCR de Windows (trait: cola de tareas, 3-oct-2026)
    use \App\Http\Livewire\Concerns\EjecutaEnPcs;
    use \App\Http\Livewire\Concerns\EligeEntidadCliente;

    protected string $grupoPc = 'facturasocr';
    protected bool $ultimoOk = false;
    public array $resultados = [];

    protected function recargarEstado(): void
    {
    }

    protected function rutaWindows(string $p): string
    {
        return $this->aWindows($p) ?: $p;
    }

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
    /** Validadas: fecha de proceso (guardado del Excel para SAGE) desde / hasta, AAAA-MM-DD. */
    public string $filtroDesde = '';
    public string $filtroHasta = '';
    /** Validadas: '' = las del proceso en curso (aún sin guardar), 'todas', o el Excel guardado (Guardados/...). */
    public string $filtroProceso = '';

    // Pestaña Proveedores: buscar y editar lo contable de cada proveedor (va a patrones.json)
    public string $filtroProv = '';
    public string $provSel = '';
    public array $provOriginal = [];
    public string $provAviso = '';
    public array $provForm = [];

    /** Hay cambios en el formulario de la factura abierta: se guardan al final de la petición. */
    protected bool $sucio = false;

    /** Web (VPS): PDF que acaba de subir el navegador (ver recibirPdfs) y lectura en segundo plano en curso. */
    public $pdfsSubidos = [];
    public bool $leyendo = false;
    public int $sinLeerAntes = 0;
    /** Web: hay una tarea pidiendo a un PC que lleve lo validado a su OneDrive. */
    public bool $sincronizando = false;

    /** Ficheros base subidos (listado de proveedores / mayor). */
    /** Un fichero nuevo por tipo de fichero base (ver TIPOS_BASE) */
    public $subidaProv = null;
    public $subidaMayor = null;
    public $subidaPlan = null;

    public function mount(): void
    {
        $this->cliente = $this->clienteOk = $this->clienteInicial($this->clientes());
        $t = intdiv((int) date('n') - 1, 3);   // trimestre natural anterior, para el chequeo contra el mayor
        $this->chequeoPeriodo = $t === 0 ? (date('Y') - 1).'-4T' : date('Y').'-'.$t.'T';
        $this->cargarCliente(true);
        $this->retomarTareas();
    }

    // ------------------------------------------------------------ modo web (VPS)

    /** VPS: lo ejecuta el propio servidor sobre su copia de trabajo (las facturas se suben a la web). */
    protected function web(): bool
    {
        return (bool) config('contabilidad.facturasocr_web');
    }

    /** Se ejecutan los scripts en esta máquina: un PC autorizado o el VPS en modo web. */
    protected function ejecutaAqui(): bool
    {
        return config('contabilidad.ejecucion_local') || $this->web();
    }

    /** Entorno de los procesos de Python: OCR de Windows (PCs) o copia de OneDrive y Tesseract (VPS). */
    protected function entornoPython(): array
    {
        $env = $this->entornoWindows();
        if ($this->web()) {
            $env['ONEDRIVE_ROOT'] = rtrim((string) config('contabilidad.facturasocr_onedrive'), '/');
            $env['FACTURAS_OCR_MOTOR'] = 'tesseract';
            // Lo que ya leyó el OCR de Windows de un PC (ocr_previo.py) se usa en vez de volver a leer con Tesseract
            if ($this->cliente !== '' && $this->clienteValido()) {
                $env['OCR_CACHE_DIR'] = $this->dirDatos().'/_ocr';
            }
        }

        return $env;
    }

    /** Carpeta de entrada de las facturas subidas a la web. */
    protected function dirEntrada(): string
    {
        return $this->dirDatos().'/Entrada';
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
        if (str_starts_with($d, '{OneDrive}') && config('contabilidad.facturasocr_onedrive')) {
            // VPS: copia de trabajo de la carpeta 2026 de OneDrive (las facturas se suben a la web, no por OneDrive)
            return rtrim(config('contabilidad.facturasocr_onedrive'), '/').substr($d, strlen('{OneDrive}'));
        }
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

    /** Canales (analítica) que ofrece el desplegable: cliente.json → "canales", o los ya usados en los patrones del cliente. */
    public function canalesCliente(): array
    {
        $c = array_map('strval', (array) ($this->cfg()['canales'] ?? []));
        if (! $c) {
            $pat = json_decode((string) @file_get_contents($this->dirDatos().'/patrones.json'), true) ?: [];
            foreach ($pat as $v) {
                if (is_array($v) && trim((string) ($v['canal'] ?? '')) !== '') {
                    $c[] = (string) $v['canal'];
                }
            }
        }
        $c = array_values(array_unique($c));
        sort($c, SORT_NATURAL);

        return $c;
    }

    protected function cfg(): array
    {
        return json_decode((string) @file_get_contents($this->dirCliente().'/cliente.json'), true) ?: [];
    }

    /** La entidad lleva el SII (cliente.json -> "sii": true): solo entonces se rellena el Comentario SII. */
    protected function sii(): bool
    {
        return ! empty($this->cfg()['sii']);
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

    protected function cargarCliente(bool $aplazarSync = false): void
    {
        $this->sel = '';
        $this->propuestaCif = null;
        $this->form = [];
        if (! $this->clienteValido()) {
            return;
        }
        if (realpath($this->dirDatos()) !== realpath($this->dirCliente()) && config('contabilidad.ejecucion_local') && ! $this->web()
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
        $this->carpeta = $this->web() ? $this->dirEntrada() : ($ult['carpeta'] ?? ($this->cfg()['carpeta_entrada'] ?? ''));
        $this->leyendo = $this->web() && $this->lecturaEnCurso() !== null;
        $this->periodo = (string) ($ult['periodo'] ?? '');
        if (! array_key_exists($this->periodo, $this->periodos())) {
            $this->periodo = $this->periodoActual();
        }
        $this->cierre = (string) ($ult['cierre'] ?? '');
        if (! array_key_exists($this->cierre, $this->mesesCierre())) {
            $this->cierre = '';
        }
        // Ficheros base centrales de la empresa (mayor, plan, proveedores): lo subido en otro proceso se instala aquí y lo de aquí se publica allí.
        // Al abrir la pantalla solo se comprueba si hay algo (rápido): si lo hay, la pantalla sale ya y lo instala después (wire:init) con el recuadro
        // «Actualizando los ficheros base…», en vez de dejar la página en blanco durante el minuto que tarda en rehacer los proveedores.
        try {
            if ($aplazarSync) {
                $this->sincronizarPendiente = $this->sincronizarCentral(true) !== [];
            } else {
                $this->sincronizarCentral();
            }
        } catch (\Throwable $e) {
            report($e);   // no impide abrir la pantalla
        }
    }

    /** ¿Hay ficheros base centrales por instalar/publicar al abrir? La pantalla lo hace tras pintarse (sincronizarInicial). */
    public bool $sincronizarPendiente = false;

    /** wire:init: instala/publica los ficheros base centrales (puede tardar un minuto si hay que rehacer los proveedores). */
    public function sincronizarInicial(): void
    {
        if (! $this->sincronizarPendiente) {
            return;
        }
        $this->sincronizarPendiente = false;
        try {
            $this->sincronizarCentral();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updatedCliente(): void
    {
        if ($this->interceptaNuevo()) {
            return;
        }
        $this->alElegirCliente();
    }

    protected function alElegirCliente(): void
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
        $this->leerSiHaySinLeer();
    }

    /** Web: al elegir IVA/periodo se leen solas las facturas que ya estaban subidas esperando (sin botón). */
    protected function leerSiHaySinLeer(): void
    {
        if ($this->web() && in_array($this->ciclo, ['M', 'T'], true) && $this->sinLeerEnEntrada() > 0) {
            $this->leerEnSegundoPlano();
        }
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
        $nombre = 'PluginFacturas_Recibidas_'.$this->cliente.'_'.date('Y-m-d_Hi').'.xlsx';
        $this->salida = '';
        if ($this->web()) {
            // Sin ventana de Windows: se entrega en Output/Entregados y el navegador lo descarga
            $dir = $this->dirDatos().'/Output/Entregados';
            @mkdir($dir, 0775, true);
            if ($this->ejecutar(['guardar_excel', '--destino', $dir.'/'.$nombre], 60, 'Guardar el Excel', false)) {
                $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('contabilidad.facturas-ocr.excel', now()->addMinutes(10), ['cliente' => $this->cliente, 'archivo' => $nombre]);
                $this->js('window.location.href = '.json_encode($url));
                $this->dispatch('proceso-terminado', mensaje: "✅ Excel listo: se descarga ahora.\n{$nombre}");
                $this->enviarAlPc();   // y se deja también en el OneDrive del PC, con todo lo contabilizado
            }
            return;
        }
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

    /** Fuera de la lista: una factura (pendiente, rechazada, no legible o duplicada) o, sin id, todas las descartadas. No se borra nada. */
    public function quitarDeLista(?string $id = null): void
    {
        $this->modificarEstado(function (array $e) use ($id) {
            foreach ($e['facturas'] as &$f) {
                // Una a una también las pendientes (p.ej. contabilizada a mano en SAGE); en bloque, solo las ya descartadas
                if ($id === null ? in_array($f['estado'], ['rechazada', 'ilegible', 'duplicada'], true)
                        : $f['id'] === $id && in_array($f['estado'], ['pendiente', 'rechazada', 'ilegible', 'duplicada'], true)) {
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

    public function updatedForm($valor = null, $clave = ''): void
    {
        $this->sucio = true;
        // CIF: español sin «ES», intracomunitario con su prefijo; si no es válido se avisa debajo del campo
        if ($clave === 'cif') {
            $a = $this->analizarCif((string) $valor);
            if (! empty($a['formato_ok']) && $a['normalizado'] !== '') {
                $this->form['cif'] = $a['normalizado'];
            }
            $this->cifAviso = (string) ($a['mensaje'] ?? '');
        }
        // Al cambiar la base de una línea, su cuota se calcula sola. Al poner el % de IVA de una línea, con el total puesto, su base y su cuota
        // salen de lo que falta del total tras las líneas de arriba (15-oct-2026, Alex: una línea → con el % basta; varias → corrige la base
        // de la 1.ª, pone el % de la 2.ª y sale el resto; igual con la 3.ª). Sin total, solo la cuota.
        if (preg_match('/^lineas\.([0-2])\.(base|pct)$/', (string) $clave, $m)) {
            if ($m[2] !== 'pct' || ! $this->repartoDesdeTotal((int) $m[1])) {
                $this->cuota((int) $m[1]);
            }
        }
        // Proveedor nuevo: se da de alta en cuanto tiene cuenta y nombre (y se actualiza al cambiar nombre o CIF), no al validar
        if (in_array($clave, ['proveedor', 'cif'], true)) {
            $this->altaProveedorNuevo();
        }
        // Al cambiar el total (p. ej. facturas en dólares, donde hay que teclearlo en euros) se recalculan la base y la cuota
        // de la única línea con % de IVA (0 % incluido); con varias líneas no se adivina el reparto.
        if ($clave === 'total') {
            $this->recalcularDesdeTotal();
        }
        // Transacción 38 (operación sin IVA): una sola línea con IVA 0 y base = total (14-oct-2026)
        if (in_array($clave, ['codigo_transaccion', 'total'], true)) {
            $this->aplicarTransaccion38();
        }
    }

    /** Con código de transacción 38: base = total (+ retención), IVA 0 %, cuota 0, y sin más líneas. */
    protected function aplicarTransaccion38(): void
    {
        $total = $this->num($this->form['total'] ?? '');
        if (trim((string) ($this->form['codigo_transaccion'] ?? '')) !== '38' || $total === null) {
            return;
        }
        $base = $total + ($this->num($this->form['cuota_retencion'] ?? '') ?? 0.0);
        $this->form['lineas'] = [
            ['base' => number_format($base, 2, '.', ''), 'pct' => '0', 'cuota' => '0.00'],
            ['base' => '', 'pct' => '', 'cuota' => ''],
            ['base' => '', 'pct' => '', 'cuota' => ''],
        ];
    }

    /** Chequeo contra el mayor (pestaña «Chequeo mayor»): periodo 2026-3T o 2026-09 y el resultado de la última comprobación de ese periodo. */
    public string $chequeoPeriodo = '';

    public function chequearMayor(): void
    {
        $p = trim($this->chequeoPeriodo);
        if (! $this->clienteValido() || ! preg_match('/^\d{4}-([1-4]T|(0[1-9]|1[0-2]))$/', $p)) {
            $this->addError('chequeo', 'Pon el periodo como 2026-3T (trimestre) o 2026-09 (mes).');
            return;
        }
        $this->resetErrorBag('chequeo');
        $this->ejecutar(['chequear', '--periodo', $p], 900, 'Chequeo contra el mayor '.$p);
    }

    /** Facturas sueltas en la raíz de _Facturas: SIMULAR (no toca nada) o APLICAR (renombra y mueve al mes del asiento del mayor, quita duplicadas, devuelve a Por revisar). */
    /** Cambios de Alex a la simulación: fichero => true = no tocar esta; fichero => '01'..'12' = otra carpeta de mes para esta. */
    public array $ordenarAccion = [];

    public array $ordenarMes = [];

    /** Pulsar sobre la acción de una fila la cambia cíclicamente entre las que admite (MOVER → A PROCESAR → NO TOCAR…). */
    public function ciclarAccion(string $fichero): void
    {
        $fila = collect($this->ordenarResultado()['filas'] ?? [])->firstWhere('fichero', $fichero);
        if (! $fila || empty($fila['permitidas'])) {
            return;
        }
        $perm = $fila['permitidas'];
        $actual = $this->ordenarAccion[$fichero] ?? $fila['accion'];
        $i = array_search($actual, $perm, true);
        $sig = $perm[($i === false ? 0 : $i + 1) % count($perm)];
        $this->ordenarAccion[$fichero] = $sig;
        $this->recordarNoTocar((string) ($fila['sha'] ?? ''), $fichero, $sig === 'NADA');
    }

    /** «No tocar» se recuerda (por el contenido del PDF) para las próximas simulaciones: ordenar_no_tocar.json {sha: {fichero, fecha}}. */
    protected function recordarNoTocar(string $sha, string $fichero, bool $si): void
    {
        if ($sha === '' || ! $this->clienteValido()) {
            return;
        }
        $f = $this->dirDatos().'/ordenar_no_tocar.json';
        $d = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        if ($si) {
            $d[$sha] = ['fichero' => $fichero, 'fecha' => now()->format('Y-m-d H:i')];
        } else {
            unset($d[$sha]);
        }
        file_put_contents($f, json_encode((object) $d, JSON_UNESCAPED_UNICODE));
    }

    public function ordenarSueltas(bool $aplicar = false): void
    {
        if (! $this->clienteValido()) {
            return;
        }
        $args = ['ordenar', '--periodo', preg_match('/^\d{4}/', $this->chequeoPeriodo) ? $this->chequeoPeriodo : date('Y').'-1T'];
        if ($aplicar) {
            $args[] = '--aplicar';
            $aj = [];
            // Clave = el «fichero» de la simulación (por trimestres puede ser «T2/…»: suelta dentro de un trimestre)
            $clave = fn ($f) => str_contains((string) $f, '..') ? '' : ltrim((string) $f, '/');
            foreach ($this->ordenarAccion as $f => $acc) {
                if ($clave($f) !== '' && in_array($acc, ['MOVER', 'QUITAR', 'A PROCESAR', 'NADA'], true)) {
                    $aj[$clave($f)]['accion'] = $acc;
                }
            }
            foreach (array_filter($this->ordenarMes) as $f => $mm) {
                if ($clave($f) !== '' && preg_match('/^((0[1-9]|1[0-2])|T[1-4])$/', (string) $mm)) {
                    $aj[$clave($f)]['mes'] = (string) $mm;
                }
            }
            @mkdir($this->dirDatos().'/Output', 0775, true);
            file_put_contents($ruta = $this->dirDatos().'/Output/ordenar_ajustes.json', json_encode((object) $aj, JSON_UNESCAPED_UNICODE));
            array_push($args, '--ajustes', $ruta);
        } else {
            $this->ordenarAccion = $this->ordenarMes = [];
        }
        $ok = $this->ejecutar($args, 900, $aplicar ? 'Ordenar facturas sueltas (aplicado)' : 'Ordenar facturas sueltas (simulación)');
        if ($aplicar && $ok) {
            $this->enviarAlPc();   // y se lleva ya al OneDrive del PC (renombradas/movidas) y se quitan allí las sueltas idénticas
        }
    }

    protected function ordenarResultado(): ?array
    {
        $f = $this->clienteValido() ? $this->dirDatos().'/Output/Ordenar_facturas.json' : '';

        return $f !== '' && is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    /** Lo que Alex ha dado por «revisado» en el chequeo (no se vuelve a proponer): clave => [fecha, nota]. */
    public bool $verRevisadas = false;

    protected function chequeoRevisados(): array
    {
        $f = $this->clienteValido() ? $this->dirDatos().'/chequeo_revisados.json' : '';
        $d = $f !== '' && is_file($f) ? json_decode((string) file_get_contents($f), true) : [];

        return is_array($d) ? $d : [];
    }

    public function marcarRevisado(string $clave, string $nota = ''): void
    {
        if (! $this->clienteValido() || ! preg_match('/^(pdf|mayor)\|/', $clave)) {
            return;
        }
        $r = $this->chequeoRevisados();
        $r[$clave] = ['fecha' => now()->format('Y-m-d H:i'), 'nota' => mb_substr($nota, 0, 200), 'quien' => auth()->user()?->name];
        file_put_contents($this->dirDatos().'/chequeo_revisados.json', json_encode((object) $r, JSON_UNESCAPED_UNICODE));
    }

    public function desmarcarRevisado(string $clave): void
    {
        $r = $this->chequeoRevisados();
        unset($r[$clave]);
        if ($this->clienteValido()) {
            file_put_contents($this->dirDatos().'/chequeo_revisados.json', json_encode((object) $r, JSON_UNESCAPED_UNICODE));
        }
    }

    protected function chequeoResultado(): ?array
    {
        $p = trim($this->chequeoPeriodo);
        if (! $this->clienteValido() || ! preg_match('/^\d{4}-([1-4]T|(0[1-9]|1[0-2]))$/', $p)) {
            return null;
        }
        $f = $this->dirDatos().'/Output/Chequeo_mayor_'.$p.'.json';

        return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    /** Aviso de CIF no válido (cif_validar.py: longitud por país, letra de DNI/NIE, control del CIF). */
    public string $cifAviso = '';

    /** @return array{valido?: bool, formato_ok?: bool, normalizado?: string, mensaje?: string} */
    protected function analizarCif(string $cif): array
    {
        if (trim($cif) === '') {
            return [];
        }
        try {
            $r = Process::timeout(15)->run([$this->pythonBin(), $this->baseDir().'/cif_validar.py', '--json', $cif]);

            return $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Base y cuota de la línea $i = lo que falta del total (+ retención) tras las líneas de arriba, con su % de IVA. false si no se puede. */
    protected function repartoDesdeTotal(int $i): bool
    {
        $total = $this->num($this->form['total'] ?? '');
        $pct = $this->num($this->form['lineas'][$i]['pct'] ?? '');
        if ($total === null || $pct === null) {
            return false;
        }
        $isp = $this->esIsp();   // ISP: la cuota no suma al total
        $resto = $total + ($this->num($this->form['cuota_retencion'] ?? '') ?? 0.0);
        for ($j = 0; $j < $i; $j++) {
            $b = $this->num($this->form['lineas'][$j]['base'] ?? '');
            if ($b !== null) {
                $resto -= $b + ($isp ? 0.0 : ($this->num($this->form['lineas'][$j]['cuota'] ?? '') ?? 0.0));
            }
        }
        $resto = round($resto, 2);
        if ($i > 0 && abs($resto) < 0.005) {
            return false;   // las de arriba ya suman el total: no hay nada que repartir
        }
        $base = round($isp ? $resto : $resto / (1 + $pct / 100), 2);
        $cuota = $isp ? round($base * $pct / 100, 2) : round($resto - $base, 2);
        $this->form['lineas'][$i]['base'] = number_format($base, 2, '.', '');
        $this->form['lineas'][$i]['cuota'] = number_format($cuota, 2, '.', '');
        return true;
    }

    protected function recalcularDesdeTotal(): void
    {
        $total = $this->num($this->form['total'] ?? '');
        if ($total === null) {
            return;
        }
        $con = [];
        foreach ([0, 1, 2] as $i) {
            if ($this->num($this->form['lineas'][$i]['pct'] ?? '') !== null) {
                $con[] = $i;
            }
        }
        if (count($con) !== 1) {
            return;
        }
        $i = $con[0];
        $pct = $this->esIsp() ? 0.0 : $this->num($this->form['lineas'][$i]['pct']);   // ISP: el total es la base
        $ret = $this->num($this->form['cuota_retencion'] ?? '') ?? 0.0;
        $bruto = $total + $ret;   // base + IVA = total + retención
        $base = round($bruto / (1 + $pct / 100), 2);
        $this->form['lineas'][$i]['base'] = number_format($base, 2, '.', '');
        $this->form['lineas'][$i]['cuota'] = number_format(round($bruto - $base, 2), 2, '.', '');
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

    // ------------------------------------------------------------ entrada por la web (VPS)
    // Las facturas se suben SIEMPRE a la web (no dependen de que el OneDrive de nadie esté al día). El navegador calcula
    // la huella de cada PDF (SHA-1; los 12 primeros caracteres son el id de la factura, igual que id_fichero() de
    // facturas_ocr.py) y solo sube las que el servidor no conoce. Al llegar se leen solas en segundo plano.

    /** SHA-1 (12 primeros caracteres) de un fichero. */
    protected function idPdf(string $ruta): string
    {
        return substr(sha1_file($ruta), 0, 12);
    }

    /** Como idPdf(), pero recordando el resultado mientras el fichero no cambie (se llama en cada pintado de la pantalla). */
    protected function idPdfCache(string $ruta): string
    {
        clearstatcache(true, $ruta);

        return \Illuminate\Support\Facades\Cache::remember('focr-id-'.sha1($ruta.'|'.filesize($ruta).'|'.filemtime($ruta)), 86400, fn () => $this->idPdf($ruta));
    }

    /** [id => nombre] de los PDF que hay ahora en la carpeta de entrada. */
    protected function pdfsDeEntrada(): array
    {
        $out = [];
        foreach (glob($this->dirEntrada().'/*') ?: [] as $f) {
            if (is_file($f) && preg_match('/\.pdf$/i', $f)) {
                $out[$this->idPdf($f)] = basename($f);
            }
        }

        return $out;
    }

    /**
     * El navegador pregunta, antes de subir: de estas [id, nombre], ¿cuáles conoce ya el servidor?
     * Devuelve ['conocidas' => [id => texto]]; lo que no esté ahí hay que subirlo.
     */
    public function huellasNuevas(array $items): array
    {
        if (! $this->web() || ! $this->clienteValido()) {
            return ['conocidas' => []];
        }
        $estados = [];
        foreach ($this->estado()['facturas'] as $f) {
            if (! empty($f['oculta']) && in_array($f['estado'], ['pendiente', 'rechazada', 'ilegible'], true)) {
                continue;   // quitada de la lista y no contabilizada: si se vuelve a subir, se recupera (recibirPdfs)
            }
            $estados[$f['id']] = ['validada' => 'ya validada', 'validando' => 'validándose', 'duplicada' => 'ya marcada como duplicada',
                'rechazada' => 'ya rechazada'][$f['estado']] ?? 'ya está en la lista';
        }
        $enEntrada = $this->pdfsDeEntrada();
        $conocidas = [];
        foreach ($items as $it) {
            $id = (string) ($it[0] ?? '');
            if ($this->copiaConOtroNombre($id, (string) ($it[1] ?? ''), $enEntrada)) {
                continue;   // mismo contenido que una ya conocida pero otro nombre: se sube y va a Duplicadas (recibirPdfs)
            }
            if (isset($enEntrada[$id])) {
                $conocidas[$id] = 'ya está en el servidor';
            } elseif (isset($estados[$id])) {
                $conocidas[$id] = $estados[$id];
            }
        }

        return ['conocidas' => $conocidas];
    }

    /**
     * ¿Es un PDF idéntico (misma huella) a una factura ya conocida pero subido con otro nombre? (14-oct-2026: Xatélite 26-000589_ de Sunbelt).
     * Esas no se descartan como «ya estaban»: van directas, con _DUPLICADA, a la carpeta Duplicadas del periodo.
     */
    protected function copiaConOtroNombre(string $id, string $nombre, array $enEntrada): bool
    {
        $limpio = fn ($n) => mb_strtolower(trim(preg_replace('/\.pdf$/i', '', basename(str_replace('\\', '/', (string) $n)))));
        $nombre = $limpio($nombre);
        if ($nombre === '' || preg_match('/[_ ]duplicada( \(\d+\))?$/u', $nombre)) {
            return false;   // ya es la copia de Duplicadas (p.ej. al volver a subir la carpeta entera): no se aparta otra vez
        }
        $nombres = isset($enEntrada[$id]) ? [$limpio($enEntrada[$id])] : [];
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['id'] === $id) {
                $nombres[] = $limpio($f['ruta'] ?? '');
                $nombres[] = $limpio($f['nombre_original'] ?? '');
                $nombres[] = $limpio($f['ruta_antes_rechazo'] ?? '');
            }
        }

        return $nombres !== [] && ! in_array($nombre, $nombres, true);
    }

    /** Termina una subida: guarda en la carpeta de entrada los PDF nuevos y lanza su lectura. */
    public function recibirPdfs(): array
    {
        $res = ['guardadas' => [], 'repetidas' => [], 'rechazadas' => [], 'recuperadas' => [], 'duplicadas' => []];
        if (! $this->web() || ! $this->clienteValido()) {
            return $res;
        }
        $subidos = array_values(array_filter((array) $this->pdfsSubidos, fn ($f) => $f instanceof \Illuminate\Http\UploadedFile));
        $this->pdfsSubidos = [];
        @mkdir($this->dirEntrada(), 0775, true);
        $conocidas = $this->huellasNuevas(array_map(fn ($f) => [$this->idPdf($f->getRealPath()), $f->getClientOriginalName()], $subidos))['conocidas'];
        $enEntrada = $this->pdfsDeEntrada();
        foreach ($subidos as $f) {
            $nombre = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $f->getClientOriginalName());
            $id = $this->idPdf($f->getRealPath());
            if (! preg_match('/\.pdf$/i', $nombre) || ! str_starts_with((string) @file_get_contents($f->getRealPath(), false, null, 0, 5), '%PDF')) {
                $res['rechazadas'][] = $nombre;
            } elseif (($oculta = $this->ocultaRecuperable($id)) && ! isset($enEntrada[$id])) {
                // Estaba quitada de la lista sin contabilizar y la vuelves a subir: se procesa (antes se descartaba como «ya estaba»)
                $destino = $this->dirEntrada().'/'.$nombre;
                if (file_exists($destino)) {
                    $destino = $this->dirEntrada().'/'.pathinfo($nombre, PATHINFO_FILENAME).'_'.$id.'.pdf';
                }
                copy($f->getRealPath(), $destino);
                $enEntrada[$id] = basename($destino);
                $this->ejecutar(['recuperar', $oculta, '--carpeta', $destino], 60, 'Recuperar '.$nombre, false);
                $res['recuperadas'][] = basename($destino);
            } elseif ($this->copiaConOtroNombre($id, $nombre, $enEntrada)) {
                @mkdir($this->dirDatos().'/_tmp', 0775, true);
                $tmp = $this->dirDatos().'/_tmp/'.$nombre;
                copy($f->getRealPath(), $tmp);
                $this->ejecutar(['copia_duplicada', $id, '--carpeta', $tmp, '--periodo', $this->periodo ?: $this->periodoActual()], 60, 'Duplicada '.$nombre, false);
                $res['duplicadas'][] = $nombre;
            } elseif (isset($conocidas[$id]) || isset($enEntrada[$id])) {
                $res['repetidas'][] = $nombre;
            } else {
                $destino = $this->dirEntrada().'/'.$nombre;
                if (file_exists($destino)) {
                    $destino = $this->dirEntrada().'/'.pathinfo($nombre, PATHINFO_FILENAME).'_'.$id.'.pdf';
                }
                copy($f->getRealPath(), $destino);
                $enEntrada[$id] = basename($destino);
                $res['guardadas'][] = basename($destino);
            }
        }
        $this->salida = count($res['guardadas']).' factura(s) recibida(s) en el servidor'
            .($res['recuperadas'] ? ', '.count($res['recuperadas']).' recuperada(s) (estaban quitadas de la lista: vuelven a Por revisar)' : '')
            .($res['repetidas'] ? ', '.count($res['repetidas']).' ya estaban (no se han vuelto a subir)' : '')
            .($res['duplicadas'] ? ', '.count($res['duplicadas']).' idéntica(s) a otra ya subida con otro nombre → a Duplicadas con _DUPLICADA: '.implode(', ', $res['duplicadas']) : '')
            .($res['rechazadas'] ? ', '.count($res['rechazadas']).' no son PDF: '.implode(', ', $res['rechazadas']) : '').'.';
        if ($res['guardadas']) {
            $this->leerEnSegundoPlano();
        }

        return $res;
    }

    /** Id de la factura si estaba quitada de la lista (oculta), sin contabilizar, y se vuelve a subir; si no, null. */
    protected function ocultaRecuperable(string $id): ?string
    {
        foreach ($this->estado()['facturas'] as $f) {
            if ($f['id'] === $id && ! empty($f['oculta']) && in_array($f['estado'], ['pendiente', 'rechazada', 'ilegible'], true)) {
                return $f['id'];
            }
        }

        return null;
    }

    /** Marcas de la lectura en segundo plano: [inicio (epoch), fin (epoch|null)] o null si no se ha lanzado nunca. */
    protected function lecturaEnCurso(): ?array
    {
        $dir = $this->dirDatos().'/_cola';
        $ini = (int) @file_get_contents($dir.'/lectura.inicio');
        if (! $ini) {
            return null;
        }
        $fin = (int) @file_get_contents($dir.'/lectura.fin');
        if ($fin >= $ini) {
            return null;
        }
        // Sin «fin»: sigue en marcha solo si algo la está haciendo (arrancando, esperando el OCR de un PC o Python trabajando).
        // Si no, es una marca vieja de una lectura que murió (error, reinicio...) y no debe bloquear las siguientes.
        if (time() - $ini < 20 || $this->pendientes || $this->pythonActivo()) {
            return [$ini, null];
        }

        return null;
    }

    /** ¿Hay un facturas_ocr.py de este cliente en marcha? (el propio Python guarda un bloqueo en /tmp mientras trabaja) */
    protected function pythonActivo(): bool
    {
        $f = @fopen('/tmp/facturasocr-'.strtolower(preg_replace('/[^A-Za-z0-9]/', '', $this->cliente)).'.lock', 'c');
        if (! $f) {
            return false;
        }
        $libre = flock($f, LOCK_EX | LOCK_NB);
        if ($libre) {
            flock($f, LOCK_UN);
        }
        fclose($f);

        return ! $libre;
    }

    /** Facturas de la entrada que aún no están en la lista (sin leer). */
    protected function sinLeerEnEntrada(): int
    {
        return count(array_filter($this->estadoEntrada(), fn ($e) => in_array($e[1], ['en el servidor', 'leyendo'], true)));
    }

    /**
     * Lee las facturas de la carpeta de entrada sin bloquear la página. Las que son una imagen (sin texto) pasan antes por el
     * OCR de Windows de un PC trabajador (ocr_previo.py: lee mejor NIF y fechas que Tesseract); si no hay ningún PC conectado, o
     * tarda más de 3 minutos, se lee ya con Tesseract. Las que tienen texto se leen directamente.
     */
    public function leerEnSegundoPlano(): void
    {
        if (! $this->web() || ! $this->clienteValido()) {
            return;
        }
        if ($faltan = $this->basesQueFaltan()) {
            $this->salida .= "\n⚠️ No puedo leer las facturas de {$this->cliente} sin ".implode(' ni ', $faltan).". Sube ".(count($faltan) > 1 ? 'esos ficheros' : 'ese fichero')." de SAGE en «Ficheros base» (Excel exportado de SAGE) y vuelve a intentarlo.";
            return;
        }
        if (! in_array($this->ciclo, ['M', 'T'], true)) {
            $this->salida .= "\nElige si el IVA es mensual o trimestral y pulsa «Leer las facturas»: ya están en el servidor.";
            return;
        }
        if ($this->lecturaEnCurso() !== null) {
            return;   // ya se está leyendo: la siguiente pasada recogerá lo nuevo
        }
        $dir = $this->dirDatos().'/_cola';
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/lectura.inicio', (string) time());
        @unlink($dir.'/lectura.fin');
        $this->leyendo = true;
        // Por defecto se lee con el OCR del servidor (Tesseract): rápido y sin depender de que haya un PC encendido. El OCR de Windows,
        // que lee mejor NIF y fechas, se pide por factura con «Escaneo de calidad» (o para todas con FACTURASOCR_OCR_WINDOWS_AUTO=true).
        if (! (config('contabilidad.facturasocr_ocr_windows_auto') && $this->pedirOcrDeWindows())) {
            $this->lanzarAnalisis();
        }
    }

    /**
     * «Escaneo de calidad» de la factura abierta: la manda a un PC, que la pasa por el OCR de Windows (todas las páginas y las zonas del NIF),
     * y al volver se vuelve a proponer con ese texto. Para las facturas que Tesseract no ha leído bien.
     */
    public function escaneoDeCalidad(): void
    {
        $f = $this->sel ? $this->factura($this->sel) : null;
        if (! $this->web() || ! $f || ! is_file($f['ruta'])) {
            return;
        }
        if (! $this->colaLista() || $this->pcsConectados() === 0) {
            $this->addError('validar', 'No hay ningún PC de trabajo conectado ahora mismo: no se puede hacer el escaneo de calidad. Inténtalo más tarde o usa «Leer con OCR» (servidor).');
            return;
        }
        $this->lanzarEnCola([['script' => 'ocr_previo.py', 'args' => ['--salida', '{DIR}/_tmp/ocr_out', '--forzar', '{E0}'], 'timeout' => 600,
            'etiqueta' => 'Escaneo de calidad (OCR de Windows) de '.basename($f['ruta'])]],
            ['entradas' => [['ruta' => $f['ruta'], 'nombre' => $f['id'].'.pdf', 'dir' => '_tmp/ocr_in', 'unico' => true]],
                'post' => 'postEscaneoCalidad', 'ctx' => ['id' => $f['id']]]);
    }

    /** Llegó el OCR de Windows de una factura: a la caché, y se vuelve a proponer con ese texto (comando «ocr», que usa la caché). */
    protected function postEscaneoCalidad(array $ctx, int $desde, array $oks): void
    {
        $this->ingerirOcr((int) ($ctx['tarea'] ?? 0));
        $antes = $this->salida;
        $ok = $this->ejecutar(array_merge(['ocr', $ctx['id']], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 300, 'Escaneo de calidad', false);
        $this->salida = $antes;
        if ($ok && $this->sel === $ctx['id']) {
            $this->abrir($ctx['id']);
        }
        $this->dispatch('proceso-terminado', mensaje: $ok ? '✅ Escaneo de calidad hecho: factura propuesta de nuevo con el OCR de Windows.' : '⚠️ El escaneo de calidad no ha sacado texto de esta factura.');
    }

    /** Pide a un PC el OCR de Windows de las facturas escaneadas de la entrada que aún no lo tienen. true si ha quedado pedido. */
    protected function pedirOcrDeWindows(): bool
    {
        if (! $this->colaLista() || $this->pcsConectados() === 0) {
            return false;
        }
        try {
            $r = Process::path($this->baseDir())->env($this->entornoPython())->timeout(60)
                ->run([$this->pythonBin(), 'ocr_previo.py', '--listar', $this->dirEntrada(), '--cliente', $this->cliente]);
        } catch (\Throwable $e) {
            // si ni siquiera se puede mirar qué necesita OCR, no se bloquea la lectura: se lee ya con Tesseract
            $this->salida .= "\n⚠️ No he podido preparar el OCR de Windows (".class_basename($e).'): se lee con el OCR del servidor.';
            return false;
        }
        $lista = $r->successful() ? (json_decode(trim($r->output()), true) ?: []) : [];
        $entradas = [];
        foreach ($lista as $f) {
            if (! is_file($this->dirDatos().'/_ocr/'.$f['id'].'.json') && count($entradas) < 40) {
                $entradas[] = ['ruta' => $this->dirEntrada().'/'.$f['nombre'], 'nombre' => $f['id'].'.pdf', 'dir' => '_tmp/ocr_in', 'unico' => true];
            }
        }
        if (! $entradas) {
            return false;
        }
        $args = ['--salida', '{DIR}/_tmp/ocr_out'];
        foreach (array_keys($entradas) as $i) {
            $args[] = '{E'.$i.'}';
        }
        $tid = $this->lanzarEnCola([['script' => 'ocr_previo.py', 'args' => $args, 'timeout' => 900,
            'etiqueta' => 'OCR de Windows de '.count($entradas).' factura(s) (escaneadas o sin NIF conocido)']],
            ['entradas' => $entradas, 'resultados' => 'ocr', 'post' => 'postOcrPrevio', 'ctx' => ['hasta' => time() + 180]]);

        return $tid !== null;
    }

    /** Llegó el OCR de Windows de un PC: se guarda en la caché del cliente y empieza la lectura. */
    protected function postOcrPrevio(array $ctx, int $desde, array $oks): void
    {
        $this->ingerirOcr((int) ($ctx['tarea'] ?? 0));
        $this->lanzarAnalisis();
    }

    /** Copia a la caché de OCR del cliente lo que subió el PC (<id>.json). */
    protected function ingerirOcr(int $tarea): void
    {
        $dst = $this->dirDatos().'/_ocr';
        @mkdir($dst, 0775, true);
        foreach (glob(\App\Support\ColaTareas::carpetaFicheros($tarea).'/*.json') ?: [] as $f) {
            if (preg_match('/^[0-9a-f]{12}\.json$/', basename($f)) && is_array(json_decode((string) file_get_contents($f), true))) {
                copy($f, $dst.'/'.basename($f));
            }
        }
    }

    /** Lanza la lectura (facturas_ocr.py analizar) en segundo plano; al terminar deja la marca lectura.fin. */
    protected function lanzarAnalisis(): void
    {
        $dir = $this->dirDatos().'/_cola';
        $env = '';
        foreach ($this->entornoPython() as $k => $v) {
            $env .= $k.'='.escapeshellarg($v).' ';
        }
        $args = array_merge(['analizar', '--carpeta', $this->dirEntrada()], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']);
        $cmd = 'cd '.escapeshellarg($this->baseDir()).' && ('.$env.escapeshellarg($this->pythonBin()).' facturas_ocr.py '.escapeshellarg($this->cliente)
            .' '.implode(' ', array_map('escapeshellarg', $args)).' > '.escapeshellarg($dir.'/lectura.log').' 2>&1; date +%s > '.escapeshellarg($dir.'/lectura.fin')
            .') < /dev/null > /dev/null 2>&1 &';
        Process::run(['bash', '-c', 'nohup setsid bash -c '.escapeshellarg($cmd).' &']);
    }

    /** wire:poll mientras se lee: al terminar enseña el resumen y deja la lista al día. */
    public function revisarLectura(): void
    {
        if (! $this->leyendo) {
            return;
        }
        $this->revisarTareas();   // el OCR de Windows que se espera de un PC (cierra la tarea y arranca la lectura)
        foreach ($this->pendientes as $tid => $p) {
            // Si el PC tarda más de 3 minutos, se lee ya con Tesseract (y la tarea se anula si nadie la había cogido)
            if (($p['ctx']['hasta'] ?? PHP_INT_MAX) < time()) {
                unset($this->pendientes[$tid]);
                \Illuminate\Support\Facades\DB::table('tareas')->where('id', $tid)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'updated_at' => now()]);
                $this->lanzarAnalisis();
            }
        }
        if ($this->lecturaEnCurso() === null) {
            // Las que se subieron mientras se leía no entran en esa pasada (lee la carpeta tal como estaba al empezar): otra pasada
            // mientras queden sin leer y la anterior haya avanzado (si no avanza, algo falla y no se repite en bucle).
            $quedan = $this->sinLeerEnEntrada();
            if ($quedan > 0 && ($this->sinLeerAntes === 0 || $quedan < $this->sinLeerAntes)) {
                $this->sinLeerAntes = $quedan;
                $this->leerEnSegundoPlano();
                return;
            }
            $this->sinLeerAntes = 0;
            $this->leyendo = false;
            if ($this->fechasPendientes) {
                $this->fechasPendientes = false;
                $this->recalcularFechas();   // el IVA/periodo cambió durante la lectura: las fechas se rehacen con lo elegido ahora
            }
            $log = trim((string) @file_get_contents($this->dirDatos().'/_cola/lectura.log'));
            $this->salida = $log !== '' ? $log : 'Lectura terminada.';
            $this->dispatch('proceso-terminado', mensaje: '✅ Lectura de facturas terminada'."\n".$this->salida);
            $this->dispatch('lectura-terminada');   // sonido, aviso del escritorio y título de la pestaña (la lectura puede tardar minutos)
        }
    }

    /**
     * Pide a un PC trabajador (FACTURASOCR_PC o cualquiera) que lleve a su OneDrive lo contabilizado: PDF de las carpetas
     * del mes, Excel entregados y estado, comprobando la huella de cada fichero (trabajador.py, h_facturasocr_sync).
     */
    public function enviarAlPc(): void
    {
        if (! $this->web() || ! $this->clienteValido() || ! \Illuminate\Support\Facades\Schema::hasTable('tareas')) {
            return;
        }
        if (! \Illuminate\Support\Facades\DB::table('tareas')->where('proceso', 'pc.facturasocr')->where('parametros', 'like', '%"cliente":"'.$this->cliente.'"%')
            ->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            \App\Support\ColaTareas::crear('pc.facturasocr', ['cliente' => $this->cliente, 'empresa' => $this->nombreEmpresaOcr()], \App\Support\ColaTareas::pcElegido() ?: (config('contabilidad.facturasocr_pc') ?: null), auth()->id());
        }
        $this->sincronizando = true;
    }

    public ?int $zTarea = null;
    public string $zAviso = '';

    /** Nombre de la entidad del cliente (para buscar su carpeta en Z:). */
    protected function nombreEmpresaOcr(): string
    {
        $cfg = json_decode((string) @file_get_contents($this->dirCliente().'/cliente.json'), true) ?: [];

        return (string) (\App\Models\Entidad::whereKey((int) ($cfg['entidad_id'] ?? 0))->value('entidad') ?? '');
    }

    /** «Traer de Z:»: un PC con el disco Z: mira la carpeta de la empresa (este mes y los dos anteriores) y sube lo que el servidor no conoce. */
    public function traerDeZ(): void
    {
        if (! $this->web() || ! $this->clienteValido() || ! \Illuminate\Support\Facades\Schema::hasTable('tareas')) {
            return;
        }
        $conocidas = array_keys($this->pdfsDeEntrada());
        foreach ($this->estado()['facturas'] as $f) {
            $conocidas[] = $f['id'];
        }
        $meses = [];
        for ($i = 0; $i < 3; $i++) {
            $meses[] = now()->startOfMonth()->subMonths($i)->format('Y_m');
        }
        $this->zAviso = '';
        $this->zTarea = \App\Support\ColaTareas::crear('pc.facturasocr_z', ['cliente' => $this->cliente, 'empresa' => $this->nombreEmpresaOcr(), 'meses' => $meses,
            'conocidas' => array_values(array_unique($conocidas))], config('contabilidad.facturasocr_z_pc') ?: null, auth()->id());
    }

    /** wire:poll mientras el PC trae de Z:: al terminar deja los PDF en la Entrada y lanza su lectura. */
    public function revisarZ(): void
    {
        if (! $this->zTarea) {
            return;
        }
        $t = \Illuminate\Support\Facades\DB::table('tareas')->find($this->zTarea);
        if (! $t || in_array($t->estado, ['pendiente', 'en_curso'], true)) {
            return;
        }
        $this->zTarea = null;
        $r = json_decode($t->resultado ?? 'null', true) ?: [];
        if ($t->estado !== 'ok') {
            $this->zAviso = '⚠️ No se ha podido traer de Z:: '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -300)) ?: 'error en el PC');

            return;
        }
        $dir = \App\Support\ColaTareas::carpetaFicheros($t->id);
        $archivos = array_map(fn ($f) => ['ruta' => $f, 'nombre' => basename($f)], array_values(array_filter(glob($dir.'/*.pdf') ?: [], 'is_file')));
        $res = \App\Support\FacturasOcrEntrada::importar($this->cliente, $archivos);
        $this->zAviso = '📥 Z: '.\App\Support\FacturasOcrEntrada::resumen($res).' ('.($r['ya'] ?? 0).' ya conocidas)'
            .(! empty($r['sin_carpeta']) ? ' · sin carpeta en Z: '.implode(', ', $r['sin_carpeta']) : '');
        if ($res['guardadas']) {
            $this->leerEnSegundoPlano();
        }
    }

    /** wire:poll mientras un PC lleva los ficheros a su OneDrive. */
    public function revisarSync(): void
    {
        if ($this->sincronizando && ! \Illuminate\Support\Facades\DB::table('tareas')->where('proceso', 'pc.facturasocr')
            ->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->sincronizando = false;
        }
    }

    /** Resultado del último envío al PC (lo sube el trabajador): fecha, PC, ficheros nuevos/ya estaban, conflictos y fallos. */
    protected function estadoSync(): ?array
    {
        try {
            return $this->web() && $this->clienteValido() && \Illuminate\Support\Facades\Schema::hasTable('estado_procesos')
                ? \App\Support\ColaTareas::estado('facturasocr.sync.'.$this->cliente) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Estado de cada PDF de la carpeta de entrada para la tabla de la pantalla: [nombre, estado]. */
    protected function estadoEntrada(): array
    {
        if (! $this->web() || ! $this->clienteValido()) {
            return [];
        }
        $porRuta = $porId = [];
        foreach ($this->estado()['facturas'] as $f) {
            $porRuta[basename($f['ruta'])] = $f['estado'];
            $porId[$f['id']] = $f['estado'];
        }
        $out = [];
        foreach (glob($this->dirEntrada().'/*.{pdf,PDF}', GLOB_BRACE) ?: [] as $f) {
            $n = basename($f);
            // Por nombre y, si no, por huella: la misma factura puede constar con otro nombre (renombrada o en la carpeta de su mes)
            $e = $porRuta[$n] ?? $porId[$this->idPdfCache($f)] ?? null;
            $out[] = [$n, $e === null ? ($this->leyendo ? 'leyendo' : 'en el servidor') : $e];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a[0], $b[0]));

        return $out;
    }

    /**
     * PDF de la entrada que ya no hacen falta ahí: [ruta del PDF en Entrada => id de la factura cuya ruta es esa (o null si es una copia)].
     * Son los de facturas validadas, duplicadas o rechazadas; también las copias de ellas (misma huella, otra ruta).
     */
    protected function vaciablesEntrada(): array
    {
        $estados = ['validada', 'duplicada', 'rechazada'];
        $porId = [];
        foreach ($this->estado()['facturas'] as $f) {
            $porId[$f['id']] = $f;
        }
        $out = [];
        foreach (glob($this->dirEntrada().'/*.{pdf,PDF}', GLOB_BRACE) ?: [] as $ruta) {
            $f = $porId[$this->idPdfCache($ruta)] ?? null;
            if ($f && in_array($f['estado'], $estados, true)) {
                $out[$ruta] = realpath($f['ruta']) === realpath($ruta) ? $f['id'] : null;
            }
        }

        return $out;
    }

    /**
     * Limpia la lista «Facturas subidas»: los PDF de Entrada de facturas ya contabilizadas, duplicadas o rechazadas se apartan a
     * Entrada/_vaciadas-<fecha> (no se borran). Por revisar, no legibles y sin leer se quedan donde están.
     */
    public function vaciarEntrada(): void
    {
        if (! $this->web() || ! $this->clienteValido() || $this->leyendo || $this->sinLeerEnEntrada() > 0) {
            return;
        }
        $mover = $this->vaciablesEntrada();
        if (! $mover) {
            return;
        }
        $dir = $this->dirEntrada().'/_vaciadas-'.date('Y-m-d');
        @mkdir($dir, 0775, true);
        $nuevas = [];
        $n = 0;
        foreach ($mover as $ruta => $id) {
            $destino = $dir.'/'.basename($ruta);
            for ($i = 2; file_exists($destino); $i++) {
                $destino = $dir.'/'.pathinfo($ruta, PATHINFO_FILENAME)." ($i).pdf";
            }
            if (@rename($ruta, $destino)) {
                $n++;
                if ($id) {
                    $nuevas[$id] = $destino;
                }
            }
        }
        $this->modificarEstado(function (array $e) use ($nuevas) {
            foreach ($e['facturas'] as &$f) {
                if (isset($nuevas[$f['id']])) {
                    $f['ruta'] = $nuevas[$f['id']];   // el visor del PDF sigue encontrándola
                }
            }

            return $e;
        });
        $this->dispatch('proceso-terminado', mensaje: '✅ Entrada vaciada: '.$n.' PDF apartados a '.basename($dir).' (dentro de Entrada; no se ha borrado ninguno).');
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
        if ($this->web()) {
            $this->leerEnSegundoPlano();
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

    /** Se cambió IVA/periodo/cierre mientras se leían facturas: las fechas de registro se recalculan al terminar la lectura. */
    public bool $fechasPendientes = false;

    public function recalcularFechas(): void
    {
        if (! $this->clienteValido() || ! in_array($this->ciclo, ['M', 'T'], true) || ! is_file($this->dirDatos().'/facturas.json')) {
            return;
        }
        // Mientras se lee la carpeta (minutos, con el cliente bloqueado) no se puede recalcular: se deja anotado y se hace al terminar
        if ($this->web() && $this->lecturaEnCurso() !== null) {
            $this->fechasPendientes = true;
            $this->dispatch('proceso-terminado', mensaje: "⏳ Se están leyendo las facturas.\nEl periodo nuevo se aplicará a las fechas de registro en cuanto termine la lectura (se hace solo).");
            return;
        }
        // Otro Python de este cliente (cola de validaciones, OCR...) tiene el bloqueo: esperarlo agotaría los 60 s y daría excepción
        if ($this->web() && $this->pythonActivo()) {
            $this->dispatch('proceso-terminado', mensaje: "⏳ La cola está trabajando con este cliente.\nCuando termine, vuelve a elegir el periodo para recalcular las fechas de registro.");
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
        return getenv('WSL_INTEROP') || ! is_dir('/run/WSL') ? [] : ['WSL_INTEROP' => '/run/WSL/1_interop'];
    }

    /** Lanza facturas_ocr.py <cliente> ...; devuelve si terminó bien. */
    protected function ejecutar(array $args, int $timeout, string $etiqueta, bool $avisar = true): bool
    {
        if (! $this->ejecutaAqui()) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            return false;
        }
        $cmd = array_merge([$this->pythonBin(), 'facturas_ocr.py', $this->cliente], $args);
        try {
            $r = Process::path($this->baseDir())->env($this->entornoPython())->timeout($timeout)->run($cmd);
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

    /** mtime de patrones / cuentas propias / proveedores la última vez que se abrió una factura (para refrescar los combos). */
    public string $marcaListas = '';

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
        if ($f['estado'] === 'pendiente' && empty($f['editada']) && $this->ejecutaAqui()
            && (($f['confianza']['proveedor'] ?? '') !== 'ok' || ! empty($f['datos']['proveedor_nuevo']) || $mismoEnCola)) {
            $salida = $this->salida;
            if ($this->ejecutar(array_merge(['reproponer', $id], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 120, 'Volver a proponer', false)) {
                $f = $this->factura($id) ?? $f;
            }
            $this->salida = $salida;
        }
        // Si desde la última factura se creó un proveedor o una cuenta (al validar, en otra pestaña...), los combos vuelven a pedir sus listas
        clearstatcache();
        $marca = @filemtime($this->dirDatos().'/patrones.json').'-'.@filemtime($this->dirDatos().'/cuentas_propias.json')
            .'-'.@filemtime($this->dirCliente().'/Base/proveedores.json');
        if ($marca !== $this->marcaListas) {
            if ($this->marcaListas !== '') {
                $this->dispatch('focr-listas');
            }
            $this->marcaListas = $marca;
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
        if (($f['estado'] ?? '') === 'pendiente' && abs((float) ($this->descuadre() ?? 0)) >= 0.01) {
            $this->aplicarTransaccion38();   // con 38 y sin cuadrar (p.ej. bases sin leer): IVA 0 y base = total
        }
        $this->cifAviso = $this->enLote ? '' : (string) ($this->analizarCif($d['cif'] ?? '')['mensaje'] ?? '');
        $this->avisarHistoria($id);
        if (! $this->enLote) {
            $this->dispatch('focr-abierta');   // el cursor vuelve al último campo que se estaba usando (ver focrFoco en la vista)
        }
    }

    /** Id de la validada que se está viendo en el modal del listado ('' = cerrado). */
    public string $verValidada = '';

    public function verValidada(string $id): void
    {
        $this->verValidada = $id;
    }

    public function cerrarValidada(): void
    {
        $this->verValidada = '';
    }

    /** ‹ › del modal: la anterior/siguiente de la lista tal como está filtrada y ordenada en pantalla. */
    public function vecinaValidada(int $paso): void
    {
        $ids = array_column($this->validadasListado(), 'id');
        $i = array_search($this->verValidada, $ids, true);
        if ($i !== false && isset($ids[$i + $paso])) {
            $this->verValidada = $ids[$i + $paso];
        }
    }

    /** Del modal a la pantalla de revisión: la validada vuelve a pendiente y se abre para corregirla. */
    public function corregirValidada(string $id): void
    {
        $this->verValidada = '';
        $this->sel = $id;   // reabrir() sigue en ella si es la abierta
        $this->reabrir($id);
    }

    public function cerrar(): void
    {
        $this->sel = '';
        $this->propuestaCif = null;
        $this->form = [];
        $this->avisarHistoria('');
    }

    /** Para que el botón «atrás» del ratón/navegador vuelva al listado desde una factura abierta (el navegador lo gestiona en el cliente). */
    protected function avisarHistoria(string $id): void
    {
        if (! $this->enLote) {
            $this->dispatch('focr-historia', id: $id);
        }
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
                $this->form['canal'] = $this->analitica ? ($pat['canal'] ?? '') : '';
            } elseif ($b = $this->cuentasBancos()[$cta] ?? null) {
                // Creada en Bancos (a veces aún sin CIF)
                $this->form['proveedor'] = $b['nombre'] ?? '';
                $this->form['cif'] = $b['cif'] ?? '';
                $this->ponerCp($b['cp'] ?? '');
            }
            $this->altaProveedorNuevo();
            return;
        }
        $this->form['proveedor'] = $p['razon'] ?? '';
        $this->form['cif'] = ($p['cif_europeo'] ?? '') ?: (($p['sigla'] ?? '').($p['nif'] ?? ''));
        $this->form['contrapartida'] = $pat['contrapartida'] ?? ($p['contrapartida'] ?? '');
        $this->form['codigo_transaccion'] = (string) (($pat['codigo_transaccion'] ?? '') !== '' ? $pat['codigo_transaccion'] : ($p['transaccion'] ?? ''));
        $this->form['clave_operacion'] = (string) ($pat['clave_operacion'] ?? '');
        $this->form['cp'] = $p['cp'] ?? '';
        $this->form['canal'] = $this->analitica ? ($pat['canal'] ?? ($p['canal'] ?? '')) : '';
        $this->form['nombre_fichero'] = $pat['nombre_fichero'] ?? '';
        if (($this->form['su_factura'] ?? '') && $this->sii()) {
            $this->form['comentario'] = mb_substr(trim('Fra '.$this->form['su_factura'].' '.$this->form['proveedor']), 0, 40);
        }
    }

    /**
     * Cuenta de proveedor tecleada a mano que no está en el listado de SAGE (proveedor nuevo con el número que elige Alex, 15-oct-2026):
     * texto del aviso si algo no encaja (otro número de cifras, ya dada a otro proveedor nuevo o en Bancos) o '' si está bien. No impide nada:
     * la pantalla pregunta «¿Estás seguro?» y, si sí, la pone.
     */
    public function avisoCuentaProveedor(string $cuenta): string
    {
        $cuenta = trim($cuenta);
        if (! preg_match('/^\d{3,12}$/', $cuenta)) {
            return 'La cuenta tiene que ser solo números.';
        }
        $largos = array_count_values(array_map('strlen', array_map('strval', array_keys($this->proveedores()))));
        arsort($largos);
        $largo = (int) (array_key_first($largos) ?? 0);
        $avisos = [];
        if ($largo && strlen($cuenta) !== $largo) {
            $avisos[] = "Las cuentas de proveedor de este cliente tienen {$largo} cifras y {$cuenta} tiene ".strlen($cuenta).'.';
        }
        if (! str_starts_with($cuenta, '4')) {
            $avisos[] = "{$cuenta} no empieza por 4 (las de proveedor son 400/410…).";
        }
        $pat = $this->patrones()[$cuenta] ?? [];
        // si es el mismo proveedor (mismo nombre, aunque el NIF sea otro) no se avisa
        $nombre = (string) ($this->form['proveedor'] ?? '');
        if (! empty($pat['nuevo'])) {
            if (! self::mismoProveedor($pat['proveedor'] ?? '', $nombre)) {
                $avisos[] = "La cuenta {$cuenta} ya se dio aquí a otro proveedor nuevo: ".($pat['proveedor'] ?? '').' ('.($pat['cif'] ?? '').').';
            }
        } elseif (($b = $this->cuentasBancos()[$cuenta] ?? null) && ! self::mismoProveedor($b['nombre'] ?? '', $nombre)) {
            $avisos[] = "La cuenta {$cuenta} está dada en Bancos a ".($b['nombre'] ?? '').'.';
        }
        return implode(' ', $avisos);
    }

    /**
     * Proveedor nuevo: la siguiente cuenta 410xxx libre (la más alta + 1) contando el listado de SAGE,
     * los nuevos ya validados aquí y los propuestos en otras facturas pendientes (misma regla que
     * cuenta_nueva() de facturas_ocr.py). Si ese CIF ya tiene cuenta nueva, la misma.
     */
    public function cuentaNueva(): void
    {
        $cif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->form['cif'] ?? ''));
        // El CIF del formulario es el del proveedor que ya tiene puesto (mal reconocido): se pide otro proveedor,
        // así que cuenta libre y datos del proveedor en blanco (14-oct-2026, Subiñas propuesta como Espumalia)
        $actual = trim((string) ($this->form['cuenta'] ?? ''));
        if ($cif !== '' && $actual !== '') {
            $p = $this->proveedores()[$actual] ?? [];
            $pat = $this->patrones()[$actual] ?? [];
            $b = $this->cuentasBancos()[$actual] ?? [];
            $suyos = array_merge($pat['nifs'] ?? [], [$pat['cif'] ?? '', $p['cif_europeo'] ?? '', ($p['sigla'] ?? '').($p['nif'] ?? ''), $p['nif'] ?? '', $b['cif'] ?? '']);
            $suyos = array_map(fn ($n) => preg_replace('/^ES/', '', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $n))), $suyos);
            if (in_array(preg_replace('/^ES/', '', $cif), array_filter($suyos), true)) {
                $cif = '';
                foreach (['proveedor', 'cif', 'nombre_fichero', 'cp', 'cod_provincia', 'provincia'] as $k) {
                    $this->form[$k] = '';
                }
            }
        }
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
        $this->sucio = true;
        $this->altaProveedorNuevo();
    }

    /**
     * Da de alta (o actualiza) en patrones.json el proveedor nuevo de la factura abierta en cuanto tiene cuenta (que no está en SAGE) y
     * nombre, para que no se pierda si se refresca la página y salga en el combo y en Proveedores (15-oct-2026, Alex). Python `alta`:
     * mientras no tenga facturas validadas se le puede cambiar nombre y CIF.
     */
    protected function altaProveedorNuevo(): void
    {
        $cta = trim((string) ($this->form['cuenta'] ?? ''));
        $nombre = trim((string) ($this->form['proveedor'] ?? ''));
        $cif = trim((string) ($this->form['cif'] ?? ''));
        if (! preg_match('/^\d{3,12}$/', $cta) || $nombre === '' || isset($this->proveedores()[$cta]) || ! $this->ejecutaAqui()) {
            return;
        }
        $pat = $this->patrones()[$cta] ?? [];
        if (! empty($pat['nuevo']) && ($pat['proveedor'] ?? '') === $nombre && ($cif === '' || ($pat['cif'] ?? '') === $cif)) {
            return;   // ya está tal cual
        }
        $tmp = tempnam(sys_get_temp_dir(), 'focr-alta-');
        file_put_contents($tmp, json_encode(['proveedor' => $nombre, 'cif' => $cif, 'cp' => (string) ($this->form['cp'] ?? '')], JSON_UNESCAPED_UNICODE));
        $antes = $this->salida;
        if ($this->ejecutar(['alta', $cta, '--datos', $tmp], 120, 'Alta de proveedor nuevo', false)) {
            $this->salida = $antes;
            $this->dispatch('focr-listas');   // el combo y Proveedores lo muestran ya
        }
        @unlink($tmp);
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
        if (! $this->ejecutaAqui()) {
            return;
        }
        $cmd = [$this->pythonBin(), 'facturas_ocr.py', $this->cliente, 'buscar_cif', $this->sel, '--nombre', trim($this->form['proveedor'])];
        try {
            $r = Process::path($this->baseDir())->env($this->entornoPython())->timeout(200)->run($cmd);
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
        if (! $this->sel || ! in_array($campo, ['cif', 'su_factura', 'fecha', 'total', 'proveedor', 'isp'], true)) {
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
        if ($campo === 'isp') {
            // Inversión del sujeto pasivo marcada a mano: no se guarda como zona; se deja el motivo listo para rechazar con ISP
            $this->motivo = 'Inversión del sujeto pasivo';
            $this->addError('zona', 'Marcada la inversión del sujeto pasivo: pulsa el botón ISP para rechazar la factura.');
            return;
        }
        if ($campo === 'proveedor') {
            $this->form['proveedor'] = $valor;   // solo el nombre; la cuenta se elige a mano o con el NIF
            $this->sucio = true;
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
        if ($campo === 'su_factura' && ($this->form['proveedor'] ?? '') !== '' && $this->sii()) {
            $this->form['comentario'] = mb_substr(trim('Fra '.$valor.' '.$this->form['proveedor']), 0, 40);
        }
        $zonas = is_array($this->form['_zonas'] ?? null) ? $this->form['_zonas'] : [];
        $zonas[$campo] = ['pagina' => $pagina, 'rect' => $rect];
        $this->form['_zonas'] = $zonas;
        $this->sucio = true;
    }

    /**
     * Cuentas de contrapartida: las del plan de SAGE (proveedores.json → cuentas) más las creadas aquí sobre la marcha
     * (cuentas_propias.json en la carpeta de datos, 14-oct-2026: 216000 Mobiliario de Sleep). [cuenta => nombre]
     */
    protected function cuentasContables(): array
    {
        $plan = json_decode((string) @file_get_contents($this->dirCliente().'/Base/proveedores.json'), true)['cuentas'] ?? [];
        $propias = json_decode((string) @file_get_contents($this->dirDatos().'/cuentas_propias.json'), true) ?: [];
        foreach ($propias as $cta => $c) {
            if (! isset($plan[$cta])) {
                $plan[(string) $cta] = ($c['nombre'] ?? '').' (nueva, crearla en SAGE)';
            }
        }
        ksort($plan, SORT_STRING);

        return $plan;
    }

    /** Cuenta de contrapartida que no está en el plan: se guarda con su nombre y aparece ya en el combo. */
    public function crearCuenta(string $cuenta, string $nombre): string
    {
        $cuenta = trim($cuenta);
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if (! $this->clienteValido() || ! preg_match('/^\d{3,12}$/', $cuenta)) {
            return 'Cuenta no válida: solo números.';
        }
        if ($nombre === '') {
            return 'Falta el nombre de la cuenta.';
        }
        $plan = $this->cuentasContables();
        $largos = array_count_values(array_map('strlen', array_map('strval', array_keys($plan))));
        arsort($largos);
        $largo = (int) (array_key_first($largos) ?? 0);
        if ($largo && strlen($cuenta) !== $largo) {
            return "Las cuentas de este cliente tienen {$largo} cifras y {$cuenta} tiene ".strlen($cuenta).'.';
        }
        $f = $this->dirDatos().'/cuentas_propias.json';
        $propias = json_decode((string) @file_get_contents($f), true) ?: [];
        $propias[$cuenta] = ['nombre' => mb_substr($nombre, 0, 60), 'creada' => now()->format('Y-m-d H:i'), 'por' => (string) (auth()->user()->name ?? '')];
        file_put_contents($f, json_encode($propias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->form['contrapartida'] = $cuenta;
        $this->sucio = true;
        $this->dispatch('focr-listas');

        return '';
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
        if ($this->ejecutaAqui()) {
            // Al día con el listado y el mayor de este PC (proveedores.json no va por git); si ya lo está, no hace nada
            $r = Process::path($this->baseDir())->env($this->entornoPython())->timeout(300)->run([$this->pythonBin(), 'facturas_base.py', $this->cliente]);
            if (! $r->successful()) {
                $error = trim($r->errorOutput()."\n".$r->output());
                Log::warning('FacturasOcr: no se pudo rehacer proveedores.json', ['salida' => $error]);
            }
        }
        if ($cual === 'cuentas') {
            $c = $this->cuentasContables();
            if (! $c) {
                // Que se vea en el propio desplegable por qué está vacío
                return [['', '⚠️ No hay cuentas de contrapartida: '.($error !== '' ? mb_substr(preg_replace('/\s+/', ' ', $error), -300)
                    : (is_file($f) ? 'proveedores.json sin cuentas (falta el mayor en '.$this->cliente.'?)' : 'no existe '.$f))]];
            }
            return array_map(fn ($k, $v) => [(string) $k, (string) $v], array_keys($c), $c);
        }
        $out = [];
        $pats = $this->patrones();
        foreach ($this->proveedores() as $cta => $p) {
            // El nombre editado aquí (Proveedores → nombre propio) manda sobre el del listado de SAGE
            $nombre = (string) (($pats[$cta]['nombre_propio'] ?? '') !== '' ? $pats[$cta]['nombre_propio'] : ($p['razon'] ?? ''));
            $out[] = [(string) $cta, trim($nombre.' · '.($p['nif'] ?? ''), ' ·')];
        }
        foreach ($pats as $cta => $pat) {
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
                'nombre' => $nuevo ? ($pat['proveedor'] ?? '') : (($pat['nombre_propio'] ?? '') ?: ($p['razon_base'] ?? $p['razon'] ?? '')),
                'nombre_aqui' => ! $nuevo && ($pat['nombre_propio'] ?? '') !== '',
                'nombre_sage' => (string) ($p['razon_base'] ?? $p['razon'] ?? ''),
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
        $nuevoForm = array_intersect_key($p, array_flip(['nombre', 'cif', 'contrapartida', 'codigo_transaccion', 'clave_operacion', 'codigo_retencion']));
        // El cuadro de edición siempre pasa al último proveedor pulsado; si el anterior tenía cambios sin guardar, se avisa de que se descartan.
        $this->provAviso = ($this->provSel !== '' && $this->provSel !== $cta && $this->provForm != $this->provOriginal)
            ? "Se han descartado los cambios sin guardar de {$this->provSel}." : '';
        $this->provSel = $cta;
        $this->provForm = $this->provOriginal = $nuevoForm;
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
            $this->provAviso = '';
            $this->dispatch('focr-listas');   // los combos de proveedor (en caché en el navegador) vuelven a pedir los nombres
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
        $nombres = $this->cuentasContables();
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
        $this->provAviso = '';
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

    /**
     * La cuenta de proveedor nuevo del formulario ya está dada aquí (o en Bancos) a otro proveedor con otro CIF: texto del aviso, o null.
     * No se impide: el botón pregunta dos veces y, si Alex insiste, se valida (puede que no tengamos el listado de SAGE al día).
     */
    protected function choqueCuenta(): ?string
    {
        $cta = trim((string) ($this->form['cuenta'] ?? ''));
        if ($cta === '' || isset($this->proveedores()[$cta])) {
            return null;
        }
        $limpio = fn ($n) => preg_replace('/^ES(?=.{9}$)/', '', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $n)));
        $cif = $limpio($this->form['cif'] ?? '');
        $pat = $this->patrones()[$cta] ?? [];
        $suyos = array_filter(array_map($limpio, array_merge($pat['nifs'] ?? [], [$pat['cif'] ?? ''])));
        // el mismo proveedor con otro NIF (Anthropic «ANT» y luego su IE) no es choque: al validar se aprende el NIF nuevo (15-oct-2026)
        if (! empty($pat['nuevo']) && $suyos && ! in_array($cif, $suyos, true) && ! self::mismoProveedor($pat['proveedor'] ?? '', $this->form['proveedor'] ?? '')) {
            return "La cuenta {$cta} ya se dio a otro proveedor nuevo: ".($pat['proveedor'] ?? '').' ('.($pat['cif'] ?? '').').';
        }
        $b = $this->cuentasBancos()[$cta] ?? [];
        if (($b['cif'] ?? '') !== '' && $limpio($b['cif']) !== $cif && ! self::mismoProveedor($b['nombre'] ?? '', $this->form['proveedor'] ?? '')) {
            return "La cuenta {$cta} está dada en Bancos a otro proveedor: ".($b['nombre'] ?? '').' ('.$b['cif'].').';
        }
        return null;
    }

    /** Misma regla que mismo_proveedor() de facturas_ocr.py: primera palabra significativa igual (sin forma social ni país). */
    public static function mismoProveedor(string $a, string $b): bool
    {
        $formas = ['sl', 'slu', 'sa', 'sau', 'sll', 'scp', 'cb', 'pbc', 'inc', 'llc', 'ltd', 'limited', 'gmbh', 'bv', 'srl', 'spa', 'sas',
            'sarl', 'ag', 'plc', 'corp', 'co', 'company', 'ireland', 'europe', 'spain', 'espana', 'the'];
        $pal = fn ($t) => array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^a-z0-9 ]+/', ' ',
            str_replace('.', '', mb_strtolower(\Illuminate\Support\Str::ascii($t)))))), fn ($w) => $w !== '' && ! in_array($w, $formas, true)));
        $pa = $pal($a);
        $pb = $pal($b);
        return $pa && $pb && $pa[0] === $pb[0] && strlen($pa[0]) >= 4;
    }

    public function validar(bool $forzar = false, bool $confirmado = false, bool $forzarCuenta = false): void
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
        // Contrapartida que no está en el plan ni creada aquí: se pregunta en el botón y, si Alex confirma, se valida igual
        // (14-oct-2026: el que decide es él; los avisos preguntan, no bloquean)
        $plan = $this->cuentasContables();
        $cp = (string) ($datos['contrapartida'] ?? '');
        if (! $confirmado && $plan && ! isset($plan[$cp])) {
            $this->addError('validar', "La contrapartida {$cp} no está en el plan de cuentas: escríbela en Contrapartida y elige «➕ cuenta nueva» para darle nombre.");
            return;
        }
        if (($choque = $this->choqueCuenta()) && ! $forzarCuenta) {
            $this->addError('validar', $choque.' Pulsa «+ Cuenta nueva» o, si la quieres igualmente, vuelve a validar y confirma las dos preguntas.');
            return;
        }
        if (! $this->ejecutaAqui()) {
            $this->addError('validar', 'Opción no válida. Solo ejecutable desde un terminal autorizado.');
            return;
        }
        $antes = array_column($this->cola(), 'id');
        // Se pasa ya a la siguiente factura; el Excel, mover el PDF y aprender lo hace la cola en segundo plano
        $cola = $this->dirDatos().'/_cola';
        @mkdir($cola, 0777, true);
        file_put_contents($cola.'/'.date('Ymd-His').'-'.substr((string) hrtime(true), -6).'-'.$this->sel.'.json',
            json_encode(['id' => $this->sel, 'datos' => $datos + ($choque ? ['forzar_cuenta' => true] : []), 'forzar' => $forzar], JSON_UNESCAPED_UNICODE));
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
        if ($this->enLote) {
            $this->cerrar();
        } else {
            $this->siguiente($antes);
        }
    }

    /** Facturas marcadas en el listado de «Por revisar» (casillas) para validarlas de una vez. */
    public array $marcadas = [];

    protected bool $enLote = false;

    /** ✅ de una fila del listado: la valida sin abrirla (si no cuadra, es duplicada o falta algo, dice por qué). */
    public function validarFila(string $id): void
    {
        $this->validarListado([$id]);
    }

    public function validarMarcadas(): void
    {
        $this->validarListado($this->marcadas);
    }

    /** Marca las que se pueden validar a ojo: lectura toda «ok», cuadran, con cuenta y contrapartida y sin duplicada. */
    public function marcarSeguras(): void
    {
        $this->marcadas = [];
        foreach ($this->cola() as $f) {
            if ($f['estado'] !== 'pendiente' || collect($f['avisos'] ?? [])->contains(fn ($a) => str_starts_with($a, 'DUPLICADA'))) {
                continue;
            }
            $conf = $f['confianza'] ?? [];
            if (! $conf || collect($conf)->contains(fn ($v) => $v !== 'ok')) {
                continue;
            }
            $d = $f['datos'] ?? [];
            if (($d['cuenta'] ?? '') === '' || ($d['contrapartida'] ?? '') === '' || ($d['total'] ?? null) === null) {
                continue;
            }
            $suma = 0.0;
            foreach ($d['lineas'] ?? [] as $l) {
                $suma += (float) ($l['base'] ?? 0) + (float) ($l['cuota'] ?? 0);
            }
            $suma -= (float) ($d['cuota_retencion'] ?? 0);
            if (abs((float) $d['total'] - $suma) < 0.015) {
                $this->marcadas[] = $f['id'];
            }
        }
    }

    /** Valida varias sin abrirlas. Las que no se pueden (no cuadran, duplicadas, faltan datos) se dejan y se cuentan aparte. */
    protected function validarListado(array $ids): void
    {
        $ok = 0;
        $no = [];
        $this->enLote = true;
        try {
            foreach (array_values(array_unique($ids)) as $id) {
                $f = $this->factura($id);
                if (! $f || $f['estado'] !== 'pendiente') {
                    continue;
                }
                $nombre = basename($f['ruta']);
                $this->abrir($id);
                if ($this->duplicados()) {
                    $no[] = "{$nombre}: parece duplicada";
                } elseif (($dc = $this->descuadre()) !== null && abs($dc) >= 0.015 || $this->lineasMal()) {
                    $no[] = "{$nombre}: no cuadra";
                } else {
                    $this->validar(false);
                    if ($this->getErrorBag()->has('validar')) {
                        $no[] = "{$nombre}: ".$this->getErrorBag()->first('validar');
                        $this->resetErrorBag();
                    } else {
                        $ok++;
                        continue;
                    }
                }
                $this->cerrar();
            }
        } finally {
            $this->enLote = false;
            $this->marcadas = [];
        }
        $this->dispatch('proceso-terminado', mensaje: ($ok ? "✅ Validadas: {$ok}" : 'Ninguna validada')
            .($no ? "\n⚠️ Sin validar (ábrelas para revisarlas):\n".implode("\n", $no) : ''));
    }

    /** Arranca (si no está ya) el proceso que valida en segundo plano lo encolado. */
    protected function lanzarCola(): void
    {
        $env = '';
        foreach ($this->entornoPython() as $k => $v) {
            $env .= $k.'='.escapeshellarg($v).' ';
        }
        $log = $this->dirDatos().'/_cola/cola.log';
        $cmd = 'cd '.escapeshellarg($this->baseDir()).' && '.$env.'nohup setsid '.escapeshellarg($this->pythonBin())
            .' facturas_ocr.py '.escapeshellarg($this->cliente).' cola >> '.escapeshellarg($log).' 2>&1 < /dev/null &';
        Process::run(['bash', '-c', $cmd]);
    }

    /** Confirmada como duplicada: el PDF, con «_DUPLICADA» al final, va a la subcarpeta Duplicadas del periodo elegido y sale de la cola. */
    public function marcarDuplicada(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        $antes = array_column($this->cola(), 'id');
        if ($this->ejecutar(['duplicada', $this->sel, '--motivo', $this->motivo, '--periodo', $this->periodo ?: $this->periodoActual()], 60, 'Duplicada', false)) {
            $this->siguiente($antes);
        } else {
            $this->addError('validar', trim($this->salida));
        }
    }

    /** Rechaza la factura abierta: el motivo va al final del nombre del PDF (no se mueve). Con $sufijo (ISP / ADC / EMITIDA) el motivo empieza por él. */
    public function rechazar(?string $sufijo = null): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        $antes = array_column($this->cola(), 'id');
        $args = ['rechazar', $this->sel, '--motivo', $this->motivo];
        if (in_array($sufijo, ['ISP', 'ADC', 'EMITIDA'], true)) {
            $args[3] = trim($sufijo.' '.trim(preg_replace('/^'.$sufijo.'\b\s*/u', '', $this->motivo)));
            array_push($args, '--sufijo', $sufijo);
        }
        if ($this->ejecutar($args, 60, 'Rechazar', false)) {
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

    /** Validadas marcadas en el listado (casillas) para retroceder varias a la vez sin abrirlas. */
    public array $marcadasValidadas = [];

    /** Retrocede a pendientes las validadas marcadas (reabrir_varias: fuera del Excel, el PDF a su carpeta). */
    public function retrocederMarcadas(bool $reproponer = false): void
    {
        $ids = array_values(array_filter($this->marcadasValidadas, fn ($i) => is_string($i) && preg_match('/^[0-9a-f]{6,}$/', $i)));
        $this->marcadasValidadas = [];
        if (! $ids) {
            return;
        }
        $this->salida = '';
        $args = ['reabrir_varias', implode(',', $ids)];
        if ($reproponer) {
            // Además se vuelven a proponer (datos rehechos con lo aprendido: abonos, etiquetas de importes, nº de factura...)
            $args = array_merge($args, $this->parametros(), ['--analitica', $this->analitica ? '1' : '0', '--reproponer']);
        }
        $ok = $this->ejecutar($args, 900, 'Retroceder validadas', false);
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $this->salida)), fn ($l) => str_contains($l, 'vuelven a pendientes') || str_contains($l, 'vuelto a proponer') || str_starts_with($l, 'OJO') || str_starts_with($l, 'No se ha podido')));
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '↩ ' : '⚠️ ').($lineas ? implode("\n", $lineas) : trim($this->salida)));
    }

    /** Elimina de la lista (y de la «memoria» de huellas) las validadas marcadas, para poder volver a subirlas como nuevas. */
    public function eliminarMarcadas(): void
    {
        $ids = array_values(array_filter($this->marcadasValidadas, fn ($i) => is_string($i) && preg_match('/^[0-9a-f]{6,}$/', $i)));
        $this->marcadasValidadas = [];
        if (! $ids) {
            return;
        }
        $this->salida = '';
        $ok = $this->ejecutar(['eliminar_varias', implode(',', $ids)], 900, 'Eliminar validadas', false);
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $this->salida)), fn ($l) => str_contains($l, 'eliminadas de la lista') || str_starts_with($l, 'OJO') || str_starts_with($l, 'No se ha podido')));
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '🗑 ' : '⚠️ ').($lineas ? implode("\n", $lineas) : trim($this->salida)));
    }

    /** Vuelve a proponer los datos con el texto ya leído (sin OCR): tras mejoras del programa o tras aprender. */
    public function reproponer(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        if ($this->ejecutar(array_merge(['reproponer', $this->sel], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 120, 'Volver a proponer', false)) {
            $this->dispatch('focr-listas');   // los combos de proveedor/cuenta vuelven a pedir sus nombres (por si se editó algo en Proveedores)
            $this->abrir($this->sel);
        } else {
            $this->addError('validar', trim($this->salida));
        }
    }

    /** Parte el PDF de la factura abierta si lleva varias (Amazon...): un PDF por factura, el original a Separadas, y se leen las nuevas. */
    public function separar(): void
    {
        if (! $this->sel) {
            return;
        }
        $this->salida = '';
        $antes = array_column($this->cola(), 'id');
        if ($this->ejecutar(array_merge(['separar', $this->sel], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 600, 'Separar', false)) {
            $this->dispatch('proceso-terminado', mensaje: "✅ PDF separado: las facturas nuevas están en la lista.");
            $this->siguiente($antes);
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
    /** Ficheros base: [patrón en el nombre, prefijo si no lo lleva, se suman también los de Base/OLD (true) o vale el último]. Igual que en facturas_base.py. */
    public const TIPOS_BASE = [
        'prov' => ['/lisProveedores/i', 'lisProveedores_', false],
        'mayor' => ['/^Mayor/i', 'Mayor_', true],
        'plan' => ['/plan/i', 'Plan_', false],
    ];

    public function updatedSubidaProv(): void { $this->subirBase('prov', 'subidaProv'); }
    public function updatedSubidaMayor(): void { $this->subirBase('mayor', 'subidaMayor'); }
    public function updatedSubidaPlan(): void { $this->subirBase('plan', 'subidaPlan'); }

    /** Guarda en Base/ el fichero elegido (con el prefijo de su tipo si no lo lleva) y rehace proveedores.json. */
    protected function subirBase(string $tipo, string $prop): void
    {
        $f = $this->{$prop};
        $this->{$prop} = null;
        if (! $f || ! $this->clienteValido()) {
            return;
        }
        $nombre = basename($f->getClientOriginalName());
        if (! preg_match('/\.xlsx$/i', $nombre)) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$nombre}: tiene que ser un Excel (.xlsx) exportado de SAGE.");
            return;
        }
        $this->instalarBase($tipo, $f->getRealPath(), $nombre, true);
    }

    /** Fichero base central (App\Support\FicherosBase) ↔ tipo local de Facturas OCR. */
    protected const CENTRAL = ['prov' => 'proveedores', 'mayor' => 'mayor', 'plan' => 'plan'];

    protected function entidadIdCliente(): int
    {
        return (int) ($this->cfg()['entidad_id'] ?? 0);
    }

    /**
     * Mete un fichero en Base/ (con el prefijo de su tipo, apartando los anteriores a Base/OLD) y rehace proveedores.json.
     * $publicar = viene de una subida en esta pantalla: se copia también a los ficheros base CENTRALES de la empresa (los demás procesos lo ven).
     */
    protected function instalarBase(string $tipo, string $origen, string $nombre, bool $publicar, bool $avisar = true): bool
    {
        [$patron, $prefijo] = self::TIPOS_BASE[$tipo];
        if (! preg_match($patron, $nombre)) {
            $nombre = $prefijo.$nombre;
        }
        $dir = $this->dirDatos().'/Base';
        @mkdir($dir, 0775, true);
        $tmp = $dir.'/.subiendo-'.$nombre;
        if (! @copy($origen, $tmp)) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se pudo guardar {$nombre}.");
            return false;
        }
        // Solo queda a la vista el último de cada tipo: los anteriores van a Base/OLD (de ahí se siguen
        // sumando los mayores; del listado y el plan vale el último)
        $apartados = [];
        foreach (glob($dir.'/*.xlsx') ?: [] as $viejo) {
            if (preg_match($patron, basename($viejo)) && ! str_starts_with(basename($viejo), '~$')) {
                @mkdir($dir.'/OLD', 0775, true);
                $dest = $dir.'/OLD/'.basename($viejo);
                if (is_file($dest)) {
                    $dest = $dir.'/OLD/'.pathinfo($viejo, PATHINFO_FILENAME).'_'.date('Ymd-His', filemtime($viejo)).'.xlsx';
                }
                if (@rename($viejo, $dest)) {
                    $apartados[] = basename($viejo);
                }
            }
        }
        if (! @rename($tmp, $dir.'/'.$nombre)) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se pudo guardar {$nombre} (¿está abierto en Excel?).");
            return false;
        }
        if ($publicar && ($eid = $this->entidadIdCliente()) && ! \App\Support\FicherosBase::existeContenido($eid, self::CENTRAL[$tipo], $dir.'/'.$nombre)) {
            \App\Support\FicherosBase::guardar($eid, self::CENTRAL[$tipo], $dir.'/'.$nombre, $nombre, 'Facturas OCR');
        }
        $this->rehacerBase("Guardado Base/{$nombre}.".($apartados ? ' A Base/OLD: '.implode(', ', $apartados).'.' : ''));

        return true;
    }

    /**
     * Ficheros base centrales ↔ Base/ de este cliente, en los dos sentidos (gana el más reciente): lo que se subió en otro proceso se instala aquí
     * y lo que se sube aquí se publica allí. Se hace al abrir el cliente. Devuelve lo hecho.
     */
    protected function sincronizarCentral(bool $soloComprobar = false): array
    {
        $hecho = [];
        $eid = $this->entidadIdCliente();
        if (! $eid || ! $this->web() || ! $this->clienteValido()) {
            return $hecho;
        }
        $dir = $this->dirDatos().'/Base';
        foreach (self::CENTRAL as $tipo => $tc) {
            [$patron] = self::TIPOS_BASE[$tipo];
            $locales = array_values(array_filter(glob($dir.'/*.xlsx') ?: [], fn ($f) => preg_match($patron, basename($f)) && ! str_starts_with(basename($f), '~$')));
            usort($locales, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $local = $locales[0] ?? null;
            $central = \App\Support\FicherosBase::ultimo($eid, $tc);
            if ($central && (! $local || (filemtime($central['ruta']) > filemtime($local) && hash_file('sha256', $central['ruta']) !== hash_file('sha256', $local)))) {
                $yaEsta = false;   // ¿el mismo contenido ya está en Base/ o en Base/OLD? entonces no se vuelve a instalar
                foreach (array_merge($locales, glob($dir.'/OLD/*.xlsx') ?: []) as $f) {
                    $yaEsta = $yaEsta || hash_file('sha256', $f) === hash_file('sha256', $central['ruta']);
                }
                if (! $yaEsta && ($soloComprobar || $this->instalarBase($tipo, $central['ruta'], $central['nombre'], false))) {
                    $hecho[] = "{$tc}: instalado el del central ({$central['nombre']})";
                }
            } elseif ($local && (! $central || (filemtime($local) > filemtime($central['ruta']) && hash_file('sha256', $local) !== hash_file('sha256', $central['ruta'])))
                && ! \App\Support\FicherosBase::existeContenido($eid, $tc, $local)) {
                if (! $soloComprobar) {
                    \App\Support\FicherosBase::guardar($eid, $tc, $local, basename($local), 'Facturas OCR (existente)');
                }
                $hecho[] = "{$tc}: publicado el de Facturas OCR ({$local})";
            }
        }

        return $hecho;
    }

    /** Quita el fichero base a la vista (p.ej. subido por error): se borra y vuelve el anterior de su tipo desde Base/OLD. */
    public function quitarBase(string $nombre): void
    {
        $dir = $this->dirDatos().'/Base';
        $ruta = $dir.'/'.basename($nombre);
        if (! $this->clienteValido() || ! is_file($ruta) || ! preg_match('/\.xlsx$/i', $ruta)) {
            return;
        }
        if (! @unlink($ruta)) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se pudo quitar {$nombre} (¿está abierto en Excel?).");
            return;
        }
        $hecho = "Quitado Base/{$nombre}.";
        foreach (self::TIPOS_BASE as [$patron]) {
            if (preg_match($patron, basename($nombre))) {
                $anteriores = array_filter(glob($dir.'/OLD/*.xlsx') ?: [], fn ($f) => preg_match($patron, basename($f)));
                usort($anteriores, fn ($a, $b) => filemtime($b) <=> filemtime($a));
                if ($anteriores && @rename($anteriores[0], $dir.'/'.basename($anteriores[0]))) {
                    $hecho .= ' Vuelve el anterior: '.basename($anteriores[0]).'.';
                }
                break;
            }
        }
        $this->rehacerBase($hecho);
    }

    protected function rehacerBase(string $hecho): void
    {
        $this->dispatch('focr-listas');   // que los combos vuelvan a pedir las listas
        $texto = $hecho;
        if ($this->ejecutaAqui()) {
            $r = Process::path($this->baseDir())->env($this->entornoPython())->timeout(300)->run([$this->pythonBin(), 'facturas_base.py', $this->cliente, '--forzar']);
            $texto .= "\n".trim($r->output()."\n".$r->errorOutput());
            // Con otra base cambian cuentas, CIF, contrapartidas y duplicadas: las pendientes que no se han
            // tocado a mano se vuelven a proponer (lo tocado a mano se respeta).
            if ($r->successful() && is_file($this->dirDatos().'/facturas.json')) {
                $this->salida = '';
                $this->ejecutar(array_merge(['reproponer'], $this->parametros(), ['--analitica', $this->analitica ? '1' : '0']), 600, 'Volver a proponer las pendientes', false);
                $texto .= "\n".trim($this->salida);
            }
        }
        $this->salida = $texto;
        $this->dispatch('proceso-terminado', mensaje: '✅ '.$texto);
    }

    /** Botón ↻ de Por revisar: vuelve a proponer todas las pendientes (también las empezadas, conservando lo tocado a mano). */
    public function revisarTodas(): void
    {
        if (! $this->clienteValido()) {
            return;
        }
        $this->salida = '';
        // Con facturas marcadas en la lista, solo esas; sin marcar, todas las pendientes
        $ids = array_values(array_filter($this->marcadas, fn ($i) => is_string($i) && preg_match('/^[0-9a-f]{6,}$/', $i)));
        $args = $ids ? ['reproponer', implode(',', $ids)] : ['reproponer'];
        if ($this->ejecutar(array_merge($args, $this->parametros(), ['--analitica', $this->analitica ? '1' : '0', '--editadas']), 600, $ids ? 'Revisar las marcadas' : 'Revisar todas', false)) {
            $this->dispatch('proceso-terminado', mensaje: '✅ '.trim($this->salida));
        }
        if ($this->sel) {
            $this->abrir($this->sel);
        }
    }

    /** Ficheros base imprescindibles que no tiene el cliente (listado de proveedores y mayor); el plan de cuentas solo se avisa en pantalla. */
    protected function basesQueFaltan(): array
    {
        $fb = $this->ficherosBase();
        $f = [];
        foreach (['prov' => 'el listado de proveedores', 'mayor' => 'el mayor'] as $t => $txt) {
            if (empty($fb[$t])) {
                $f[] = $txt;
            }
        }

        return $f;
    }

    /** Ficheros base que hay, por tipo, del más reciente al más antiguo: [nombre, fecha, tamaño]. */
    protected function ficherosBase(): array
    {
        $out = array_fill_keys(array_keys(self::TIPOS_BASE), []);
        foreach (glob($this->dirDatos().'/Base/*.xlsx') ?: [] as $f) {
            $n = basename($f);
            if (str_starts_with($n, '~$')) {
                continue;
            }
            foreach (self::TIPOS_BASE as $tipo => [$patron]) {
                if (preg_match($patron, $n)) {
                    $out[$tipo][] = ['nombre' => $n, 'fecha' => date('d/m/Y H:i', filemtime($f)), 't' => filemtime($f),
                        'mb' => round(filesize($f) / 1048576, 1)];
                    break;
                }
            }
        }
        foreach ($out as &$l) {
            usort($l, fn ($a, $b) => $b['t'] <=> $a['t']);
        }
        unset($l);
        foreach (self::TIPOS_BASE as $tipo => [$patron]) {
            $out['old_'.$tipo] = count(array_filter(glob($this->dirDatos().'/Base/OLD/*.xlsx') ?: [], fn ($f) => preg_match($patron, basename($f))));
        }
        return $out;
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

    /** "Guardados/PluginFacturas_Recibidas_2026-09-28_1904.xlsx" -> "28/09/2026 19:04" */
    public static function fechaProceso(string $excel): string
    {
        return preg_match('/(\d{4})-(\d{2})-(\d{2})_(\d{2})(\d{2})/', $excel, $m) ? "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}:{$m[5]}" : basename($excel);
    }

    /** Validadas tal como se ven en el listado: filtros de texto, mes y proceso aplicados, de la más reciente a la más antigua. */
    protected function validadasListado(): array
    {
        $estado = $this->clienteValido() ? $this->estado() : ['facturas' => []];
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
        // Fecha de proceso = cuándo se guardó el Excel para SAGE (sale del nombre: Guardados/…_AAAA-MM-DD_HHMM.xlsx)
        $guardado = fn ($f) => preg_match('/(\d{4}-\d{2}-\d{2})_\d{4}/', (string) ($f['excel'] ?? ''), $m) && str_starts_with((string) $f['excel'], 'Guardados/') ? $m[1] : '';
        if ($this->filtroDesde !== '') {
            $validadas = array_values(array_filter($validadas, fn ($f) => ($d = $guardado($f)) !== '' && $d >= $this->filtroDesde));
        }
        if ($this->filtroHasta !== '') {
            $validadas = array_values(array_filter($validadas, fn ($f) => ($d = $guardado($f)) !== '' && $d <= $this->filtroHasta));
        }
        // Con fecha de proceso elegida se miran todas (guardadas o no), salvo que se haya elegido un proceso concreto
        if ($this->filtroProceso !== 'todas' && ! ($this->filtroProceso === '' && ($this->filtroDesde !== '' || $this->filtroHasta !== ''))) {
            $validadas = array_values(array_filter($validadas, fn ($f) => $this->filtroProceso === ''
                ? ! str_starts_with((string) ($f['excel'] ?? ''), 'Guardados/')
                : ($f['excel'] ?? '') === $this->filtroProceso));
        }
        usort($validadas, fn ($a, $b) => strcmp($b['validada_el'] ?? '', $a['validada_el'] ?? ''));
        return $validadas;
    }

    public function render()
    {
        $valido = $this->clienteValido();
        $estado = $valido ? $this->estado() : ['facturas' => []];
        // Las quitadas de la lista (rechazadas/duplicadas ya vistas) no cuentan en ninguna pestaña
        $todas = array_values(array_filter($estado['facturas'], fn ($f) => empty($f['oculta'])));
        $validadas = $this->validadasListado();
        // Procesos ya guardados para SAGE (uno por Excel en Output/Guardados), del más reciente al más antiguo
        $procesos = [];
        foreach ($todas as $f) {
            $x = (string) ($f['excel'] ?? '');
            if (str_starts_with($x, 'Guardados/') && in_array($f['estado'], ['validada', 'validando'], true)) {
                $procesos[$x] = ($procesos[$x] ?? 0) + 1;
            }
        }
        krsort($procesos);
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
        if (! $this->web() && $uc && ($uc['pc'] ?? '') !== gethostname() && strtotime($uc['fecha'] ?? '') > time() - 900) {
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
            'chequeo' => $this->vista === 'chequeo' ? $this->chequeoResultado() : null,
            'revisados' => $this->vista === 'chequeo' ? $this->chequeoRevisados() : [],
            'ordenar' => in_array($this->vista, ['chequeo', 'ordenar'], true) ? $this->ordenarResultado() : null,
            'clientes' => $this->opcionesClientes(),
            'cola' => $cola,
            'cuenta' => array_count_values(array_column($todas, 'estado')),
            'validadas' => $validadas,
            'provs' => $provs,
            'esNuevoProv' => $this->provSel !== '' && ! isset($this->proveedores()[$this->provSel]),
            // Factura abierta con proveedor que no está en SAGE: se puede buscar su CIF/CP en internet
            'provFueraSage' => $this->sel !== '' && ! isset($this->proveedores()[$this->form['cuenta'] ?? '']),
            'nombresCuentas' => $this->vista === 'proveedores' && $valido
                ? $this->cuentasContables() : [],
            'mesesReg' => $mesesReg,
            'actual' => $actual,
            'posicion' => $actual ? array_search($this->sel, array_column($cola, 'id'), true) : false,
            'isp' => $this->sel ? $this->esIsp() : false,
            'esNuevo' => $this->sel && ($this->form['cuenta'] ?? '') !== '' && ! isset($this->proveedores()[$this->form['cuenta']]),
            'primeraAbierta' => $this->primeraAbierta(),
            'periodos' => $this->periodos(),
            'mesesCierre' => $this->mesesCierre(),
            'pdfs' => $valido ? $this->pdfsEnCarpeta() : 0,
            'web' => $this->web(),
            'sinLeer' => $this->web() ? $this->sinLeerEnEntrada() : 0,
            'esperandoOcr' => (bool) $this->pendientes,
            'sync' => $this->estadoSync(),
            'entrada' => $this->estadoEntrada(),
            'vaciables' => $this->web() && $valido ? count($this->vaciablesEntrada()) : 0,
            'lecturaDesde' => $this->web() && $valido ? ($this->lecturaEnCurso()[0] ?? null) : null,
            'procesos' => $procesos,
            'enExcel' => count(array_filter($todas, fn ($f) => ($f['excel'] ?? '') === self::EXCEL && in_array($f['estado'], ['validada', 'validando'], true))),
            'ultimoExcel' => $estado['ultimo_excel'] ?? null,
            'quitables' => count(array_filter($todas, fn ($f) => empty($f['oculta']) && in_array($f['estado'], ['rechazada', 'ilegible', 'duplicada'], true))),
            'base' => $valido ? $this->base() : [],
            'ficherosBase' => $valido ? $this->ficherosBase() : [],
            'basesFaltan' => $valido ? $this->basesQueFaltan() : [],
            'descuadre' => $this->sel ? $this->descuadre() : null,
            'lineasMal' => $this->sel ? $this->lineasMal() : [],
            'duplicados' => $this->sel ? $this->duplicados() : [],
            'planCp' => $this->sel ? $this->cuentasContables() : [],
            'choqueCuenta' => $this->sel ? $this->choqueCuenta() : null,   // para preguntar al validar si la contrapartida no está en el plan
            'gemelas' => $this->gemelas,
            'duplicadas' => array_values(array_filter($todas, fn ($f) => $f['estado'] === 'duplicada')),
            'totalNum' => $this->sel ? ($this->num($this->form['total'] ?? '') ?? 0) : 0,
            'entidad' => $valido ? $this->entidad() : null,
            'hayAnalitica' => $this->hayColumnaAnalitica(),
            'sii' => $valido && $this->sii(),
        ]);
    }
}
