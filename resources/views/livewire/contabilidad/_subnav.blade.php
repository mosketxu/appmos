{{-- Sub-navegación de la pestaña Contabilidad: Procesos FIQ / Facturación PDF.
     Son pantallas independientes (rutas y componentes Livewire distintos). --}}
<div class="flex gap-4 px-4 pt-4 text-sm font-medium border-b border-gray-200">
    <a href="{{ route('contabilidad.procesos') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.procesos') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Procesos FIQ
    </a>
    <a href="{{ route('contabilidad.facturacion-pdf') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.facturacion-pdf') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Facturación PDF
    </a>
</div>
