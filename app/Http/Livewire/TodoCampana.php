<?php

namespace App\Http\Livewire;

use App\Models\TodoAviso;
use Livewire\Component;

/** Campana del TO-DO en el centro de la barra de menú: avisos sin leer (se refresca sola cada 30 s). */
class TodoCampana extends Component
{
    public function leer(int $id)
    {
        $a = TodoAviso::where('user_id', auth()->id())->findOrFail($id);
        TodoAviso::where('user_id', auth()->id())->where('tarea_id', $a->tarea_id)->sinLeer()->update(['leido_at' => now()]);

        return redirect()->route('todo', ['t' => $a->tarea_id]);
    }

    public function marcarTodas(): void
    {
        TodoAviso::where('user_id', auth()->id())->sinLeer()->update(['leido_at' => now()]);
    }

    public function render()
    {
        $q = TodoAviso::where('user_id', auth()->id())->sinLeer();
        return view('livewire.todo-campana', [
            'total' => (clone $q)->count(),
            'avisos' => $q->with(['tarea:id,titulo', 'origen:id,name'])->latest('id')->limit(10)->get(),
        ]);
    }
}
