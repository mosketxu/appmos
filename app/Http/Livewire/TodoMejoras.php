<?php

namespace App\Http\Livewire;

use App\Models\TodoTarea;
use App\Support\TodoClaude;
use Livewire\Component;

/**
 * Pestaña «Pedir mejora» del TO-DO (22-oct-2026): las mejoras son tareas con título «Mejora: …» (se piden con ?nueva=mejora en el TO-DO).
 * Alex (gestor) ve todas las pedidas; cada usuario, las que ha pedido o tiene asignadas.
 */
class TodoMejoras extends Component
{
    public string $estado = 'abiertas';

    public function render()
    {
        $u = auth()->user();
        $q = TodoTarea::with('creador')->where('titulo', 'like', 'Mejora:%')->orderByDesc('id');
        if (! TodoClaude::esGestor($u)) {
            $q->where(fn ($w) => $w->where('creador_id', $u->id)->orWhereHas('asignados', fn ($a) => $a->where('users.id', $u->id)));
        }
        if ($this->estado === 'abiertas') {
            $q->whereNotIn('estado', ['hecha', 'cancelada']);
        }

        return view('livewire.todo-mejoras', ['mejoras' => $q->get(), 'veTodas' => TodoClaude::esGestor($u)]);
    }
}
