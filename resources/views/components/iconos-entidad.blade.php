@props(['entidad', 'ruta' => null])
{{-- Iconos de las pantallas de una entidad (Pus, Contactos, Historial, Impuestos, Ficha, Facturas, Conceptos). Se marca el de la pantalla
     actual comparando con $ruta (no con request(): dentro de un componente Livewire, al refrescar, la petición es la de Livewire). --}}
<div class="flex items-center" style="column-gap:14px" x-data="{ fb: false }" x-on:keydown.escape.window="fb = false">
    @php $act = fn ($r) => $ruta === $r ? 'ico-act' : ''; @endphp
    <x-icon.key href="{{ route('entidad.pu',$entidad) }}" title="Pus" class="{{ $act('entidad.pu') }}"/>
    <x-icon.usergroup href="{{ route('entidad.contacto',$entidad) }}" title="Contactos" class="{{ $act('entidad.contacto') }}"/>
    <x-icon.clock-a href="{{ route('entidad.historial',$entidad) }}" title="Historial" class="{{ $act('entidad.historial') }}"/>
    <x-icon.impuestos href="{{ route('entidad.impuestos',$entidad) }}" title="Impuestos" class="{{ $act('entidad.impuestos') }}"/>
    <x-icon.edit-a href="{{ route('entidad.edit',$entidad) }}" title="{{ auth()->user()->can('entidades.editar') ? 'Editar' : 'Ficha' }}" class="{{ $act('entidad.edit') }}"/>
    {{-- Ficheros base de SAGE de la entidad (plan, clientes, proveedores, mayor, sumas y saldos): modal con el mismo recuadro que el resto de pantallas (19-nov-2026) --}}
    <button type="button" x-on:click="fb = true" title="Ficheros base de SAGE" class="mr-2 text-lg leading-none transform hover:scale-125">📂</button>
    <div x-show="fb" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.4);display:none" x-on:click.self="fb = false">
        <div class="w-full max-w-3xl p-4 overflow-y-auto bg-white rounded-lg shadow-xl" style="max-height:90vh">
            <div class="flex items-start justify-between gap-2 mb-2">
                <h3 class="font-semibold text-gray-900">Ficheros base · {{ $entidad->entidad }}</h3>
                <button type="button" x-on:click="fb = false" class="text-xl leading-none text-gray-400 hover:text-gray-700">&times;</button>
            </div>
            @livewire('ficheros-base-subida', ['entidadId' => (int) $entidad->id, 'abierto' => true], key('fb-ico-'.$entidad->id))
        </div>
    </div>
    @can('facturacion.ver')
    <x-jet-dropdown align="center" width="w-36">
        <x-slot name="trigger">
            <button type="button" title="Facturas / Prefacturas" class="mr-2 text-gray-500 transform hover:text-gray-700 hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 012-2h5l5 5v9a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm7 0v3h3l-3-3zM7 11h6v1.5H7V11zm0 3h6v1.5H7V14z"/></svg>
            </button>
        </x-slot>
        <x-slot name="content">
            <div class="w-36">
                <x-jet-dropdown-link href="{{ route('facturacion.show',$entidad)}}" class="text-center">{{ __('Facturas') }}</x-jet-dropdown-link>
                <x-jet-dropdown-link href="{{ route('facturacion.prefacturasentidad',$entidad)}}" class="text-center">{{ __('Prefacturas') }}</x-jet-dropdown-link>
            </div>
        </x-slot>
    </x-jet-dropdown>
    <a href="{{ route('facturacionconcepto.entidad',$entidad)}}" title="Fac.Conceptos" class="w-5 mr-2 text-green-600 transform hover:text-green-800 hover:scale-125 {{ $act('facturacionconcepto.entidad') }}"><x-icon.coins/></a>
    @endcan
</div>
