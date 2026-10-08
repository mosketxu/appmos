@props(['target', 'titulo' => 'Pensando… procesando', 'nota' => 'Puede tardar un minuto. No cierres la página.'])
{{-- Indicador central mientras el servidor trabaja: fondo oscurecido (no en blanco) y un recuadro con círculo girando.
     Estilos en línea para que valga en cualquier pantalla de Contabilidad. Uso: <x-contabilidad.procesando target="accion1, accion2" /> --}}
<div data-procesando wire:loading.flex wire:target="{{ $target }}" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(17,24,39,.4);align-items:center;justify-content:center">
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;padding:1.5rem 2rem;background:#fff;border-radius:.75rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.4)">
        <svg style="width:3.5rem;height:3.5rem;color:#4f46e5;animation:procesando-giro 1s linear infinite" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <div style="font-size:1rem;font-weight:600;color:#1f2937">{{ $titulo }}</div>
        <div style="font-size:.75rem;color:#6b7280">{{ $nota }}</div>
        <style>@keyframes procesando-giro{to{transform:rotate(360deg)}}</style>
    </div>
</div>
