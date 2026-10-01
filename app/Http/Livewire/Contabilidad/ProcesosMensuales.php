<?php

namespace App\Http\Livewire\Contabilidad;

use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026): pestaña que agrupa varios procesos que se hacen
 * cada mes. Cada proceso será una tarjeta en PROCESOS. Ver
 * Contabilidad/ProcesosMensuales/PLAN.md.
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class ProcesosMensuales extends Component
{
    /** [clave => ['icono', 'titulo', 'descripcion']] — se irán añadiendo. */
    public const PROCESOS = [
        'petdocimpuestos' => ['icono' => '📨', 'titulo' => 'Pet. Documentación Impuestos',
            'descripcion' => 'Petición mensual de la documentación para los impuestos. En preparación.'],
    ];

    public function render()
    {
        return view('livewire.contabilidad.procesos-mensuales', ['procesos' => self::PROCESOS]);
    }
}
