<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Pantalla para lanzar, desde Appmos, FacturacionPDFyMail/procesar_facturas.py
 * (parte PDF+correo de Suma y Balerga). Suma y Balerga son procesos
 * independientes: cada uno se procesa y se envía por separado, nunca juntos.
 *
 * Mismo patrón que Contabilidad\Procesos (scripts de monthlyFIQ): botón,
 * salida en pantalla, aviso modal al terminar, bloqueado en el VPS de
 * producción via config('contabilidad.ejecucion_local').
 *
 * Sin --send, procesar_facturas.py se queda en modo vista previa (parte los
 * PDF y escribe el plan de correos en _envios.csv, pero no manda nada ni
 * archiva la entrada) al ejecutarse en sesión no interactiva -- por eso
 * "Procesar (vista previa)" no necesita ningún flag de seguridad aparte.
 * "Procesar y enviar" añade --send y manda los correos de verdad.
 */
class FacturacionPdf extends Component
{
    public string $salida = '';

    protected function scriptDir(): string
    {
        return '/mnt/e/Claude/FacturacionPDFyMail';
    }

    protected function clientes(): array
    {
        return [
            'Suma' => [
                'label' => 'Suma',
                'ayuda' => 'Factura de renta mensual, un PDF y un correo por empresa inquilina.',
            ],
            'Balerga' => [
                'label' => 'Balerga',
                'ayuda' => 'Factura de renta de plaza, un PDF y un correo por inquilino.',
            ],
        ];
    }

    public function getClientesProperty(): array
    {
        return $this->clientes();
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    public function procesar(string $cliente): void
    {
        $this->ejecutarCliente($cliente, enviar: false);
    }

    public function procesarYEnviar(string $cliente): void
    {
        $this->ejecutarCliente($cliente, enviar: true);
    }

    protected function ejecutarCliente(string $cliente, bool $enviar): void
    {
        if (! isset($this->clientes()[$cliente])) {
            return;
        }

        $args = ['python3', 'procesar_facturas.py', '--client', $cliente];
        if ($enviar) {
            $args[] = '--send';
        }

        $etiqueta = $enviar
            ? "Facturación PDF · {$cliente} (procesar y ENVIAR)"
            : "Facturación PDF · {$cliente} (vista previa, sin enviar)";

        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 180, $etiqueta);
    }

    /**
     * Igual que Contabilidad\Procesos::ejecutarScript: cualquier fallo (el
     * proceso, el propio report() si el log tampoco fuera escribible, o
     * cualquier otra excepción) se convierte en un aviso en pantalla, nunca
     * en un error que rompa la página.
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): void
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return;
        }

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
            $this->salida .= "\n\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n"
                . get_class($e) . ': ' . $e->getMessage() . "\n"
                . 'en ' . $e->getFile() . ':' . $e->getLine() . "\n"
                . 'comando: ' . implode(' ', array_map(fn ($a) => "'" . $a . "'", $args)) . "\n"
                . "traza:\n" . implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8));
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida para el detalle.");
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Si ni siquiera se puede registrar el error, no debe romper la pantalla por eso.
            }
        }
    }

    public function render()
    {
        return view('livewire.contabilidad.facturacion-pdf');
    }
}
