/* Confirmaciones y avisos con modal propio en vez de los cuadros del navegador (19-nov-2026, pedido de Alex).
   - window.preguntar(texto, {si, no}) → Promise<boolean>: para JS/Alpine:  if (!(await preguntar('¿…?'))) return;
   - wire:confirm="…" de Livewire pasa solo por aquí (el clic se para y sigue al aceptar).
   - window.alert(texto) también sale como modal (no bloquea; el código siguiente continúa, como ya pasaba con los avisos de la pantalla). */
(function () {
  function modal(texto, opciones) {
    opciones = opciones || {};
    return new Promise(function (resolver) {
      var antes = document.activeElement;
      var fondo = document.createElement('div');
      fondo.style.cssText = 'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(17,24,39,.5)';
      var caja = document.createElement('div');
      caja.setAttribute('role', 'dialog');
      caja.setAttribute('aria-modal', 'true');
      caja.style.cssText = 'width:100%;max-width:32rem;background:#fff;border-radius:8px;box-shadow:0 20px 25px -5px rgba(0,0,0,.25);padding:20px;font-family:inherit';
      var p = document.createElement('div');
      p.style.cssText = 'white-space:pre-wrap;word-break:break-word;font-size:14px;line-height:1.45;color:#374151;max-height:60vh;overflow:auto';
      p.textContent = String(texto == null ? '' : texto);
      var fila = document.createElement('div');
      fila.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;margin-top:16px;flex-wrap:wrap';
      function boton(txt, primario) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = txt;
        b.style.cssText = 'padding:6px 14px;font-size:14px;border-radius:6px;cursor:pointer;' + (primario
          ? 'background:#15803d;color:#fff;border:1px solid #15803d;font-weight:600'
          : 'background:#fff;color:#374151;border:1px solid #d1d5db');
        return b;
      }
      function cerrar(r) {
        document.removeEventListener('keydown', tecla, true);
        fondo.remove();
        try { if (antes && antes.focus) antes.focus(); } catch (e) {}
        resolver(r);
      }
      function tecla(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); cerrar(false); }
      }
      var ok = boton(opciones.si || (opciones.soloAviso ? 'Aceptar' : 'Aceptar'), true);
      ok.addEventListener('click', function () { cerrar(true); });
      if (!opciones.soloAviso) {
        var no = boton(opciones.no || 'Cancelar', false);
        no.addEventListener('click', function () { cerrar(false); });
        fila.appendChild(no);
      }
      fila.appendChild(ok);
      caja.appendChild(p);
      caja.appendChild(fila);
      fondo.appendChild(caja);
      document.body.appendChild(fondo);
      document.addEventListener('keydown', tecla, true);
      ok.focus();
    });
  }

  window.confirmNativo = window.confirm.bind(window);
  window.preguntar = function (texto, opciones) { return modal(texto, opciones); };
  window.alert = function (texto) { modal(texto, { soloAviso: true }); };

  // wire:confirm: Livewire guarda en el elemento __livewire_confirm(accion, parar); se sustituye por una versión con modal.
  var guardado = new WeakMap();
  Object.defineProperty(HTMLElement.prototype, '__livewire_confirm', {
    configurable: true,
    set: function (f) { guardado.set(this, f); },
    get: function () {
      var original = guardado.get(this);
      if (!original) return undefined;
      var el = this;
      var attr = Array.prototype.slice.call(el.attributes).map(function (a) { return a.name; })
        .filter(function (n) { return n.indexOf('wire:confirm') === 0; })[0];
      if (!attr || attr.indexOf('.prompt') !== -1) return original;   // wire:confirm.prompt (pide escribir algo) se deja como estaba
      return function (accion, parar) {
        var texto = (el.getAttribute(attr) || '').replace(/\\n/g, '\n') || '¿Estás seguro?';
        parar();   // frena este clic ahora…
        modal(texto).then(function (si) { if (si) accion(); });   // …y lo ejecuta si se acepta
      };
    }
  });
})();
