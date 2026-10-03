<?php

namespace App\Http\Livewire\Contabilidad;

use App\Http\Livewire\Concerns\EjecutaEnPcs;
use App\Support\ColaTareas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Neteges (30-sep-2026): conciliación bancaria como Bancos, pero consultando
 * más ficheros (de momento, además, el fichero de Ventas). Se replica aparte
 * porque tendrá características propias. Ver Contabilidad/Neteges/PLAN.md.
 *
 * Ficheros base, cada uno en su fila (botón o arrastrando), guardados en Base/Recibidos
 * con fecha/hora delante:
 *   - plan de cuentas y mayor de SAGE (uno solo con todas las cuentas): neteges_base.py los
 *     acumula en Base/Base Neteges.xlsx, como Bancos. Del mayor solo se guardan las cuentas
 *     de banco: 572... y las "otras cuentas de banco" marcadas en pantalla.
 *   - ficheros de Ventas (varios, de una vez o en varias; una fila por factura o por línea):
 *     neteges_ventas.py los junta sin duplicados en Base/Ventas Neteges.xlsx (Facturas, Líneas,
 *     Clientes con su cuenta SAGE, Cambios) y avisa si una factura que ya estaba llega distinta.
 *   - listado de clientes o de proveedores de SAGE (el último de cada tipo; se reconoce cuál es):
 *     sirve para la cuenta SAGE de cada cliente (plugin de facturas).
 *   - "Mis clientes": el fichero de clientes que mantiene Alex (vale el último; manda sobre el de SAGE).
 * Extracto del banco con su cuenta: de momento solo se guarda en Input (el proceso, después).
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados). En la web (VPS) no ejecuta nada
 * ella misma: cada acción deja una tarea para el PC trabajador que guarda la base de Neteges (NETEGES_PC; los
 * ficheros viajan por git, así que solo un PC debe modificarlos), con los ficheros subidos como entrada, y el
 * estado de la pantalla es la copia en BD de neteges_estado.py (3-oct-2026, trait EjecutaEnPcs).
 */
class Neteges extends Component
{
    use EjecutaEnPcs;
    use WithFileUploads;

    protected string $grupoPc = 'neteges';

    /** ¿Terminó bien el último paso remoto? (lo usa el trait) */
    protected bool $ultimoOk = false;

    /** Neteges no enseña ficheros resultado en la pantalla (se descargan con «⬇»); el trait lo pide. */
    public array $resultados = [];

    /** Web: listados de carpetas que sube el PC (recibidos, remesas, plugins, fechas...). */
    public array $listadosPc = [];

    /** Ficheros base recién subidos; al terminar la subida el navegador llama a procesarSubidas(fila). */
    public array $subidas = [];

    /** Cuenta que no empieza por 572 a marcar como de banco (p.ej. 551002). */
    public string $otraCuenta = '';

    /** Extracto del banco y cuenta a la que pertenece. */
    public $extracto = null;
    public string $cuenta = '';

    /** neteges_base.py --estado: plan, cada cuenta, último mayor, otras cuentas de banco, ventas. */
    public array $estadoBase = [];

    /** neteges_plugin.py --pendientes: emitidas que no están en SAGE ni en un plugin ya hecho. */
    public array $estadoPlugin = [];

    /** Periodo del plugin de emitidas (input date: AAAA-MM-DD; vacío = sin límite). */
    public string $pluginDesde = '';
    public string $pluginHasta = '';

    /** neteges_cobros.py --estado: listados de cobros y control de bancos que prepara Neteges. */
    public array $estadoNeteges = [];

    /** neteges_ventas.py --estado: líneas, facturas, desde/hasta, ficheros, cambios. */
    public array $estadoVentas = [];

    /** Salida de la última ejecución de neteges_base.py / neteges_ventas.py. */
    public string $salida = '';

    public function mount(): void
    {
        $this->cargarEstado();
        $this->sincronizarEstado('neteges.estado');
        $this->retomarTareas();
    }

