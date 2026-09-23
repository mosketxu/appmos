<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pantalla de bancos por cliente (una carpeta por cliente dentro de
 * Contabilidad/Bancos). Ver PLAN.md en Contabilidad/Bancos para el detalle.
 *
 * Al empezar cada proceso se suben (botón o arrastrando) los ficheros base:
 *   - uno por cuenta de banco, mayor de SAGE con nombre 572xxx...
 *   - el plan de cuentas del cliente.
 * Se guardan tal cual en <Cliente>/Base/Recibidos (con fecha/hora delante) y
 * bancos_base.py añade sus filas nuevas a <Cliente>/Base/Base <Cliente>.xlsx.
 *
 * La conciliación (bancos_conciliacion.py) todavía pide la partida de cada
 * extracto por consola, así que aún no se lanza desde aquí.
 */
class Bancos extends Component
{
    use WithFileUploads;

    public string $cliente = '';
    public string $salida = '';

    /** Ficheros recién subidos (input o arrastrados); se procesan en cuanto terminan de subir. */
    public array $subidas = [];

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
            $this->resultados[] = ['ruta' => $win, 'url' => $this->fileUrl($ruta)];
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
            'pendientes' => $this->ficheros('Input'),
            'generados' => $this->ficheros('Output'),
        ]);
    }
}
