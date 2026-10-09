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

    /** «Es correcto, lo he apagado»: deja de avisar de ese PC hasta que vuelva a encenderse. Solo Admin y gestores. */
    public function silenciarPc(string $nombre): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->hasRole('Admin') || in_array($u->email, config('contabilidad.claude_todo_gestores', []), true)), 403);
        \App\Support\VigilanciaTrabajadores::silenciar($nombre);
    }

    public function marcarTodas(): void
    {
        TodoAviso::where('user_id', auth()->id())->sinLeer()->update(['leido_at' => now()]);
    }

    public function render()
    {
        if (config('contabilidad.todo_url')) {   // en un PC no hay avisos propios: están en la web
            return view('livewire.todo-campana', ['total' => 0, 'avisos' => collect(), 'oculta' => true]);
        }
        \App\Support\VigilanciaTrabajadores::revisar();   // si ningún PC está vivo, avisa al abrir Appmos (la campana se refresca sola)
        $q = TodoAviso::where('user_id', auth()->id())->sinLeer();
        $u = auth()->user();
        $gestor = $u && ($u->hasRole('Admin') || in_array($u->email, config('contabilidad.claude_todo_gestores', []), true));
        return view('livewire.todo-campana', [
            'caidos' => $gestor ? \App\Support\VigilanciaTrabajadores::caidos() : [],
            'total' => (clone $q)->count(),
            'avisos' => $q->with(['tarea:id,titulo', 'origen:id,name'])->latest('id')->limit(10)->get(),
        ]);
    }
}
