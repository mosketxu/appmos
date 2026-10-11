{{-- Botón 🎤 de dictado (Web Speech API; en el móvil además va el micrófono del teclado). Escribe en el textarea/input hermano. Se oculta si el navegador no lo soporta. --}}
<button type="button" wire:ignore title="Dictar" aria-label="Dictar"
    x-data="{ on: false, rec: null, ok: !! (window.SpeechRecognition || window.webkitSpeechRecognition) }" x-show="ok" style="display:none"
    x-on:click="
        if (on) { rec.stop(); return; }
        const campo = $el.parentElement.querySelector('textarea, input[type=text]');
        const R = window.SpeechRecognition || window.webkitSpeechRecognition;
        rec = new R(); rec.lang = 'es-ES'; rec.continuous = false; rec.interimResults = false;
        rec.onresult = (e) => {
            let t = ''; for (let i = e.resultIndex; i < e.results.length; i++) { if (e.results[i].isFinal) t += e.results[i][0].transcript; }
            t = t.trim(); if (! t) return;
            campo.value = (campo.value ? campo.value.replace(/\s*$/, ' ') : '') + t;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        };
        rec.onend = () => { on = false; }; rec.onerror = () => { on = false; };
        rec.start(); on = true; campo.focus();"
    x-bind:style="'position:absolute; right:.4rem; bottom:.4rem; width:2.4rem; height:2.4rem; border-radius:9999px; font-size:1.2rem; line-height:1; border:1px solid #c7d2fe; background:' + (on ? '#dc2626' : '#eef2ff')">
    <span x-text="on ? '⏹' : '🎤'"></span>
</button>
