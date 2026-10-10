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

    // «Dejar de presentar desde…» (baja con historial): obligación, ejercicio y periodo elegidos
    public ?int $bajaOb = null;
    public int $bajaEj = 0;
    public string $bajaPer = '';
    public string $aviso = '';

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

    /** ¿Tiene algo más que casillas pendientes o vacías (presentados, revisiones, comentarios)? Entonces no se borra: se da de baja. */
    protected function tieneHistorial(int $id): bool
    {
        return DB::table('impuesto_estados')->where('entidad_impuesto_id', $id)->whereNotIn('estado', ['pendiente', 'no'])->exists()
            || DB::table('impuesto_comentarios')->where('entidad_impuesto_id', $id)->exists();
    }

    public function abrirBaja(int $id): void
    {
        abort_unless($this->puedeEditar(), 403);
        $ob = EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id);
        $this->bajaOb = $id;
        $this->bajaEj = (int) now()->format('Y');
        $pend = DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $id, 'ejercicio' => $this->bajaEj, 'estado' => 'pendiente'])->pluck('periodo')->all();
        usort($pend, fn ($a, $b) => Impuestos::ordinal($a) <=> Impuestos::ordinal($b));
        $this->bajaPer = $pend[0] ?? Impuestos::periodos($ob->periodicidad)[0];
        $this->aviso = '';
    }

    public function cerrarBaja(): void
    {
        $this->bajaOb = null;
    }

    /** Deja de presentar desde ese periodo: lo anterior (presentados, comentarios, PDF) se conserva; las pendientes de ahí en adelante se quitan. */
    public function darDeBaja(): void
    {
        abort_unless($this->puedeEditar() && $this->bajaOb, 403);
        $ob = EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($this->bajaOb);
        if ($this->bajaEj < 2009 || $this->bajaEj > 2100 || ! in_array($this->bajaPer, Impuestos::periodos($ob->periodicidad), true)) {
            return;
        }
        $ob->update(['baja_ejercicio' => $this->bajaEj, 'baja_periodo' => $this->bajaPer]);
        $ob = $ob->fresh();
        DB::table('impuesto_estados')->where('entidad_impuesto_id', $ob->id)->whereIn('estado', ['pendiente', 'no'])->get(['id', 'ejercicio', 'periodo'])
            ->each(function ($r) use ($ob) {
                if (Impuestos::dadaDeBaja($ob, (int) $r->ejercicio, $r->periodo)) {
                    DB::table('impuesto_estados')->where('id', $r->id)->delete();
                }
            });
        $this->bajaOb = null;
        $this->dispatch('impuestos-cambiados');
    }

    public function reactivar(int $id): void
    {
        abort_unless($this->puedeEditar(), 403);
        $ob = EntidadImpuesto::where('entidad_id', $this->entidadId)->findOrFail($id);
        $ob->update(['baja_ejercicio' => null, 'baja_periodo' => null]);
        $this->asegurar($ob->id);
        $this->dispatch('impuestos-cambiados');
    }

    public function quitar(int $id): void
    {
        abort_unless($this->puedeEditar(), 403);
        if ($this->tieneHistorial($id)) {
            $this->aviso = 'Ese impuesto tiene historial (presentados, revisiones o comentarios): no se borra; usa «Dejar de presentar».';

            return;
        }
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
            ->get(['ei.id', 'ei.etiqueta', 'ei.periodicidad', 'ei.baja_ejercicio', 'ei.baja_periodo', 'ei.user_id', 'ei.observaciones', 'm.codigo', 'm.nombre']);
        $modelos = ImpuestoModelo::where('activo', true)->orderBy('orden')->get();
        $responsables = DB::table('sumas')->join('users', 'users.id', '=', 'sumas.user_id')->orderBy('sumas.nombre')->get(['users.id', 'sumas.nombre']);

        $suma = DB::table('entidades')->join('sumas', 'sumas.id', '=', 'entidades.suma_id')->where('entidades.id', $this->entidadId)->value('sumas.nombre');
        $co = DB::table('entidad_user')->join('users', 'users.id', '=', 'entidad_user.user_id')->where('entidad_user.entidad_id', $this->entidadId)->pluck('users.name');
        $respEntidad = collect([$suma])->merge($co)->filter()->unique()->implode(', ');

        $conHistorial = DB::table('impuesto_estados')->whereIn('entidad_impuesto_id', $obs->pluck('id')->all() ?: [0])->whereNotIn('estado', ['pendiente', 'no'])->pluck('entidad_impuesto_id')
            ->merge(DB::table('impuesto_comentarios')->whereIn('entidad_impuesto_id', $obs->pluck('id')->all() ?: [0])->pluck('entidad_impuesto_id'))->flip();

        return view('livewire.entidad-impuestos', ['conHistorial' => $conHistorial, 'obs' => $obs, 'modelos' => $modelos, 'responsables' => $responsables, 'respEntidad' => $respEntidad, 'editar' => $this->puedeEditar()]);
    }
}
