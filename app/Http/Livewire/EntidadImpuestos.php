<?php

namespace App\Http\Livewire;

use App\Models\EntidadImpuesto;
use App\Models\ImpuestoModelo;
use App\Support\Impuestos;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Zona «Impuestos» de una entidad (7-oct-2026): qué impuestos presenta y con qué periodicidad, y quién los lleva. Se guarda al momento.
 * Alimenta la pestaña TO-DO → Impuestos (App\Http\Livewire\Impuestos). Cambiar solo con el permiso entidades.editar.
 */
class EntidadImpuestos extends Component
{
    public int $entidadId;

    public string $nuevoModelo = '';
    public string $nuevaPeriodicidad = '';
    public string $nuevaEtiqueta = '';

    // Alta de un impuesto nuevo en el catálogo (solo Admin)
    public bool $crearModelo = false;
    public string $mCodigo = '';
    public string $mNombre = '';
    public string $mPeriodicidad = 'T';

    public function mount(int $entidadId): void
    {
        $this->entidadId = $entidadId;
    }

    protected function puedeEditar(): bool
    {
        return (bool) auth()->user()?->can('entidades.editar');
    }

    public function updatedNuevoModelo(): void
    {
        $this->nuevaPeriodicidad = (string) ImpuestoModelo::where('codigo', $this->nuevoModelo)->value('periodicidad');
    }

    public function anadir(): void
    {
        abort_unless($this->puedeEditar(), 403);
        $m = ImpuestoModelo::where('codigo', $this->nuevoModelo)->first();
        if (! $m || ! array_key_exists($this->nuevaPeriodicidad, Impuestos::PERIODICIDADES)) {
            return;
        }
        $ob = EntidadImpuesto::firstOrCreate(['entidad_id' => $this->entidadId, 'modelo_id' => $m->id, 'etiqueta' => trim($this->nuevaEtiqueta)], ['periodicidad' => $this->nuevaPeriodicidad]);
        $this->asegurar($ob->id);
        $this->nuevoModelo = '';
        $this->nuevaPeriodicidad = '';
        $this->nuevaEtiqueta = '';
        $this->dispatch('impuestos-cambiados');
    }

    public function cambiarPeriodicidad(int $id, string $periodicidad): void
    {
        abort_unless($this->puedeEditar() && array_key_exists($periodicidad, Impuestos::PERIODICIDADES), 403);
        $ob = EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id);
        $ob->update(['periodicidad' => $periodicidad]);
        $this->asegurar($ob->id);
        $this->dispatch('impuestos-cambiados');
    }

    public function cambiarResponsable(int $id, string $userId): void
    {
        abort_unless($this->puedeEditar(), 403);
        EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id)->update(['user_id' => $userId === '' ? null : (int) $userId]);
    }

    public function cambiarEtiqueta(int $id, string $texto): void
    {
        abort_unless($this->puedeEditar(), 403);
        $ob = EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id);
        $texto = trim($texto);
        if (EntidadImpuesto::where('entidad_id', $this->entidadId)->where('modelo_id', $ob->modelo_id)->where('etiqueta', $texto)->where('id', '!=', $id)->exists()) {
            return;   // ya hay otra con esa etiqueta
        }
        $ob->update(['etiqueta' => $texto]);
        $this->dispatch('impuestos-cambiados');
    }

    public function guardarObservaciones(int $id, string $texto): void
    {
        abort_unless($this->puedeEditar(), 403);
        EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id)->update(['observaciones' => trim($texto) ?: null]);
    }

    public function quitar(int $id): void
    {
        abort_unless($this->puedeEditar(), 403);
        EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id)->delete();   // sus estados se borran con ella
        $this->dispatch('impuestos-cambiados');
    }

    public function guardarModelo(): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);
        $this->validate(['mCodigo' => 'required|max:10|alpha_dash|unique:impuesto_modelos,codigo', 'mNombre' => 'required|max:120', 'mPeriodicidad' => 'required|in:M,T,A,P'],
            [], ['mCodigo' => 'código', 'mNombre' => 'nombre']);
        ImpuestoModelo::create(['codigo' => strtoupper($this->mCodigo), 'nombre' => $this->mNombre, 'periodicidad' => $this->mPeriodicidad,
            'orden' => (int) ImpuestoModelo::max('orden') + 10, 'mes_anual' => $this->mPeriodicidad === 'A' ? 12 : null]);
        $this->nuevoModelo = strtoupper($this->mCodigo);
        $this->nuevaPeriodicidad = $this->mPeriodicidad;
        $this->reset('crearModelo', 'mCodigo', 'mNombre');
    }

    /** Crea las casillas que falten de esa obligación en todos los ejercicios con datos. */
    protected function asegurar(int $obId): void
    {
        foreach (Impuestos::ejercicios() as $ej) {
            Impuestos::asegurarEjercicio($ej, $obId);
        }
    }

    public function render()
    {
        $obs = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->orderBy('m.orden')->orderBy('ei.etiqueta')
            ->get(['ei.id', 'ei.etiqueta', 'ei.periodicidad', 'ei.user_id', 'ei.observaciones', 'm.codigo', 'm.nombre']);
        $modelos = ImpuestoModelo::where('activo', true)->orderBy('orden')->get();
        $responsables = DB::table('sumas')->join('users', 'users.id', '=', 'sumas.user_id')->orderBy('sumas.nombre')->get(['users.id', 'sumas.nombre']);

        return view('livewire.entidad-impuestos', ['obs' => $obs, 'modelos' => $modelos, 'responsables' => $responsables, 'editar' => $this->puedeEditar()]);
    }
}
