{{--
    Selector de fichero con icono (17-oct-2026, muestra pedida por Alex: «no me gusta Seleccionar archivo / Ningún archivo
    seleccionado; que salga un icono de subir fichero»). Si gusta, sustituirá a los <input type="file"> de toda la aplicación.
    Uso: <x-subir-fichero wire:model="sysFichero" accept=".xlsx" texto="Sumas y saldos 09" :fichero="$sysFichero" title="…" />
    - texto: lo que se sube (al lado del icono); title: ayuda al pasar el ratón
    - fichero: el TemporaryUploadedFile de Livewire (para enseñar el nombre ya subido)
--}}
@props(['texto' => 'Subir fichero', 'accept' => null, 'fichero' => null, 'opcional' => false])
@php
    $modelo = $attributes->wire('model')->value();
    $nombre = $fichero && method_exists($fichero, 'getClientOriginalName') ? $fichero->getClientOriginalName() : null;
@endphp
<div x-data="{ arrastrando: false }" class="inline-flex flex-col text-xs">
    <label {{ $attributes->only('title') }}
        x-on:dragover.prevent="arrastrando = true" x-on:dragleave.prevent="arrastrando = false"
        x-on:drop.prevent="arrastrando = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change'))"
        x-bind:class="arrastrando ? 'border-indigo-500 bg-indigo-50' : ''"
        class="inline-flex items-center gap-1.5 px-2 py-1 border rounded-md cursor-pointer select-none
               {{ $nombre ? 'border-green-300 bg-green-50 text-green-800 hover:bg-green-100' : 'border-dashed border-gray-300 bg-white text-gray-700 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700' }}">
        <span wire:loading.remove wire:target="{{ $modelo }}" class="inline-flex">
            @if ($nombre)
                <svg style="width:1rem;height:1rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            @else
                <svg style="width:1rem;height:1rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9M4.5 15v2.25A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25V15"/></svg>
            @endif
        </span>
        <svg wire:loading wire:target="{{ $modelo }}" class="animate-spin" style="width:1rem;height:1rem" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <span class="truncate" style="max-width:16rem">{{ $nombre ?? $texto }}@if (! $nombre && $opcional) <span class="text-gray-400">(opcional)</span>@endif</span>
        <input x-ref="input" type="file" class="hidden" {{ $attributes->whereStartsWith('wire:model') }} @if ($accept) accept="{{ $accept }}" @endif>
    </label>
    @if ($nombre)
        <button type="button" wire:click="$set('{{ $modelo }}', null)" class="self-start mt-0.5 text-gray-400 hover:text-red-600" title="Quitar el fichero elegido">✕ quitar</button>
    @endif
</div>
