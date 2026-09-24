<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pantalla de bancos por cliente (una carpeta por cliente dentro de
 * Contabilidad/Bancos). Ver PLAN.md en Contabilidad/Bancos para el detalle.
 *
 * Al empezar cada proceso se suben (botón o arrastrando) los ficheros base:
 *   - uno por cuenta de banco, mayor de SAGE con nombre 572xxx...
 *   - puntualmente, el mayor de otra cuenta que hace de banco (551002...),
 *   - el plan de cuentas del cliente.
 * Se guardan tal cual en <Cliente>/Base/Recibidos (con fecha/hora delante) y
 * bancos_base.py añade sus filas nuevas a <Cliente>/Base/Base <Cliente>.xlsx.
 *
 * Luego, para cada extracto del banco: se elige la cuenta (combo con las
 * cuentas 572/551... cargadas en la base), se sube el extracto y
 * bancos_conciliacion.py --partida <cuenta> genera Output/bancos<cuenta>.xlsx
 * buscando la contrapartida en Variables -> Maestro -> Plan cuentas de la base.
 * El extracto se archiva en Input/input_old como siempre.
 *
 * El Maestro se ve y se edita aquí (bancos_maestro.py): las filas de SAGE
 * salen del Maestro de la base y lo que se añade o corrige a mano se guarda
 * en su pestaña Variables, que la conciliación mira antes que el Maestro (el
 * Maestro se rehace entero con cada subida de ficheros base).
 */
class Bancos extends Component
{
    use WithFileUploads;

    public string $cliente = '';
    public string $salida = '';

    /** Ficheros recién subidos (input o arrastrados); se procesan en cuanto terminan de subir. */
    public array $subidas = [];

    /** Extracto del banco a conciliar y cuenta (partida) a la que pertenece. */
    public $extracto = null;
    public string $cuenta = '';

    /** Maestro del cliente (filas SAGE + manuales) y plan de cuentas [codigo => nombre], ver cargarMaestro(). */
    public array $maestro = [];
    public array $planCuentas = [];
    public string $avisoMaestro = '';

    /** Listas comunes de Doc_y_Config/Configuracion.xlsx (bancos_config.py) y la prueba de limpieza. */
    public array $config = ['textos' => [], 'genericas' => []];
    public string $pruebaTexto = '';
    public ?array $pruebaResultado = null;

    /** Aviso junto a la lista tocada tras añadir/quitar: ['clave', 'ok', 'texto', 'n']. */
    public array $avisoConfig = [];

    /** Ficheros resultado de la última ejecución (mismo mecanismo que Contabilidad\Procesos). */
    public array $resultados = [];

    public function mount(): void
    {
        $this->cliente = $this->clientes()[0] ?? '';
        $this->cargarMaestro();
        $this->cargarConfig();
    }

