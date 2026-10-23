<div x-data="{
        sobre: null,
        subir(id, files) {
            if (! files || ! files.length) return;
            $wire.$set('temaSubida', id, false);
            const dt = new DataTransfer();
            for (const f of files) dt.items.add(f);
            $refs.fi.files = dt.files;
            $refs.fi.dispatchEvent(new Event('change', { bubbles: true }));
        },
        elegir(id) { $wire.$set('temaSubida', id, false); $refs.fi.click(); },
    }">
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    <input type="file" multiple wire:model="subidas" x-ref="fi" class="hidden">
<div class="flex flex-wrap items-start gap-4 p-4">
<div class="p-0 fo-izq" style="flex:1 1 28rem; min-width:min(100%, 24rem); max-width:56rem">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h1 class="text-lg font-semibold text-gray-800">Formación</h1>
        <span class="text-sm text-gray-500">{{ $explicados }} de {{ $total }} explicados</span>
        <div class="ml-auto flex gap-1 text-sm">
            @foreach (['todos' => 'Todos', 'pendiente' => 'Pendientes', 'explicado' => 'Explicados'] as $k => $txt)
                <button wire:click="$set('filtro','{{ $k }}')" type="button"
                    class="px-3 py-1 rounded border {{ $filtro === $k ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100' }}">{{ $txt }}</button>
            @endforeach
        </div>
        @if ($puedeEditar && ! $formulario)
            <button type="button" wire:click="nuevo" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">＋ Nuevo</button>
        @endif
    </div>

    @if ($puedeEditar)
        @if ($formulario)
        <form wire:submit.prevent="guardar" class="p-3 mb-4 bg-white border rounded">
            <div class="relative"><input type="text" wire:model="titulo" placeholder="Tema a explicar" class="w-full text-sm border-gray-300 rounded" style="padding-right:3rem"><x-boton-dictado /></div>
            @error('titulo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="relative mt-2"><textarea wire:model="descripcion" rows="2" placeholder="Notas (opcional): qué contar, para quién, material…" class="w-full text-sm border-gray-300 rounded" style="padding-right:3rem"></textarea><x-boton-dictado /></div>
            <div class="flex gap-2 mt-2">
                <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">{{ $editando ? 'Guardar cambios' : 'Añadir' }}</button>
                <button type="button" wire:click="cancelar" class="px-3 py-1 text-sm text-gray-600 bg-white border rounded hover:bg-gray-100">Cancelar</button>
            </div>
        </form>
        @endif
        <div wire:loading wire:target="subidas" class="px-3 py-2 mb-2 text-sm text-indigo-900 border border-indigo-200 rounded bg-indigo-50">⏳ Subiendo archivos…</div>
        @if ($errorArchivos)
            <div class="px-3 py-2 mb-2 text-sm text-red-700 border border-red-200 rounded bg-red-50">{{ $errorArchivos }}</div>
        @endif
    @endif

    <ul class="space-y-2">
        @forelse ($temas as $t)
            @php $misArchivos = $archivos[$t->id] ?? collect(); @endphp
            <li class="flex items-start gap-3 p-3 bg-white border rounded {{ $t->estado === 'explicado' ? 'opacity-70' : '' }}"
                @if ($puedeEditar)
                    x-on:dragover.prevent="sobre = {{ $t->id }}" x-on:dragleave.prevent="sobre = null" x-on:drop.prevent="sobre = null; subir({{ $t->id }}, $event.dataTransfer.files)"
                    x-bind:style="sobre === {{ $t->id }} ? 'outline:2px dashed #4f46e5; background:#eef2ff' : ''"
                @endif>
                @if ($puedeEditar)
                    <input type="checkbox" wire:click="alternarEstado({{ $t->id }})" @checked($t->estado === 'explicado') class="mt-1 rounded" title="Marcar como explicado">
                @else
                    <span class="mt-0.5">{{ $t->estado === 'explicado' ? '✅' : '⬜' }}</span>
                @endif
                <div class="flex-1 min-w-0" x-data="{ abierto: {{ $archivoSel && $misArchivos->contains('id', $archivoSel) ? 'true' : 'false' }} }">
                    <button type="button" x-on:click="abierto = ! abierto" class="flex items-center w-full gap-1 text-left font-medium text-gray-800 {{ $t->estado === 'explicado' ? 'line-through' : '' }}" x-bind:aria-expanded="abierto">
                        <span class="text-xs text-gray-500" x-text="abierto ? '▼' : '▶'"></span><span>{{ $t->titulo }}</span>
                        @if ($misArchivos->count())<span class="text-xs font-normal text-gray-400 no-underline">📎 {{ $misArchivos->count() }}</span>@endif
                    </button>
                    <div x-show="abierto" x-cloak>
                    @if ($t->descripcion)
                        <div class="text-sm text-gray-600 whitespace-pre-line">{{ $t->descripcion }}</div>
                    @endif
                    @if ($t->fecha_explicado)
                        <div class="text-xs text-gray-400">Explicado el {{ \Carbon\Carbon::parse($t->fecha_explicado)->format('d/m/Y') }}</div>
                    @endif
                    @if ($misArchivos->count() || $puedeEditar)
                        <div class="flex flex-wrap items-center gap-1 mt-2">
                            @foreach ($misArchivos as $a)
                                <span class="inline-flex items-center overflow-hidden text-xs border rounded" style="{{ $archivoSel === $a->id ? 'border-color:#4f46e5; background:#e0e7ff; font-weight:600' : 'background:#f9fafb' }}">
                                    <button type="button" wire:click="seleccionar({{ $a->id }})" class="px-2 py-1 text-left text-gray-800 hover:underline" title="Ver a la derecha">📎 {{ $a->nombre }} <span class="text-gray-500">· {{ $a->tamano >= 1048576 ? number_format($a->tamano / 1048576, 1, ',', '.').' MB' : max(1, round($a->tamano / 1024)).' KB' }}</span></button>
                                    @if ($puedeEditar)
                                        <button type="button" wire:click="quitarArchivo({{ $a->id }})" wire:confirm="¿Quitar este archivo?" class="px-1 text-gray-500 hover:text-red-600" title="Quitar">✕</button>
                                    @endif
                                </span>
                            @endforeach
                            @if ($puedeEditar)
                                <button type="button" x-on:click="elegir({{ $t->id }})" class="px-2 py-1 text-xs text-indigo-700 border border-indigo-300 border-dashed rounded hover:bg-indigo-50">＋ Añadir archivos (o arrástralos aquí)</button>
                            @endif
                        </div>
                    @endif
                    </div>
                </div>
                @if ($puedeEditar)
                    <div class="flex gap-1 text-sm text-gray-500">
                        <button wire:click="mover({{ $t->id }}, -1)" type="button" title="Subir" class="px-1 hover:text-gray-900">↑</button>
                        <button wire:click="mover({{ $t->id }}, 1)" type="button" title="Bajar" class="px-1 hover:text-gray-900">↓</button>
                        <button wire:click="editar({{ $t->id }})" type="button" title="Editar" class="px-1 hover:text-gray-900">✎</button>
                        <button wire:click="borrar({{ $t->id }})" wire:confirm="¿Borrar este tema y sus archivos?" type="button" title="Borrar" class="px-1 hover:text-red-600">🗑</button>
                    </div>
                @endif
            </li>
        @empty
            <li class="p-4 text-sm text-center text-gray-500 bg-white border rounded">Aún no hay temas.</li>
        @endforelse
    </ul>
</div>

{{-- Visor: el archivo elegido se ve aquí --}}
<div class="p-3 bg-white border rounded fo-der" style="flex:1 1 30rem; min-width:min(100%, 24rem); position:sticky; top:1rem">
    @if ($actual)
        @php
            $ext = strtolower($actual->extension);
            $url = route('formacion.archivo', $actual->id);
            $visible = isset(\App\Http\Controllers\FormacionArchivoController::VISIBLES[$ext]);
        @endphp
        <div class="flex flex-wrap items-center gap-2 mb-2">
            <div class="flex-1 min-w-0 font-medium text-gray-800 truncate" title="{{ $actual->nombre }}">{{ $actual->nombre }}</div>
            <a href="{{ $url }}?descargar=1" class="px-2 py-1 text-xs text-indigo-700 border border-indigo-300 rounded hover:bg-indigo-50">⬇ Descargar</a>
            @if ($visible)<a href="{{ $url }}" target="_blank" class="px-2 py-1 text-xs text-indigo-700 border border-indigo-300 rounded hover:bg-indigo-50">↗ Abrir aparte</a>@endif
            <button type="button" wire:click="seleccionar({{ $actual->id }})" class="px-2 py-1 text-xs text-gray-600 border rounded hover:bg-gray-100" title="Cerrar">✕</button>
        </div>
        @if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true))
            <img src="{{ $url }}" alt="{{ $actual->nombre }}" class="mx-auto rounded" style="max-width:100%; max-height:78vh; object-fit:contain">
        @elseif (in_array($ext, ['mp4', 'webm'], true))
            <video src="{{ $url }}" controls class="w-full rounded" style="max-height:78vh"></video>
        @elseif (in_array($ext, ['mp3', 'm4a', 'wav'], true))
            <audio src="{{ $url }}" controls class="w-full"></audio>
        @elseif ($visible)
            <iframe src="{{ $url }}" class="w-full border rounded" style="height:78vh" title="{{ $actual->nombre }}"></iframe>
        @else
            <div class="p-6 text-sm text-center text-gray-600 border border-dashed rounded">
                Este tipo de archivo (.{{ $ext ?: '?' }}) no se puede ver aquí.<br>
                Pulsa <b>Descargar</b> para abrirlo en tu PC.
            </div>
        @endif
    @else
        <div class="p-8 text-sm text-center text-gray-500">👈 Pulsa un archivo de la izquierda para verlo aquí.</div>
    @endif
</div>
</div>
</div>
