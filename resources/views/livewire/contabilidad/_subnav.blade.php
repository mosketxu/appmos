{{-- Sub-navegación de la pestaña Contabilidad, con aspecto de pestañas de hoja de Excel:
     Procesos FIQ / Facturación PDF / Durcal / Bancos / Facturas OCR / IS.
     Son pantallas independientes (rutas y componentes Livewire distintos).
     $activa (opcional): ruta de la pestaña activa, para que siga marcada cuando
     Livewire re-renderiza (en esas peticiones request()->routeIs() ya no vale).
     Estilos propios en un bloque style porque el app.css de Tailwind 2 está compilado y
     purgado (no hay node_modules para regenerarlo). --}}
@php
    // [permiso, ruta, texto, url de la web si se usa desde allí]
    $pestanas = [
        ['contabilidad.procesos', 'contabilidad.procesos', 'Procesos FIQ', null],
        ['contabilidad.facturacionpdf', 'contabilidad.facturacion-pdf', 'Facturación PDF', null],
        ['contabilidad.durcal', 'contabilidad.durcal', 'Durcal', null],
        ['contabilidad.bancos', 'contabilidad.bancos', 'Bancos', config('contabilidad.bancos_url')],
        ['contabilidad.facturasocr', 'contabilidad.facturas-ocr', 'Facturas OCR', null],
        ['contabilidad.is', 'contabilidad.is', 'IS', config('contabilidad.is_url')],
    ];
@endphp
<style>
    .hojas { display:flex; align-items:flex-end; gap:2px; padding:10px 16px 0; background:#e5e7eb; border-bottom:1px solid #9ca3af; flex-wrap:wrap; }
    .hojas a { display:block; white-space:nowrap; padding:7px 18px 6px; font-size:.875rem; font-weight:500;
               color:#4b5563; background:#f3f4f6; border:1px solid #c4c8cf; border-bottom:0; border-radius:6px 6px 0 0; }
    .hojas a:hover { background:#fff; color:#111827; }
    .hojas a.activa { margin-bottom:-1px; padding-bottom:7px; background:#f9fafb; color:#047857; font-weight:700;
                      border-color:#9ca3af; box-shadow:inset 0 3px 0 #059669; }
</style>
<nav class="hojas">
    @foreach ($pestanas as [$permiso, $ruta, $texto, $url])
        @can($permiso)
            <a href="{{ $url ?: route($ruta) }}"
               class="{{ (($activa ?? null) === $ruta || request()->routeIs($ruta)) ? 'activa' : '' }}">{{ $texto }}</a>
        @endcan
    @endforeach
</nav>

{{-- Fuera de los PCs autorizados (VPS) Contabilidad no ejecuta nada: tira de
     OneDrive y no se abre a internet. Marca de agua para que se vea claro. --}}
@php
    $enBancos = ($activa ?? null) === 'contabilidad.bancos' || request()->routeIs('contabilidad.bancos');
    $enIs = ($activa ?? null) === 'contabilidad.is' || request()->routeIs('contabilidad.is');
    // Bancos e IS van por su cuenta: operativos en la web aunque el resto esté bloqueado
    $web = $enBancos ? ['Bancos', 'bancos'] : ($enIs ? ['IS', 'is'] : null);
    $bloqueado = $web ? ! config("contabilidad.{$web[1]}_ejecucion") : ! config('contabilidad.ejecucion_local');
@endphp
@if ($web && ! config("contabilidad.{$web[1]}_ejecucion") && config("contabilidad.{$web[1]}_url"))
    <div class="px-4 py-3 mx-4 mt-3 text-sm font-semibold text-center text-indigo-900 border border-indigo-300 rounded-md bg-indigo-50">
        🌐 {{ $web[0] }} se usa desde la web:
        <a href="{{ config("contabilidad.{$web[1]}_url") }}" class="underline">{{ config("contabilidad.{$web[1]}_url") }}</a>
        (aquí no se ejecuta para no tener dos copias distintas de los datos).
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
