<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
<div class="p-4 max-w-4xl">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h1 class="text-lg font-semibold text-gray-800">Formación</h1>
        <span class="text-sm text-gray-500">{{ $explicados }} de {{ $total }} explicados</span>
        <div class="ml-auto flex gap-1 text-sm">
            @foreach (['todos' => 'Todos', 'pendiente' => 'Pendientes', 'explicado' => 'Explicados'] as $k => $txt)
                <button wire:click="$set('filtro','{{ $k }}')" type="button"
                    class="px-3 py-1 rounded border {{ $filtro === $k ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100' }}">{{ $txt }}</button>
            @endforeach
        </div>
    </div>

    @if ($puedeEditar)
        <form wire:submit.prevent="guardar" class="p-3 mb-4 bg-white border rounded">
            <div class="relative"><input type="text" wire:model="titulo" placeholder="Tema a explicar" class="w-full text-sm border-gray-300 rounded" style="padding-right:3rem"><x-boton-dictado /></div>
            @error('titulo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="relative mt-2"><textarea wire:model="descripcion" rows="2" placeholder="Notas (opcional): qué contar, para quién, material…" class="w-full text-sm border-gray-300 rounded" style="padding-right:3rem"></textarea><x-boton-dictado /></div>
            <div class="flex gap-2 mt-2">
                <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">{{ $editando ? 'Guardar cambios' : 'Añadir' }}</button>
                @if ($editando)
                    <button type="button" wire:click="cancelar" class="px-3 py-1 text-sm text-gray-600 bg-white border rounded hover:bg-gray-100">Cancelar</button>
                @endif
            </div>
        </form>
    @endif

    <ul class="space-y-2">
        @forelse ($temas as $t)
            <li class="flex items-start gap-3 p-3 bg-white border rounded {{ $t->estado === 'explicado' ? 'opacity-70' : '' }}">
                @if ($puedeEditar)
                    <input type="checkbox" wire:click="alternarEstado({{ $t->id }})" @checked($t->estado === 'explicado') class="mt-1 rounded" title="Marcar como explicado">
                @else
                    <span class="mt-0.5">{{ $t->estado === 'explicado' ? '✅' : '⬜' }}</span>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-800 {{ $t->estado === 'explicado' ? 'line-through' : '' }}">{{ $t->titulo }}</div>
                    @if ($t->descripcion)
                        <div class="text-sm text-gray-600 whitespace-pre-line">{{ $t->descripcion }}</div>
                    @endif
                    @if ($t->fecha_explicado)
                        <div class="text-xs text-gray-400">Explicado el {{ \Carbon\Carbon::parse($t->fecha_explicado)->format('d/m/Y') }}</div>
                    @endif
                </div>
                @if ($puedeEditar)
                    <div class="flex gap-1 text-sm text-gray-500">
                        <button wire:click="mover({{ $t->id }}, -1)" type="button" title="Subir" class="px-1 hover:text-gray-900">↑</button>
                        <button wire:click="mover({{ $t->id }}, 1)" type="button" title="Bajar" class="px-1 hover:text-gray-900">↓</button>
                        <button wire:click="editar({{ $t->id }})" type="button" title="Editar" class="px-1 hover:text-gray-900">✎</button>
                        <button wire:click="borrar({{ $t->id }})" wire:confirm="¿Borrar este tema?" type="button" title="Borrar" class="px-1 hover:text-red-600">🗑</button>
                    </div>
                @endif
            </li>
        @empty
            <li class="p-4 text-sm text-center text-gray-500 bg-white border rounded">Aún no hay temas.</li>
        @endforelse
    </ul>
</div>
</div>
