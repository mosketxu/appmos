<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

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
 * El script sigue escribiendo siempre sobre la ruta fija de OneDrive (la
 * que determina el desplegable de mes) -- pedido explícito del usuario
 * 2026-09-18: "para este ejemplo ve directo a la carpeta, pero en un
 * futuro quiero un input que me pida el fichero". El input de fichero no
 * sustituye la ruta fija, es una confirmación visual antes de ejecutar:
 * hay que subir el .XLS del mes elegido y su nombre tiene que coincidir
 * con el esperado, si no "Ejecutar" no deja avanzar (ver
 * $archivoCoincide / nombreEsperado()).
 */
class Durcal extends Component
{
    use WithFileUploads;

    public int $mes;
    public string $salida = '';

    /** Fichero subido solo para confirmar visualmente que es el correcto antes de ejecutar (ver clase). */
    public $archivo = null;

    /** Ficheros resultado de la última ejecución (mismo mecanismo que Contabilidad\Procesos). */
    public array $resultados = [];

    public function mount(): void
    {
        $m = (int) date('n') - 1;
        $this->mes = $m < 1 ? 12 : $m;
    }

    /** Nombre exacto que debe tener el fichero de nómina del mes elegido, ver PROCESO_GENERAL.md. */
    protected function nombreEsperado(): string
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        return "{$mm} 00602_DURCAL SOFTWARE, S.L..XLS";
    }

    /** null = sin subir todavía, true/false = si el nombre subido coincide con el esperado para el mes elegido. */
    public function getArchivoCoincideProperty(): ?bool
    {
        if (! $this->archivo instanceof UploadedFile) {
            return null;
        }
        return strcasecmp($this->archivo->getClientOriginalName(), $this->nombreEsperado()) === 0;
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
        $this->validate(['archivo' => 'required|file'], [
            'archivo.required' => 'Sube primero el fichero de nómina del mes para confirmar que es el correcto.',
        ]);
        if ($this->archivoCoincide !== true) {
            $this->addError('archivo', 'El fichero subido ("'.$this->archivo->getClientOriginalName().'") no es "'.$this->nombreEsperado().'". Revisa el mes o el fichero antes de ejecutar.');
            return;
        }

        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->resultados = [];
        $etiqueta = "Durcal · Activación (mes {$mm}, REAL)";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript([$this->pythonBin(), 'activarDurcal.py', $mm, '--real'], 180, $etiqueta);
        $this->anexarResultados($archivos);
        $this->archivo = null;
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
