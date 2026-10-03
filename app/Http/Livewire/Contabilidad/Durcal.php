<?php

namespace App\Http\Livewire\Contabilidad;

use App\Http\Livewire\Concerns\EjecutaEnPcs;
use Livewire\Component;

/**
 * Durcal en la web (3-oct-2026): activa sueldos + Seguridad Social de empresa de los empleados marcados «ACTIVAR» en
 * Contabilidad/Durcal/Datos/personal.xlsx, repartidos por proyecto, y (1) rellena el bloque de resultado en el .XLS de nómina del mes y
 * (2) da de alta una fila por empleado y proyecto en «Amortizacion Alpify 2026.xlsm» para amortizar en 36 meses. Ver PROCESO_GENERAL.md.
 *
 * La pantalla está en la web y el trabajo lo hace el PC trabajador (Excel por COM, OneDrive): `activarDurcal.py MM --real` por la cola de
 * tareas (trait EjecutaEnPcs, grupo `durcal`, PC fijo DURCAL_PC). Las nóminas son datos personales: NO se suben a Appmos; el PC
 * solo comunica qué ficheros hay (nombre, tamaño, fecha: `pc.estado`) y la pantalla enseña eso como confirmación antes de ejecutar.
 */
class Durcal extends Component
{
    use EjecutaEnPcs;

    public int $mes;
    public string $salida = '';

    /** Ficheros resultado de la última ejecución: [clave => [['ruta' => 'E:\...', ...]]] (solo la ruta en el PC; no se descargan). */
    public array $resultados = [];

    /** Lo que subió el PC: nóminas encontradas, Amortizacion, personal.xlsx. */
    public array $estadoPc = [];

    protected string $grupoPc = 'durcal';

    protected bool $ultimoOk = false;

    public function mount(): void
    {
        $m = (int) date('n') - 1;
        $this->mes = $m < 1 ? 12 : $m;
        $this->recargarEstado();
        $this->retomarTareas();
        $this->sincronizarEstado('durcal.estado');
    }

    /** Siempre por los PCs: Durcal necesita Excel y el OneDrive del PC. */
    protected function remoto(): bool
    {
        return true;
    }

    protected function recargarEstado(): void
    {
        $this->estadoPc = $this->estadoRemoto('durcal.estado') ?? [];
    }

    public function sincronizarAhora(): void
    {
        $this->sincronizarEstado('durcal.estado', true);
    }

    protected function mm(): string
    {
        return str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
    }

    /** La nómina del mes elegido que ha visto el PC (null si no la encuentra o aún no se sabe). */
    public function getNominaProperty(): ?array
    {
        return $this->estadoPc['nominas'][$this->mm()] ?? null;
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    public function ejecutar(): void
    {
        if (! $this->nomina) {
            $this->salida .= "\n\n⚠️ El PC no ha visto el fichero de nómina del mes {$this->mm()} en el OneDrive (o aún no ha comunicado su estado): pulsa «Comprobar en el PC».";

            return;
        }
        $this->resultados = [];
        $etiqueta = "Durcal · Activación (mes {$this->mm()}, REAL)";
        $this->lanzarEnCola(
            [['script' => 'activarDurcal.py', 'args' => [$this->mm(), '--real'], 'timeout' => 300, 'etiqueta' => $etiqueta]],
            ['resultados' => 'durcal'],
        );
    }

    /** /mnt/e/Foo/Bar -> E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }

        return $p;
    }

    public function render()
    {
        return view('livewire.contabilidad.durcal');
    }
}
