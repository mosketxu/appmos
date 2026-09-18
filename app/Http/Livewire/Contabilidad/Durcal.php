<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Pantalla para lanzar, desde Appmos, Contabilidad/Durcal/activarDurcal.py:
 * activa sueldos + Seguridad Social de empresa de los empleados marcados
 * "ACTIVAR" en Datos/personal.xlsx, repartidos por proyecto, y:
 *   1. Rellena el bloque de resultado en el propio .XLS de nómina del mes
 *      (en la carpeta de OneDrive de Durcal 2026).
 *   2. Da de alta una fila por empleado+proyecto en "Amortizacion Alpify
 *      2026.xlsx" para amortizar en 36 meses.
 * Ver PROCESO_GENERAL.md en Contabilidad/Durcal para el detalle.
 *
 * Por ahora coge el fichero de nómina fijo de la carpeta de OneDrive (no
 * hay subida desde el navegador todavía -- pedido explícito del usuario
 * 2026-09-18: "para este ejemplo ve directo a la carpeta, pero en un
 * futuro quiero un input que me pida el fichero").
 */
class Durcal extends Component
{
    public int $mes;
    public string $salida = '';

    /** Ficheros resultado de la última ejecución (mismo mecanismo que Contabilidad\Procesos). */
    public array $resultados = [];

    public function mount(): void
    {
        $m = (int) date('n') - 1;
        $this->mes = $m < 1 ? 12 : $m;
    }

    protected function scriptDir(): string
    {
        return '/mnt/e/Claude/Contabilidad/Durcal';
    }

    /** Igual que FacturacionPdf::pythonBin() -- venv propio si existe, si no el python3 del sistema. */
    protected function pythonBin(): string
    {
        $venvPython = $this->scriptDir().'/.venv/bin/python3';
        return is_file($venvPython) ? $venvPython : 'python3';
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    public function ejecutar(): void
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->resultados = [];
        $etiqueta = "Durcal · Activación (mes {$mm}, REAL)";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript([$this->pythonBin(), 'activarDurcal.py', $mm, '--real'], 180, $etiqueta);
        $this->anexarResultados($archivos);
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

    /**
     * Igual que Contabilidad\Procesos::ejecutarScript: cualquier fallo se
     * convierte en un aviso en pantalla, nunca en un error que rompa la
     * página. Devuelve las rutas absolutas marcadas por el script con
     * líneas "RESULT_FILE: <ruta>" (no se muestran en la caja de Salida).
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): array
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return [];
        }

        $resultFiles = [];
        try {
            $result = Process::path($this->scriptDir())->timeout($timeout)->run($args);
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
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código ".$result->exitCode().'). Mira la caja de Salida para el detalle.');
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
        return view('livewire.contabilidad.durcal');
    }
}
