<?php

namespace App\Http\Livewire;

use App\Support\ColaTareas;
use App\Support\TodoClaude;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * «Claude automático» en la barra de menú, a la izquierda del usuario (solo Admin): trabajadores en línea, uso de hoy
 * y el botón de pausar/reanudar todos los desarrollos automáticos. Se refresca solo cada 30 s.
 */
class TodoClaudeEstado extends Component
{
    public function pausar(bool $pausar): void
    {
        abort_unless(TodoClaude::esGestor(auth()->user()), 403);
        TodoClaude::pausarGlobal($pausar);
    }

    public function render()
    {
        if (config('contabilidad.todo_url') || ! TodoClaude::esGestor(auth()->user()) || ! TodoClaude::usuario()) {   // en un PC: no hay trabajadores ni uso propios
            return view('livewire.todo-claude-estado', ['ec' => null]);
        }
        $limite = now()->subSeconds(ColaTareas::LATIDO_MAX);
        $trabajadores = DB::table('trabajadores')->where('activo', true)->orderBy('nombre')->get()->map(fn ($w) => [
            'nombre' => $w->nombre, 'en_linea' => $w->ultimo_latido && $w->ultimo_latido >= $limite,
            'principal' => $w->nombre === config('contabilidad.claude_todo_primario'),
        ])->all();
        $enCurso = DB::table('tareas')->where('proceso', 'claude.todo')->where('estado', 'en_curso')->pluck('parametros')
            ->map(fn ($p) => \App\Models\TodoTarea::find((int) (json_decode($p, true)['tarea_id'] ?? 0))?->titulo)->filter()->values()->all();

        return view('livewire.todo-claude-estado', ['ec' => [
            'trabajadores' => $trabajadores, 'en_curso' => $enCurso, 'pausado' => TodoClaude::pausadoGlobal(),
            'hoy' => TodoClaude::ejecucionesHoy(), 'limite' => TodoClaude::limiteDia(),
            'porcentaje' => TodoClaude::porcentajeUso(), 'coste' => TodoClaude::costeHoy(),
            'plan' => TodoClaude::usoPlan(), 'freno' => (int) config('contabilidad.claude_todo_max_uso', 80), 'agotado' => TodoClaude::planAgotado(),
            // El contador del día se pone a cero a medianoche (hora del servidor)
            'reinicio' => ($m = (int) now()->diffInMinutes(now()->addDay()->startOfDay())) >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min',
        ]]);
    }
}
