<?php

namespace App\Http\Livewire;

use App\Models\TodoAviso;
use App\Models\TodoComentario;
use App\Models\TodoTarea;
use App\Models\User;
use App\Support\TodoClaude;
use App\Support\TodoCorreo;
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

    /** Texto que filtra las filas (título, descripción, creador o asignados). */
    public string $buscar = '';

    // Alta
    public bool $nueva = false;
    public string $titulo = '';
    public string $descripcion = '';
    /** Permisos de Claude para la tarea nueva (solo los aplica si quien la crea es gestor). */
    public array $permisosNuevos = [];

    /** Personas a las que se asigna la tarea nueva. */
    public array $asignadosIds = [];
    public string $prioridad = 'normal';
    public ?string $fechaLimite = null;

    // Respuesta a la tarea abierta (y, si se quiere, personas que se añaden con ella)
    public string $comentario = '';
    public array $respAsignar = [];
    public bool $respUrgente = false;

    /** Mensaje de confirmación o de por qué no se ha podido hacer algo (se enseña bajo la barra de filtros). */
    public ?string $mensaje = null;
    public string $mensajeTipo = 'ok';
    public ?string $fechaComentario = null;

    public function mount(): void
    {
        $this->verUsuario = auth()->id();
        $this->asignadosIds = [auth()->id()];
        $this->fechaComentario = now()->format('Y-m-d');
        // Viene de un aviso de la campana: abre esa tarea
        if ($t = (int) request('t')) {
            $tarea = TodoTarea::with('asignados')->find($t);
            if ($tarea && $this->puede($tarea)) {
                $this->abierta = $tarea->id;
                $this->leerAvisos($tarea->id);
            }
        }
    }

    protected function leerAvisos(int $tareaId): void
    {
        TodoAviso::where('user_id', auth()->id())->where('tarea_id', $tareaId)->sinLeer()->update(['leido_at' => now()]);
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
     * Listas de prioridades que se pueden ver y ordenar: la propia y, solo si eres Admin, la de cualquiera.
     * La prioridad de cada usuario es suya (decisión de Alex, 3-oct-2026).
     */
    public function getPersonasProperty()
    {
        return $this->esAdmin() ? $this->usuarios : $this->usuarios->where('id', auth()->id())->values();
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

    /** Quien tiene que enterarse de lo que pasa en la tarea: quien la creó y quienes la tienen asignada. */
    protected function interesados(TodoTarea $t): array
    {
        return array_values(array_unique(array_merge([$t->creador_id], $t->asignados()->pluck('users.id')->all())));
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
        TodoAviso::para($t, $ids, 'te ha asignado una tarea');
        if (TodoClaude::esGestor(auth()->user())) {
            foreach ($this->permisosNuevos as $pm) {
                TodoClaude::ponerPermiso($t, (string) $pm, true);
            }
        }
        $this->siClaude($t, $ids);
        TodoCorreo::avisarAsignacion($t, $ids);
        $this->reset('titulo', 'descripcion', 'prioridad', 'fechaLimite', 'nueva', 'permisosNuevos');
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

    /** Si entre los recién asignados está Claude: autorizar y encolar (Admin) o pedir visto bueno (otros). */
    protected function siClaude(TodoTarea $t, array $ids): void
    {
        $c = TodoClaude::usuario();
        if ($c && in_array($c->id, array_map('intval', $ids), true)) {
            TodoClaude::alAsignar($t, auth()->user());
        }
    }

    /** Visto bueno de un Admin para que Claude haga una tarea que le asignó otro usuario. */
    public function autorizarClaude(int $id): void
    {
        abort_unless(TodoClaude::esGestor(auth()->user()), 403);
        TodoClaude::autorizar(TodoTarea::findOrFail($id), auth()->user());
    }

    /** «Ejecutar ya»: que Claude no espere a la próxima pasada. Lo pueden pedir el Admin y quien creó la tarea. */
    public function ejecutarYa(int $id): void
    {
        $t = TodoTarea::findOrFail($id);
        abort_unless($this->esAdmin() || $t->creador_id === auth()->id(), 403);
        $this->mensajeTipo = 'aviso';
        if (! TodoClaude::asignada($t)) {
            $this->mensaje = 'Esta tarea no está asignada a Claude.';
        } elseif (! $t->claude_autorizada_at) {
            $this->mensaje = 'Claude todavía no está autorizado para esta tarea: hace falta el visto bueno de Alex.';
        } elseif (! $t->abierta()) {
            $this->mensaje = 'La tarea está cerrada; reábrela (estado «Pendiente») para que Claude la retome.';
        } elseif ($t->claude_pausada) {
            $this->mensaje = 'La tarea está pausada para Claude: reanúdala primero.';
        } elseif (TodoClaude::pausadoGlobal()) {
            $this->mensaje = 'Todos los desarrollos automáticos están en pausa: reanúdalos en el botón de la barra.';
        } elseif (! TodoClaude::permitido()) {
            $this->mensaje = 'Claude ha hecho hoy el máximo de ejecuciones de seguridad ('.TodoClaude::limiteDia().'); se reinicia a las 00:00.';
        } else {
            $cola = TodoClaude::encolar($t, true);
            $enLinea = \DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(\App\Support\ColaTareas::LATIDO_MAX))->pluck('nombre')->all();
            if ($cola === 'en_curso') {
                $this->mensaje = 'Claude ya está trabajando en esta tarea ahora mismo; leerá lo último cuando termine la pasada.';
            } elseif ($enLinea) {
                $this->mensaje = 'Hecho: la tarea está en la cola y la cogerá '.implode(' o ', $enLinea).' en unos segundos. Verás el resultado como respuesta de Claude y en la campana.';
                $this->mensajeTipo = 'ok';
            } else {
                $this->mensaje = 'La tarea está en la cola, pero ningún PC trabajador está en línea ahora (en este entorno puede que no haya ninguno): empezará en cuanto uno conecte.';
            }
        }
    }

    /**
     * «Pedir prioridad»: quien creó la tarea (o un Admin) avisa a los asignados de que la prioricen. No toca el orden de nadie (la prioridad
     * de cada uno es suya); deja una marca visible y un aviso en la campana.
     */
    public function pedirPrioridad(int $id): void
    {
        $t = TodoTarea::with('asignados')->findOrFail($id);
        abort_unless($t->creador_id === auth()->id() || $this->esAdmin(), 403);
        $destino = $t->asignados->pluck('id')->reject(fn ($u) => $u === auth()->id())->all();
        if (! $t->abierta() || ! $destino) {
            return;
        }
        $t->update(['prioridad_pedida_at' => now(), 'prioridad_pedida_por' => auth()->id()]);
        $this->evento($t, 'pidió que se priorice la tarea');
        TodoAviso::para($t, $destino, 'te pide priorizar «'.mb_substr($t->titulo, 0, 50).'»');
    }

    public function quitarPrioridadPedida(int $id): void
    {
        $t = TodoTarea::with('asignados')->findOrFail($id);
        abort_unless($this->puede($t), 403);
        $t->update(['prioridad_pedida_at' => null, 'prioridad_pedida_por' => null]);
    }

    /** Concede o quita un permiso a Claude en una tarea: solo Alex (gestor). */
    public function permisoClaude(int $id, string $permiso): void
    {
        abort_unless(TodoClaude::esGestor(auth()->user()), 403);
        $t = TodoTarea::findOrFail($id);
        TodoClaude::ponerPermiso($t, $permiso, ! in_array($permiso, (array) $t->claude_permisos, true));
    }

    public function pausarClaudeTarea(int $id, bool $pausar): void
    {
        abort_unless(TodoClaude::esGestor(auth()->user()), 403);
        TodoClaude::pausarTarea(TodoTarea::findOrFail($id), $pausar);
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
        if ($this->abierta) {
            $this->leerAvisos($id);
        }
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
        TodoAviso::para($t, $this->interesados($t), 'cambió el estado a «'.TodoTarea::ESTADOS[$estado].'»');
        $t->prioridad_pedida_at = null;
        $t->prioridad_pedida_por = null;
        $t->estado = $estado;
        $t->cerrada_at = in_array($estado, TodoTarea::CERRADOS, true) ? now() : null;
        $t->save();
        if ($estado === 'pendiente') {   // se reabre una tarea de Claude: que la retome
            TodoClaude::alResponder($t, auth()->id());
        }
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
            TodoAviso::para($t, [$user], 'te ha asignado una tarea');
            $this->siClaude($t, [$user]);
            TodoCorreo::avisarAsignacion($t, [$user]);
        }
    }

    /**
     * Arrastrar y soltar: $ids = los ids de las tareas abiertas de $persona (por defecto, la lista que se ve) en el orden nuevo.
     * Se ignora lo que no sea mío o esté cerrado; mis abiertas que no vengan en la lista quedan al final.
     */
    public function reordenar(array $ids, ?int $persona = null): void
    {
        $yo = $persona ?: $this->usuarioVisto();
        // La prioridad de cada usuario es suya: solo ordeno la mía o, si soy Alex (gestor), la de Claude. La de los demás es solo lectura.
        if (! $this->personas->contains('id', $yo) || ! ($yo === auth()->id() || ($yo === TodoClaude::usuario()?->id && TodoClaude::esGestor(auth()->user())))) {
            return;
        }
        $visibles = $this->tareasVisiblesDe($yo);
        $mias = \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')
            ->where('p.user_id', $yo)->whereNotIn('t.estado', TodoTarea::CERRADOS)
            ->orderBy('p.orden')->orderBy('p.id')->pluck('p.tarea_id')->all();
        // Solo las que llegan (las que se ven en pantalla y puedo tocar); ocupan los mismos huecos que tenían en la lista de $yo,
        // así las que no se ven no se mueven de su sitio
        $nuevo = array_values(array_unique(array_intersect(array_map('intval', $ids), $mias, $visibles)));
        $huecos = array_keys(array_filter($mias, fn ($id) => in_array($id, $nuevo, true)));
        $lista = $mias;
        foreach ($huecos as $k => $pos) {
            $lista[$pos] = $nuevo[$k];
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
        abort_unless($yo === auth()->id() && $this->puede($t) && $t->abierta() && $t->estaAsignadaA($yo) && in_array($t->id, $this->tareasVisiblesDe($yo), true), 403);
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
            TodoAviso::para($t, $nuevos, 'te ha asignado una tarea');
            $this->siClaude($t, $nuevos);
            TodoCorreo::avisarAsignacion($t, $nuevos);
        }
        TodoClaude::alResponder($t, auth()->id(), $this->respUrgente && ($this->esAdmin() || $t->creador_id === auth()->id()));
        TodoAviso::para($t, array_diff($this->interesados($t), $nuevos), 'ha respondido');
        $t->touch();
        $this->respAsignar = [];
        $this->respUrgente = false;
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
        $q = TodoTarea::with(['creador:id,name', 'asignados:id,name', 'prioridadPedidaPor:id,name'])->withCount(['comentarios' => fn ($c) => $c->where('tipo', 'respuesta')])->select('todo_tareas.*', 'mi.orden as mi_orden')
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
        if (($b = trim($this->buscar)) !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $b).'%';
            $q->where(fn ($w) => $w->where('todo_tareas.titulo', 'like', $like)->orWhere('todo_tareas.descripcion', 'like', $like)
                ->orWhereHas('creador', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('asignados', fn ($c) => $c->where('users.name', 'like', $like)));
        }
        match ($this->filtroEstado) {
            'abiertas' => $q->whereNotIn('estado', TodoTarea::CERRADOS),
            'cerradas' => $q->whereIn('estado', TodoTarea::CERRADOS),
            default => null,
        };
        // Las cerradas al final; primero las mías por mi orden de prioridad, luego las que solo he pedido
        $tareas = $q->orderByRaw("estado in ('hecha','cancelada')")
            ->orderByRaw('mi.orden is null')->orderBy('mi.orden')->orderByDesc('todo_tareas.id')->get();
        // Mi lista de prioridades (la de la persona cuya lista se ve): las abiertas que tiene asignadas, por su orden y
        // numeradas 1, 2, 3… Primero van esas; después el resto (creadas para otros, cerradas), sin número ni ⠿.
        $mias = $tareas->filter(fn ($t) => $t->abierta() && $t->mi_orden !== null)->sortBy([['mi_orden', 'asc'], ['id', 'desc']])->values();
        foreach ($mias as $i => $t) {
            $t->posicion = $i + 1;
        }
        foreach ($mias as $t) {
            $t->grupo = $yo;
            $t->soloLectura = $yo !== auth()->id();   // el Admin ve cómo ha priorizado otra persona, sin poder tocarlo
        }
        $cl = TodoClaude::usuario()?->id;
        $deClaude = collect();
        if ($cl && $cl !== $yo && TodoClaude::esGestor(auth()->user())) {
            // Alex también ordena la lista de Claude (sus tareas asignadas a Claude, que no son suyas)
            $deClaude = $tareas->filter(fn ($t) => $t->abierta() && $t->mi_orden === null && $t->asignados->contains('id', $cl))
                ->sortBy(fn ($t) => [$t->asignados->firstWhere('id', $cl)->pivot->orden, -$t->id])->values();
            foreach ($deClaude as $t) {
                $t->grupo = $cl;
            }
        }
        $ids = $mias->pluck('id')->merge($deClaude->pluck('id'))->all();
        $tareas = $mias->concat($deClaude)->concat($tareas->reject(fn ($t) => in_array($t->id, $ids, true))->values());

        $detalle = null;
        if ($this->abierta) {
            $detalle = TodoTarea::with(['creador:id,name', 'asignados:id,name', 'comentarios.user:id,name'])->find($this->abierta);
            if ($detalle && ! $this->puede($detalle)) {
                $detalle = null;
            }
        }

        $colaClaude = \DB::table('tareas')->where('proceso', 'claude.todo')->whereIn('estado', ['pendiente', 'en_curso'])->get(['parametros', 'estado', 'no_antes_de'])
            ->mapWithKeys(fn ($c) => [(int) (json_decode($c->parametros, true)['tarea_id'] ?? 0) => $c]);

        return view('livewire.todo', ['colaClaude' => $colaClaude, 'tareas' => $tareas, 'detalle' => $detalle, 'yo' => $yo, 'esAdmin' => $this->esAdmin(), 'claudeId' => TodoClaude::usuario()?->id, 'esGestor' => TodoClaude::esGestor(auth()->user())]);
    }
}
