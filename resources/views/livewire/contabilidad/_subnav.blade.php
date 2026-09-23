{{-- Sub-navegación de la pestaña Contabilidad: Procesos FIQ / Facturación PDF / Durcal / Bancos.
     Son pantallas independientes (rutas y componentes Livewire distintos).
     $activa (opcional): ruta de la pestaña activa, para que siga marcada cuando
     Livewire re-renderiza (en esas peticiones request()->routeIs() ya no vale). --}}
<div class="flex gap-4 px-4 pt-4 text-sm font-medium border-b border-gray-200">
    <a href="{{ route('contabilidad.procesos') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.procesos') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Procesos FIQ
    </a>
    <a href="{{ route('contabilidad.facturacion-pdf') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.facturacion-pdf') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Facturación PDF
    </a>
    <a href="{{ route('contabilidad.durcal') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.durcal') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Durcal
    </a>
    <a href="{{ route('contabilidad.bancos') }}"
       class="pb-2 -mb-px border-b-2 {{ (($activa ?? null) === 'contabilidad.bancos' || request()->routeIs('contabilidad.bancos')) ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Bancos
    </a>
</div>
