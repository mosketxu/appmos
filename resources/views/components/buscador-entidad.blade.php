@props(['entidad', 'ruta'])
{{-- Buscador de entidad (15-oct-2026): va a la derecha del nombre en la cabecera de cada pantalla de la entidad
     (antes estaba en el menú, junto a los iconos). Se escribe el nombre, se elige de la lista y abre la misma pantalla de esa entidad. --}}
@php
    $urlsEntidades = \App\Models\Entidad::orderBy('entidad')->get(['id', 'entidad'])
        ->mapWithKeys(fn ($e) => [$e->entidad => route($ruta, $e->id)]);
@endphp
<div x-data="{ q: @js($entidad->entidad), urls: @js($urlsEntidades) }" style="flex:1 1 18rem; max-width:40rem; min-width:12rem">
    <input type="text" list="lista-buscador-entidad" x-model="q" @focus="$event.target.select()"
           @change="if (urls[q] && q !== @js($entidad->entidad)) window.location = urls[q]"
           placeholder="Buscar entidad…" title="Escribe el nombre y elige la entidad de la lista"
           class="w-full py-1 text-sm border-gray-300 rounded-md">
    <datalist id="lista-buscador-entidad">
        @foreach ($urlsEntidades as $nombre => $url)
            <option value="{{ $nombre }}"></option>
        @endforeach
    </datalist>
</div>
