{{-- Sub-navegación de la pestaña Contabilidad: Procesos FIQ / Facturación PDF / Durcal / Bancos.
     Son pantallas independientes (rutas y componentes Livewire distintos).
     $activa (opcional): ruta de la pestaña activa, para que siga marcada cuando
     Livewire re-renderiza (en esas peticiones request()->routeIs() ya no vale). --}}
<div class="flex gap-4 px-4 pt-4 text-sm font-medium border-b border-gray-200">
    @can('contabilidad.procesos')
    <a href="{{ route('contabilidad.procesos') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.procesos') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Procesos FIQ
    </a>
    @endcan
    @can('contabilidad.facturacionpdf')
    <a href="{{ route('contabilidad.facturacion-pdf') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.facturacion-pdf') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Facturación PDF
    </a>
    @endcan
    @can('contabilidad.durcal')
    <a href="{{ route('contabilidad.durcal') }}"
       class="pb-2 -mb-px border-b-2 {{ request()->routeIs('contabilidad.durcal') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Durcal
    </a>
    @endcan
    @can('contabilidad.bancos')
    <a href="{{ config('contabilidad.bancos_url') ?: route('contabilidad.bancos') }}"
       class="pb-2 -mb-px border-b-2 {{ (($activa ?? null) === 'contabilidad.bancos' || request()->routeIs('contabilidad.bancos')) ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Bancos
    </a>
    @endcan
</div>

{{-- Fuera de los PCs autorizados (VPS) Contabilidad no ejecuta nada: tira de
     OneDrive y no se abre a internet. Marca de agua para que se vea claro. --}}
@php
    $enBancos = ($activa ?? null) === 'contabilidad.bancos' || request()->routeIs('contabilidad.bancos');
    // Bancos va por su cuenta: operativo en la web aunque el resto esté bloqueado
    $bloqueado = $enBancos ? ! config('contabilidad.bancos_ejecucion') : ! config('contabilidad.ejecucion_local');
@endphp
@if ($enBancos && ! config('contabilidad.bancos_ejecucion') && config('contabilidad.bancos_url'))
    <div class="px-4 py-3 mx-4 mt-3 text-sm font-semibold text-center text-indigo-900 border border-indigo-300 rounded-md bg-indigo-50">
        🌐 Bancos se usa desde la web:
        <a href="{{ config('contabilidad.bancos_url') }}" class="underline">{{ config('contabilidad.bancos_url') }}</a>
        (aquí no se ejecuta para no tener dos bases distintas).
    </div>
    <div class="fixed inset-0 z-40 flex items-center justify-center overflow-hidden pointer-events-none select-none" aria-hidden="true">
        <div class="text-5xl font-black leading-tight text-center text-indigo-600 uppercase md:text-7xl opacity-10 whitespace-nowrap" style="transform:rotate(-30deg)">
            Hazlo desde la web
        </div>
    </div>
@elseif ($bloqueado)
    <div class="px-4 py-2 mx-4 mt-3 text-sm font-semibold text-center text-red-800 border border-red-300 rounded-md bg-red-50">
        🔒 NO OPERATIVO DESDE LA WEB POR SEGURIDAD — solo se ejecuta desde los PCs autorizados.
    </div>
    <div class="fixed inset-0 z-40 flex items-center justify-center overflow-hidden pointer-events-none select-none" aria-hidden="true">
        <div class="text-5xl font-black leading-tight text-center text-red-600 uppercase md:text-7xl opacity-10 whitespace-nowrap" style="transform:rotate(-30deg)">
            No operativo desde la web<br>por seguridad
        </div>
    </div>
@endif
