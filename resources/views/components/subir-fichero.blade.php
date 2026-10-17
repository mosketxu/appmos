{{--
    Selector de fichero con icono (17-oct-2026, pedido de Alex: «no me gusta Seleccionar archivo / Ningún archivo seleccionado»).
    Sin fichero: verde (llama la atención; `neutro` para lo que es de verdad opcional, p. ej. adjuntos). Con fichero: neutro, con su
    nombre y ✕ para quitarlo, dentro, a la derecha. Admite arrastrar. Estilos en public/css/subir-fichero.css.
    Uso: <x-subir-fichero wire:model="sysFichero" accept=".xlsx" texto="Sumas y saldos 09" :fichero="$sysFichero" title="…" />
         Sin wire:model (subida propia en JS): <x-subir-fichero multiple x-on:change="subir($event.target.files); $event.target.value = ''" texto="…" />
    Directriz (Alex, 17-oct-2026, toda la web): el texto de estado («✓ …», «falta…», el fichero que se usará) va A LA DERECHA
    del botón, en un contenedor flex-wrap: si no cabe, baja solo. Nunca debajo a propósito.
--}}
@props(['texto' => 'Subir fichero', 'accept' => null, 'fichero' => null, 'opcional' => false, 'neutro' => false])
@php
    $modelo = $attributes->wire('model')->value();
    $paso = $attributes->filter(fn ($v, $k) => str_starts_with($k, 'wire:model') || str_starts_with($k, 'x-on:') || str_starts_with($k, '@') || $k === 'multiple');
    $nombre = $fichero && method_exists($fichero, 'getClientOriginalName') ? $fichero->getClientOriginalName() : null;
@endphp
<div x-data="{ arrastrando: false }" class="inline-flex">
    <label {{ $attributes->only('title') }}
        x-on:dragover.prevent="arrastrando = true" x-on:dragleave.prevent="arrastrando = false"
        x-on:drop.prevent="arrastrando = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change'))"
        x-bind:class="arrastrando && 'subir-arrastrando'"
        class="subir-btn {{ ! $nombre && ! $neutro ? 'vacio' : '' }}">
        <x-subir.icono :target="$modelo ?: null" />
        <span class="truncate" style="max-width:16rem">{{ $nombre ?? $texto }}@if (! $nombre && $opcional) <span style="font-weight:400;color:#4b5563">(opcional)</span>@endif</span>
        @if ($nombre && $modelo)
            <button type="button" x-on:click.prevent.stop="$wire.set('{{ $modelo }}', null)" class="subir-x" title="Quitar el fichero elegido">✕</button>
        @endif
        <input x-ref="input" type="file" class="hidden" {{ $paso }} @if ($accept) accept="{{ $accept }}" @endif>
    </label>
</div>
