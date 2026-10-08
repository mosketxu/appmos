{{-- Indicador de «pensando» para TODA la aplicación: cualquier acción de Livewire (clic en un botón, etc.) que tarde más de ~0,8 s
     oscurece la pantalla y muestra el círculo central. No salen los refrescos automáticos (wire:poll), ni teclear, ni las acciones rápidas.
     Si la pantalla ya tiene su propio recuadro (x-contabilidad.procesando, con texto propio) no se duplica. --}}
<div id="procesando-global" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(17,24,39,.4);align-items:center;justify-content:center">
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;padding:1.5rem 2rem;background:#fff;border-radius:.75rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.4)">
        <svg style="width:3.5rem;height:3.5rem;color:#4f46e5;animation:procesando-giro 1s linear infinite" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <div id="procesando-global-titulo" style="font-size:1rem;font-weight:600;color:#1f2937">Pensando… procesando</div>
        <div id="procesando-global-nota" style="font-size:.75rem;color:#6b7280">Puede tardar un momento. No cierres la página.</div>
        <style>@keyframes procesando-giro{to{transform:rotate(360deg)}}</style>
    </div>
</div>
<script>
    document.addEventListener('livewire:init', () => {
        const caja = document.getElementById('procesando-global');
        if (! caja || ! window.Livewire) return;
        const SILENCIOSAS = ['$refresh', '$set', '$commit', '$dispatch'];
        let activas = 0, temporizador = null;
        const propio = () => [...document.querySelectorAll('[data-procesando]')].some(e => getComputedStyle(e).display !== 'none');
        const terminar = () => {
            activas = Math.max(0, activas - 1);
            if (activas === 0) { clearTimeout(temporizador); temporizador = null; caja.style.display = 'none'; }
        };
        Livewire.hook('commit', ({ commit, succeed, fail }) => {
            const llamadas = (commit && commit.calls) || [];
            const accion = llamadas.some(c => ! SILENCIOSAS.includes(c.method) && ! String(c.method).startsWith('__'));
            const cambios = commit && commit.updates && Object.keys(commit.updates).length > 0;   // p.ej. un combo con wire:model.live
            if (! accion && ! cambios) return;
            activas++;
            if (! temporizador) {
                temporizador = setTimeout(() => { if (activas > 0 && ! propio()) { document.getElementById('procesando-global-titulo').textContent = 'Pensando… procesando'; document.getElementById('procesando-global-nota').textContent = 'Puede tardar un momento. No cierres la página.'; caja.style.display = 'flex'; } }, 800);
            }
            succeed(terminar);
            fail(terminar);
        });
    });

    /* Cambio de pantalla (enlace normal, no Livewire): si el servidor tarda en responder más de 0,7 s, el recuadro con aviso breve en vez de
       dejar la página parada sin decir nada. Se quita al cargar la nueva (o al volver con «atrás»). */
    (function () {
        const caja = document.getElementById('procesando-global');
        if (! caja) return;
        let t = null;
        window.addEventListener('pageshow', () => { clearTimeout(t); caja.style.display = 'none'; });
        document.addEventListener('click', e => {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            const a = e.target.closest && e.target.closest('a[href]');
            if (! a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('wire:click') || a.getAttribute('href').startsWith('#')) return;
            const u = new URL(a.href, location.href);
            if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search)) return;
            const sitio = u.pathname.replace(/^\/(contabilidad\/)?/, '').split('/')[0] || 'la pantalla';
            clearTimeout(t);
            t = setTimeout(() => {
                document.getElementById('procesando-global-titulo').textContent = 'Abriendo ' + sitio.replace(/-/g, ' ') + '…';
                document.getElementById('procesando-global-nota').textContent = 'Un momento, el servidor está preparando la pantalla.';
                caja.style.display = 'flex';
            }, 700);
        });
    })();
</script>
