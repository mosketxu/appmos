<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])

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
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">TO-DO</h1>
            <button type="button" wire:click="$toggle('nueva')" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">＋ Nueva tarea</button>

            @if ($esAdmin)
                <label class="flex items-center gap-1 ml-4 text-sm text-gray-600">
                    Ver tareas de
                    <select wire:model.live="verUsuario" class="py-1 text-sm border-gray-300 rounded-md">
                        @foreach ($this->usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($yo !== auth()->id())
                    <span class="px-2 py-0.5 text-xs text-amber-800 bg-amber-100 rounded">Estás viendo las tareas de otra persona (como Admin)</span>
                @endif
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="inline-flex overflow-hidden border border-gray-300 rounded-md">
                @foreach (['mias' => 'Asignadas a '.($yo === auth()->id() ? 'mí' : 'esta persona'), 'pedidas' => 'Que he pedido a otros', 'todas' => 'Todas'] as $k => $t)
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
        </div>

        @if ($nueva)
            <form wire:submit="crear" class="grid gap-2 p-3 bg-white border border-indigo-200 rounded-lg md:grid-cols-4">
                <div class="md:col-span-4">
                    <input type="text" wire:model="titulo" placeholder="Título de la tarea" class="{{ $campo }}" autofocus>
                    @error('titulo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <textarea wire:model="descripcion" rows="3" placeholder="Descripción (opcional)" class="{{ $campo }} md:col-span-4"></textarea>
                <label class="text-xs text-gray-500">Asignar a
                    <select wire:model="asignadoId" class="{{ $campo }}">
                        @foreach ($this->usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-gray-500">Prioridad
                    <select wire:model="prioridad" class="{{ $campo }}">
                        @foreach (\App\Models\TodoTarea::PRIORIDADES as $k => $t) <option value="{{ $k }}">{{ $t }}</option> @endforeach
                    </select>
                </label>
                <label class="text-xs text-gray-500">Fecha límite
                    <input type="date" wire:model="fechaLimite" class="{{ $campo }}">
                </label>
                <div class="flex items-end justify-end gap-2">
                    <button type="button" wire:click="$set('nueva', false)" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crear</button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-left text-gray-500 uppercase bg-gray-100">
                    <tr>
                        @if ($vista === 'mias' && $filtroEstado !== 'cerradas') <th class="px-3 py-2 text-center">Prioridad<br>(orden)</th> @endif
                        <th class="px-3 py-2">Tarea</th>
                        <th class="px-3 py-2">Estado</th>
                        <th class="px-3 py-2">Prioridad</th>
                        <th class="px-3 py-2">Asignada a</th>
                        <th class="px-3 py-2">Creada por</th>
                        <th class="px-3 py-2">Fecha límite</th>
                        <th class="px-3 py-2 text-center">💬</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tareas as $t)
                        @php $vencida = $t->abierta() && $t->fecha_limite && $t->fecha_limite->isPast() && ! $t->fecha_limite->isToday(); @endphp
                        <tr wire:key="t{{ $t->id }}" wire:click="abrir({{ $t->id }})"
                            class="border-t border-gray-100 cursor-pointer hover:bg-indigo-50 {{ $abierta === $t->id ? 'bg-indigo-50' : '' }} {{ $t->abierta() ? '' : 'text-gray-400' }}">
                            @if ($vista === 'mias' && $filtroEstado !== 'cerradas')
                                <td class="px-3 py-2 text-center whitespace-nowrap" wire:click.stop>
                                    @if ($t->abierta())
                                        <button type="button" wire:click="mover({{ $t->id }}, -1)" @disabled($loop->first) title="Subir (más prioritaria)" class="px-1.5 border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-30">▲</button>
                                        <span class="inline-block w-5 font-semibold text-gray-600">{{ $loop->iteration }}</span>
                                        <button type="button" wire:click="mover({{ $t->id }}, 1)" title="Bajar (menos prioritaria)" class="px-1.5 border border-gray-300 rounded hover:bg-gray-100">▼</button>
                                    @endif
                                </td>
                            @endif
                            <td class="px-3 py-2 font-medium">{{ $t->titulo }}</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 text-xs border rounded-full {{ $badge[$t->estado] }}">{{ \App\Models\TodoTarea::ESTADOS[$t->estado] }}</span></td>
                            <td class="px-3 py-2 {{ $prio[$t->prioridad] }}">{{ \App\Models\TodoTarea::PRIORIDADES[$t->prioridad] }}</td>
                            <td class="px-3 py-2">{{ $t->asignado->name }}</td>
                            <td class="px-3 py-2">{{ $t->creador->name }}</td>
                            <td class="px-3 py-2 {{ $vencida ? 'text-red-600 font-semibold' : '' }}">{{ $t->fecha_limite?->format('d/m/Y') ?? '—' }}{{ $vencida ? ' ⚠' : '' }}</td>
                            <td class="px-3 py-2 text-center">{{ $t->comentarios_count ?: '' }}</td>
                        </tr>
                        @if ($detalle && $detalle->id === $t->id)
                            <tr wire:key="d{{ $t->id }}" class="border-t border-indigo-100 bg-indigo-50/40">
                                <td colspan="8" class="p-3">
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
                                                <label class="text-xs text-gray-500">Asignada a
                                                    <select wire:change="cambiarAsignado({{ $detalle->id }}, $event.target.value)" class="block py-1 text-sm border-gray-300 rounded-md">
                                                        @foreach ($this->usuarios as $u)
                                                            <option value="{{ $u->id }}" @selected($detalle->asignado_id === $u->id)>{{ $u->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>
                                            @if ($esAdmin || $detalle->creador_id === auth()->id())
                                                <button type="button" wire:click="borrar({{ $detalle->id }})" wire:confirm="¿Borrar esta tarea y sus comentarios?" class="text-xs text-red-600 underline">Borrar tarea</button>
                                            @endif
                                        </div>

                                        <div class="space-y-2 md:col-span-2">
                                            <h3 class="text-sm font-semibold text-gray-700">Comentarios</h3>
                                            @forelse ($detalle->comentarios as $c)
                                                <div wire:key="c{{ $c->id }}" class="p-2 text-sm bg-white border border-gray-200 rounded-md">
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span><b>{{ $c->user->name }}</b> · {{ $c->fecha->format('d/m/Y') }}</span>
                                                        @if ($c->user_id === auth()->id() || $esAdmin)
                                                            <button type="button" wire:click="borrarComentario({{ $c->id }})" wire:confirm="¿Borrar el comentario?" class="text-gray-400 hover:text-red-600">✕</button>
                                                        @endif
                                                    </div>
                                                    <p class="whitespace-pre-line">{{ $c->texto }}</p>
                                                </div>
                                            @empty
                                                <p class="text-sm italic text-gray-400">Todavía no hay comentarios.</p>
                                            @endforelse

                                            <form wire:submit="comentar({{ $detalle->id }})" class="flex flex-wrap items-start gap-2">
                                                <input type="date" wire:model="fechaComentario" class="text-sm border-gray-300 rounded-md">
                                                <div class="flex-1 min-w-[16rem]">
                                                    <textarea wire:model="comentario" rows="2" placeholder="Añadir un comentario…" class="{{ $campo }}"></textarea>
                                                    @error('comentario') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                                </div>
                                                <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Añadir</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">No hay tareas aquí.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