    /** Ejecuta bancos_config.py y devuelve [ok, salida]. */
    protected function config_py(array $args): array
    {
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run(array_merge([$this->pythonBin(), 'bancos_config.py'], $args));
            return [$r->successful(), trim($r->output()."\n".$r->errorOutput())];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    public function cargarConfig(): void
    {
        [$ok, $out] = $this->config_py(['listar']);
        $datos = $ok ? json_decode($out, true) : null;
        $this->config = is_array($datos) ? $datos : ['textos' => [], 'genericas' => [], 'abreviaturas' => []];
    }

    public function anadirConfig(string $clave, string $texto, string $comentario = ''): void
    {
        $this->editarConfig(['anadir', $clave, $texto, $comentario]);
    }

    public function anadirAbreviatura(string $texto, string $abreviatura, string $comentario = ''): void
    {
        $this->editarConfig(['anadir', 'abreviaturas', $texto, $abreviatura, $comentario]);
    }

    public function borrarConfig(string $clave, string $texto): void
    {
        $this->editarConfig(['borrar', $clave, $texto]);
    }

    protected function editarConfig(array $args): void
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado('Bancos · Configuración');
            return;
        }
        [$ok, $out] = $this->config_py($args);
        // "Textos a quitar" cambia el concepto limpio: se rehace el Maestro de
        // todos los clientes con base para que la tabla y la conciliación lo usen ya.
        if ($ok && ($args[1] ?? '') === 'textos') {
            $rehechos = [];
            foreach ($this->clientes() as $c) {
                if (is_file($this->baseDir()."/{$c}/Base/Base {$c}.xlsx")) {
                    $r = Process::path($this->baseDir())->timeout(120)->run([$this->pythonBin(), 'bancos_base.py', $c, '--rehacer']);
                    $rehechos[] = $c.($r->successful() ? '' : ' (⚠️ '.trim($r->output().' '.$r->errorOutput()).')');
                }
            }
            if ($rehechos) {
                $out .= "\nMaestro rehecho: ".implode(', ', $rehechos);
            }
            $this->cargarMaestro();
        }
        $this->avisoConfig = ['clave' => $args[1] ?? '', 'ok' => $ok, 'texto' => $out, 'n' => ($this->avisoConfig['n'] ?? 0) + 1];
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '✅ ' : '⚠️ ')."Configuración común\n{$out}");
        $this->cargarConfig();
        if ($this->pruebaTexto !== '') {
            $this->probarConcepto();
        }
    }

    /** Cómo queda un concepto con las listas actuales (limpio + palabras que cuentan). */
    public function probarConcepto(): void
    {
        $this->pruebaResultado = null;
        if (trim($this->pruebaTexto) === '') {
            return;
        }
        [$ok, $out] = $this->config_py(['probar', $this->pruebaTexto]);
        $datos = $ok ? json_decode($out, true) : null;
        $this->pruebaResultado = is_array($datos) ? $datos : ['limpio' => '⚠️ '.$out, 'palabras' => []];
    }

    protected function baseDir(): string
    {
        return '/mnt/e/Claude/Contabilidad/Bancos';
    }

    /**
     * Subcarpetas de Bancos que son clientes (todas menos Doc_y_Config y las
     * ocultas) y que el usuario puede ver: cada carpeta se enlaza con su
     * entidad de Appmos en <Cliente>/cliente.json ({"entidad_id": ...}); un
     * usuario sin "entidades.todas" solo ve las de sus entidades, y las
     * carpetas sin enlazar solo las ve quien puede ver todas.
     */
    protected function clientes(): array
    {
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        $dirs = [];
        foreach (glob($this->baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $nombre = basename($d);
            if (in_array($nombre, ['Doc_y_Config', 'plantillas'], true) || str_starts_with($nombre, '.') || str_starts_with($nombre, '_')) {
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
        return in_array($this->cliente, $this->clientes(), true);
    }

    /** Ficheros sueltos (no carpetas) de <cliente>/<sub>, más recientes primero si $recientes. */
    protected function ficheros(string $sub, bool $recientes = false): array
    {
        if (! $this->clienteValido()) {
            return [];
        }
        $dir = $this->baseDir().'/'.$this->cliente.'/'.$sub;
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $f) {
            if (is_file($f) && ! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        natcasesort($out);
        $out = array_values($out);
        return $recientes ? array_reverse($out) : $out;
    }

    public function updatedCliente(): void
    {
        $this->resultados = [];
        $this->cuenta = '';
        $this->extracto = null;
        $this->cargarMaestro();
    }

    protected function basePath(): string
    {
        return $this->baseDir().'/'.$this->cliente.'/Base/Base '.$this->cliente.'.xlsx';
    }

    /**
     * Cuentas de banco cargadas en la base: las pestañas con código de cuenta
     * (572003, 551002...), con su nombre sacado de la pestaña "Plan cuentas".
     * [codigo => nombre]
     */
    protected function cuentasBanco(): array
    {
        if (! $this->clienteValido() || ! is_file($this->basePath())) {
            return [];
        }
        try {
            $hojas = IOFactory::createReader('Xlsx')->listWorksheetNames($this->basePath());
        } catch (\Throwable $e) {
            return [];
        }
        $codigos = array_values(array_filter($hojas, fn ($n) => preg_match('/^\d{6,}$/', $n)));
        sort($codigos);
        $out = [];
        foreach ($codigos as $c) {
            $out[$c] = $this->planCuentas[$c] ?? '';
        }
        return $out;
    }

    /** Lee Maestro + Variables + plan de cuentas de la base (bancos_maestro.py listar). */
    public function cargarMaestro(): void
    {
        $this->maestro = [];
        $this->planCuentas = [];
        $this->avisoMaestro = '';
        if (! $this->clienteValido() || ! is_file($this->basePath())) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'listar']);
            $datos = json_decode($r->output(), true);
            if (! $r->successful() || ! is_array($datos)) {
                $this->avisoMaestro = 'No se ha podido leer el Maestro: '.trim($r->output()."\n".$r->errorOutput());
                return;
            }
            $this->maestro = $datos['filas'] ?? [];
            $this->planCuentas = $datos['cuentas'] ?? [];
        } catch (\Throwable $e) {
            $this->avisoMaestro = 'No se ha podido leer el Maestro: '.$e->getMessage();
        }
    }

    /** Alta o cambio de una fila manual del Maestro (se guarda en Variables). */
    public function guardarMaestro(string $concepto, string $cuenta, string $anterior = ''): void
    {
        $args = [$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'guardar', $concepto, trim($cuenta)];
        if ($anterior !== '') {
            $args[] = $anterior;
        }
        $this->editarMaestro($args);
    }

    public function borrarMaestro(string $concepto): void
    {
        $this->editarMaestro([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'borrar', $concepto]);
    }

    protected function editarMaestro(array $args): void
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado('Bancos · Maestro');
            return;
        }
        if (! $this->clienteValido()) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run($args);
            $texto = trim($r->output()."\n".$r->errorOutput());
        } catch (\Throwable $e) {
            $r = null;
            $texto = $e->getMessage();
        }
        $ok = $r && $r->successful();
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '✅ ' : '⚠️ ')."Maestro {$this->cliente}\n{$texto}");
        $this->cargarMaestro();
    }

    /** Si el nombre del extracto empieza por una de las cuentas, se preselecciona. */
    public function updatedExtracto(): void
    {
        $this->resetErrorBag('extracto');
        if ($this->extracto instanceof UploadedFile && $this->cuenta === ''
            && preg_match('/^(\d{6,})/', $this->extracto->getClientOriginalName(), $m)
            && array_key_exists($m[1], $this->cuentasBanco())) {
            $this->cuenta = $m[1];
        }
    }

    public function conciliar(): void
    {
        $this->resetErrorBag(['extracto', 'cuenta']);
        $etiqueta = "Bancos · {$this->cliente} · bancos{$this->cuenta}";
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        if (! $this->clienteValido()) {
            $this->addError('extracto', 'Elige primero un cliente.');
            return;
        }
        if (! array_key_exists($this->cuenta, $this->cuentasBanco())) {
            $this->addError('cuenta', 'Elige la cuenta del banco.');
            return;
        }
        if (! $this->extracto instanceof UploadedFile) {
            $this->addError('extracto', 'Sube el extracto del banco.');
            return;
        }
        $nombre = str_replace(['/', '\\'], '_', $this->extracto->getClientOriginalName());
        if (! in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            $this->addError('extracto', 'El extracto tiene que ser un Excel (.xlsx / .xls).');
            return;
        }

        $dir = $this->baseDir().'/'.$this->cliente.'/Input';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('extracto', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $destino = "{$dir}/{$nombre}";
        if (file_exists($destino)) {
            $destino = "{$dir}/".date('Ymd-His')." {$nombre}";
        }
        if (! @copy($this->extracto->getRealPath(), $destino)) {
            $this->addError('extracto', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
            return;
        }

        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript([$this->pythonBin(), 'bancos_conciliacion.py', $this->cliente, '--partida', $this->cuenta, $destino], 180, $etiqueta);
        $this->anexarResultados($archivos);
        $this->extracto = null;
        $this->cargarMaestro(); // la conciliación añade a Variables lo que no encuentra
    }

    /** Descarga un fichero de la carpeta del cliente (Output/..., Base/...). */
    public function descargar(string $relativa)
    {
        if (! $this->clienteValido()) {
            return null;
        }
        $raiz = realpath($this->baseDir().'/'.$this->cliente);
        $ruta = realpath($raiz.'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta)) {
            $this->addError('extracto', "No se encuentra {$relativa}.");
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    public function updatedSubidas(): void
    {
        $this->resetErrorBag('subidas');
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }

        $etiqueta = "Bancos · {$this->cliente} · ficheros base";
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        if (! $this->clienteValido()) {
            $this->addError('subidas', 'Elige primero un cliente.');
            return;
        }

        $malos = [];
        foreach ($ficheros as $f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
                $malos[] = $f->getClientOriginalName();
            }
        }
        if ($malos) {
            $this->addError('subidas', 'Solo Excel (.xlsx / .xls). No se ha subido nada; sobran: '.implode(', ', $malos));
            return;
        }

        $dir = $this->baseDir().'/'.$this->cliente.'/Base/Recibidos';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('subidas', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }

        $sello = date('Ymd-His');
        $rutas = [];
        foreach ($ficheros as $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            $destino = "{$dir}/{$sello} {$nombre}";
            if (! @copy($f->getRealPath(), $destino)) {
                $this->addError('subidas', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                return;
            }
            $rutas[] = $destino;
        }

        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript(array_merge([$this->pythonBin(), 'bancos_base.py', $this->cliente], $rutas), 120, $etiqueta);
        $this->anexarResultados($archivos);
        $this->cargarMaestro();
    }

    /** Igual que Durcal::pythonBin() -- venv propio si existe, si no el python3 del sistema. */
    protected function pythonBin(): string
    {
        $venvPython = $this->baseDir().'/.venv/bin/python3';
        return is_file($venvPython) ? $venvPython : 'python3';
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    /** /mnt/e/Foo/Bar -> E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    /** /mnt/e/Foo/Bar -> file:///E:/Foo/Bar */
    protected function fileUrl(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return 'file:///'.strtoupper($m[1]).':/'.str_replace('%2F', '/', rawurlencode($m[2]));
        }
        return 'file://'.$p;
    }

    /** Añade rutas RESULT_FILE a $resultados, sin duplicar (mismo mecanismo que Contabilidad\Procesos). */
    protected function anexarResultados(array $rutas): void
    {
        $yaEstan = array_column($this->resultados, 'ruta');
        foreach ($rutas as $ruta) {
            $win = $this->rutaWindows($ruta);
            if (in_array($win, $yaEstan, true)) {
                continue;
            }
            $yaEstan[] = $win;
            $raiz = $this->baseDir().'/'.$this->cliente.'/';
            $relativa = str_starts_with($ruta, $raiz) ? substr($ruta, strlen($raiz)) : basename($ruta);
            $this->resultados[] = ['ruta' => $win, 'url' => $this->fileUrl($ruta), 'relativa' => $relativa];
        }
    }

    protected function avisarNoAutorizado(string $etiqueta): void
    {
        $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
        $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
    }

    /**
     * Igual que Durcal::ejecutarScript: cualquier fallo se convierte en un
     * aviso en pantalla, nunca en un error que rompa la página. Devuelve las
     * rutas absolutas marcadas por el script con líneas "RESULT_FILE: <ruta>".
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): array
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado($etiqueta);
            return [];
        }

        $resultFiles = [];
        try {
            $result = Process::path($this->baseDir())->timeout($timeout)->run($args);
            $texto = trim($result->output()."\n".$result->errorOutput());
            if (preg_match_all('/^RESULT_FILE:\s*(.+?)\s*$/m', $texto, $m)) {
                $resultFiles = array_map('trim', $m[1]);
                $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $texto));
            }
            $this->salida .= $texto;
            if ($result->successful()) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida ".$result->exitCode().'.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con avisos o errores (código ".$result->exitCode().'). Mira la caja de Salida para el detalle.');
            }
        } catch (\Throwable $e) {
            $this->salida .= "\n\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n"
                .get_class($e).': '.$e->getMessage()."\n"
                .'en '.$e->getFile().':'.$e->getLine()."\n"
                .'comando: '.implode(' ', array_map(fn ($a) => "'".$a."'", $args))."\n"
                ."traza:\n".implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8));
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida para el detalle.");
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Si ni siquiera se puede registrar el error, no debe romper la pantalla por eso.
            }
        }

        return $resultFiles;
    }

    public function render()
    {
        return view('livewire.contabilidad.bancos', [
            'clientes' => $this->clientes(),
            'recibidos' => array_slice($this->ficheros('Base/Recibidos', true), 0, 15),
            'cuentas' => $this->cuentasBanco(),
            'hayBase' => $this->clienteValido() && is_file($this->basePath()),
            'pendientes' => $this->ficheros('Input'),
            'generados' => $this->ficheros('Output'),
        ]);
    }
}
