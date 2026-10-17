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
{{-- Sin fichero: verde, para que llame la atención. Con fichero: neutro, con su nombre y ✕ para quitarlo (dentro, a la derecha). --}}
<div x-data="{ arrastrando: false }" class="inline-flex text-xs">
    <label {{ $attributes->only('title') }}
        x-on:dragover.prevent="arrastrando = true" x-on:dragleave.prevent="arrastrando = false"
        x-on:drop.prevent="arrastrando = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change'))"
        x-bind:style="arrastrando ? 'box-shadow:0 0 0 2px #6366f1' : ''"
        class="inline-flex items-center gap-1.5 px-2 py-1 border rounded-md cursor-pointer select-none"
        @if ($nombre)
            style="background:#fff;border-color:#d1d5db;color:#374151"
            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'"
        @else
            style="background:#f0fdf4;border-color:#22c55e;color:#166534;font-weight:600"
            onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'"
        @endif>
        <svg wire:loading.remove wire:target="{{ $modelo }}" style="width:1rem;height:1rem;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9M4.5 15v2.25A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25V15"/></svg>
        <svg wire:loading wire:target="{{ $modelo }}" class="animate-spin" style="width:1rem;height:1rem;flex-shrink:0" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <span class="truncate" style="max-width:16rem">{{ $nombre ?? $texto }}@if (! $nombre && $opcional) <span style="font-weight:400;color:#4b5563">(opcional)</span>@endif</span>
        @if ($nombre)
            <button type="button" x-on:click.prevent.stop="$wire.set('{{ $modelo }}', null)" title="Quitar el fichero elegido"
                style="margin-left:.15rem;padding:0 .2rem;line-height:1;color:#9ca3af;border-radius:.25rem"
                onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#9ca3af'">✕</button>
        @endif
        <input x-ref="input" type="file" class="hidden" {{ $attributes->whereStartsWith('wire:model') }} @if ($accept) accept="{{ $accept }}" @endif>
    </label>
</div>
