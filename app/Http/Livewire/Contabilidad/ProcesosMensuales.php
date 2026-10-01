<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Support\Accesos;
use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026): agrupa varios procesos que se hacen cada mes.
 * Los procesos van por empresa: cada usuario ve sus empresas (las del panel de
 * control: Responsable Suma + asignadas, Accesos::entidadesPropias) y ejecuta
 * para cada una los procesos de PROCESOS. También Admin y gestores: aunque en
 * Entidades vean todas, aquí solo las que gestionan (se les marcan en el panel).
 * Ver Contabilidad/ProcesosMensuales/PLAN.md.
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class ProcesosMensuales extends Component
{
    /** [clave => ['icono', 'titulo', 'descripcion', 'listo']]: una columna por proceso. */
    public const PROCESOS = [
        'petdocimpuestos' => ['icono' => '📨', 'titulo' => 'Pet. Documentación Impuestos',
            'descripcion' => 'Petición mensual de la documentación para los impuestos.', 'listo' => false],
    ];

    public string $buscar = '';

    public function render()
    {
        $usuario = auth()->user();
        $empresas = Entidad::withoutGlobalScopes()
            ->whereIn('id', Accesos::entidadesPropias($usuario) ?: [0])
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('entidad', 'like', '%'.$this->buscar.'%')->orWhere('alias', 'like', '%'.$this->buscar.'%')))
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'mail_peticion_check', 'mail_peticion']);

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => self::PROCESOS,
            'usuario' => $usuario,
            'empresas' => $empresas,
        ]);
    }
}
