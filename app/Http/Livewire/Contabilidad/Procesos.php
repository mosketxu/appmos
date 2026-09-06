<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Pantalla para lanzar, desde Appmos, los scripts Node/Python de
 * Contabilidad/monthlyFIQ (Anaplan, Laboral, Monthly sales, RentasVariables)
 * sin tener que entrar por consola. Pedido explícito del usuario
 * (2026-09-06): "podrías poner botones para ejecutarlos independientemente
 * o marcar un check para ejecutar todos los que estén marcados seguidos."
 *
 * Como Appmos corre en la misma máquina que los ficheros de OneDrive, los
 * scripts se ejecutan tal cual (sin API de Graph/OneDrive) -- ver
 * PROCESO_GENERAL.md en Contabilidad/monthlyFIQ para el detalle de cada uno.
 */
class Procesos extends Component
{
    public int $mes;
    public array $marcados = [];
    public bool $modoReal = false;
    public string $salida = '';

    // RentasVariables (formularios aparte, no encajan en el check general)
    public string $rvTienda = 'BCN';
    public int $rvMesInicio;
    public int $rvMesFin;
    public bool $rvReal = false;
    public string $rvEmailPrueba = '';
    public bool $rvCorreccion = false;

    public function mount(): void
    {
        $this->mes = (int) date('n');
        $this->rvMesInicio = $this->mes;
        $this->rvMesFin = $this->mes;
    }

    protected function scriptDir(): string
    {
        return '/mnt/e/Claude/Contabilidad/monthlyFIQ';
    }

    /**
     * Orden fijo (es el orden en que se ejecutan si se marcan varios a la vez).
     */
    protected function procesos(): array
    {
        return [
            'anaplan_split' => [
                'label' => 'Anaplan · separar por canal',
                'script' => 'sysSplit.js',
                'soportaReal' => false,
                'siempreReal' => false,
                'ayuda' => 'Genera _test_output_sysSplit_MM.xlsx. Nunca toca ficheros reales.',
            ],
            'anaplan_consolida' => [
                'label' => 'Anaplan · consolidar en SyS 2026',
                'script' => 'anaplanConsolida.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Vuelca el SaldoP de cada cuenta en Anaplan/SyS 2026.xlsx.',
            ],
            'anaplan_desviaciones' => [
                'label' => 'Anaplan · informe de desviaciones',
                'script' => 'anaplanDesviaciones.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Compara el mes con el anterior (o la media, cuentas prioritarias) y resalta en amarillo.',
            ],
            'laboral' => [
                'label' => 'Laboral · imputación de costes',
                'script' => 'imputacionCostes.js',
                'soportaReal' => false,
                'siempreReal' => false,
                'ayuda' => 'Genera _test_output_imputacionCostes_MM.xlsx. Nunca toca el fichero real de Laboral.',
            ],
            'monthly_sales' => [
                'label' => 'Monthly sales',
                'script' => 'monthlyFIQ.js',
                'soportaReal' => true,
                'siempreReal' => true,
                'ayuda' => '⚠️ SIEMPRE escribe sobre el fichero real de Monthly y sobre Ctrol Dinamico (con backup automático). No tiene modo de prueba.',
            ],
        ];
    }

    public function getProcesosProperty(): array
    {
        return $this->procesos();
    }

    public function ejecutar(string $id): void
    {
        $this->ejecutarUno($id);
    }

    public function ejecutarMarcados(): void
    {
        foreach (array_keys($this->procesos()) as $id) {
            if (in_array($id, $this->marcados, true)) {
                $this->ejecutarUno($id);
            }
        }
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    protected function ejecutarUno(string $id): void
    {
        $procesos = $this->procesos();
        if (! isset($procesos[$id])) {
            return;
        }
        $p = $procesos[$id];
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        $args = ['node', $p['script'], $mm];
        $usaReal = $p['siempreReal'] || ($p['soportaReal'] && $this->modoReal);
        if ($p['soportaReal'] && $this->modoReal) {
            $args[] = '--real';
        }

        $etiquetaModo = $p['siempreReal'] ? 'SIEMPRE REAL' : ($usaReal ? 'REAL' : 'prueba');
        $this->salida .= "\n\n===== {$p['label']} (mes {$mm}, {$etiquetaModo}) =====\n";

        $result = Process::path($this->scriptDir())->timeout(180)->run($args);
        $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
    }

    // -- RentasVariables (formularios aparte) -------------------------------

    public function ejecutarRvCalculos(): void
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $args = ['node', 'calculosRentasVariables.js', $mm, '--no-open'];
        if ($this->rvReal) {
            $args[] = '--real';
        }
        $this->salida .= "\n\n===== RentasVariables · Cálculos (mes {$mm}, " . ($this->rvReal ? 'REAL' : 'prueba') . ") =====\n";
        $result = Process::path($this->scriptDir())->timeout(180)->run($args);
        $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
    }

    public function ejecutarRvDeclaracion(): void
    {
        $mi = str_pad((string) $this->rvMesInicio, 2, '0', STR_PAD_LEFT);
        $mf = str_pad((string) $this->rvMesFin, 2, '0', STR_PAD_LEFT);
        $args = ['node', 'rentasVariablesDeclaracion.js', $this->rvTienda, $mi];
        if ($mf !== $mi) {
            $args[] = $mf;
        }
        if ($this->rvReal) {
            $args[] = '--real';
        }
        $this->salida .= "\n\n===== RentasVariables · Declaración {$this->rvTienda} ({$mi}-{$mf}, " . ($this->rvReal ? 'REAL' : 'prueba') . ") =====\n";
        $result = Process::path($this->scriptDir())->timeout(180)->run($args);
        $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
    }

    public function ejecutarRvEnvio(): void
    {
        $args = ['python3', 'enviarRentasVariables.py', $this->rvTienda];
        if ($this->rvCorreccion) {
            $args[] = '--correction';
        }
        if ($this->rvReal) {
            $args[] = '--real';
        } elseif ($this->rvEmailPrueba !== '') {
            $args[] = '--test';
            $args[] = $this->rvEmailPrueba;
        } else {
            $this->salida .= "\n\n===== RentasVariables · Envío {$this->rvTienda} =====\nERROR: pon un correo de prueba o marca 'enviar de verdad'.\n";
            return;
        }
        $this->salida .= "\n\n===== RentasVariables · Envío {$this->rvTienda} (" . ($this->rvReal ? 'REAL, a los destinatarios de verdad' : 'prueba a ' . $this->rvEmailPrueba) . ") =====\n";
        $result = Process::path($this->scriptDir())->timeout(120)->run($args);
        $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
    }

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