    /** «↻ Sincronizar estado» del panel de PCs. */
    public function sincronizarAhora(): void
    {
        $this->sincronizarEstado('neteges.estado', true);
    }

    /** Ha llegado estado nuevo de un PC. */
    protected function recargarEstado(): void
    {
        $this->cargarEstado();
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.neteges_dir'), '/');
    }

    protected function basePath(): string
    {
        return $this->baseDir().'/Base/Base Neteges.xlsx';
    }

    /** venv propio si existe (openpyxl + xlrd), si no el python3 del sistema. */
    protected function pythonBin(): string
    {
        $venv = $this->baseDir().'/.venv/bin/python3';
        return is_file($venv) ? $venv : 'python3';
    }

    /** Lista de extractos de Input con su cuenta (de neteges_estado.py). */
    public array $estadoExtractos = [];

    /**
     * Todo el estado de la pantalla en una sola llamada (neteges_estado.py, 2-oct-2026): antes eran 5
     * scripts seguidos (~15 s por carga). Guarda el resultado mientras no cambie ningún fichero, así
     * que lo normal es que conteste al momento.
     */
    public function cargarEstado(): void
    {
        if ($this->remoto()) {
            $d = $this->estadoRemoto('neteges.estado') ?? [];
            $e = $d['estado'] ?? [];
            $this->listadosPc = $d['listados'] ?? [];
            $this->estadoBase = $e['base'] ?? [];
            $this->estadoVentas = $e['ventas'] ?? [];
            $this->estadoPlugin = $e['plugin'] ?? [];
            $this->estadoNeteges = $e['neteges'] ?? [];
            $this->estadoExtractos = $e['extractos'] ?? [];
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(300)->run([$this->pythonBin(), 'neteges_estado.py']);
            $e = $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $ex) {
            $e = [];
        }
        $this->estadoBase = $e['base'] ?? [];
        $this->estadoVentas = $e['ventas'] ?? [];
        $this->estadoPlugin = $e['plugin'] ?? [];
        $this->estadoNeteges = $e['neteges'] ?? [];
        $this->estadoExtractos = $e['extractos'] ?? [];
    }

    /** Cuentas de banco cargadas en la base (pestañas con código de cuenta). */
    protected function cuentasBanco(): array
    {
        if ($this->remoto()) {
            $c = array_map('strval', array_keys($this->estadoBase['cuentas'] ?? []));
            sort($c);
            return $c;
        }
        if (! is_file($this->basePath())) {
            return [];
        }
        if (! empty($this->estadoBase['cuentas'])) {
            $c = array_map('strval', array_keys($this->estadoBase['cuentas']));
            sort($c);
            return $c;
        }
        try {
            $hojas = IOFactory::createReader('Xlsx')->listWorksheetNames($this->basePath());
        } catch (\Throwable $e) {
            return [];
        }
        $c = array_values(array_filter($hojas, fn ($n) => preg_match('/^\d{6,}$/', $n)));
        sort($c);
        return $c;
    }

