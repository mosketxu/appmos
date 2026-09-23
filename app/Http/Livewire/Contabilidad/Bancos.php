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

    /** Ficheros resultado de la última ejecución (mismo mecanismo que Contabilidad\Procesos). */
    public array $resultados = [];

    public function mount(): void
    {
        $this->cliente = $this->clientes()[0] ?? '';
    }

    protected function baseDir(): string
    {
        return '/mnt/e/Claude/Contabilidad/Bancos';
    }

    /** Subcarpetas de Bancos que son clientes (todas menos plantillas y las ocultas). */
    protected function clientes(): array
    {
        $dirs = [];
        foreach (glob($this->baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $nombre = basename($d);
            if ($nombre === 'plantillas' || str_starts_with($nombre, '.') || str_starts_with($nombre, '_')) {
                continue;
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
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $codigos = array_values(array_filter($reader->listWorksheetNames($this->basePath()), fn ($n) => preg_match('/^\d{6,}$/', $n)));
            $nombres = [];
            if ($codigos) {
                $reader->setLoadSheetsOnly(['Plan cuentas']);
                $hoja = $reader->load($this->basePath())->getSheetByName('Plan cuentas');
                foreach ($hoja ? $hoja->toArray(null, false, false) : [] as $fila) {
                    if (in_array((string) ($fila[0] ?? ''), $codigos, true)) {
                        $nombres[(string) $fila[0]] = (string) ($fila[1] ?? '');
                    }
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        sort($codigos);
        $out = [];
        foreach ($codigos as $c) {
            $out[$c] = $nombres[$c] ?? '';
        }
        return $out;
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
