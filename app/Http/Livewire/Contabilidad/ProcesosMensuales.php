<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\User;
use App\Support\Accesos;
use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026): agrupa varios procesos que se hacen cada mes.
 * Los procesos van por empresa: cada usuario ve sus empresas (las del panel de
 * control: Responsable Suma + asignadas, Accesos::entidadesPropias) y ejecuta
 * para cada una los procesos de PROCESOS. Quien tiene entidades.todas puede
 * elegir otro usuario para ver (y ejecutar) las suyas.
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

    /** Usuario cuyas empresas se enseñan (el conectado, salvo que con entidades.todas elija otro). */
    public ?int $usuarioId = null;

    public string $buscar = '';

    public function mount(): void
    {
        $this->usuarioId = auth()->id();
    }

    protected function usuario(): User
    {
        $u = auth()->user();
        if ($u->can('entidades.todas') && $this->usuarioId && $this->usuarioId !== $u->id) {
            return User::find($this->usuarioId) ?? $u;
        }
        return $u;
    }

    public function render()
    {
        $usuario = $this->usuario();
        $empresas = Entidad::withoutGlobalScopes()
            ->whereIn('id', Accesos::entidadesPropias($usuario) ?: [0])
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('entidad', 'like', '%'.$this->buscar.'%')->orWhere('alias', 'like', '%'.$this->buscar.'%')))
            ->orderBy('entidad')->get(['id', 'entidad', 'alias']);

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => self::PROCESOS,
            'usuario' => $usuario,
            'usuarios' => auth()->user()->can('entidades.todas')
                ? User::where('activo', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'empresas' => $empresas,
        ]);
    }
}
