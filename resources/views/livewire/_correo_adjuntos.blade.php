{{-- Adjuntar al borrador (voluntario): adjuntos del correo original y ficheros del equipo de quien lo crea. Variable $modo: «antes» (se añaden al crear el borrador) o «despues» (borrador ya creado). --}}
<div class="p-2 mt-2 bg-white border rounded">
    <div class="text-xs font-semibold text-gray-600">📎 Adjuntar al borrador <span class="font-normal text-gray-400">(opcional)</span></div>
    @if ($adjuntos)
        <div class="mt-1 text-xs text-gray-500">Adjuntos de este correo:</div>
        @foreach ($adjuntos as $a)
            <label class="block text-xs text-gray-700"><input type="checkbox" wire:model="adjOriginales" value="{{ $a['id'] }}"> {{ $a['nombre'] }}
                <span class="text-gray-400">{{ $a['tamano'] >= 1048576 ? round($a['tamano'] / 1048576, 1).' MB' : max(1, round($a['tamano'] / 1024)).' KB' }}</span></label>
        @endforeach
    @endif
    <div class="flex flex-wrap items-center mt-1 text-xs text-gray-600 gap-x-2 gap-y-1">
        <x-subir-fichero wire:model="subidasAdj" multiple texto="Adjuntar ficheros de tu equipo" title="Hasta 12 MB cada uno. Se adjuntan al borrador de Outlook; no se envían a Claude." />
        <span wire:loading wire:target="subidasAdj" class="text-amber-700">⏳ Subiendo…</span>
    </div>
    @foreach ($errors->get('subidasAdj*') as $msgs) @foreach ((array) $msgs as $msg) <div class="text-xs text-red-600">{{ $msg }}</div> @endforeach @endforeach
    @if ($modo === 'despues')
        <button type="button" wire:click="adjuntarAlBorrador" wire:loading.attr="disabled" wire:target="adjuntarAlBorrador" class="px-3 py-1 mt-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">📎 Añadir al borrador ya creado</button>
        <span wire:loading wire:target="adjuntarAlBorrador" class="text-xs text-amber-700">⏳</span>
    @else
        <div class="mt-1 text-xs text-gray-400">Se añadirán al pulsar «Crear borrador en Outlook».</div>
    @endif
</div>
