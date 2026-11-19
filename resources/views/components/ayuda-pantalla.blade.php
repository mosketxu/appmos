@props(['ruta' => null])
@php $ayuda = \App\Support\Ayuda::para($ruta ?? request()->route()?->getName()); @endphp
@if ($ayuda)
    {{-- Ayuda de la pantalla (19-nov-2026): icono de libro con «?» junto al título (lo coloca public/js/ayuda.js) y un modal nativo con las instrucciones (config/ayuda.php). --}}
    <button type="button" id="ayuda-btn" class="ayuda-btn ayuda-fija" title="Instrucciones de esta pantalla" aria-label="Instrucciones de esta pantalla" aria-haspopup="dialog">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v14.5H6.5A1.5 1.5 0 0 0 5 19V4.5z"/>
            <path d="M5 19a1.5 1.5 0 0 0 1.5 1.5H19v-3"/>
            <path d="M8.6 3v14.5"/>
            <text x="14" y="13.4" text-anchor="middle" font-size="9.5" font-weight="700" fill="currentColor" stroke="none" font-family="Arial, sans-serif">?</text>
        </svg>
    </button>
    <dialog id="ayuda-dialog" class="ayuda-dialog" aria-labelledby="ayuda-titulo">
        <div class="ayuda-cab">
            <h3 id="ayuda-titulo">📖 {{ $ayuda['titulo'] }}</h3>
            <button type="button" class="ayuda-cerrar" id="ayuda-cerrar" aria-label="Cerrar">✕</button>
        </div>
        <div class="ayuda-cuerpo">{!! $ayuda['html'] !!}</div>
    </dialog>
    <link rel="stylesheet" href="{{ asset('css/ayuda.css') }}?v={{ filemtime(public_path('css/ayuda.css')) }}">
    <script src="{{ asset('js/ayuda.js') }}?v={{ filemtime(public_path('js/ayuda.js')) }}" defer></script>
@endif
