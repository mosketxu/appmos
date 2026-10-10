<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_todo', ['activa' => 'todo'])

    @php
        $badge = [
            'pendiente' => 'bg-gray-100 text-gray-700 border-gray-300',
            'en_curso' => 'bg-blue-100 text-blue-800 border-blue-300',
            'bloqueada' => 'bg-red-100 text-red-800 border-red-300',
            'hecha' => 'bg-green-100 text-green-800 border-green-300',
            'cancelada' => 'bg-gray-200 text-gray-500 border-gray-300 line-through',
        ];
        $prio = ['alta' => 'text-red-600 font-semibold', 'normal' => 'text-gray-600', 'baja' => 'text-gray-400'];
        $campo = 'w-full text-sm border-gray-300 rounded-md';
    @endphp

    <div class="p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <h1 class="text-2xl font-semibold text-gray-900">Tareas</h1>
            <button type="button" wire:click="$toggle('nueva')" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">＋ Nueva tarea</button>

            @if ($this->personas->count() > 1)
                <label class="flex items-center gap-1 ml-4 text-sm text-gray-600">
                    {{ $esAdmin ? 'Ver tareas de' : 'Prioridades de' }}
                    <select wire:model.live="verUsuario" class="py-1 text-sm border-gray-300 rounded-md">
                        @foreach ($this->personas as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($yo !== auth()->id())
                    <span class="px-2 py-0.5 text-xs text-amber-800 bg-amber-100 rounded">Estás viendo la lista de otra persona: arrastra el ⠿ para ordenar sus prioridades</span>
                @endif
            @endif

            <div class="inline-flex overflow-hidden border border-gray-300 rounded-md">
                @foreach (['todas' => 'Todas (creadas y asignadas)', 'mias' => 'Asignadas a '.($yo === auth()->id() ? 'mí' : 'esta persona'), 'pedidas' => 'Que he pedido a otros'] as $k => $t)
                    <button type="button" wire:click="$set('vista','{{ $k }}')"
                        class="px-3 py-1 {{ $vista === $k ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">{{ $t }}</button>
                @endforeach
            </div>
            <div class="inline-flex overflow-hidden border border-gray-300 rounded-md">
                @foreach (['abiertas' => 'Abiertas', 'cerradas' => 'Cerradas', 'todas' => 'Todas'] as $k => $t)
                    <button type="button" wire:click="$set('filtroEstado','{{ $k }}')"
                        class="px-3 py-1 {{ $filtroEstado === $k ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">{{ $t }}</button>
                @endforeach
            </div>
            <div class="relative flex-1" style="min-width:14rem">
                <input type="search" wire:model.live.debounce.250ms="buscar" placeholder="Filtrar tareas…" autocomplete="off"
                       class="w-full py-1 pl-7 pr-2 text-sm text-gray-800 bg-white border-gray-300 rounded-md shadow-sm">
                <span class="absolute text-gray-400 pointer-events-none" style="left:.5rem;top:.3rem">🔍</span>
            </div>
        </div>

        @if ($nueva)
            <form wire:submit="crear" class="grid max-w-4xl gap-2 p-3 bg-white border border-indigo-200 rounded-lg md:grid-cols-4">
                <div class="md:col-span-4">
                    <input type="text" wire:model="titulo" placeholder="Título de la tarea" class="{{ $campo }}" autofocus>
                    @error('titulo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <textarea wire:model="descripcion" rows="3" placeholder="Descripción (opcional)" class="{{ $campo }} md:col-span-4"></textarea>
                <div class="text-xs text-gray-500 md:col-span-4">Asignar a
                    <x-todo-asignados :usuarios="$this->usuarios" :seleccionados="$asignadosIds" accion="alternarNuevo(%d)" />
                    @error('asignadosIds') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                @if ($esGestor && $claudeId && in_array($claudeId, array_map('intval', $asignadosIds), true))
                    <div class="p-2 text-xs border border-indigo-200 rounded-md md:col-span-4 bg-indigo-50">
                        <b class="text-indigo-900">🤖 Permisos de Claude para esta tarea</b>
                        <span class="text-gray-600">(solo tú los concedes; sin marcar nada solo lee, edita ficheros del proyecto, hace tests y commits locales)</span>
                        <div class="flex flex-wrap mt-1 gap-x-4 gap-y-1">
                            @foreach (\App\Models\TodoTarea::PERMISOS_CLAUDE as $k => [$etiqueta, $ayuda])
                                <label class="inline-flex items-center gap-1 whitespace-nowrap" title="{{ $ayuda }}">
                                    <input type="checkbox" wire:model="permisosNuevos" value="{{ $k }}" class="border-gray-300 rounded"> {{ $etiqueta }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <label class="text-xs text-gray-500">Prioridad
                    <select wire:model="prioridad" class="{{ $campo }}">
                        @foreach (\App\Models\TodoTarea::PRIORIDADES as $k => $t) <option value="{{ $k }}">{{ $t }}</option> @endforeach
                    </select>
                </label>
                <label class="text-xs text-gray-500">Fecha límite
                    <input type="date" wire:model="fechaLimite" class="{{ $campo }}">
                </label>
                <div class="flex items-end justify-start gap-2 md:col-span-4">
                    <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crear tarea</button>
                    <button type="button" wire:click="$set('nueva', false)" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        @endif

        @if ($mensaje)
            <div class="flex items-start justify-between gap-3 px-3 py-2 text-sm border rounded-lg {{ $mensajeTipo === 'ok' ? 'text-green-800 bg-green-50 border-green-300' : 'text-amber-900 bg-amber-50 border-amber-300' }}">
                <span>{{ $mensaje }}</span>
                <button type="button" wire:click="$set('mensaje', null)" class="text-gray-500 hover:text-gray-800">✕</button>
            </div>
        @endif

        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm whitespace-nowrap">
                <thead class="text-xs text-left text-gray-500 uppercase bg-gray-100">
                    <tr>
                        @if ($filtroEstado !== 'cerradas') <th class="py-2 pl-2 pr-1 text-center" title="Arrastra el ⠿ para cambiar el orden de TU lista de prioridades">⠿</th> @endif
                        <th class="px-2 py-2">Asignada a</th>
                        <th class="px-2 py-2">Creada por</th>
                        <th class="px-2 py-2">Tarea</th>
                        <th class="px-2 py-2">Estado</th>
                        <th class="px-2 py-2">Prioridad</th>
                        <th class="px-2 py-2">Fecha límite</th>
                        <th class="px-2 py-2 text-center">💬</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Secciones: mi lista, la lista de Claude (si la puedo ordenar) y el resto. Con una sola no hace falta cabecera.
                        $claveSeccion = fn ($t) => isset($t->grupo) ? 'g'.$t->grupo : 'otras';
                        $cuentaSecciones = $tareas->groupBy($claveSeccion)->map->count();
                        $seccionAnt = null;
                    @endphp
                    @forelse ($tareas as $t)
                        @php $seccion = $claveSeccion($t); @endphp
                        @if ($cuentaSecciones->count() > 1 && $seccion !== $seccionAnt)
                            @php
                                $esMia = $seccion === 'g'.$yo;
                                $esClaude = $claudeId && $seccion === 'g'.$claudeId;
                                $titulo = $esMia ? ($yo === auth()->id() ? 'MIS TAREAS' : 'TAREAS DE '.strtoupper($this->personas->firstWhere('id', $yo)?->name ?? 'ESTA PERSONA'))
                                    : ($esClaude ? '🤖 TAREAS DE CLAUDE' : 'OTRAS TAREAS (creadas para otros y cerradas)');
                                $nota = $esMia ? ($yo === auth()->id() ? 'Arrastra el ⠿ para ordenar tu prioridad' : 'Así las ha priorizado esa persona: es suya y no se puede cambiar desde aquí') : ($esClaude ? 'Su propia lista de prioridades: arrastra el ⠿ para ordenar lo que hará primero' : 'Sin prioridad propia: no se ordenan aquí');
                                $fondo = $esMia ? '#1d4ed8' : ($esClaude ? '#6d28d9' : '#4b5563');
                            @endphp
                            <tr wire:key="sec-{{ $seccion }}" aria-hidden="true">
                                <td colspan="8" style="background:{{ $fondo }};color:#fff;padding:.45rem .75rem;border-top:{{ $seccionAnt === null ? '0' : '14px solid #f3f4f6' }}">
                                    <span style="font-weight:700;letter-spacing:.04em">{{ $titulo }}</span>
                                    <span style="opacity:.85"> · {{ $cuentaSecciones[$seccion] }}</span>
                                    <span style="opacity:.75;font-size:.75rem;text-transform:none;margin-left:.75rem">{{ $nota }}</span>
                                </td>
                            </tr>
                        @endif
                        @php $seccionAnt = $seccion; @endphp
                        @php
                            $mia = isset($t->grupo) && ! ($t->soloLectura ?? false);   // está en una lista de prioridades que puedo ordenar (la mía; la de Claude si soy Alex): lleva ⠿
                            $vencida = $t->abierta() && $t->fecha_limite && $t->fecha_limite->isPast() && ! $t->fecha_limite->isToday();
                        @endphp
                        <tr wire:key="t{{ $t->id }}" @if ($mia) data-orden="{{ $t->id }}" data-grupo="{{ $t->grupo }}" @endif wire:click="abrir({{ $t->id }})"
                            class="cursor-pointer hover:bg-indigo-50 {{ $t->abierta() ? '' : 'text-gray-400' }}"
                            style="{{ $abierta === $t->id ? 'background:#e0e7ff;box-shadow:inset 4px 0 0 #6366f1;border-top:2px solid #6366f1' : 'border-top:1px solid #9ca3af' }}">
                            @if ($filtroEstado !== 'cerradas')
                                <td class="py-2 pl-2 pr-1 text-center whitespace-nowrap" wire:click.stop>
                                    @if ($mia)
                                        <span data-handle title="Arrastra para cambiar la prioridad de {{ $t->grupo === $yo ? 'tu lista' : 'la lista de Claude' }}" class="inline-block px-1 text-lg leading-none text-gray-400 select-none cursor-grab hover:text-indigo-600">⠿</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-2 py-2">{{ $t->asignados->pluck('name')->implode(', ') }}</td>
                            <td class="px-2 py-2">{{ $t->creador->name }}</td>
                            <td class="px-2 py-2 font-medium max-w-xl truncate" title="{{ $t->titulo }}">{{ $t->titulo }}
                                @if ($claudeId && $t->asignados->contains('id', $claudeId))
                                    @if (! $t->claude_autorizada_at) <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-amber-800 bg-amber-100 rounded" title="Claude necesita el visto bueno de Alex">🤖 sin autorizar</span>
                                    @elseif ($t->claude_pausada) <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-gray-600 bg-gray-200 rounded" title="Pausada para Claude">⏸ pausada</span>
                                    @elseif ($t->abierta())
                                        @php $q = $colaClaude[$t->id] ?? null; @endphp
                                        @if ($q && $q->estado === 'en_curso') <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-white bg-indigo-600 rounded" title="Claude está trabajando en esta tarea ahora mismo">🤖 trabajando…</span>
                                        @elseif ($q && $q->no_antes_de && \Illuminate\Support\Carbon::parse($q->no_antes_de)->isFuture()) <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-indigo-800 bg-indigo-100 rounded" title="Está en la cola: Claude la hará en la próxima pasada. «Ejecutar ya» la adelanta.">🤖 ⏰ {{ \Illuminate\Support\Carbon::parse($q->no_antes_de)->format('H:i') }}</span>
                                        @elseif ($q) <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-indigo-800 bg-indigo-100 rounded" title="En la cola: la cogerá un PC trabajador en unos segundos">🤖 ⏳ en cola</span>
                                        @else <span class="ml-1 text-xs font-normal text-indigo-600" title="Claude la hará automáticamente cuando le toque (ya está autorizada)">🤖</span> @endif
                                    @endif
                                @endif
                                @if ($t->prioridad_pedida_at && $t->abierta())
                                    <span class="ml-1 px-1.5 py-0.5 text-xs font-normal text-yellow-900 bg-yellow-200 border border-yellow-400 rounded" title="{{ $t->prioridadPedidaPor?->name }} pide que se priorice esta tarea ({{ $t->prioridad_pedida_at->format('d/m H:i') }})">⚑ {{ $t->prioridadPedidaPor?->name }} pide prioridad</span>
                                @endif</td>
                            <td class="px-2 py-2"><button type="button" wire:click.stop="siguienteEstado({{ $t->id }})" title="Clic para pasar al siguiente estado (Pendiente → En curso → Bloqueada → Hecha → Pendiente)" class="px-2 py-0.5 text-xs border rounded-full cursor-pointer hover:opacity-75 {{ $badge[$t->estado] }}">{{ \App\Models\TodoTarea::ESTADOS[$t->estado] }}</button></td>
                            <td class="px-2 py-2 {{ $prio[$t->prioridad] }}">{{ \App\Models\TodoTarea::PRIORIDADES[$t->prioridad] }}</td>
                            <td class="px-2 py-2 {{ $vencida ? 'text-red-600 font-semibold' : '' }}">{{ $t->fecha_limite?->format('d/m/Y') ?? '—' }}{{ $vencida ? ' ⚠' : '' }}</td>
                            <td class="px-2 py-2 text-center">{{ $t->comentarios_count ?: '' }}</td>
                        </tr>
                        @if ($detalle && $detalle->id === $t->id)
                            <tr wire:key="d{{ $t->id }}" style="background:#eef2ff;box-shadow:inset 4px 0 0 #6366f1;border-bottom:2px solid #6366f1">
                                <td colspan="8" class="p-3 whitespace-normal">
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div class="space-y-2">
                                            @if ($detalle->descripcion)
                                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $detalle->descripcion }}</p>
                                            @else
                                                <p class="text-sm italic text-gray-400">Sin descripción.</p>
                                            @endif
                                            <p class="text-xs text-gray-500">Creada el {{ $detalle->created_at->format('d/m/Y H:i') }}{{ $detalle->cerrada_at ? ' · cerrada el '.$detalle->cerrada_at->format('d/m/Y H:i') : '' }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                <label class="text-xs text-gray-500">Estado
                                                    <select wire:change="cambiarEstado({{ $detalle->id }}, $event.target.value)" class="block py-1 text-sm border-gray-300 rounded-md">
                                                        @foreach (\App\Models\TodoTarea::ESTADOS as $k => $l)
                                                            <option value="{{ $k }}" @selected($detalle->estado === $k)>{{ $l }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <div class="text-xs text-gray-500">Asignada a
                                                    <x-todo-asignados :usuarios="$this->usuarios" :seleccionados="$detalle->asignados->pluck('id')->all()" :accion="'alternarAsignado('.$detalle->id.', %d)'" />
                                                </div>
                                            </div>
                                            @if ($claudeId && $detalle->asignados->contains('id', $claudeId))
                                                <div class="p-2 text-xs border rounded-md {{ $detalle->claude_autorizada_at ? 'bg-indigo-50 border-indigo-200 text-indigo-900' : 'bg-amber-50 border-amber-300 text-amber-900' }}">
                                                    @if (! $detalle->claude_autorizada_at)
                                                        🤖 <b>Claude necesita el visto bueno de Alex</b> para hacer esta tarea.
                                                        @if ($esGestor) <button type="button" wire:click="autorizarClaude({{ $detalle->id }})" class="ml-1 px-2 py-0.5 text-white bg-green-600 rounded hover:bg-green-700">✔ Autorizar</button> @endif
                                                    @else
                                                        🤖 Claude la hará automáticamente (autorizada). {{ $detalle->claude_pausada ? 'Ahora está PAUSADA.' : 'Pasa cada hora; puedes adelantarlo.' }}
                                                        <span class="block mt-1 mb-1">
                                                            <b>Permisos:</b>
                                                            @foreach (\App\Models\TodoTarea::PERMISOS_CLAUDE as $k => [$etiqueta, $ayuda])
                                                                @php $tiene = in_array($k, (array) $detalle->claude_permisos, true); @endphp
                                                                @if ($esGestor)
                                                                    <label class="inline-flex items-center gap-1 mr-2 whitespace-nowrap" title="{{ $ayuda }}"><input type="checkbox" wire:click="permisoClaude({{ $detalle->id }}, '{{ $k }}')" @checked($tiene) class="border-gray-300 rounded"> {{ $etiqueta }}</label>
                                                                @elseif ($tiene)
                                                                    <span class="px-1.5 py-0.5 mr-1 bg-white border border-indigo-200 rounded">{{ $etiqueta }}</span>
                                                                @endif
                                                            @endforeach
                                                            @if (! $esGestor && empty($detalle->claude_permisos)) <span class="text-gray-500">ninguno (solo lee, edita el proyecto y hace tests)</span> @endif
                                                        </span>
                                                        <span class="flex flex-wrap gap-2 mt-1">
                                                            @if (($esAdmin || $detalle->creador_id === auth()->id()) && $detalle->abierta() && ! $detalle->claude_pausada)
                                                                <button type="button" wire:click="ejecutarYa({{ $detalle->id }})" class="px-2 py-0.5 text-white bg-indigo-600 rounded hover:bg-indigo-700" title="Claude coge ya la tarea tal como está (sin escribir nada nuevo). Si has escrito una respuesta, usa «Responder y que Claude la lea ya», abajo.">⚡ Ejecutar ya la tarea</button>
                                                            @endif
                                                            @if ($esGestor)
                                                                <button type="button" wire:click="pausarClaudeTarea({{ $detalle->id }}, {{ $detalle->claude_pausada ? 'false' : 'true' }})" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ $detalle->claude_pausada ? '▶ Reanudar' : '⏸ Pausar esta' }}</button>
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($detalle->abierta())
                                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                                    @if (($esAdmin || $detalle->creador_id === auth()->id()) && $detalle->asignados->where('id', '!=', auth()->id())->isNotEmpty())
                                                        <button type="button" wire:click="pedirPrioridad({{ $detalle->id }})" wire:confirm="¿Pedir a los asignados que prioricen esta tarea? (les llega un aviso; su orden no cambia)" class="px-2 py-0.5 text-yellow-900 bg-yellow-100 border border-yellow-400 rounded hover:bg-yellow-200">⚑ Pedir prioridad</button>
                                                    @endif
                                                    @if ($detalle->prioridad_pedida_at)
                                                        <span class="text-yellow-800">⚑ {{ $detalle->prioridadPedidaPor?->name }} pidió prioridad el {{ $detalle->prioridad_pedida_at->format('d/m H:i') }}</span>
                                                        <button type="button" wire:click="quitarPrioridadPedida({{ $detalle->id }})" class="text-gray-500 underline">Quitar marca</button>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($detalle->abierta())
                                                <button type="button" wire:click="cambiarEstado({{ $detalle->id }}, 'hecha')" wire:confirm="¿Dar la tarea por finalizada (sin responder)?" class="px-2 py-0.5 text-xs text-green-800 bg-green-100 border border-green-300 rounded hover:bg-green-200" title="Cierra la tarea sin escribir respuesta, aunque Claude esté esperando una">✅ Finalizar tarea</button>
                                            @endif
                                            @if ($esAdmin || $detalle->creador_id === auth()->id())
                                                <button type="button" wire:click="borrar({{ $detalle->id }})" wire:confirm="¿Borrar esta tarea y sus comentarios?" class="text-xs text-red-600 underline">Borrar tarea</button>
                                            @endif
                                        </div>

                                        <div class="space-y-2 md:col-span-2">
                                            <h3 class="text-sm font-semibold text-gray-700">Respuestas</h3>
                                            @forelse ($detalle->comentarios as $c)
                                                @if ($c->tipo === 'evento')
                                                    <p wire:key="c{{ $c->id }}" class="px-1 text-xs italic text-gray-500">
                                                        ➜ <b>{{ $c->user->name }}</b> {{ $c->texto }} · {{ $c->fecha->format('d/m/Y') }}
                                                    </p>
                                                @else
                                                    <div wire:key="c{{ $c->id }}" class="p-2 text-sm bg-white border border-gray-200 rounded-md">
                                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                                            <span><b class="text-gray-800">{{ $c->user->name }}</b> responde · {{ $c->fecha->format('d/m/Y') }}</span>
                                                            @if ($c->user_id === auth()->id() || $esAdmin)
                                                                <button type="button" wire:click="borrarComentario({{ $c->id }})" wire:confirm="¿Borrar la respuesta?" class="text-gray-400 hover:text-red-600">✕</button>
                                                            @endif
                                                        </div>
                                                        <p class="whitespace-pre-line">{{ $c->texto }}</p>
                                                    </div>
                                                @endif
                                            @empty
                                                <p class="text-sm italic text-gray-400">Todavía no hay respuestas.</p>
                                            @endforelse

                                            <form wire:submit="comentar({{ $detalle->id }})" class="space-y-2">
                                                <div class="flex flex-wrap items-start gap-2">
                                                    <input type="date" wire:model="fechaComentario" class="text-sm border-gray-300 rounded-md">
                                                    <div class="flex-1 min-w-[16rem]">
                                                        <textarea wire:model="comentario" rows="2" placeholder="Escribe tu respuesta…" class="{{ $campo }}"></textarea>
                                                        @error('comentario') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                                    </div>
                                                    <span class="flex flex-col gap-1">
                                                        <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Responder</button>
                                                        @if ($claudeId && $detalle->asignados->contains('id', $claudeId) && ($esAdmin || $detalle->creador_id === auth()->id()) && $detalle->abierta())
                                                            {{-- Un solo botón: envía la respuesta Y avisa a Claude para que la lea ya, sin esperar a la próxima hora en punto --}}
                                                            <button type="button" wire:click="comentarYa({{ $detalle->id }})" wire:loading.attr="disabled" class="px-3 py-1 text-sm text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50"
                                                                    title="Envía tu respuesta y Claude la lee ya, sin esperar a la hora en punto">⚡ Responder y que Claude la lea ya</button>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500">Asignar también a (opcional)
                                                    <x-todo-asignados :usuarios="$this->usuarios->whereNotIn('id', $detalle->asignados->pluck('id')->all())->values()" :seleccionados="$respAsignar" accion="alternarRespuesta(%d)" :minimo="0" texto="＋ Asignar a alguien ▾" />
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr wire:key="s{{ $t->id }}" aria-hidden="true"><td colspan="8" style="height:14px;padding:0;background:#f3f4f6"></td></tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">No hay tareas aquí.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @script
    <script>
        const raiz = $wire.$el;   // Arrastrar y soltar para el orden de prioridad (NO poner nada antes de este const: Livewire/Alpine lo necesita en la primera línea)
        let origen = null, destino = null, antes = true;
        const limpiar = () => raiz.querySelectorAll('tr[data-orden]').forEach(r => { r.style.boxShadow = ''; r.style.opacity = ''; });

        raiz.addEventListener('mousedown', e => {
            const h = e.target.closest('[data-handle]');
            if (h) h.closest('tr').draggable = true;
        });
        raiz.addEventListener('mouseup', () => raiz.querySelectorAll('tr[draggable=true]').forEach(r => r.draggable = false));
        raiz.addEventListener('dragstart', e => {
            const tr = e.target.closest ? e.target.closest('tr[data-orden]') : null;
            if (!tr || !tr.draggable) return;
            origen = tr;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', tr.dataset.orden);
            setTimeout(() => tr.style.opacity = '.4');
        });
        raiz.addEventListener('dragover', e => {
            if (!origen) return;
            const tr = e.target.closest('tr[data-orden]');
            if (!tr || tr === origen || tr.dataset.grupo !== origen.dataset.grupo) return;   // solo dentro de la lista de la misma persona
            e.preventDefault();
            const r = tr.getBoundingClientRect();
            antes = e.clientY < r.top + r.height / 2;
            destino = tr;
            raiz.querySelectorAll('tr[data-orden]').forEach(x => { if (x !== origen) x.style.boxShadow = ''; });
            tr.style.boxShadow = antes ? 'inset 0 3px 0 #6366f1' : 'inset 0 -3px 0 #6366f1';
        });
        raiz.addEventListener('drop', e => {
            if (!origen || !destino) return;
            e.preventDefault();
            const ids = [...raiz.querySelectorAll('tr[data-orden]')].filter(r => r !== origen && r.dataset.grupo === origen.dataset.grupo).map(r => r.dataset.orden);
            ids.splice(ids.indexOf(destino.dataset.orden) + (antes ? 0 : 1), 0, origen.dataset.orden);
            limpiar();
            $wire.reordenar(ids.map(Number), Number(origen.dataset.grupo));
        });
        raiz.addEventListener('dragend', () => {
            limpiar();
            raiz.querySelectorAll('tr[draggable=true]').forEach(r => r.draggable = false);
            origen = destino = null;
        });
    </script>
    @endscript
</div>
