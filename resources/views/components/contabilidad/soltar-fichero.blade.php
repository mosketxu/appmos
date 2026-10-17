{{-- Selector de fichero con arrastrar y soltar (mismo aspecto que el extracto de Bancos).
     Uso: <x-contabilidad.soltar-fichero model="archivoGenerico" accept="application/pdf,.pdf" :fichero="$archivoGenerico"
          texto="Arrastra aquí el PDF o haz clic para elegirlo" /> --}}
@props(['model', 'accept' => '', 'fichero' => null, 'texto' => 'Arrastra aquí el fichero o haz clic para elegirlo'])
<div x-data="{ encima: false }"
     x-on:dragover.prevent="encima = true"
     x-on:dragleave.prevent="encima = false"
     x-on:drop.prevent="encima = false; $event.dataTransfer.files.length && $wire.upload('{{ $model }}', $event.dataTransfer.files[0])">
    @php $hay = $fichero && method_exists($fichero, 'getClientOriginalName'); @endphp
    <label :class="encima && 'encima'" class="subir-zona {{ $hay ? '' : 'vacio' }}" style="font-size:.875rem">
        <input type="file" wire:model="{{ $model }}" @if ($accept) accept="{{ $accept }}" @endif class="hidden">
        <x-subir.icono :target="$model" />
        <span class="break-all">
            {{ $fichero && method_exists($fichero, 'getClientOriginalName') ? $fichero->getClientOriginalName() : $texto }}
        </span>
    </label>
</div>
