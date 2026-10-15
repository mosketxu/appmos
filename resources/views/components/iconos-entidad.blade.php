@props(['entidad', 'ruta' => null])
{{-- Iconos de las pantallas de una entidad (Pus, Contactos, Historial, Impuestos, Ficha, Facturas, Conceptos). Se marca el de la pantalla
     actual comparando con $ruta (no con request(): dentro de un componente Livewire, al refrescar, la petición es la de Livewire). --}}
<div class="flex items-center">
    @php $act = fn ($r) => $ruta === $r ? 'ico-act' : ''; @endphp
    <x-icon.key href="{{ route('entidad.pu',$entidad) }}" title="Pus" class="{{ $act('entidad.pu') }}"/>
    <x-icon.usergroup href="{{ route('entidad.contacto',$entidad) }}" title="Contactos" class="{{ $act('entidad.contacto') }}"/>
    <x-icon.clock-a href="{{ route('entidad.historial',$entidad) }}" title="Historial" class="{{ $act('entidad.historial') }}"/>
    <x-icon.impuestos href="{{ route('entidad.impuestos',$entidad) }}" title="Impuestos" class="{{ $act('entidad.impuestos') }}"/>
    <x-icon.edit-a href="{{ route('entidad.edit',$entidad) }}" title="{{ auth()->user()->can('entidades.editar') ? 'Editar' : 'Ficha' }}" class="{{ $act('entidad.edit') }}"/>
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
