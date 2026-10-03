<?php

namespace App\Http\Livewire;

use App\Models\TodoComentario;
use App\Models\TodoTarea;
use App\Models\User;
use Livewire\Component;

/**
 * Pestaña TO-DO (3-oct-2026): tareas tipo ticket. Cada usuario ve las que ha creado y las que le han
 * asignado, con su estado; el creador y el asignado (y el Admin) cambian el estado y añaden comentarios con fecha.
 * El Admin puede elegir ver las tareas de cualquier usuario.
 */
class Todo extends Component
{
    /** todas = creadas y asignadas · mias = asignadas a la persona vista · pedidas = creadas por ella para otros */
    public string $vista = 'todas';

    /** abiertas | cerradas | todas */
    public string $filtroEstado = 'abiertas';

    /** Usuario cuyas tareas se ven (solo el Admin puede cambiarlo). */
    public int $verUsuario = 0;

    public ?int $abierta = null;

    // Alta
    public bool $nueva = false;
    public string $titulo = '';
    public string $descripcion = '';
    /** Personas a las que se asigna la tarea nueva. */
    public array $asignadosIds = [];
    public string $prioridad = 'normal';
    public ?string $fechaLimite = null;

    // Comentario de la tarea abierta
    public string $comentario = '';
    public ?string $fechaComentario = null;

    public function mount(): void
    {
        $this->verUsuario = auth()->id();
        $this->asignadosIds = [auth()->id()];
        $this->fechaComentario = now()->format('Y-m-d');
    }

