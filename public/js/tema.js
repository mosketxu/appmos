/* Tema de Appmos: 'auto' (el del sistema, por defecto), 'claro' u 'oscuro'. Se guarda en este navegador. Se carga en <head>, antes de pintar. */
(function () {
    var CLAVE = 'appmos-tema';
    function leer() { try { return localStorage.getItem(CLAVE) || 'auto'; } catch (e) { return 'auto'; } }
    function aplicar() {
        var t = leer();
        var oscuro = t === 'oscuro' || (t === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', oscuro);
    }
    window.appmosTema = {
        get: leer,
        set: function (t) { try { localStorage.setItem(CLAVE, t); } catch (e) {} aplicar(); },
    };
    aplicar();
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        (mq.addEventListener ? mq.addEventListener.bind(mq, 'change') : mq.addListener.bind(mq))(aplicar);
    }
})();
