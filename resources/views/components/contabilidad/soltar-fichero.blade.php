{{-- Selector de fichero con arrastrar y soltar (mismo aspecto que el extracto de Bancos).
     Uso: <x-contabilidad.soltar-fichero model="archivoGenerico" accept="application/pdf,.pdf" :fichero="$archivoGenerico"
          texto="Arrastra aquí el PDF o haz clic para elegirlo" /> --}}
@props(['model', 'accept' => '', 'fichero' => null, 'texto' => 'Arrastra aquí el fichero o haz clic para elegirlo'])
<div x-data="{ encima: false }"
     x-on:dragover.prevent="encima = true"
     x-on:dragleave.prevent="encima = false"
     x-on:drop.prevent="encima = false; $event.dataTransfer.files.length && $wire.upload('{{ $model }}', $event.dataTransfer.files[0])">
    <label :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
           class="flex items-center gap-2 px-3 py-2 text-sm border-2 border-dashed rounded-md cursor-pointer">
        <input type="file" wire:model="{{ $model }}" @if ($accept) accept="{{ $accept }}" @endif class="hidden">
        <span>📄</span>
        <span class="text-gray-700 break-all">
            {{ $fichero && method_exists($fichero, 'getClientOriginalName') ? $fichero->getClientOriginalName() : $texto }}
        </span>
    </label>
</div>
