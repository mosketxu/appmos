<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Formación (22-oct-2026): listado de cosas que Alex quiere explicar a sus compañeros. Ven todos; edita quien tenga formacion.editar.
 */
class Formacion extends Component
{
    public string $titulo = '';
    public string $descripcion = '';
    public ?int $editando = null;
    public string $filtro = 'todos'; // todos | pendiente | explicado

    public function hydrate(): void
    {
        $this->autorizar();
    }

    protected function autorizar(bool $editar = false): void
    {
        abort_unless(auth()->user()?->can($editar ? 'formacion.editar' : 'formacion.ver'), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    public function guardar(): void
    {
        $this->autorizar(true);
        $this->validate(['titulo' => 'required|string|max:200', 'descripcion' => 'nullable|string|max:5000']);
        $datos = ['titulo' => trim($this->titulo), 'descripcion' => trim($this->descripcion) ?: null, 'updated_at' => now()];
        if ($this->editando) {
            DB::table('formacion_temas')->where('id', $this->editando)->update($datos);
        } else {
            DB::table('formacion_temas')->insert($datos + ['orden' => (int) DB::table('formacion_temas')->max('orden') + 1, 'created_at' => now()]);
        }
        $this->cancelar();
    }

    public function editar(int $id): void
    {
        $this->autorizar(true);
        if ($t = DB::table('formacion_temas')->find($id)) {
            $this->editando = $id;
            $this->titulo = $t->titulo;
            $this->descripcion = (string) $t->descripcion;
        }
    }

    public function cancelar(): void
    {
        $this->reset('titulo', 'descripcion', 'editando');
        $this->resetValidation();
    }

    public function alternarEstado(int $id): void
    {
        $this->autorizar(true);
        $t = DB::table('formacion_temas')->find($id);
        if ($t) {
            $explicado = $t->estado !== 'explicado';
            DB::table('formacion_temas')->where('id', $id)->update([
                'estado' => $explicado ? 'explicado' : 'pendiente',
                'fecha_explicado' => $explicado ? now()->toDateString() : null,
                'updated_at' => now(),
            ]);
        }
    }

    public function mover(int $id, int $dir): void
    {
        $this->autorizar(true);
        $ids = DB::table('formacion_temas')->orderBy('orden')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $tid) {
            DB::table('formacion_temas')->where('id', $tid)->update(['orden' => $n + 1]);
        }
    }

    public function borrar(int $id): void
    {
        $this->autorizar(true);
        DB::table('formacion_temas')->where('id', $id)->delete();
        if ($this->editando === $id) {
            $this->cancelar();
        }
    }

    public function render()
    {
        $q = DB::table('formacion_temas')->orderBy('orden')->orderBy('id');
        $total = (clone $q)->count();
        $explicados = (clone $q)->where('estado', 'explicado')->count();
        if (in_array($this->filtro, ['pendiente', 'explicado'], true)) {
            $q->where('estado', $this->filtro);
        }
        return view('livewire.formacion', [
            'temas' => $q->get(),
            'total' => $total,
            'explicados' => $explicados,
            'puedeEditar' => (bool) auth()->user()?->can('formacion.editar'),
        ]);
    }
}
