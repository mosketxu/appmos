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

    // Respuesta a la tarea abierta (y, si se quiere, personas que se añaden con ella)
    public string $comentario = '';
    public array $respAsignar = [];
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

    /** Persona cuya lista se ve (y cuyo orden de prioridad se mueve): uno mismo, o alguien de $this->personas. */
    protected function usuarioVisto(): int
    {
        $v = $this->verUsuario ?: auth()->id();
        return $this->personas->contains('id', $v) ? $v : auth()->id();
    }

    /**
     * Listas que puede ver: la suya y, si es Admin, la de cualquiera; si no, la de las personas con las que
     * comparte alguna tarea (a quien se la ha asignado, o quien se la ha asignado a él), para poder ordenarles las prioridades.
     */
    public function getPersonasProperty()
    {
        $yo = auth()->id();
        if ($this->esAdmin()) {
            return $this->usuarios;
        }
        $mias = \DB::table('todo_tareas')->where('creador_id', $yo)
            ->orWhereIn('id', \DB::table('todo_tarea_user')->where('user_id', $yo)->select('tarea_id'))->pluck('id');
        $ids = \DB::table('todo_tarea_user')->whereIn('tarea_id', $mias)->pluck('user_id')->push($yo)->unique();
        return $this->usuarios->whereIn('id', $ids->all())->values();
    }

    /** Puede ver y tocar la tarea: Admin, quien la creó o a quien está asignada. */
    protected function puede(TodoTarea $t): bool
    {
        $id = auth()->id();
        return $this->esAdmin() || $t->creador_id === $id || $t->estaAsignadaA($id);
    }

    /**
     * Anota en el hilo una línea de evento (asignaciones, cambios de estado) con quién la hizo y cuándo.
     * Es solo un registro: no dispara nada más (ni correos ni avisos), así que no puede encadenarse.
     */
    protected function evento(TodoTarea $t, string $texto): void
    {
        TodoComentario::create(['tarea_id' => $t->id, 'user_id' => auth()->id(), 'tipo' => 'evento', 'fecha' => now()->format('Y-m-d'), 'texto' => $texto]);
    }

    protected function nombres(array $ids): string
    {
        return User::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ');
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
        $ids = array_values(array_unique(array_map('intval', $this->asignadosIds)));
        foreach ($ids as $uid) {
            $t->asignados()->attach($uid, ['orden' => TodoTarea::siguienteOrden($uid)]);
        }
        $this->evento($t, 'creó la tarea y la asignó a '.$this->nombres($ids));
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

    /** Marca o desmarca a una persona para añadirla a la tarea junto con la respuesta (puede quedar vacío). */
    public function alternarRespuesta(int $user): void
    {
        $ids = array_map('intval', $this->respAsignar);
        $this->respAsignar = in_array($user, $ids, true) ? array_values(array_diff($ids, [$user])) : [...$ids, $user];
    }

    public function abrir(int $id): void
    {
        $this->abierta = $this->abierta === $id ? null : $id;
        $this->comentario = '';
        $this->respAsignar = [];
        $this->fechaComentario = now()->format('Y-m-d');
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->puede($t) && isset(TodoTarea::ESTADOS[$estado]), 403);
        if ($t->estado === $estado) {
            return;
        }
        $this->evento($t, 'cambió el estado de «'.TodoTarea::ESTADOS[$t->estado].'» a «'.TodoTarea::ESTADOS[$estado].'»');
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
                $this->evento($t, 'quitó a '.$this->nombres([$user]));
            }
        } else {
            $t->asignados()->attach($user, ['orden' => TodoTarea::siguienteOrden($user)]);
            $this->evento($t, 'asignó a '.$this->nombres([$user]));
        }
    }

    /**
     * Arrastrar y soltar: $ids = los ids de mis tareas abiertas en el orden nuevo (el de la pantalla).
     * Se ignora lo que no sea mío o esté cerrado; mis abiertas que no vengan en la lista quedan al final.
     */
    public function reordenar(array $ids): void
    {
        $yo = $this->usuarioVisto();
        $visibles = $this->tareasVisiblesDe($yo);
        $mias = \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')
            ->where('p.user_id', $yo)->whereNotIn('t.estado', TodoTarea::CERRADOS)
            ->orderBy('p.orden')->orderBy('p.id')->pluck('p.tarea_id')->all();
        // Solo las que se ven; ocupan los mismos huecos que tenían en la lista de $yo (las que no se ven no se mueven)
        $nuevo = array_values(array_unique(array_intersect(array_map('intval', $ids), $mias, $visibles)));
        $huecos = array_keys(array_filter($mias, fn ($id) => in_array($id, $visibles, true)));
        $lista = $mias;
        foreach ($huecos as $k => $pos) {
            if (isset($nuevo[$k])) {
                $lista[$pos] = $nuevo[$k];
            }
        }
        if (count(array_unique($lista)) !== count($mias)) {
            return;   // lista incoherente (ids repetidos o a medias): no se toca nada
        }
        foreach ($lista as $n => $tid) {
            \DB::table('todo_tarea_user')->where('user_id', $yo)->where('tarea_id', $tid)->update(['orden' => $n + 1]);
        }
    }

    /** Ids de las tareas de $persona que el usuario conectado puede ver (todas si es Admin o es su propia lista). */
    protected function tareasVisiblesDe(int $persona): array
    {
        $q = \DB::table('todo_tarea_user as p')->where('p.user_id', $persona);
        if (! $this->esAdmin() && $persona !== auth()->id()) {
            $q->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')
                ->where(fn ($w) => $w->where('t.creador_id', auth()->id())
                    ->orWhereIn('t.id', \DB::table('todo_tarea_user')->where('user_id', auth()->id())->select('tarea_id')));
        }
        return $q->pluck('p.tarea_id')->map(fn ($i) => (int) $i)->all();
    }

    /**
     * Sube (-1) o baja (+1) la tarea en la lista de prioridades de la persona cuya lista se está viendo
     * (la propia, o la elegida si eres Admin), entre sus abiertas. Antes se renumera 1..n sin huecos.
     */
    public function mover(int $id, int $sentido): void
    {
        $yo = $this->usuarioVisto();
        $t = TodoTarea::with('asignados')->findOrFail($id);
        abort_unless($this->puede($t) && $t->abierta() && $t->estaAsignadaA($yo) && in_array($t->id, $this->tareasVisiblesDe($yo), true), 403);
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
        // Personas que se añaden con la respuesta (las que ya estaban asignadas se ignoran)
        $nuevos = array_values(array_diff(array_map('intval', $this->respAsignar), $t->asignados()->pluck('users.id')->all()));
        $nuevos = User::whereIn('id', $nuevos)->pluck('id')->all();
        foreach ($nuevos as $uid) {
            $t->asignados()->attach($uid, ['orden' => TodoTarea::siguienteOrden($uid)]);
        }
        if ($nuevos) {
            $this->evento($t, 'asignó a '.$this->nombres($nuevos));
        }
        $t->touch();
        $this->respAsignar = [];
        $this->comentario = '';
        $this->fechaComentario = now()->format('Y-m-d');
    }

    public function borrarComentario(int $id): void
    {
        $c = TodoComentario::findOrFail($id);
        abort_unless($c->tipo === 'respuesta' && ($c->user_id === auth()->id() || $this->esAdmin()), 403);
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
        $q = TodoTarea::with(['creador:id,name', 'asignados:id,name'])->withCount(['comentarios' => fn ($c) => $c->where('tipo', 'respuesta')])->select('todo_tareas.*', 'mi.orden as mi_orden')
            ->leftJoin('todo_tarea_user as mi', fn ($j) => $j->on('mi.tarea_id', '=', 'todo_tareas.id')->where('mi.user_id', $yo));

        $q->where(function ($q) use ($yo) {
            match ($this->vista) {
                'mias' => $q->whereNotNull('mi.user_id'),
                'pedidas' => $q->where('creador_id', $yo)->whereNull('mi.user_id'),
                default => $q->where('creador_id', $yo)->orWhereNotNull('mi.user_id'),
            };
        });
        if (! $this->esAdmin() && $yo !== auth()->id()) {
            $q->whereIn('todo_tareas.id', $this->tareasVisiblesDe($yo));
        }
        match ($this->filtroEstado) {
            'abiertas' => $q->whereNotIn('estado', TodoTarea::CERRADOS),
            'cerradas' => $q->whereIn('estado', TodoTarea::CERRADOS),
            default => null,
        };
        // Las cerradas al final; primero las mías por mi orden de prioridad, luego las que solo he pedido
        $tareas = $q->orderByRaw("estado in ('hecha','cancelada')")
            ->orderByRaw('mi.orden is null')->orderBy('mi.orden')->orderByDesc('todo_tareas.id')->get();
        // Posición (1, 2, 3…) entre mis tareas abiertas
        $pos = 0;
        foreach ($tareas as $t) {
            $t->mi_posicion = ($t->abierta() && $t->mi_orden !== null) ? ++$pos : null;
        }

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
