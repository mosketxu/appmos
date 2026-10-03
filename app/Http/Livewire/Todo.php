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
    /** mias = asignadas a la persona vista · pedidas = creadas por ella para otros · todas */
    public string $vista = 'mias';

    /** abiertas | cerradas | todas */
    public string $filtroEstado = 'abiertas';

    /** Usuario cuyas tareas se ven (solo el Admin puede cambiarlo). */
    public int $verUsuario = 0;

    public ?int $abierta = null;

    // Alta
    public bool $nueva = false;
    public string $titulo = '';
    public string $descripcion = '';
    public ?int $asignadoId = null;
    public string $prioridad = 'normal';
    public ?string $fechaLimite = null;

    // Comentario de la tarea abierta
    public string $comentario = '';
    public ?string $fechaComentario = null;

    public function mount(): void
    {
        $this->verUsuario = auth()->id();
        $this->asignadoId = auth()->id();
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
        return $this->esAdmin() || $t->creador_id === $id || $t->asignado_id === $id;
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
            'asignadoId' => 'required|exists:users,id',
            'prioridad' => 'required|in:baja,normal,alta',
            'fechaLimite' => 'nullable|date',
        ], [
            'titulo.required' => 'Pon un título.',
            'asignadoId.required' => 'Elige a quién se asigna.',
        ]);
        $t = TodoTarea::create([
            'titulo' => trim($this->titulo),
            'descripcion' => trim($this->descripcion) ?: null,
            'creador_id' => auth()->id(),
            'asignado_id' => $this->asignadoId,
            'prioridad' => $this->prioridad,
            'orden' => TodoTarea::siguienteOrden((int) $this->asignadoId),
            'fecha_limite' => $this->fechaLimite ?: null,
        ]);
        $this->reset('titulo', 'descripcion', 'prioridad', 'fechaLimite', 'nueva');
        // Para que la tarea recién creada se vea aunque sea para otra persona
        if ($t->asignado_id !== $this->usuarioVisto()) {
            $this->vista = $t->creador_id === $this->usuarioVisto() ? 'pedidas' : 'todas';
        }
        $this->filtroEstado = 'abiertas';
        $this->asignadoId = auth()->id();
        $this->abierta = $t->id;
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

    public function cambiarAsignado(int $id, int $user): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->puede($t) && User::whereKey($user)->exists(), 403);
        $t->update(['asignado_id' => $user, 'orden' => TodoTarea::siguienteOrden($user)]);
    }

    /**
     * Sube (-1) o baja (+1) la tarea en la lista de prioridades de quien la tiene asignada, entre las abiertas.
     * Primero se renumera 1..n para que no haya huecos ni repetidos.
     */
    public function mover(int $id, int $sentido): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->puede($t) && $t->abierta(), 403);
        $ids = TodoTarea::where('asignado_id', $t->asignado_id)->whereNotIn('estado', TodoTarea::CERRADOS)
            ->orderBy('orden')->orderBy('id')->pluck('id')->all();
        $i = array_search($t->id, $ids, true);
        $j = $i + ($sentido < 0 ? -1 : 1);
        if ($i === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $tid) {
            TodoTarea::whereKey($tid)->update(['orden' => $n + 1]);
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
        $q = TodoTarea::with(['creador:id,name', 'asignado:id,name'])->withCount('comentarios');

        $q->where(function ($q) use ($yo) {
            match ($this->vista) {
                'mias' => $q->where('asignado_id', $yo),
                'pedidas' => $q->where('creador_id', $yo)->where('asignado_id', '!=', $yo),
                default => $q->where(fn ($q) => $q->where('creador_id', $yo)->orWhere('asignado_id', $yo)),
            };
        });
        match ($this->filtroEstado) {
            'abiertas' => $q->whereNotIn('estado', TodoTarea::CERRADOS),
            'cerradas' => $q->whereIn('estado', TodoTarea::CERRADOS),
            default => null,
        };
        $tareas = $q->orderByRaw("estado in ('hecha','cancelada')")
            ->orderBy('asignado_id')->orderBy('orden')->orderBy('id')->get();

        $detalle = null;
        if ($this->abierta) {
            $detalle = TodoTarea::with(['creador:id,name', 'asignado:id,name', 'comentarios.user:id,name'])->find($this->abierta);
            if ($detalle && ! $this->puede($detalle)) {
                $detalle = null;
            }
        }

        return view('livewire.todo', ['tareas' => $tareas, 'detalle' => $detalle, 'yo' => $yo, 'esAdmin' => $this->esAdmin()]);
    }
}
