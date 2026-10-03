{{-- Selector de personas: desplegable con casillas + etiquetas con ✕. $accion = método Livewire con %d (id de la persona) --}}
@props(['usuarios', 'seleccionados', 'accion'])
@php
    $sel = collect($seleccionados)->map(fn ($i) => (int) $i);
    $llamar = fn ($id) => sprintf($accion, $id);
@endphp
<div class="flex flex-wrap items-center gap-1.5 mt-1">
    @foreach ($usuarios->whereIn('id', $sel->all()) as $u)
        <span wire:key="chip-{{ $u->id }}" class="inline-flex items-center gap-1 px-2 py-0.5 text-sm text-indigo-800 bg-indigo-100 border border-indigo-300 rounded-full whitespace-nowrap">
            {{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}
            @if ($sel->count() > 1)
                <button type="button" wire:click="{{ $llamar($u->id) }}" title="Quitar a {{ $u->name }}" class="leading-none text-indigo-500 hover:text-red-600">✕</button>
            @endif
        </span>
    @endforeach

    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
        <button type="button" @click="open = !open" class="inline-flex items-center gap-1 px-2 py-0.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-full hover:bg-gray-50">
            ＋ Añadir persona ▾
        </button>
        <div x-show="open" style="display:none" class="absolute left-0 z-20 w-56 py-1 mt-1 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg max-h-64">
            @foreach ($usuarios as $u)
                <label wire:key="opt-{{ $u->id }}" class="flex items-center gap-2 px-3 py-1 text-sm text-gray-700 cursor-pointer hover:bg-indigo-50 whitespace-nowrap">
                    <input type="checkbox" wire:click="{{ $llamar($u->id) }}" @checked($sel->contains($u->id)) class="border-gray-300 rounded">
                    {{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}
                </label>
            @endforeach
        </div>
    </div>
</div>
