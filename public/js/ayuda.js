/* Ayuda de pantalla (19-nov-2026). Coloca el botón del libro con «?» junto al título de la pantalla (primer h1, o h2) y abre/cierra el modal.
   Las pantallas son componentes Livewire que se repintan: un observador vuelve a poner el botón si desaparece o cambia el título. Sin título, se queda flotante. */
(function () {
    var btn = document.getElementById('ayuda-btn'), dlg = document.getElementById('ayuda-dialog');
    if (!btn || !dlg) return;
    var pendiente = false;
    function titulo() {
        var hs = document.querySelectorAll('main h1, main h2');
        for (var i = 0; i < hs.length; i++) { if (hs[i].offsetParent !== null && hs[i].textContent.trim() !== '') return hs[i]; }
        return null;
    }
    function colocar() {
        pendiente = false;
        var t = titulo();
        if (t) {
            if (btn.parentElement !== t) t.appendChild(btn);
            btn.classList.remove('ayuda-fija');
        } else {
            if (btn.parentElement !== document.body) document.body.appendChild(btn);
            btn.classList.add('ayuda-fija');
        }
    }
    function programar() { if (!pendiente) { pendiente = true; (window.requestAnimationFrame || setTimeout)(colocar); } }
    colocar();
    var main = document.querySelector('main') || document.body;
    new MutationObserver(programar).observe(main, { childList: true, subtree: true });
    btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); });
    document.getElementById('ayuda-cerrar').addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });     // clic fuera (en el fondo oscuro)
})();
