@props(['entidad', 'ruta'])
{{-- Buscador e iconos de la entidad (15-oct-2026): van en la cabecera, a la derecha del nombre (antes estaban en el menú).
     Se escribe el nombre, se elige de la lista y abre la misma pantalla de esa entidad. Los iconos, justo después del buscador (en pantalla grande a la derecha del todo quedaban muy lejos). --}}
@php
    $rutaBuscador = \Illuminate\Support\Facades\Route::has($ruta) ? $ruta : 'entidad.edit';
    $urlsEntidades = \App\Models\Entidad::orderBy('entidad')->get(['id', 'entidad'])
        ->mapWithKeys(fn ($e) => [$e->entidad => route($rutaBuscador, $e->id)]);
@endphp
<div class="flex flex-wrap items-center" style="flex:1 1 30rem; column-gap:1rem; row-gap:.25rem; min-width:0">
    {{-- wire:ignore: dentro de la ficha (Livewire) un refresco no debe borrar lo que se está escribiendo.
         Se abre al elegir de la lista (Chrome no lanza «change» hasta salir del campo), con Intro o al salir. --}}
    <div wire:ignore style="flex:1 1 16rem; max-width:40rem; min-width:12rem"
         x-data="{ q: @js($entidad->entidad), actual: @js($entidad->entidad), urls: @js($urlsEntidades),
                   ir() { if (this.urls[this.q] && this.q !== this.actual) window.location = this.urls[this.q] } }">
        <input type="text" list="lista-buscador-entidad" x-model="q" @focus="$event.target.select()"
               @input="if (! $event.inputType || $event.inputType === 'insertReplacementText') ir()"
               @change="ir()" @keydown.enter.prevent="ir()"
               placeholder="Buscar entidad…" title="Escribe el nombre y elige la entidad de la lista"
               class="w-full py-1 text-sm border-gray-300 rounded-md">
        <datalist id="lista-buscador-entidad">
            @foreach ($urlsEntidades as $nombre => $url)
                <option value="{{ $nombre }}"></option>
            @endforeach
        </datalist>
    </div>
    <div class="flex items-center px-2 py-1 bg-gray-100 rounded-lg" style="margin-left:1rem">
        <x-iconos-entidad :entidad="$entidad" :ruta="$ruta"/>
    </div>
</div>
