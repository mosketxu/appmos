{{-- Pestañas de IMPUESTOS (9-oct-2026): una subpestaña por impuesto, mismo aspecto de hoja de Excel que Contabilidad y el TO-DO.
     $activa: 'seguimiento' | 'iva' | 'm111' | 'm349' | 'm216' | 'is' | 'pago202' | 'intrastat'. Para añadir un impuesto nuevo: una fila en $pestanas (y su ruta). Estilos propios (el app.css de Tailwind está compilado y purgado). --}}
@php
    // [permiso, ruta, texto, clave de $activa]
    $pestanas = [
        ['proceso.imp.seguimiento', 'impuestos', 'Seguimiento de impuestos', 'seguimiento'],
        ['proceso.imp.iva', 'impuestos.libro-iva', 'IVA M303', 'iva'],
        ['proceso.imp.m111', 'impuestos.m111', 'IRPF M111', 'm111'],
        ['proceso.is.m200', 'contabilidad.is', 'IS M200', 'is'],
        ['proceso.is.m202', 'impuestos.pago-cuenta', 'Pago Cuenta M202', 'pago202'],
        ['proceso.imp.m349', 'impuestos.m349', 'M349', 'm349'],
        ['proceso.imp.m216', 'impuestos.m216', 'M216', 'm216'],
        ['proceso.imp.intrastat', 'impuestos.intrastat', 'Intrastat', 'intrastat'],
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
    @foreach ($pestanas as [$permiso, $ruta, $texto, $clave])
        @can($permiso)
            <a href="{{ route($ruta) }}" class="{{ ($activa ?? '') === $clave ? 'activa' : '' }}">{{ $texto }}</a>
        @endcan
    @endforeach
</nav>
