{{-- Icono de subir (y la rueda mientras sube). cargando = expresión Alpine; target = propiedad de Livewire. --}}
@props(['cargando' => null, 'target' => null])
@php
    $ico = '<svg class="subir-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9M4.5 15v2.25A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25V15"/></svg>';
    $rueda = '<svg class="subir-ico animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp
@if ($cargando)
    <span x-show="!({{ $cargando }})" style="display:inline-flex">{!! $ico !!}</span><span x-show="{{ $cargando }}" x-cloak style="display:inline-flex">{!! $rueda !!}</span>
@elseif ($target)
    <span wire:loading.remove wire:target="{{ $target }}" style="display:inline-flex">{!! $ico !!}</span><span wire:loading wire:target="{{ $target }}" style="display:none">{!! $rueda !!}</span>
@else
    {!! $ico !!}
@endif