    protected function esAdmin(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    protected function usuarioVisto(): int
    {
        return $this->esAdmin() ? ($this->verUsuario ?: auth()->id()) : auth()->id();
    }

    /** Puede ver y tocar la tarea: Admin, quien la creó o a quien está asignada. */
    protected function puede(TodoTarea $t): bool
    {
        $id = auth()->id();
        return $this->esAdmin() || $t->creador_id === $id || $t->estaAsignadaA($id);
    }

    public function getUsuariosProperty()
    {
        // Los compañeros de Suma con correo (los que pueden entrar) y Claude, que recibe tareas sin entrar en Appmos
        return User::where('activo', true)
            ->where(fn ($q) => $q->whereNotNull('email')->orWhere('name', 'Claude'))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function crear(): void
    {
        $this->validate([
            'titulo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:5000',
            'asignadosIds' => 'required|array|min:1',
            'asignadosIds.*' => 'exists:users,id',
            'prioridad' => 'required|in:baja,normal,alta',
            'fechaLimite' => 'nullable|date',
        ], [
            'titulo.required' => 'Pon un título.',
            'asignadosIds.required' => 'Elige a quién se asigna.',
            'asignadosIds.min' => 'Elige al menos a una persona.',
        ]);
        $t = TodoTarea::create([
            'titulo' => trim($this->titulo),
            'descripcion' => trim($this->descripcion) ?: null,
            'creador_id' => auth()->id(),
            'prioridad' => $this->prioridad,
            'fecha_limite' => $this->fechaLimite ?: null,
        ]);
        foreach (array_unique(array_map('intval', $this->asignadosIds)) as $uid) {
            $t->asignados()->attach($uid, ['orden' => TodoTarea::siguienteOrden($uid)]);
        }
        $this->reset('titulo', 'descripcion', 'prioridad', 'fechaLimite', 'nueva');
        // Para que la tarea recién creada se vea
        $this->vista = 'todas';
        $this->filtroEstado = 'abiertas';
        $this->asignadosIds = [auth()->id()];
        $this->abierta = $t->id;
    }

    /** Marca o desmarca a una persona en el formulario de tarea nueva (siempre queda al menos una). */
    public function alternarNuevo(int $user): void
    {
        $ids = array_map('intval', $this->asignadosIds);
        if (in_array($user, $ids, true)) {
            $ids = array_values(array_diff($ids, [$user]));
        } else {
            $ids[] = $user;
        }
        if ($ids) {
            $this->asignadosIds = $ids;
        }
    }

    public function abrir(int $id): void
    {
        $this->abierta = $this->abierta === $id ? null : $id;
        $this->comentario = '';
        $this->fechaComentario = now()->format('Y-m-d');
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->puede($t) && isset(TodoTarea::ESTADOS[$estado]), 403);
        $t->estado = $estado;
        $t->cerrada_at = in_array($estado, TodoTarea::CERRADOS, true) ? now() : null;
        $t->save();
    }

    /** Añade o quita a una persona de la tarea (siempre queda al menos una). */
    public function alternarAsignado(int $id, int $user): void
    {
        $t = TodoTarea::with('asignados')->findOrFail($id);
        abort_unless($this->puede($t) && User::whereKey($user)->exists(), 403);
        if ($t->estaAsignadaA($user)) {
            if ($t->asignados->count() > 1) {
                $t->asignados()->detach($user);
            }
        } else {
            $t->asignados()->attach($user, ['orden' => TodoTarea::siguienteOrden($user)]);
        }
    }

    /**
     * Sube (-1) o baja (+1) la tarea en la lista de prioridades de la persona cuya lista se está viendo
     * (la propia, o la elegida si eres Admin), entre sus abiertas. Antes se renumera 1..n sin huecos.
     */
    public function mover(int $id, int $sentido): void
    {
        $yo = $this->usuarioVisto();
        $t = TodoTarea::with('asignados')->findOrFail($id);
        abort_unless($this->puede($t) && $t->abierta() && $t->estaAsignadaA($yo), 403);
        $ids = \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')
            ->where('p.user_id', $yo)->whereNotIn('t.estado', TodoTarea::CERRADOS)
            ->orderBy('p.orden')->orderBy('p.id')->pluck('p.tarea_id')->all();
        $i = array_search($t->id, $ids);
        $j = $i === false ? false : $i + ($sentido < 0 ? -1 : 1);
        if ($j === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $tid) {
            \DB::table('todo_tarea_user')->where('user_id', $yo)->where('tarea_id', $tid)->update(['orden' => $n + 1]);
        }
    }

    public function comentar(int $id): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->puede($t), 403);
        $this->validate([
            'comentario' => 'required|string|max:5000',
            'fechaComentario' => 'required|date',
        ], ['comentario.required' => 'Escribe el comentario.']);
        TodoComentario::create([
            'tarea_id' => $t->id,
            'user_id' => auth()->id(),
            'fecha' => $this->fechaComentario,
            'texto' => trim($this->comentario),
        ]);
        $t->touch();
        $this->comentario = '';
        $this->fechaComentario = now()->format('Y-m-d');
    }

    public function borrarComentario(int $id): void
    {
        $c = TodoComentario::findOrFail($id);
        abort_unless($c->user_id === auth()->id() || $this->esAdmin(), 403);
        $c->delete();
    }

    public function borrar(int $id): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($t->creador_id === auth()->id() || $this->esAdmin(), 403);
        $t->delete();
        $this->abierta = null;
    }

    public function render()
    {
        $yo = $this->usuarioVisto();
        $q = TodoTarea::with(['creador:id,name', 'asignados:id,name'])->withCount('comentarios')->select('todo_tareas.*')
            ->leftJoin('todo_tarea_user as mi', fn ($j) => $j->on('mi.tarea_id', '=', 'todo_tareas.id')->where('mi.user_id', $yo));

        $q->where(function ($q) use ($yo) {
            match ($this->vista) {
                'mias' => $q->whereNotNull('mi.user_id'),
                'pedidas' => $q->where('creador_id', $yo)->whereNull('mi.user_id'),
                default => $q->where('creador_id', $yo)->orWhereNotNull('mi.user_id'),
            };
        });
        match ($this->filtroEstado) {
            'abiertas' => $q->whereNotIn('estado', TodoTarea::CERRADOS),
            'cerradas' => $q->whereIn('estado', TodoTarea::CERRADOS),
            default => null,
        };
        // Las cerradas al final; primero las mías por mi orden de prioridad, luego las que solo he pedido
        $tareas = $q->orderByRaw("estado in ('hecha','cancelada')")
            ->orderByRaw('mi.orden is null')->orderBy('mi.orden')->orderByDesc('todo_tareas.id')->get();

        $detalle = null;
        if ($this->abierta) {
            $detalle = TodoTarea::with(['creador:id,name', 'asignados:id,name', 'comentarios.user:id,name'])->find($this->abierta);
            if ($detalle && ! $this->puede($detalle)) {
                $detalle = null;
            }
        }

        return view('livewire.todo', ['tareas' => $tareas, 'detalle' => $detalle, 'yo' => $yo, 'esAdmin' => $this->esAdmin()]);
    }
}
