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
    public bool $rvEnviarReal = false;
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
     * `node --jitless ...` en vez de `node ...`. Encontrado 2026-09-07: el
     * servicio systemd de apache2 tiene `MemoryDenyWriteExecute=yes`
     * (hardening por defecto de Ubuntu), que bloquea el JIT de V8 heredado
     * por cualquier hijo de Apache -- Node moría con
     * `ProcessSignaledException: signal "5"` (SIGTRAP) nada más arrancar en
     * CUALQUIER script. Mismo síntoma que el aviso ya presente desde hace
     * meses en /appmos-error.log sobre el JIT de PCRE de PHP fallando por
     * "security restrictions". `--jitless` desactiva el JIT de V8 (solo
     * intérprete) y evita la mprotect(PROT_EXEC) que el sandbox bloquea; para
     * estos scripts (E/S y regex sobre ficheros pequeños, nada de bucles
     * pesados) el coste de rendimiento es despreciable. Alternativa
     * descartada: quitar `MemoryDenyWriteExecute` del servicio de Apache
     * entero solo para esto debilitaría el sandbox de TODO Apache, no merece
     * la pena.
     */
    protected function nodeCmd(string $script, array $rest = []): array
    {
        return ['node', '--jitless', $script, ...$rest];
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
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Real: guarda Anaplan/SyS MM Split.xlsx (backup de la versión anterior si ya existía). Prueba: _test_output_sysSplit_MM.xlsx.',
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
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Real: escribe sobre el fichero de imputación de costes original de Laboral 2026/MM/ (con backup). Prueba: _test_output_imputacionCostes_MM.xlsx.',
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

    /**
     * Ejecuta `$args` en `scriptDir()` y añade la salida a `$this->salida`.
     * Pedido explícito del usuario (2026-09-07, tras toparse con una página
     * rota tal cual porque el log de Laravel no era escribible): CUALQUIER
     * fallo -- el proceso, el propio `report()` del error si el log también
     * fallara, o cualquier otra excepción -- se convierte en un aviso dentro
     * de la salida en pantalla, nunca en un error que rompa la página.
     *
     * `$etiqueta` identifica el proceso en el aviso modal de fin (pedido
     * explícito 2026-09-07: "que salga una ventana avisando que ha acabado,
     * una por cada proceso, que la tenga que cerrar yo" -- `alert()` de JS,
     * que bloquea hasta que el usuario le da a OK, evento
     * `proceso-terminado` escuchado en la vista).
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): void
    {
        try {
            $result = Process::path($this->scriptDir())->timeout($timeout)->run($args);
            $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
            if ($result->successful()) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida " . $result->exitCode() . '.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código " . $result->exitCode() . "). Mira la caja de Salida para el detalle.");
            }
        } catch (\Throwable $e) {
            // Pedido explícito del usuario (2026-09-07): nada de mensaje genérico --
            // que salga tal cual (clase, mensaje, fichero:línea, comando exacto y las
            // primeras líneas de la traza) para poder copiarlo y pegarlo aquí.
            $this->salida .= "\n\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n"
                . get_class($e) . ': ' . $e->getMessage() . "\n"
                . 'en ' . $e->getFile() . ':' . $e->getLine() . "\n"
                . 'comando: ' . implode(' ', array_map(fn ($a) => "'" . $a . "'", $args)) . "\n"
                . "traza:\n" . implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8));
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida para el detalle.");
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Si ni siquiera se puede registrar el error (p.ej. el propio log
                // sin permisos de escritura), no debe romper la pantalla por eso.
            }
        }
    }

    protected function ejecutarUno(string $id): void
    {
        $procesos = $this->procesos();
        if (! isset($procesos[$id])) {
            return;
        }
        $p = $procesos[$id];
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        $args = $this->nodeCmd($p['script'], [$mm]);
        $usaReal = $p['siempreReal'] || ($p['soportaReal'] && $this->modoReal);
        if ($p['soportaReal'] && $this->modoReal) {
            $args[] = '--real';
        }

        $etiquetaModo = $p['siempreReal'] ? 'SIEMPRE REAL' : ($usaReal ? 'REAL' : 'prueba');
        $etiqueta = "{$p['label']} (mes {$mm}, {$etiquetaModo})";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";

        $this->ejecutarScript($args, 180, $etiqueta);
    }

    // -- RentasVariables (formularios aparte) -------------------------------

    public function ejecutarRvCalculos(): void
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $args = $this->nodeCmd('calculosRentasVariables.js', [$mm, '--no-open']);
        if ($this->rvReal) {
            $args[] = '--real';
        }
        $etiqueta = 'RentasVariables · Cálculos (mes ' . $mm . ', ' . ($this->rvReal ? 'REAL' : 'prueba') . ')';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 180, $etiqueta);
    }

    public function ejecutarRvDeclaracion(): void
    {
        $mi = str_pad((string) $this->rvMesInicio, 2, '0', STR_PAD_LEFT);
        $mf = str_pad((string) $this->rvMesFin, 2, '0', STR_PAD_LEFT);
        $args = $this->nodeCmd('rentasVariablesDeclaracion.js', [$this->rvTienda, $mi]);
        if ($mf !== $mi) {
            $args[] = $mf;
        }
        if ($this->rvReal) {
            $args[] = '--real';
        }
        $etiqueta = "RentasVariables · Declaración {$this->rvTienda} ({$mi}-{$mf}, " . ($this->rvReal ? 'REAL' : 'prueba') . ')';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 180, $etiqueta);
    }

    public function ejecutarRvEnvio(): void
    {
        $args = ['python3', 'enviarRentasVariables.py', $this->rvTienda];
        if ($this->rvCorreccion) {
            $args[] = '--correction';
        }
        if ($this->rvEnviarReal) {
            $args[] = '--real';
        } elseif ($this->rvEmailPrueba !== '') {
            $args[] = '--test';
            $args[] = $this->rvEmailPrueba;
        } else {
            $this->salida .= "\n\n===== RentasVariables · Envío {$this->rvTienda} =====\nERROR: pon un correo de prueba o marca 'enviar de verdad'.\n";
            return;
        }
        $etiqueta = "RentasVariables · Envío {$this->rvTienda} (" . ($this->rvEnviarReal ? 'REAL, a los destinatarios de verdad' : 'prueba a ' . $this->rvEmailPrueba) . ')';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 120, $etiqueta);
    }

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