    /** Ejecuta neteges_base.py (o el script que se diga) y deja el texto en $salida; devuelve si fue bien. */
    protected function ejecutar(array $args, string $etiqueta, string $script = 'neteges_base.py', array $entradas = []): bool
    {
        if ($this->remoto()) {
            $this->ejecutarEnPc([['script' => $script, 'args' => $args, 'timeout' => 300, 'etiqueta' => $etiqueta]], $etiqueta, $entradas);
            return true;   // el resultado llega después (revisarTareas)
        }
        try {
            $r = Process::path($this->baseDir())->timeout(300)->run(array_merge([$this->pythonBin(), $script], $args));
            $ok = $r->successful();
            $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $r->output()."\n".$r->errorOutput()));
        } catch (\Throwable $e) {
            $ok = false;
            $texto = $e->getMessage();
        }
        $this->salida = "===== {$etiqueta} =====\n{$texto}";
        $this->dispatch('proceso-terminado', mensaje: ($ok ? "✅ {$etiqueta}\nTerminado." : "⚠️ {$etiqueta}\nCon avisos o errores: mira la Salida."));
        $this->cargarEstado();
        return $ok;
    }

    /** Web: deja los pasos como una tarea para el PC de Neteges; al terminar, postNeteges() deja la Salida como en local. */
    protected function ejecutarEnPc(array $pasos, string $etiqueta, array $entradas = [], string $post = 'postNeteges'): void
    {
        $this->salida = '';
        $this->lanzarEnCola($pasos, ['entradas' => $entradas, 'post' => $post, 'ctx' => ['etiqueta' => $etiqueta, 'ini' => 0]]);
    }

    /** Texto de lo que han hecho los pasos remotos, sin las cabeceras ni los avisos de «pedido» que pone el trait. */
    protected function textoRemoto(): string
    {
        $t = preg_replace('/^(⏳|⚠️ Ahora mismo no hay ningún PC).*$/mu', '', $this->salida);
        $t = preg_replace('/^===== .* =====$/mu', '', $t);

        return trim(preg_replace("/\n{3,}/", "\n\n", $t));
    }

    /** Fin de una acción de Neteges hecha por el PC: la Salida queda igual que en local y se relee el estado. */
    protected function postNeteges(array $ctx, int $desde, array $oks): void
    {
        $this->salida = "===== {$ctx['etiqueta']} =====\n".$this->textoRemoto();
        $this->cargarEstado();
    }

    public function procesarSubidas(string $fila): void
    {
        $this->resetErrorBag('subidas');
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }
        $filas = ['plan' => 'plan de cuentas', 'mayor' => 'mayor', 'ventas' => 'fichero Ventas', 'clientessage' => 'clientes SAGE',
            'netcobros' => 'listado de cobros de Neteges', 'netbbva' => 'control de bancos BBVA de Neteges',
            'netsabadell' => 'control de bancos Sabadell de Neteges',
            'proveedoressage' => 'proveedores SAGE', 'misclientes' => 'mis clientes', 'remesas' => 'ficheros de remesas'];
        if (! isset($filas[$fila])) {
            $this->addError('subidas', 'Fila desconocida.');
            return;
        }
        $etiqueta = 'Neteges · '.$filas[$fila];
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }

        $extensiones = match ($fila) {
            'plan', 'mayor' => ['xlsx', 'xls'],
            'remesas' => ['xlsx', 'xls', 'csv', 'txt', 'xml', 'pdf', 'q19', 'n19'],
            default => ['xlsx', 'xls', 'csv'],
        };
        $malos = array_map(fn ($f) => $f->getClientOriginalName(),
            array_filter($ficheros, fn ($f) => ! in_array(strtolower($f->getClientOriginalExtension()), $extensiones, true)));
        if ($malos) {
            $this->addError('subidas', 'Solo '.implode(' / ', array_map(fn ($e) => ".{$e}", $extensiones)).'. No se ha subido nada; sobran: '.implode(', ', $malos));
            return;
        }

        if ($this->remoto()) {
            $this->subidasEnPc($fila, $etiqueta, $ficheros);
            return;
        }
        $dir = $this->baseDir().($fila === 'remesas' ? '/Base/Remesas' : '/Base/Recibidos');
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('subidas', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $sello = date('Ymd-His');
        $rutas = [];
        foreach ($ficheros as $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            if (! @copy($f->getRealPath(), "{$dir}/{$sello} {$nombre}")) {
                $this->addError('subidas', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                return;
            }
            $rutas[] = "{$dir}/{$sello} {$nombre}";
        }

        if ($fila === 'ventas') {
            $this->ejecutar($rutas, $etiqueta, 'neteges_ventas.py');
            return;
        }
        if ($fila === 'remesas') {
            $this->ejecutar([], $etiqueta, 'neteges_cobros.py');
            return;
        }
        if (in_array($fila, ['netcobros', 'netbbva', 'netsabadell'], true)) {
            // El script reconoce el tipo por dentro; --esperado avisa si se ha subido el fichero en otra fila
            $esperado = ['netcobros' => 'cobros', 'netbbva' => 'BBVA_NET', 'netsabadell' => 'SABADELL_NET'][$fila];
            $this->ejecutar(array_merge(['--subir', "--esperado={$esperado}"], $rutas), $etiqueta, 'neteges_cobros.py');
            return;
        }
        if ($fila === 'misclientes') {
            $this->ejecutar(['--mis-clientes', end($rutas)], $etiqueta, 'neteges_ventas.py');
            return;
        }
        if ($fila === 'clientessage' || $fila === 'proveedoressage') {
            $this->ejecutar(['--listado', end($rutas), $fila === 'clientessage' ? 'clientes' : 'proveedores'], $etiqueta, 'neteges_ventas.py');
            return;
        }
        $ok = $this->ejecutar(array_merge(['--espera', $fila], $rutas), $etiqueta);
        if ($ok && $fila === 'plan') {
            // El plan da las cuentas 430 y sus CIF: se rehace la cuenta SAGE de los clientes de Ventas
            $previa = $this->salida;
            $this->ejecutar(['--identificar'], 'Neteges · cuentas SAGE de los clientes', 'neteges_ventas.py');
            $this->salida = $previa."

".$this->salida;
        }
    }

    /**
     * Web: lo mismo que procesarSubidas() en local, pero los ficheros viajan como entrada de la tarea y el PC los
     * guarda en Base/Recibidos (o Base/Remesas) con la fecha delante; en los argumentos, {E0}, {E1}... son sus rutas allí.
     */
    protected function subidasEnPc(string $fila, string $etiqueta, array $ficheros): void
    {
        $entradas = array_map(fn ($f) => [
            'ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(),
            'dir' => $fila === 'remesas' ? 'Base/Remesas' : 'Base/Recibidos', 'sello' => true,
        ], $ficheros);
        $rutas = array_map(fn ($i) => '{E'.$i.'}', array_keys($entradas));
        $ultima = end($rutas);
        $paso = fn (string $script, array $args, array $extra = []) => ['script' => $script, 'args' => $args, 'timeout' => 300, 'etiqueta' => $etiqueta] + $extra;

        $pasos = match (true) {
            $fila === 'ventas' => [$paso('neteges_ventas.py', $rutas)],
            $fila === 'remesas' => [$paso('neteges_cobros.py', [])],
            in_array($fila, ['netcobros', 'netbbva', 'netsabadell'], true) => [$paso('neteges_cobros.py', array_merge(
                ['--subir', '--esperado='.['netcobros' => 'cobros', 'netbbva' => 'BBVA_NET', 'netsabadell' => 'SABADELL_NET'][$fila]], $rutas))],
            $fila === 'misclientes' => [$paso('neteges_ventas.py', ['--mis-clientes', $ultima])],
            in_array($fila, ['clientessage', 'proveedoressage'], true) => [$paso('neteges_ventas.py', ['--listado', $ultima, $fila === 'clientessage' ? 'clientes' : 'proveedores'])],
            // plan: da las cuentas 430 y sus CIF → se rehace la cuenta SAGE de los clientes de Ventas (solo si el plan entró bien)
            $fila === 'plan' => [$paso('neteges_base.py', array_merge(['--espera', $fila], $rutas)),
                $paso('neteges_ventas.py', ['--identificar'], ['solo_si_ok' => true])],
            default => [$paso('neteges_base.py', array_merge(['--espera', $fila], $rutas))],
        };
        $this->ejecutarEnPc($pasos, $etiqueta, $entradas);
    }

    public function anadirOtraCuenta(): void
    {
        $cuenta = trim($this->otraCuenta);
        if (! preg_match('/^\d{6,}$/', $cuenta)) {
            $this->addError('otraCuenta', 'Escribe la cuenta completa (6 cifras o más).');
            return;
        }
        $this->otraCuenta = '';
        $this->editarOtrasCuentas('anadir', $cuenta);
    }

    public function quitarOtraCuenta(string $cuenta): void
    {
        $this->editarOtrasCuentas('quitar', $cuenta);
    }

    protected function editarOtrasCuentas(string $accion, string $cuenta): void
    {
        $this->resetErrorBag('otraCuenta');
        $etiqueta = 'Neteges · '.($accion === 'anadir' ? 'añadir' : 'quitar')." cuenta de banco {$cuenta}";
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        $this->ejecutar(['--otras-cuentas', $accion, $cuenta], $etiqueta);
    }

    /** Extractos recién subidos (varios a la vez, Excel/XML/TXT): se guardan en Input y se detecta su cuenta. */
    public array $extractos = [];

    public function procesarExtractos(): void
    {
        $this->resetErrorBag('extractos');
        $ficheros = array_values(array_filter($this->extractos, fn ($f) => $f instanceof UploadedFile));
        $this->extractos = [];
        if (! $ficheros) {
            return;
        }
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · extractos');
            return;
        }
        $validas = ['xlsx', 'xls', 'xml', 'txt', 'n43', 'csv'];
        $malos = array_map(fn ($f) => $f->getClientOriginalName(),
            array_filter($ficheros, fn ($f) => ! in_array(strtolower($f->getClientOriginalExtension()), $validas, true)));
        if ($malos) {
            $this->addError('extractos', 'Solo Excel, XML o TXT. No se ha subido nada; sobran: '.implode(', ', $malos));
            return;
        }
        if ($this->remoto()) {
            // el PC los guarda en Input (si ya hay uno con ese nombre, con la fecha delante) y detecta su cuenta
            $entradas = array_map(fn ($f) => ['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => 'Input', 'unico' => true], $ficheros);
            $this->ejecutar(array_merge(['detectar'], array_map(fn ($i) => '{E'.$i.'}', array_keys($entradas))),
                'Neteges · extractos ('.count($entradas).')', 'neteges_extractos.py', $entradas);
            return;
        }
        $dir = $this->baseDir().'/Input';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('extractos', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $rutas = [];
        foreach ($ficheros as $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            $destino = file_exists("{$dir}/{$nombre}") ? "{$dir}/".date('Ymd-His')." {$nombre}" : "{$dir}/{$nombre}";
            if (! @copy($f->getRealPath(), $destino)) {
                $this->addError('extractos', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                return;
            }
            $rutas[] = $destino;
        }
        $this->ejecutar(array_merge(['detectar'], $rutas), 'Neteges · extractos ('.count($rutas).')', 'neteges_extractos.py');
    }

    /** Cuenta elegida a mano para un extracto de Input ('' = sin cuenta). */
    public function asignarCuenta(string $fichero, string $cuenta): void
    {
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · extractos');
            return;
        }
        if ($this->remoto()) {
            $this->ejecutar(['asignar', $fichero, $cuenta], 'Neteges · cuenta del extracto '.$fichero, 'neteges_extractos.py');
            return;
        }
        try {
            Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'neteges_extractos.py', 'asignar', $fichero, $cuenta]);
        } catch (\Throwable $e) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ '.$e->getMessage());
        }
        $this->cargarEstado();
    }

    /**
     * Busca en el Outlook de este PC los listados que manda Neteges (cobros*.xlsx, BBBVA26NET,
     * SABADELL26NET) y los guarda en Base/Recibidos (bajarAdjuntosNeteges.ps1). Bajo Apache hace
     * falta WSL_INTEROP para llamar a powershell.exe, como en FacturasOcr.
     */
    public function buscarEnCorreo(): void
    {
        $etiqueta = 'Neteges · buscar en el correo los ficheros de Neteges';
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        if ($this->remoto()) {
            $this->ejecutarEnPc([
                ['script' => 'bajarAdjuntosNeteges.ps1', 'args' => ['-Destino', '{DIRWIN}\\Base\\Recibidos'], 'timeout' => 600, 'etiqueta' => $etiqueta],
                ['script' => 'neteges_cobros.py', 'args' => [], 'timeout' => 120, 'etiqueta' => $etiqueta],
            ], $etiqueta, [], 'postBuscarEnCorreo');
            return;
        }
        $ps = '/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe';
        try {
            $r = Process::path($this->baseDir())->timeout(600)
                ->env(getenv('WSL_INTEROP') ? [] : ['WSL_INTEROP' => '/run/WSL/1_interop'])
                ->run([is_file($ps) ? $ps : 'powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File',
                    $this->rutaWindows($this->baseDir().'/bajarAdjuntosNeteges.ps1'),
                    '-Destino', $this->rutaWindows($this->baseDir().'/Base/Recibidos')]);
            $lineas = array_filter(array_map('trim', explode("\n", $r->output())));
            $nuevos = array_map(fn ($l) => '• '.basename(str_replace('\\', '/', substr($l, 9))), array_filter($lineas, fn ($l) => str_starts_with($l, 'GUARDADO|')));
            $texto = $r->successful()
                ? ($nuevos ? "Nuevos:\n".implode("\n", $nuevos) : 'No hay ficheros nuevos de Neteges en el correo.')
                : '⚠️ No se ha podido leer el Outlook: '.trim($r->output()."\n".$r->errorOutput());
            $res = Process::path($this->baseDir())->timeout(120)->run([$this->pythonBin(), 'neteges_cobros.py']);
            $texto .= "\n\n".trim($res->output());
        } catch (\Throwable $e) {
            $texto = '⚠️ '.$e->getMessage();
        }
        $this->salida = "===== {$etiqueta} =====\n{$texto}";
        $this->dispatch('proceso-terminado', mensaje: (str_contains($texto, '⚠️') ? '⚠️ ' : '✅ ').$etiqueta);
        $this->cargarEstado();
    }

    /** Fin de «buscar en el correo» en el PC: mismo texto que en local (nuevos ficheros + resultado de neteges_cobros.py). */
    protected function postBuscarEnCorreo(array $ctx, int $desde, array $oks): void
    {
        $t = $this->textoRemoto();
        $lineas = array_filter(array_map('trim', explode("\n", $t)));
        $nuevos = array_map(fn ($l) => '• '.basename(str_replace('\\', '/', substr($l, 9))), array_filter($lineas, fn ($l) => str_starts_with($l, 'GUARDADO|')));
        $resto = trim(implode("\n", array_filter($lineas, fn ($l) => ! str_starts_with($l, 'GUARDADO|'))));
        $texto = ($oks[0] ?? false)
            ? ($nuevos ? "Nuevos:\n".implode("\n", $nuevos) : 'No hay ficheros nuevos de Neteges en el correo.')."\n\n".$resto
            : '⚠️ No se ha podido leer el Outlook del PC: '.$resto;
        $this->salida = "===== {$ctx['etiqueta']} =====\n".trim($texto);
        $this->cargarEstado();
    }

    /** Plugin de SAGE con las emitidas pendientes del periodo (neteges_plugin.py; una fila por factura). */
    public function prepararPlugin(): void
    {
        $etiqueta = 'Neteges · plugin de facturas emitidas';
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        $args = [];
        foreach (['--desde' => $this->pluginDesde, '--hasta' => $this->pluginHasta] as $k => $v) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
                array_push($args, $k, "{$m[3]}/{$m[2]}/{$m[1]}");
            }
        }
        $this->ejecutar($args, $etiqueta, 'neteges_plugin.py');
    }

    /** Vuelve a dejar pendientes las facturas de un plugin (p.ej. si no se llegó a importar). */
    public function desmarcarPlugin(string $fichero): void
    {
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · plugin');
            return;
        }
        $this->ejecutar(['--desmarcar', basename($fichero)], 'Neteges · quitar marcas de '.basename($fichero), 'neteges_plugin.py');
    }

    /** Concilia los cobros de los extractos con Ventas (neteges_conciliar.py → Output/Conciliacion cobros Neteges.xlsx). */
    public function conciliar(): void
    {
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · conciliar cobros');
            return;
        }
        $this->ejecutar([], 'Neteges · conciliar cobros', 'neteges_conciliar.py');
    }

    /** Quita extractos de Input ($fichero = '' → todos); quedan en Input/Borrados. */
    public function borrarExtracto(string $fichero = ''): void
    {
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · extractos');
            return;
        }
        $this->ejecutar(['borrar', $fichero === '' ? '--todos' : $fichero],
            'Neteges · borrar '.($fichero === '' ? 'todos los extractos' : $fichero), 'neteges_extractos.py');
    }

    /** Empieza las ventas de cero (el acumulado se aparta a Base/Recibidos). */
    public function vaciarVentas(): void
    {
        if (! config('contabilidad.ejecucion_local') && ! $this->colaLista()) {
            $this->avisarNoAutorizado('Neteges · ventas');
            return;
        }
        $this->ejecutar(['--vaciar'], 'Neteges · vaciar ventas', 'neteges_ventas.py');
    }

    /** Extractos de Input con su cuenta y nombres de las cuentas de banco (de neteges_estado.py). */
    protected function listaExtractos(): array
    {
        return $this->estadoExtractos;
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    /** Descarga un fichero de la carpeta de Neteges (Base/..., Input/...). */
    public function descargar(string $relativa)
    {
        if ($this->remoto()) {
            $this->pedirFichero($relativa);
            return null;
        }
        $raiz = realpath($this->baseDir());
        $ruta = realpath($this->baseDir().'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta)) {
            $this->addError('subidas', "No se encuentra {$relativa}.");
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    /** Ficheros sueltos de <sub>, más recientes primero si $recientes. */
    protected function ficheros(string $sub, bool $recientes = false): array
    {
        $out = [];
        foreach (glob($this->baseDir().'/'.$sub.'/*') ?: [] as $f) {
            if (is_file($f) && ! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        natcasesort($out);
        $out = array_values($out);
        return $recientes ? array_reverse($out) : $out;
    }

    /** /mnt/e/Foo/Bar -> E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    protected function avisarNoAutorizado(string $etiqueta): void
    {
        $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
    }

    public function render()
    {
        if ($this->remoto()) {
            $l = $this->listadosPc;
            return view('livewire.contabilidad.neteges', [
                'cuentas' => $this->cuentasBanco(),
                'hayBase' => (bool) ($l['hayBase'] ?? false),
                'recibidos' => array_slice($l['recibidos'] ?? [], 0, 15),
                'remesas' => $l['remesas'] ?? [],
                'plugins' => $l['plugins'] ?? [],
                'haySustitucion' => $l['sustitucion'] ?? null,
                'hayConciliacion' => $l['conciliacion'] ?? null,
                'extractosInput' => $this->estadoExtractos['extractos'] ?? [],
                'nombresCuentas' => $this->estadoExtractos['cuentas'] ?? [],
                'carpeta' => $l['carpeta'] ?? '(aún sin datos del PC)',
            ]);
        }

        return view('livewire.contabilidad.neteges', [
            'cuentas' => $this->cuentasBanco(),
            'hayBase' => is_file($this->basePath()),
            'recibidos' => array_slice($this->ficheros('Base/Recibidos', true), 0, 15),
            'remesas' => $this->ficheros('Base/Remesas'),
            'plugins' => array_values(array_filter($this->ficheros('Output', true), fn ($f) => str_starts_with($f, 'PluginFacturas_Emitidas_'))),
            'haySustitucion' => is_file($f = $this->baseDir().'/Output/Sustitucion cobros Neteges.xlsx') ? date('d/m H:i', filemtime($f)) : null,
            'hayConciliacion' => is_file($f = $this->baseDir().'/Output/Conciliacion cobros Neteges.xlsx') ? date('d/m H:i', filemtime($f)) : null,
            'extractosInput' => ($lista = $this->listaExtractos())['extractos'] ?? [],
            'nombresCuentas' => $lista['cuentas'] ?? [],
            'carpeta' => $this->rutaWindows($this->baseDir()),
        ]);
    }
}
