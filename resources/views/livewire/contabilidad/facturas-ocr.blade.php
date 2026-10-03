<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    {{-- Estilos propios: el app.css de Tailwind 2 está compilado y purgado --}}
    <style>
        .focr-card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; box-shadow:0 1px 2px rgba(0,0,0,.05); }
        .focr-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:.75rem 1rem; }
        .focr-lbl { display:block; font-size:.75rem; font-weight:600; color:#4b5563; margin-bottom:.15rem; }
        .focr-in { width:100%; font-size:.875rem; border:1px solid #d1d5db; border-radius:.375rem; padding:.3rem .5rem; background:#fff; }
        .focr-in.ok { border-color:#34d399; background:#f0fdf4; }
        .focr-in.revisar { border-color:#f59e0b; background:#fffbeb; }
        .focr-in.falta { border-color:#ef4444; background:#fef2f2; }
        .focr-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.4rem .8rem; border-radius:.375rem; font-size:.875rem; font-weight:600; border:1px solid transparent; cursor:pointer; }
        .focr-btn:disabled { opacity:.5; cursor:not-allowed; }
        .b-indigo { background:#4f46e5; color:#fff; } .b-indigo:hover { background:#4338ca; }
        .b-verde { background:#059669; color:#fff; } .b-verde:hover { background:#047857; }
        .b-rojo { background:#fff; color:#b91c1c; border-color:#fca5a5; } .b-rojo:hover { background:#fef2f2; }
        .b-gris { background:#fff; color:#374151; border-color:#d1d5db; } .b-gris:hover { background:#f9fafb; }
        .focr-tabla { width:100%; font-size:.8125rem; border-collapse:collapse; }
        .focr-tabla th { text-align:left; font-weight:600; color:#6b7280; background:#f9fafb; padding:.4rem .5rem; border-bottom:1px solid #e5e7eb; position:sticky; top:0; }
        .focr-tabla td { padding:.35rem .5rem; border-bottom:1px solid #f3f4f6; vertical-align:top; }
        .focr-tabla tr.clic:hover td { background:#eef2ff; cursor:pointer; }
        .focr-chip { display:inline-block; padding:0 .4rem; border-radius:9999px; font-size:.7rem; font-weight:600; line-height:1.3rem; }
        .c-ok { background:#d1fae5; color:#065f46; } .c-revisar { background:#fef3c7; color:#92400e; } .c-falta { background:#fee2e2; color:#991b1b; }
        .c-gris { background:#f3f4f6; color:#4b5563; }
        .focr-tabs { display:flex; gap:2px; border-bottom:1px solid #d1d5db; }
        .focr-tabs button { padding:.45rem 1rem; font-size:.875rem; font-weight:600; color:#6b7280; border:1px solid transparent; border-bottom:0; border-radius:.375rem .375rem 0 0; }
        .focr-tabs button.on { background:#fff; color:#047857; border-color:#d1d5db; margin-bottom:-1px; box-shadow:inset 0 3px 0 #059669; }
        /* Revisión a pantalla completa: PDF lo más grande posible y los datos a la derecha */
        .focr-rev { position:fixed; inset:0; z-index:60; background:#111827; display:flex; flex-direction:column; }
        .focr-rev-bar { display:flex; align-items:center; gap:.6rem; padding:.4rem .75rem; background:#1f2937; color:#e5e7eb; font-size:.85rem; }
        .focr-rev-body { flex:1; display:flex; min-height:0; }
        .focr-rev-pdf { flex:1; min-width:0; background:#374151; }
        .focr-rev-pdf { display:flex; flex-direction:column; }
        .focr-pdfbar { display:flex; align-items:center; gap:.35rem; padding:.3rem .5rem; background:#374151; color:#e5e7eb; font-size:.8rem; }
        .focr-pdfbar button { background:#4b5563; color:#fff; border-radius:.25rem; padding:.1rem .5rem; }
        .focr-pdfbar button:hover { background:#6b7280; }
        .focr-pags { flex:1; overflow:auto; padding:.75rem; }
        .focr-pag { position:relative; margin:0 auto .75rem; box-shadow:0 2px 8px rgba(0,0,0,.5); background:#fff; }
        .focr-pag canvas { display:block; }
        .focr-rev-form .focr-in { padding:.15rem .4rem; font-size:.8rem; }
        .focr-rev-form .focr-lbl { font-size:.66rem; margin-bottom:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .focr-rev-form .g4 { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.3rem .45rem; }
        .focr-avisos { font-size:.72rem; line-height:1.25; max-height:4.6rem; overflow-y:auto; padding:.3rem .5rem; margin-bottom:.4rem; border:1px solid #fcd34d; border-radius:.3rem; background:#fffbeb; color:#78350f; }
        .focr-imp { width:100%; margin-top:.3rem; border-collapse:separate; border-spacing:.35rem .15rem; font-size:.72rem; }
        .focr-imp th { font-size:.66rem; font-weight:600; color:#4b5563; text-align:left; }
        .focr-imp td:first-child { color:#6b7280; width:1.6rem; }
        .focr-imp td:last-child { width:3.4rem; }
        .focr-imp .focr-in { text-align:right; }
        .focr-nocuadra { margin-bottom:.45rem; padding:.45rem .6rem; border:3px solid #dc2626; border-radius:.4rem; background:#fee2e2; color:#7f1d1d; font-weight:700; font-size:.8rem; line-height:1.35; animation:focr-parpadeo 1s ease-in-out 3; }
        @keyframes focr-parpadeo { 50% { background:#fca5a5; } }
        .focr-in.mal { border:2px solid #dc2626 !important; background:#fee2e2 !important; }
        .focr-busc { position:relative; }
        .focr-flecha { position:absolute; right:.3rem; top:.35rem; color:#6b7280; font-size:.8rem; }
        .focr-lista { position:absolute; z-index:5; left:0; right:0; top:100%; max-height:300px; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:.375rem; box-shadow:0 6px 16px rgba(0,0,0,.15); font-size:.8rem; }
        .focr-lista div { padding:.25rem .5rem; cursor:pointer; }
        .focr-lista div.on, .focr-lista div:hover { background:#eef2ff; }
        .textLayer { position:absolute; inset:0; overflow:hidden; line-height:1; text-align:initial; }
        .textLayer span, .textLayer br { color:transparent; position:absolute; white-space:pre; cursor:text; transform-origin:0% 0%; }
        .textLayer ::selection { background:rgba(59,130,246,.35); }
        .focr-caja { position:absolute; border:2px solid #f59e0b; background:rgba(245,158,11,.15); pointer-events:none; }
        .focr-rev-form { flex:0 0 45%; min-width:420px; background:#f9fafb; overflow-y:auto; padding:.75rem; border-left:1px solid #374151; }
        /* Pantallas anchas (apaisadas): mitad y mitad; en las normales 55/45 */
        @media (min-width:1600px) { .focr-rev-form { flex-basis:50%; } }
        .focr-rev-form .fila { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.5rem; }
        .focr-rev-form .fila3 { display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:.35rem; margin-bottom:.35rem; align-items:end; }
        .focr-sec { font-size:.7rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#6b7280; margin:.75rem 0 .35rem; }
    </style>

    <div class="fixed flex flex-col gap-2 top-4 right-4" style="z-index:70; width:24rem; max-width:calc(100vw - 2rem)">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 p-3 bg-white border border-gray-300 rounded-lg shadow-lg">
                <pre class="flex-1 font-sans text-sm text-gray-800 whitespace-pre-wrap" x-text="aviso.mensaje"></pre>
                <button type="button" class="text-lg leading-none text-gray-400 hover:text-gray-700"
                        x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)">&times;</button>
            </div>
        </template>
    </div>

    <script>
        // Visor de PDF propio (PDF.js) para poder recordar el zoom entre facturas y recuadrar datos
        // Buscador de cuenta (proveedor / contrapartida): la lista se pide una vez a Livewire y se filtra aquí
        window.focrListas = window.focrListas || {};
        window.addEventListener('focr-listas', () => { window.focrListas = {}; });
        window.buscador = function (lista, campo, libre) {
            const norm = (t) => String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
            return {
                lista: [], q: '', open: false, i: 0,
                async init() {
                    const clave = lista + ':' + this.$wire.cliente;
                    if (!window.focrListas[clave]) window.focrListas[clave] = await this.$wire.lista(lista);
                    this.lista = window.focrListas[clave];
                    this.mostrar();
                    this.$wire.$watch('form', () => { if (!this.open) this.mostrar(); });
                },
                valor() { return (this.$wire.form || {})[campo] || ''; },
                etiqueta(v) { const r = this.lista.find((x) => x[0] === v); return v ? (r ? v + ' · ' + r[1] : v) : ''; },
                mostrar() { this.q = this.etiqueta(this.valor()); },
                get res() {
                    const q = norm(this.q);
                    if (this.lista.length === 1 && !this.lista[0][0]) return this.lista;   // mensaje de error
                    if (!q || q === norm(this.etiqueta(this.valor()))) return this.lista;
                    const ps = q.split(/\s+/).filter(Boolean);
                    return this.lista.filter((r) => { const t = norm(r[0] + ' ' + r[1]); return ps.every((p) => t.includes(p)); });
                },
                abrir() { this.open = true; this.i = 0; },
                alternar() { if (this.open) return this.cerrar(); this.$refs.q.focus(); },
                cerrar() { this.open = false; this.mostrar(); },
                salir() { setTimeout(() => { if (this.open) this.cerrar(); }, 150); },
                mover(d) { this.open = true; this.i = Math.max(0, Math.min(this.res.length - 1, this.i + d)); },
                elegir(r) { if (!r[0]) return; this.open = false; this.$wire.set('form.' + campo, r[0]); this.q = this.etiqueta(r[0]); },
                intro() {
                    const r = this.res[this.i];
                    if (this.open && r) return this.elegir(r);
                    const v = this.q.trim().split(/[\s·]/)[0];
                    if (/^\d{3,}$/.test(v)) { this.open = false; this.$wire.set('form.' + campo, v); }
                },
            };
        };

        window.visorPdf = function (url, miniatura, siguiente) {
            let pdf = null;   // fuera del objeto de Alpine: su proxy rompe los campos privados de PDF.js
            return {
                url, zoom: 'ancho', escala: 1, modo: null,
                init() {
                    try { this.zoom = localStorage.getItem('focr-zoom') || 'ancho'; } catch (e) {}
                    this.$nextTick(() => this.previa());   // $refs aún no está en init()
                    this.lib()
                        .then(() => pdfjsLib.getDocument({ url: this.url, disableRange: true, disableStream: true }).promise)
                        .then((d) => { pdf = d; return this.pintar(); })
                        .then(() => {
                            // Mientras se revisa esta, se precarga la siguiente (PDF y miniatura quedan en la caché)
                            (siguiente || []).forEach((u) => fetch(u, { credentials: 'same-origin' }).catch(() => {}));
                        });
                },
                // Miniatura de la 1ª página al instante, hasta que PDF.js pinta el PDF de verdad
                previa() {
                    if (!miniatura) return;
                    const cont = this.$refs.pags, img = new Image();
                    img.onload = () => {
                        if (pdf || !cont || !cont.isConnected) return;
                        const anchoPt = img.naturalWidth * 72 / 100;
                        const w = this.zoom === 'ancho' ? cont.clientWidth - 28 : anchoPt * parseFloat(this.zoom);
                        img.style.cssText = 'display:block; margin:0 auto; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.5); width:' + w + 'px';
                        cont.innerHTML = '';
                        cont.appendChild(img);
                    };
                    img.src = miniatura;
                },
                lib() {
                    if (window.pdfjsLib) return Promise.resolve();
                    return new Promise((ok, ko) => {
                        const s = document.createElement('script');
                        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                        s.onload = () => {
                            // El worker viene de otro dominio (cdnjs): se arranca desde un blob que lo importa
                            const w = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                            pdfjsLib.GlobalWorkerOptions.workerPort = new Worker(URL.createObjectURL(new Blob([`importScripts('${w}');`], { type: 'text/javascript' })));
                            ok();
                        };
                        s.onerror = ko;
                        document.head.appendChild(s);
                    });
                },
                get etiqueta() { return this.zoom === 'ancho' ? 'Ancho' : Math.round(parseFloat(this.zoom) * 100) + ' %'; },
                async pintar() {
                    const cont = this.$refs.pags, dpr = window.devicePixelRatio || 1;
                    if (!pdf || !cont || !cont.isConnected) return;   // ya se ha pasado a otra factura
                    const ancho = cont.clientWidth - 28;
                    cont.innerHTML = '';
                    for (let n = 1; n <= pdf.numPages; n++) {
                        const pg = await pdf.getPage(n);
                        const v1 = pg.getViewport({ scale: 1 });
                        const esc = this.zoom === 'ancho' ? ancho / v1.width : parseFloat(this.zoom);
                        this.escala = esc;
                        const vp = pg.getViewport({ scale: esc * dpr });
                        const caja = document.createElement('div');
                        caja.className = 'focr-pag';
                        caja.style.width = (vp.width / dpr) + 'px';
                        caja.style.height = (vp.height / dpr) + 'px';
                        const c = document.createElement('canvas');
                        c.width = vp.width; c.height = vp.height;
                        c.style.width = (vp.width / dpr) + 'px'; c.style.height = (vp.height / dpr) + 'px';
                        caja.appendChild(c);
                        this.recuadrar(caja, n);
                        cont.appendChild(caja);
                        await pg.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
                        // Capa de texto invisible encima: se puede seleccionar, doble clic y copiar
                        const capa = document.createElement('div');
                        capa.className = 'textLayer';
                        capa.style.setProperty('--scale-factor', esc);
                        caja.appendChild(capa);
                        pdfjsLib.renderTextLayer({ textContentSource: await pg.getTextContent(), container: capa,
                            viewport: pg.getViewport({ scale: esc }), textDivs: [] });
                    }
                },
                guardar() { try { localStorage.setItem('focr-zoom', this.zoom); } catch (e) {} },
                mas(d) {
                    let z = this.zoom === 'ancho' ? this.escala : parseFloat(this.zoom);
                    z = Math.min(5, Math.max(0.3, Math.round((z * (d > 0 ? 1.15 : 1 / 1.15)) * 100) / 100));
                    this.zoom = String(z); this.guardar(); this.pintar();
                },
                ajustar() { this.zoom = 'ancho'; this.guardar(); this.pintar(); },
                // Arrastrar un recuadro sobre la página: se manda en fracciones de la página a leerZona()
                recuadrar(caja, pagina) {
                    let ini = null, marco = null;
                    const pos = (e) => { const r = caja.getBoundingClientRect(); return [(e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height]; };
                    caja.addEventListener('mousedown', (e) => {
                        if (!this.modo) return;
                        e.preventDefault();
                        ini = pos(e);
                        marco = document.createElement('div'); marco.className = 'focr-caja'; caja.appendChild(marco);
                    });
                    caja.addEventListener('mousemove', (e) => {
                        if (!ini) return;
                        const p = pos(e);
                        Object.assign(marco.style, {
                            left: Math.min(ini[0], p[0]) * 100 + '%', top: Math.min(ini[1], p[1]) * 100 + '%',
                            width: Math.abs(p[0] - ini[0]) * 100 + '%', height: Math.abs(p[1] - ini[1]) * 100 + '%',
                        });
                    });
                    const fin = (e) => {
                        if (!ini) return;
                        const p = pos(e), a = ini, campo = this.modo;
                        ini = null; this.modo = null;
                        setTimeout(() => marco && marco.remove(), 1500);
                        if (Math.abs(p[0] - a[0]) < 0.005 || Math.abs(p[1] - a[1]) < 0.003) return;
                        this.$wire.leerZona(campo, pagina, a[0], a[1], p[0], p[1]);
                    };
                    caja.addEventListener('mouseup', fin);
                    caja.addEventListener('mouseleave', fin);
                },
            };
        };
    </script>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.facturas-ocr'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.facturas-ocr'])

    @php
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
        $eur = fn ($v) => ($v === null || $v === '') ? '' : number_format((float) $v, 2, ',', '.');
        $chip = fn ($e) => ['ok' => 'c-ok', 'revisar' => 'c-revisar', 'falta' => 'c-falta', 'sin_iva' => 'c-revisar'][$e] ?? 'c-gris';
        // Resumen de importes para las tablas: base, % (o "varios"), IVA
        $importes = function ($d) {
            $ls = array_values(array_filter($d['lineas'] ?? [], fn ($l) => ($l['base'] ?? '') !== '' && $l['base'] !== null));
            $pcts = array_values(array_unique(array_map(fn ($l) => rtrim(rtrim(number_format((float) ($l['pct'] ?? 0), 2, ',', ''), '0'), ','), $ls)));
            return [
                'base' => $ls ? array_sum(array_map(fn ($l) => (float) $l['base'], $ls)) : null,
                'iva' => $ls ? array_sum(array_map(fn ($l) => (float) ($l['cuota'] ?? 0), $ls)) : null,
                'pct' => count($pcts) > 1 ? 'varios' : ($pcts[0] ?? ''),
                'detalle' => implode(' · ', array_map(fn ($l) => number_format((float) $l['base'], 2, ',', '.').' al '.$l['pct'].' % = '.number_format((float) ($l['cuota'] ?? 0), 2, ',', '.'), $ls)),
            ];
        };
        $etiqEstado = ['pendiente' => ['⏳', 'Pendiente', 'c-gris'], 'rechazada' => ['✖', 'Rechazada', 'c-falta'], 'ilegible' => ['👁', 'No legible', 'c-falta'], 'validada' => ['✔', 'Validada', 'c-ok']];
    @endphp

    <div class="p-4 space-y-4">
        <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3 gap-y-2">
            <span>Facturas OCR — recibidas —</span>
            <select wire:model.live="cliente" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
                @forelse ($clientes as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @empty
                    <option value="">(no hay clientes)</option>
                @endforelse
            </select>
            @if ($entidad)
                <span class="text-sm font-normal text-gray-500">{{ $entidad->entidad }} · {{ $entidad->nif }}</span>
            @endif
        </h1>

        @if ($otroPc)
            <div class="focr-nocuadra" style="animation:none; border-color:#f59e0b; background:#fffbeb; color:#78350f">
                ⚠️ {{ $otroPc['pc'] }} ha tocado estas facturas hace {{ max(1, (int) round((time() - strtotime($otroPc['fecha'])) / 60)) }} min.
                <span style="font-weight:400">Si acabas de cambiar de PC, espera a que OneDrive termine de sincronizar y recarga la página.</span>
            </div>
        @endif
        @foreach ($fallidas as $f)
            <div class="focr-nocuadra" style="animation:none">
                ⚠️ {{ basename($f['ruta']) }} ({{ $f['datos']['proveedor'] ?? '' }} {{ $f['datos']['su_factura'] ?? '' }}): {{ $f['error_validar'] }}
                <span style="font-weight:400">Ha vuelto a pendientes con tus datos; corrígelo y valida otra vez.</span>
            </div>
        @endforeach
        @if ($conflictos)
            <div class="focr-nocuadra" style="animation:none">
                ⚠️ OneDrive ha creado copias en conflicto ({{ implode(', ', $conflictos) }}): se ha trabajado a la vez en dos PCs.
                <span style="font-weight:400">Avísame antes de seguir para juntarlas.</span>
            </div>
        @endif
        @if ($cliente !== '')
            {{-- 1. Parámetros y lectura de la carpeta --}}
            <div class="p-4 focr-card">
                <div class="focr-grid">
                    <div style="grid-column:1/-1">
                    @if ($web)
                        {{-- Web (VPS): las facturas se suben SIEMPRE aquí (no dependen de OneDrive). El navegador calcula la
                             huella SHA-1 de cada PDF y solo sube las que el servidor no conoce; al llegar se leen solas. --}}
                        <label class="focr-lbl">Facturas (PDF): arrástralas aquí o haz clic. Se suben al servidor y se leen solas.</label>
                        <div x-data="focrSubida()" wire:key="subida">
                            <input type="file" multiple accept="application/pdf,.pdf" x-ref="f" style="display:none" x-on:change="elegir($event.target.files); $event.target.value = ''">
                            <div x-on:click="$refs.f.click()" x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; elegir($event.dataTransfer.files)"
                                 :style="encima ? 'background:#eef2ff;border-color:#6366f1' : ''"
                                 style="border:2px dashed #cbd5e1; border-radius:.5rem; padding:1.1rem; text-align:center; cursor:pointer; color:#475569">
                                📥 Arrastra aquí los PDF de las facturas o haz clic para elegirlos
                            </div>
                            <template x-if="archivos.length">
                                <div class="mt-2 text-xs" style="max-height:11rem; overflow:auto">
                                    <template x-for="a in archivos" :key="a.clave">
                                        <div style="display:flex; gap:.5rem; align-items:center; padding:.1rem 0">
                                            <span style="min-width:7.5rem" x-text="a.estado === 'huella' ? '🔎 comprobando…' : a.estado === 'ya' ? '✔ ya la tengo' : a.estado === 'subiendo' ? '⏫ subiendo ' + a.pct + ' %' : a.estado === 'subida' ? '✅ en el servidor' : '⚠️ error'"></span>
                                            <span x-text="a.nombre" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"></span>
                                            <span style="color:#64748b" x-text="a.texto || ''"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        @php
                            $nLeidas = collect($entrada)->filter(fn ($e) => ! in_array($e[1], ['en el servidor', 'leyendo'], true))->count();
                        @endphp
                        <div class="mt-2 text-xs text-gray-600" x-data="{ t0: {{ (int) $lecturaDesde }}, ahora: Math.floor(Date.now() / 1000) }" x-init="setInterval(() => ahora = Math.floor(Date.now() / 1000), 1000)">
                            {{ count($entrada) }} PDF en la carpeta de entrada · {{ $nLeidas }} leídos
                            @if ($lecturaDesde)
                                · <b style="color:#b45309">⏳ leyendo… <span x-text="Math.max(0, ahora - t0) + ' s'"></span></b> (no hace falta esperar: puedes seguir con otras)
                            @endif
                        </div>
                        @if ($leyendo)
                            <div wire:poll.3s="revisarLectura"></div>
                        @endif
                        {{-- Archivo: lo contabilizado se lleva al OneDrive de un PC, comprobando huellas --}}
                        <div class="mt-2 text-xs text-gray-600" style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap">
                            <span>📦 Archivo en OneDrive del PC:
                                @if ($sync)
                                    último envío {{ $sync['fecha'] }} ({{ $sync['pc'] }}) · {{ $sync['total'] }} ficheros comprobados, {{ $sync['nuevos'] }} nuevos
                                    @if (! empty($sync['conflictos'])) · <b style="color:#b45309">⚠ {{ count($sync['conflictos']) }} cambiados en el PC (el del servidor queda como «.vps»)</b>@endif
                                    @if (! empty($sync['fallidos'])) · <b style="color:#b91c1c">❌ {{ count($sync['fallidos']) }} sin llegar bien: {{ implode(', ', array_slice($sync['fallidos'], 0, 3)) }}</b>@endif
                                @else
                                    todavía no se ha enviado
                                @endif
                            </span>
                            <button type="button" wire:click="enviarAlPc" wire:loading.attr="disabled" class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.75rem" @disabled($sincronizando)>
                                {{ $sincronizando ? '⏳ enviando al PC…' : '↻ Enviar ahora al PC' }}
                            </button>
                            @if ($sincronizando)
                                <span wire:poll.3s="revisarSync"></span>
                            @endif
                        </div>
                    @else
                        <label class="focr-lbl">Carpeta con las facturas (solo los PDF de esa carpeta, sin subcarpetas)</label>
                        <div class="flex gap-2" style="align-items:center">
                            <input type="text" wire:model.live.debounce.500ms="carpeta" class="focr-in" style="font-family:monospace">
                            <button type="button" wire:click="elegirCarpeta" wire:loading.attr="disabled" class="focr-btn b-gris" style="white-space:nowrap">
                                <span wire:loading.remove wire:target="elegirCarpeta">📁 Elegir carpeta…</span>
                                <span wire:loading wire:target="elegirCarpeta">Elige la carpeta en la ventana de Windows…</span>
                            </button>
                        </div>
                        @error('carpeta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        <div class="mt-1 text-xs text-gray-500">{{ $pdfs }} PDF en la carpeta.</div>
                    @endif
                    </div>
                    <div>
                        <label class="focr-lbl">IVA del cliente</label>
                        <select wire:model.live="ciclo" class="focr-in {{ $ciclo === '' ? 'falta' : '' }}">
                            <option value="">— elegir —</option>
                            <option value="M">Mensual</option>
                            <option value="T">Trimestral</option>
                        </select>
                        <div class="mt-1 text-xs text-gray-500">
                            @if ($entidad && (int) $entidad->cicloimpuesto_id === 0)
                                La entidad no lo tiene: se grabará en Entidades al elegirlo.
                            @else
                                De Entidades (Ciclo Impuesto).
                            @endif
                        </div>
                        @error('ciclo') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="focr-lbl">Periodo fiscal en el que entran</label>
                        <select wire:model.live="periodo" class="focr-in" @disabled($ciclo === '')>
                            @foreach ($periodos as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($ciclo === 'T')
                        <div>
                            <label class="focr-lbl">Cierre mensual</label>
                            <select wire:model.live="cierre" class="focr-in">
                                <option value="">Sin cierre mensual</option>
                                @foreach ($mesesCierre as $k => $v)
                                    <option value="{{ $k }}">Registrar desde {{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="focr-lbl">Contabilidad analítica</label>
                        <label class="flex items-center gap-2 mt-1 text-sm">
                            <input type="checkbox" wire:model.live="analitica" class="rounded">
                            Poner el código de canal del proveedor
                        </label>
                        @unless ($hayAnalitica)
                            <div class="mt-1 text-xs" style="color:#b45309">Falta la migración en la base de datos: de momento no se guarda en la entidad.</div>
                        @endunless
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <button type="button" wire:click="analizar" wire:loading.attr="disabled" class="focr-btn b-indigo" @disabled($ciclo === '')>
                        <span wire:loading.remove wire:target="analizar">📄 {{ $web ? 'Leer las facturas de entrada' : 'Leer las facturas de la carpeta' }}</span>
                        <span wire:loading wire:target="analizar">Leyendo… (las que son imagen pasan por OCR)</span>
                    </button>
                    @if ($primeraAbierta)
                        <span class="text-sm">Fecha de registro = fecha de la factura; si es anterior, el <b>{{ $primeraAbierta->format('d/m/Y') }}</b></span>
                    @endif
                </div>
                @if (trim($salida) !== '')
                    <pre class="p-2 mt-3 text-xs text-gray-700 whitespace-pre-wrap border border-gray-200 rounded bg-gray-50">{{ trim($salida) }}</pre>
                @endif
            </div>

            {{-- Ficheros base --}}
            <details class="p-3 focr-card">
                <summary class="text-sm font-semibold text-gray-700 cursor-pointer">
                    Ficheros base: listado de proveedores, mayor y plan de cuentas de SAGE
                    @if ($base)
                        <span class="font-normal text-gray-500">— {{ $base['n'] }} proveedores, actualizado {{ $base['generado'] }}</span>
                    @endif
                </summary>
                <div class="mt-2 text-xs text-gray-600">
                    <p>El <b>listado de proveedores</b> (…lisProveedores….xlsx) da cuenta, CIF, contrapartida, código de IVA (910/921 CEE, 810/821 extranjero:
                        inversión del sujeto pasivo), transacción, retención, canal y nación. El <b>mayor</b> (Mayor….xlsx) sirve para comprobar las
                        contrapartidas (manda la más usada el último año; distinta del listado en {{ $base['difieren'] ?? 0 }} proveedores),
                        reconocer el formato del nº de factura y avisar de facturas ya contabilizadas. El <b>plan de cuentas</b>
                        (…Plan….xlsx, exportado de SAGE con "Código cuenta" y "Descripción") llena el combo de contrapartida con todas las cuentas.</p>
                    <table class="mt-2 focr-tabla" style="font-size:.78rem">
                        @foreach ([
                            'prov' => ['Listado de proveedores', 'subidaProv', 'Vale el último que subes; el anterior pasa a Base/OLD. Lo puesto a mano aquí (●, proveedores nuevos) no se toca.'],
                            'mayor' => ['Mayor', 'subidaMayor', 'El anterior pasa a Base/OLD pero se sigue sumando: uno de los últimos meses completa al de años anteriores (un asiento que esté en los dos se toma del más reciente).'],
                            'plan' => ['Plan de cuentas', 'subidaPlan', 'Vale el último que subes; el anterior pasa a Base/OLD.'],
                        ] as $tipo => [$titulo, $prop, $ayuda])
                            <tr>
                                <td style="width:11rem; vertical-align:top"><b>{{ $titulo }}</b></td>
                                <td style="vertical-align:top">
                                    @forelse ($ficherosBase[$tipo] ?? [] as $k => $fb)
                                        <div class="{{ $k && $tipo === 'plan' ? 'text-gray-400' : '' }}">
                                            <a href="#" wire:click.prevent="descargar(@js('Base/'.$fb['nombre']))" class="text-indigo-600 underline" title="Abrir (se descarga una copia)">📄 {{ $fb['nombre'] }}</a> <span class="text-gray-500">· {{ $fb['fecha'] }} · {{ $fb['mb'] }} MB</span>
                                            <button type="button" wire:click="quitarBase(@js($fb['nombre']))" wire:confirm="¿Quitar {{ $fb['nombre'] }}? Se borra y vuelve el anterior de Base/OLD (si hay)."
                                                    class="text-gray-400 hover:text-red-600" title="Quitar este fichero">✕</button>
                                        </div>
                                    @empty
                                        <span class="text-gray-400">(ninguno)</span>
                                    @endforelse
                                    <div class="text-gray-500" style="font-size:.7rem">{{ $ayuda }}
                                        @if ($ficherosBase['old_'.$tipo] ?? 0) ({{ $ficherosBase['old_'.$tipo] }} en OLD{{ $tipo === 'mayor' ? ', sumándose' : '' }}.) @endif
                                        ✕ = quitar el de arriba (vuelve el anterior de OLD).</div>
                                </td>
                                <td style="width:15rem; vertical-align:top">
                                    <label class="focr-btn b-gris" style="padding:.2rem .6rem; font-size:.75rem; cursor:pointer">
                                        <span wire:loading.remove wire:target="{{ $prop }}">📂 Elegir uno nuevo…</span>
                                        <span wire:loading wire:target="{{ $prop }}">Subiendo y rehaciendo…</span>
                                        <input type="file" wire:model="{{ $prop }}" accept=".xlsx" style="display:none">
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </details>

            {{-- Revisión / histórico --}}
            <div>
                <div class="focr-tabs">
                    <button type="button" wire:click="$set('vista','revisar')" class="{{ $vista === 'revisar' ? 'on' : '' }}">
                        Por revisar ({{ $cuenta['pendiente'] ?? 0 }} pendientes{{ ($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0) ? ', '.(($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0)).' al final' : '' }})
                    </button>
                    <button type="button" wire:click="$set('vista','historico')" class="{{ $vista === 'historico' ? 'on' : '' }}">
                        Validadas ({{ $enExcel }} sin guardar{{ array_sum($procesos) ? ' · '.array_sum($procesos).' ya guardadas' : '' }})
                        @if ($guardando) <span class="focr-chip c-gris" title="Excel, mover el PDF y aprender, en segundo plano">💾 {{ $guardando }}…</span> @endif
                    </button>
                    <button type="button" wire:click="$set('vista','proveedores')" class="{{ $vista === 'proveedores' ? 'on' : '' }}">Proveedores</button>
                    @if ($cuenta['duplicada'] ?? 0)
                        <button type="button" wire:click="$set('vista','duplicadas')" class="{{ $vista === 'duplicadas' ? 'on' : '' }}">
                            Duplicadas ({{ $cuenta['duplicada'] }})
                        </button>
                    @endif
                </div>

                <div class="overflow-auto focr-card" style="border-top-left-radius:0; max-height:70vh">
                    @if ($vista === 'proveedores')
                        <div class="flex flex-wrap items-center gap-2 p-2 border-b border-gray-200">
                            <input type="search" wire:model.live.debounce.300ms="filtroProv" placeholder="Buscar cuenta, nombre, CIF o contrapartida…" class="focr-in" style="max-width:340px">
                            <span class="text-xs text-gray-500">{{ count($provs) }} proveedores · <b>●</b> = puesto aquí (manda sobre la ficha de SAGE). Clic en uno para editarlo.</span>
                            <button type="button" wire:click="descargarProveedores" class="focr-btn b-gris ml-auto" style="padding:.2rem .5rem; font-size:.75rem" title="El listado tal cual se ve (con el filtro), para abrir en Excel">💾 Listado (CSV)</button>
                        </div>
                        @if ($provSel !== '')
                            <div class="p-3 border-b border-gray-200" style="background:#eef2ff">
                                <div class="text-sm" style="margin-bottom:.4rem"><b>{{ $provSel }}</b> {{ $provForm['nombre'] ?? '' }}
                                    @if ($esNuevoProv) <span class="focr-chip c-revisar">nuevo (aún no está en SAGE)</span> @endif</div>
                                <div class="flex flex-wrap gap-2 items-end">
                                    @if ($esNuevoProv)
                                        <label class="text-xs">Nombre<br><input type="text" wire:model="provForm.nombre" class="focr-in" style="width:220px"></label>
                                        <label class="text-xs">CIF<br><input type="text" wire:model="provForm.cif" class="focr-in" style="width:130px"></label>
                                    @endif
                                    <label class="text-xs">Contrapartida<br><input type="text" wire:model="provForm.contrapartida" list="focr-cuentas-prov" class="focr-in" style="width:110px"></label>
                                    <label class="text-xs">Cód. transacción<br><input type="text" wire:model="provForm.codigo_transaccion" class="focr-in" style="width:80px"></label>
                                    <label class="text-xs">Clave operación<br><input type="text" wire:model="provForm.clave_operacion" class="focr-in" style="width:80px"></label>
                                    <label class="text-xs">Cód. retención<br><input type="text" wire:model="provForm.codigo_retencion" class="focr-in" style="width:80px"></label>
                                    <button type="button" wire:click="guardarProveedor" wire:loading.attr="disabled" class="focr-btn b-verde">
                                        <span wire:loading.remove wire:target="guardarProveedor">💾 Guardar</span><span wire:loading wire:target="guardarProveedor">Guardando…</span>
                                    </button>
                                    <button type="button" wire:click="cerrarProveedor" class="focr-btn b-gris">Cancelar</button>
                                </div>
                                <datalist id="focr-cuentas-prov">
                                    @foreach ($nombresCuentas as $c => $n) <option value="{{ $c }}">{{ $n }}</option> @endforeach
                                </datalist>
                                <div class="text-xs text-gray-600" style="margin-top:.35rem">
                                    Se usará en las próximas facturas de este proveedor (las pendientes sin tocar se vuelven a proponer al guardar).
                                    Contrapartida vacía = la de la ficha de SAGE o la más usada en el mayor; cód. de transacción/retención igual al de la ficha = el de la ficha.
                                    En SAGE no se cambia nada.
                                </div>
                                @error('proveedor') <pre class="focr-avisos" style="white-space:pre-wrap; background:#fef2f2; border-color:#fca5a5; color:#991b1b; margin-top:.4rem">{{ $message }}</pre> @enderror
                            </div>
                        @endif
                        <table class="focr-tabla">
                            <thead><tr><th>Cuenta</th><th>Proveedor</th><th>CIF</th><th>Contrapartida</th><th>Cód. trans.</th><th>Clave op.</th><th>Cód. ret.</th><th style="text-align:right">Validadas aquí</th><th>Última</th></tr></thead>
                            <tbody>
                            @forelse (array_slice($provs, 0, 300, true) as $p)
                                <tr class="clic" wire:click="abrirProveedor('{{ $p['cuenta'] }}')" wire:key="p-{{ $p['cuenta'] }}" @if ($provSel === $p['cuenta']) style="background:#eef2ff" @endif>
                                    <td>{{ $p['cuenta'] }}</td>
                                    <td>{{ $p['nombre'] }} @if ($p['nuevo']) <span class="focr-chip c-revisar">nuevo</span> @endif</td>
                                    <td>{{ $p['cif'] }}</td>
                                    <td title="{{ $nombresCuentas[$p['contrapartida']] ?? '' }}{{ $p['contrapartida_sage'] !== '' ? ' · en SAGE: '.$p['contrapartida_sage'] : '' }}">{{ $p['contrapartida'] }} <span class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($nombresCuentas[$p['contrapartida']] ?? '', 22) }}</span>@if ($p['contrapartida_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td title="{{ $p['transaccion_sage'] !== '' ? 'En SAGE: '.$p['transaccion_sage'] : '' }}">{{ $p['codigo_transaccion'] }}@if ($p['transaccion_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td>{{ $p['clave_operacion'] }}</td>
                                    <td>{{ $p['codigo_retencion'] }}@if ($p['retencion_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td style="text-align:right">{{ $p['validadas'] ?: '' }}</td>
                                    <td class="text-xs">{{ $p['ultima'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-500">Ningún proveedor{{ $filtroProv ? ' con ese filtro' : ' (falta subir el listado de proveedores en Ficheros base)' }}.</td></tr>
                            @endforelse
                            @if (count($provs) > 300)
                                <tr><td colspan="9" class="p-2 text-center text-xs text-gray-500">… y {{ count($provs) - 300 }} más: afina la búsqueda.</td></tr>
                            @endif
                            </tbody>
                        </table>
                    @elseif ($vista === 'duplicadas')
                        <table class="focr-tabla">
                            <thead><tr><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th style="text-align:right">Total</th><th>Motivo</th><th>Fichero (en Duplicadas)</th><th>Cuándo</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($duplicadas as $f)
                                @php $d = $f['datos'] ?? []; @endphp
                                <tr wire:key="d-{{ $f['id'] }}">
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td class="text-xs">{{ $f['motivo_rechazo'] ?? '' }} {{ collect($f['avisos'] ?? [])->filter(fn ($a) => str_starts_with($a, 'DUPLICADA'))->implode(' · ') }}</td>
                                    <td class="text-xs" style="max-width:340px; word-break:break-all">
                                        <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $f['id']]) }}" target="_blank" class="text-indigo-600 underline">{{ $f['ruta'] }}</a>
                                    </td>
                                    <td class="text-xs">{{ $f['duplicada_el'] ?? '' }}</td>
                                    <td><button type="button" wire:click="reabrir('{{ $f['id'] }}')" class="focr-btn b-gris" style="padding:.15rem .5rem; font-size:.72rem" title="Vuelve a la carpeta de la que se leyó y a pendientes">↺ No es duplicada</button>
                                        <button type="button" wire:click="quitarDeLista('{{ $f['id'] }}')" class="focr-btn b-gris" style="padding:.15rem .5rem; font-size:.72rem" title="Quitarla de la lista (el PDF sigue en Duplicadas)">✕ Quitar</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @elseif ($vista === 'revisar')
                        @if ($cola)
                            <div class="flex items-center gap-2 p-2 border-b border-gray-200 text-xs text-gray-500">
                                <button type="button" wire:click="revisarTodas" wire:loading.attr="disabled"
                                        wire:confirm="Volver a proponer todas las facturas por revisar con los ficheros base y las reglas de ahora? En las empezadas se conserva lo que has cambiado a mano."
                                        class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.8rem" title="Volver a proponer todas las pendientes (también las empezadas: lo tocado a mano se conserva)">
                                    <span wire:loading.remove wire:target="revisarTodas">↻ Revisar todas</span>
                                    <span wire:loading wire:target="revisarTodas">↻ Revisando…</span>
                                </button>
                                @if ($quitables)
                                    <span class="ml-auto">Rechazadas, no legibles y duplicadas ya vistas:</span>
                                    <button type="button" wire:click="quitarDeLista" wire:confirm="¿Quitar de la lista todas las rechazadas, no legibles y duplicadas ({{ $quitables }})? No se borra ningún PDF." class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.75rem">✕ Quitarlas de la lista ({{ $quitables }})</button>
                                @endif
                            </div>
                        @endif
                        <table class="focr-tabla">
                            <thead><tr>
                                <th></th><th>Fichero</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th>F. registro</th>
                                <th>Contrap.</th><th style="text-align:right">Base</th><th style="text-align:right">% IVA</th><th style="text-align:right">IVA</th>
                                <th style="text-align:right">Total</th><th>Lectura</th><th>Avisos</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($cola as $f)
                                @php $d = $f['datos'] ?? []; $e = $etiqEstado[$f['estado']] ?? ['', $f['estado'], 'c-gris']; @endphp
                                <tr class="clic" wire:click="abrir('{{ $f['id'] }}')" wire:key="c-{{ $f['id'] }}">
                                    <td><span class="focr-chip {{ $e[2] }}" style="white-space:nowrap">{{ $e[0] }} {{ $e[1] }}</span></td>
                                    <td style="max-width:260px; word-break:break-all">{{ basename($f['ruta']) }} @if (! empty($f['ocr'])) <span class="focr-chip c-revisar">OCR</span> @endif
                                        @if (collect($f['avisos'] ?? [])->contains(fn ($a) => str_starts_with($a, 'DUPLICADA'))) <span class="focr-chip c-falta">DUPLICADA</span> @endif
                                        @if (! empty($f['editada'])) <span class="focr-chip c-gris" title="Tocada a mano el {{ $f['editada'] }}; se guarda sola">✎ a medias</span> @endif</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    @php $im = $importes($d); @endphp
                                    <td>{{ $d['contrapartida'] ?? '' }}</td>
                                    <td style="text-align:right">{{ $eur($im['base']) }}</td>
                                    <td style="text-align:right" title="{{ $im['detalle'] }}">{!! $im['pct'] === 'varios' ? '<span class="focr-chip c-revisar">varios</span>' : e($im['pct']) !!}</td>
                                    <td style="text-align:right">{{ $eur($im['iva']) }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td style="white-space:nowrap">
                                        @foreach (($f['confianza'] ?? []) as $k => $v)
                                            <span class="focr-chip {{ $chip($v) }}" title="{{ $k }}: {{ $v }}">{{ ['proveedor' => 'P', 'su_factura' => 'Nº', 'fecha' => 'F', 'importes' => '€'][$k] ?? $k }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-xs text-gray-600">{{ implode(' · ', $f['avisos'] ?? []) }}
                                        @if (! empty($f['motivo_rechazo'])) <b>Rechazo:</b> {{ $f['motivo_rechazo'] }} @endif
                                        <button type="button" wire:click.stop="quitarDeLista('{{ $f['id'] }}')"
                                                @if ($f['estado'] === 'pendiente') wire:confirm="¿Quitar {{ basename($f['ruta']) }} de la lista? (p.ej. si la contabilizas a mano en SAGE). El PDF no se toca." @endif
                                                class="focr-btn b-gris" style="padding:.05rem .4rem; font-size:.7rem" title="Quitarla de la lista (el PDF no se toca)">✕ Quitar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="p-4 text-center text-gray-500">No hay facturas por revisar. Elige la carpeta y pulsa «Leer las facturas».</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="flex flex-wrap items-center gap-2 p-2 border-b border-gray-200">
                            <input type="search" wire:model.live.debounce.300ms="filtro" placeholder="Buscar proveedor, cuenta, nº, fichero…" class="focr-in" style="max-width:320px">
                            <select wire:model.live="filtroProceso" class="focr-in" style="max-width:260px" title="Cada vez que guardas el Excel para SAGE se cierra un proceso">
                                <option value="">Proceso en curso (sin guardar: {{ $enExcel }})</option>
                                @foreach ($procesos as $x => $n)
                                    <option value="{{ $x }}">Guardado el {{ \App\Http\Livewire\Contabilidad\FacturasOcr::fechaProceso($x) }} ({{ $n }})</option>
                                @endforeach
                                <option value="todas">Todas</option>
                            </select>
                            <select wire:model.live="filtroMes" class="focr-in" style="max-width:200px">
                                <option value="">Todos los meses de registro</option>
                                @foreach ($mesesReg as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                            <span class="ml-auto text-xs text-gray-500">
                                @if ($ultimoExcel) Último guardado: {{ $ultimoExcel['fecha'] }} ({{ $ultimoExcel['facturas'] }} facturas) · @endif
                            </span>
                            @if ($enExcel)
                                <button type="button" wire:click="guardarExcel" wire:loading.attr="disabled" class="focr-btn b-verde" style="padding:.3rem .8rem; font-size:.85rem"
                                        title="Te pregunta dónde guardarlo (ventana de Windows) y cierra el proceso: lo que valides después irá a un Excel nuevo. Copia en {{ $dirDatos }}/Output/Guardados">
                                    <span wire:loading.remove wire:target="guardarExcel">💾 Guardar el Excel para SAGE ({{ $enExcel }} facturas)</span>
                                    <span wire:loading wire:target="guardarExcel">Elige dónde guardarlo…</span>
                                </button>
                            @else
                                <span class="text-xs text-gray-400">Nada nuevo para SAGE</span>
                            @endif
                        </div>
                        <table class="focr-tabla">
                            <thead><tr>
                                <th>F. registro</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th>Contrap.</th>
                                <th style="text-align:right">Base</th><th style="text-align:right">% IVA</th><th style="text-align:right">IVA</th><th style="text-align:right">Total</th>
                                <th>Excel</th><th>Fichero</th><th>Validada</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($validadas as $f)
                                @php $d = $f['datos']; @endphp
                                <tr wire:key="v-{{ $f['id'] }}" @if ($f['estado'] === 'validada') class="clic" wire:click="abrir('{{ $f['id'] }}')" title="Ver lo validado (y volverla a pendiente para corregirla)" @endif>
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    @php $im = $importes($d); @endphp
                                    <td>{{ $d['contrapartida'] ?? '' }}</td>
                                    <td style="text-align:right">{{ $eur($im['base']) }}</td>
                                    <td style="text-align:right" title="{{ $im['detalle'] }}">{!! $im['pct'] === 'varios' ? '<span class="focr-chip c-revisar">varios</span>' : e($im['pct']) !!}</td>
                                    <td style="text-align:right">{{ $eur($im['iva']) }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td class="text-xs" style="white-space:nowrap">
                                        @if (str_starts_with($f['excel'] ?? '', 'Guardados/'))
                                            <span class="focr-chip c-gris" title="{{ $f['excel'] }}">Guardado {{ \App\Http\Livewire\Contabilidad\FacturasOcr::fechaProceso($f['excel']) }}</span>
                                        @elseif (! empty($f['excel']))
                                            <span class="focr-chip c-ok">En curso</span>
                                        @endif
                                        fila {{ $f['fila_excel'] ?? '' }}</td>
                                    <td class="text-xs" style="max-width:340px; word-break:break-all">
                                        <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $f['id']]) }}" target="_blank" class="text-indigo-600 underline">{{ $f['ruta'] }}</a>
                                    </td>
                                    <td class="text-xs" style="white-space:nowrap">{{ $f['estado'] === 'validando' ? 'guardando…' : ($f['validada_el'] ?? '') }}
                                        @if (! empty($f['automatica'])) <span class="focr-chip c-ok" title="Validada sola: el proveedor se validó 3 veces seguidas sin tocar nada">auto</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="p-4 text-center text-gray-500">{{ $filtroProceso === '' && ! $filtro && ! $filtroMes ? 'Nada validado en este proceso todavía (lo guardado antes está en el desplegable de procesos).' : 'Ninguna factura validada'.($filtro || $filtroMes || $filtroProceso ? ' con ese filtro' : '').'.' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Revisión de una factura a pantalla completa --}}
    @if ($actual)
        @php
            $conf = $actual['confianza'] ?? [];
            $cl = fn ($k) => $conf[$k] ?? '';
            $e = $etiqEstado[$actual['estado']] ?? ['', $actual['estado'], 'c-gris'];
        @endphp
        <div class="focr-rev" x-data x-on:keydown.escape.window="$wire.cerrar()">
            <div class="focr-rev-bar">
                <button type="button" wire:click="mover(-1)" class="focr-btn b-gris" style="padding:.2rem .6rem" title="Anterior">◀</button>
                <span>{{ $posicion !== false ? $posicion + 1 : '?' }} / {{ count($cola) }}</span>
                <button type="button" wire:click="mover(1)" class="focr-btn b-gris" style="padding:.2rem .6rem" title="Siguiente">▶</button>
                <span class="focr-chip {{ $e[2] }}">{{ $e[0] }} {{ $e[1] }}</span>
                <span class="truncate" style="flex:1" title="{{ $actual['ruta'] }}">{{ basename($actual['ruta']) }}</span>
                @if ($guardando) <span class="focr-chip c-gris" title="Excel, mover el PDF y aprender de las ya validadas, en segundo plano">💾 guardando {{ $guardando }}…</span> @endif
                @if ($actual['estado'] !== 'validada')
                    <button type="button" wire:click="reproponer" wire:loading.attr="disabled" class="focr-btn b-gris" style="padding:.2rem .6rem"
                            title="Vuelve a calcular los datos con lo ya leído del PDF (rápido; pierde lo cambiado a mano en esta factura)">
                        <span wire:loading.remove wire:target="reproponer">↻ Volver a proponer</span>
                        <span wire:loading wire:target="reproponer">Proponiendo…</span>
                    </button>
                    <button type="button" wire:click="releerOcr" wire:loading.attr="disabled" class="focr-btn b-gris" style="padding:.2rem .6rem"
                            title="Vuelve a leer el PDF con el OCR de Windows (para escaneados o mal leídos; tarda unos segundos)">
                        <span wire:loading.remove wire:target="releerOcr">🔍 Leer con OCR</span>
                        <span wire:loading wire:target="releerOcr">Leyendo con OCR…</span>
                    </button>
                @endif
                <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $actual['id']]) }}" target="_blank" class="focr-btn b-gris" style="padding:.2rem .6rem">↗ Abrir aparte</a>
                <button type="button" wire:click="cerrar" class="focr-btn b-gris" style="padding:.2rem .6rem">✕ Cerrar (Esc)</button>
            </div>
            <div class="focr-rev-body">
                <div class="focr-rev-pdf" wire:key="pdf-{{ $actual['id'] }}-{{ md5($actual['ruta']) }}" wire:ignore
                     @php $sig = $cola[($posicion === false ? -1 : $posicion) + 1] ?? null; @endphp
                     x-data="visorPdf(@js(route('contabilidad.facturas-ocr.pdf', [$cliente, $actual['id']]).'?r='.md5($actual['ruta'])),
                                      @js(route('contabilidad.facturas-ocr.miniatura', [$cliente, $actual['id']]).'?r='.md5($actual['ruta'])),
                                      @js($sig ? [route('contabilidad.facturas-ocr.pdf', [$cliente, $sig['id']]).'?r='.md5($sig['ruta']),
                                                  route('contabilidad.facturas-ocr.miniatura', [$cliente, $sig['id']]).'?r='.md5($sig['ruta'])] : []))"
                     x-on:resize.window.debounce.300ms="if (zoom === 'ancho') pintar()">
                    <div class="focr-pdfbar">
                        <button type="button" x-on:click="mas(-1)" title="Menos zoom">−</button>
                        <span x-text="etiqueta" style="min-width:3.5rem; text-align:center"></span>
                        <button type="button" x-on:click="mas(1)" title="Más zoom (también Ctrl + rueda)">+</button>
                        <button type="button" x-on:click="ajustar()">Ajustar al ancho</button>
                        <span style="flex:1"></span>
                        <span class="focr-recuadro" x-show="!modo">Recuadrar en el PDF:
                            <button type="button" x-on:click="modo='cif'">NIF</button>
                            <button type="button" x-on:click="modo='su_factura'">Nº factura</button>
                            <button type="button" x-on:click="modo='fecha'">Fecha</button>
                            <button type="button" x-on:click="modo='total'">Total</button>
                        </span>
                        <span x-show="modo" style="color:#fde68a">Arrastra un recuadro sobre <b x-text="{cif:'el NIF', su_factura:'el nº de factura', fecha:'la fecha', total:'el total'}[modo]"></b>
                            <button type="button" x-on:click="modo=null">Cancelar</button></span>
                    </div>
                    <div class="focr-pags" x-ref="pags" x-on:wheel="if ($event.ctrlKey) { $event.preventDefault(); mas($event.deltaY < 0 ? 1 : -1) }"
                         :style="modo ? 'cursor:crosshair' : ''"></div>
                </div>
                <div class="focr-rev-form">
                    @if (! empty($actual['error_validar']))
                        <div class="focr-nocuadra" style="animation:none">⚠️ {{ $actual['error_validar'] }}</div>
                    @endif
                    @if (! empty($actual['avisos']))
                        <div class="focr-avisos">
                            @foreach ($actual['avisos'] as $a) <div>• {{ $a }}</div> @endforeach
                        </div>
                    @endif
                    @php $noCuadra = ($descuadre !== null && abs($descuadre) >= 0.015) || $lineasMal; @endphp
                    @if ($gemelas)
                        <div class="focr-avisos" style="max-height:none; border-color:#93c5fd; background:#eff6ff; color:#1e3a8a">
                            ℹ️ Hay {{ count($gemelas) > 1 ? 'otras '.count($gemelas).' facturas' : 'otra factura' }} con el mismo nº:
                            <b>{{ implode(', ', $gemelas) }}</b>. Esta es la primera: al validarla, {{ count($gemelas) > 1 ? 'esas quedarán' : 'esa quedará' }} como duplicada.
                        </div>
                    @endif
                    @if ($duplicados)
                        <div class="focr-nocuadra">
                            <div style="font-size:1.05rem">⚠️ FACTURA DUPLICADA</div>
                            @foreach ($duplicados as $m) <div>{{ $m }}</div> @endforeach
                            <div style="display:flex; gap:.5rem; align-items:center; margin-top:.3rem">
                                <button type="button" wire:click="marcarDuplicada" wire:loading.attr="disabled" class="focr-btn" style="background:#dc2626; color:#fff; padding:.2rem .7rem; font-size:.78rem">
                                    Sí, es duplicada → a la carpeta Duplicadas
                                </button>
                                <span style="font-weight:400; font-size:.72rem">Si de verdad es otra factura, valida (te lo preguntará).</span>
                            </div>
                        </div>
                    @endif
                    @if ($noCuadra)
                        <div class="focr-nocuadra">
                            <div style="font-size:1.05rem">⚠️ LA FACTURA NO CUADRA</div>
                            @if ($descuadre !== null && abs($descuadre) >= 0.015)
                                <div>Total {{ $eur($totalNum) }} € y bases + IVA − retención dan {{ $eur($totalNum - $descuadre) }} €: diferencia <b>{{ $eur($descuadre) }} €</b></div>
                            @endif
                            @foreach ($lineasMal as $m) <div>{{ $m }}</div> @endforeach
                            <div style="font-weight:400; font-size:.72rem">Revisa los importes (o la propia factura, que puede venir mal calculada) antes de validar.</div>
                        </div>
                    @endif
                    @error('zona') <div class="focr-avisos" style="background:#fef2f2; border-color:#fca5a5; color:#991b1b">{{ $message }}</div> @enderror
                    <div wire:loading wire:target="leerZona" class="focr-avisos" style="background:#eef2ff; border-color:#c7d2fe; color:#312e81">Leyendo el recuadro…</div>
                    @error('validar') <pre class="focr-avisos" style="white-space:pre-wrap; background:#fef2f2; border-color:#fca5a5; color:#991b1b">{{ $message }}</pre> @enderror

                    <div class="g4">
                        <div style="grid-column:span 3">
                            <label class="focr-lbl">Proveedor
                                @if ($esNuevo) <span class="focr-chip c-revisar">nuevo</span> @endif
                                @if (! empty($actual['motivo_proveedor'])) <span style="font-weight:400; color:#9ca3af">· {{ $actual['motivo_proveedor'] }}</span> @endif
                            </label>
                            <div class="focr-busc" wire:ignore wire:key="bcuenta-{{ $actual['id'] }}" x-data="buscador('proveedores', 'cuenta', true)">
                                <input type="text" x-ref="q" class="focr-in {{ $cl('proveedor') }}" style="padding-right:1.4rem" x-model="q" placeholder="cuenta / nombre / NIF"
                                       x-on:focus="$el.select(); abrir()" x-on:click="abrir()" x-on:input="abrir()" x-on:keydown.down.prevent="mover(1)" x-on:keydown.up.prevent="mover(-1)"
                                       x-on:keydown.enter.prevent="intro()" x-on:keydown.escape.stop="cerrar()" x-on:blur="salir()">
                                <button type="button" class="focr-flecha" tabindex="-1" x-on:mousedown.prevent="alternar()">▾</button>
                                <div class="focr-lista" x-show="open && res.length" x-cloak>
                                    <template x-for="(r, k) in res" :key="r[0]">
                                        <div :class="k === i ? 'on' : ''" x-on:mousedown.prevent="elegir(r)"><b x-text="r[0]"></b> <span x-text="r[1]"></span></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div style="align-self:end">
                            <button type="button" wire:click="cuentaNueva" class="focr-btn b-gris" style="width:100%; justify-content:center; padding:.2rem .3rem; font-size:.72rem" title="Proveedor nuevo: siguiente 410 libre">+ Cuenta nueva</button>
                        </div>

                        <div style="grid-column:span 2"><label class="focr-lbl">Nombre</label><input type="text" wire:model.blur="form.proveedor" class="focr-in"></div>
                        <div>
                            <label class="focr-lbl">CIF europeo
                                @if ($provFueraSage)
                                    <button type="button" wire:click="buscarCif" wire:loading.attr="disabled" wire:target="buscarCif"
                                            title="Buscar en internet el CIF y el código postal de este proveedor (unos céntimos)" style="margin-left:.3rem; color:#1d4ed8">
                                        <span wire:loading.remove wire:target="buscarCif">🔎 buscar</span><span wire:loading wire:target="buscarCif">⏳</span>
                                    </button>
                                @endif
                            </label>
                            <input type="text" wire:model.blur="form.cif" class="focr-in">
                            @if (($form['cp'] ?? '') !== '')
                                <div style="font-size:.68rem; color:#6b7280">CP {{ $form['cp'] }} {{ $form['provincia'] ?? '' }}</div>
                            @endif
                        </div>
                        <div><label class="focr-lbl">Nombre corto fichero</label><input type="text" wire:model.blur="form.nombre_fichero" class="focr-in" placeholder="p.ej. Aquaservice"></div>
                        @if ($propuestaCif)
                            <div style="grid-column:1 / -1; font-size:.72rem; padding:.3rem .5rem; border:1px solid #c7d2fe; background:#eef2ff; border-radius:.3rem">
                                @if (! empty($propuestaCif['error']))
                                    <span style="color:#b91c1c">⚠️ {{ $propuestaCif['error'] }}</span>
                                @else
                                    Internet{{ ! empty($propuestaCif['de_cache']) ? ' (ya buscado antes)' : '' }}:
                                    <b>{{ $propuestaCif['nombre_oficial'] ?: '—' }}</b> · CIF <b>{{ $propuestaCif['cif'] ?: '—' }}</b>
                                    · CP <b>{{ $propuestaCif['cp'] ?: '—' }}</b> {{ $propuestaCif['poblacion'] ?? '' }}
                                    · confianza {{ $propuestaCif['confianza'] ?: '?' }}
                                    @if (! empty($propuestaCif['fuente']))
                                        · <a href="{{ $propuestaCif['fuente'] }}" target="_blank" rel="noopener" style="color:#1d4ed8; text-decoration:underline">fuente</a>
                                    @endif
                                    @if (! empty($propuestaCif['nota']))
                                        <div style="color:#6b7280">{{ $propuestaCif['nota'] }}</div>
                                    @endif
                                    @if (! empty($propuestaCif['cif']) || ! empty($propuestaCif['cp']))
                                        <button type="button" wire:click="aceptarCif" class="focr-btn b-gris" style="margin-top:.2rem; padding:.1rem .5rem; font-size:.72rem">✔ Poner CIF y CP</button>
                                    @endif
                                @endif
                            </div>
                        @endif

                        <div style="grid-column:span 2">
                            <label class="focr-lbl">Contrapartida</label>
                            <div class="focr-busc" wire:ignore wire:key="bcontrapartida-{{ $actual['id'] }}" x-data="buscador('cuentas', 'contrapartida', false)">
                                <input type="text" x-ref="q" class="focr-in {{ ($form['contrapartida'] ?? '') === '' ? 'falta' : '' }}" style="padding-right:1.4rem" x-model="q" placeholder="cuenta / nombre"
                                       x-on:focus="$el.select(); abrir()" x-on:click="abrir()" x-on:input="abrir()" x-on:keydown.down.prevent="mover(1)" x-on:keydown.up.prevent="mover(-1)"
                                       x-on:keydown.enter.prevent="intro()" x-on:keydown.escape.stop="cerrar()" x-on:blur="salir()">
                                <button type="button" class="focr-flecha" tabindex="-1" x-on:mousedown.prevent="alternar()">▾</button>
                                <div class="focr-lista" x-show="open && res.length" x-cloak>
                                    <template x-for="(r, k) in res" :key="r[0]">
                                        <div :class="k === i ? 'on' : ''" x-on:mousedown.prevent="elegir(r)"><b x-text="r[0]"></b> <span x-text="r[1]"></span></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div><label class="focr-lbl">Cód. transacción</label><input type="text" wire:model.blur="form.codigo_transaccion" class="focr-in"></div>
                        <div><label class="focr-lbl">Clave operación (M)</label><input type="text" wire:model.blur="form.clave_operacion" class="focr-in" placeholder="vacía"></div>

                        <div><label class="focr-lbl">Nº factura</label><input type="text" wire:model.blur="form.su_factura" class="focr-in {{ $cl('su_factura') }}"></div>
                        <div><label class="focr-lbl">F. expedición</label><input type="date" wire:model.blur="form.fecha_expedicion" class="focr-in {{ $cl('fecha') }}"></div>
                        <div><label class="focr-lbl">F. operación</label><input type="date" wire:model.blur="form.fecha_operacion" class="focr-in"></div>
                        <div><label class="focr-lbl">F. registro</label><input type="date" wire:model.blur="form.fecha_registro" class="focr-in"></div>

                        @if ($sii)
                            <div style="grid-column:span 3"><label class="focr-lbl">Comentario SII</label><input type="text" wire:model.blur="form.comentario" maxlength="40" class="focr-in"></div>
                        @else
                            <div style="grid-column:span 3"></div>   {{-- sin SII el Comentario SII va vacío (cliente.json -> "sii") --}}
                        @endif
                        <div><label class="focr-lbl">Serie</label><input type="text" wire:model.blur="form.serie" class="focr-in"></div>
                    </div>

                    <div class="g4" style="margin-top:.45rem; padding-top:.4rem; border-top:1px solid #e5e7eb">
                        <div><label class="focr-lbl">Total factura {{ $isp ? '(= base, ISP)' : '' }}</label><input type="text" wire:model.blur="form.total" class="focr-in {{ $cl('importes') }} {{ $descuadre !== null && abs($descuadre) >= 0.015 ? 'mal' : '' }}" style="text-align:right"></div>
                        <div style="align-self:end; padding-bottom:.15rem">
                            @if ($descuadre !== null)
                                @if (abs($descuadre) < 0.015)
                                    <span class="focr-chip c-ok">✔ Cuadra</span>
                                @else
                                    <span class="focr-chip c-falta" style="font-size:.8rem; padding:.1rem .6rem">✖ NO CUADRA {{ $eur($descuadre) }}</span>
                                @endif
                            @endif
                        </div>
                        @if ($analitica)
                            <div><label class="focr-lbl">Canal (analítica)</label><input type="text" wire:model.blur="form.canal" class="focr-in"></div>
                        @endif
                    </div>
                    <table class="focr-imp">
                        <tr><th></th><th>Base</th><th>% IVA</th><th>Cuota</th><th></th></tr>
                        @foreach ([0, 1, 2] as $i)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><input type="text" wire:model.blur="form.lineas.{{ $i }}.base" class="focr-in {{ isset($lineasMal[$i]) ? 'mal' : '' }}"></td>
                                <td><input type="text" wire:model.blur="form.lineas.{{ $i }}.pct" class="focr-in {{ isset($lineasMal[$i]) ? 'mal' : '' }}"></td>
                                <td><input type="text" wire:model.blur="form.lineas.{{ $i }}.cuota" class="focr-in {{ isset($lineasMal[$i]) ? 'mal' : '' }}"></td>
                                <td><button type="button" wire:click="cuota({{ $i }})" class="focr-btn b-gris" style="padding:.1rem .4rem" title="Calcular la cuota con base y %">=</button></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td title="Retención">Ret.</td>
                            <td><input type="text" wire:model.blur="form.base_retencion" class="focr-in" placeholder="base"></td>
                            <td><input type="text" wire:model.blur="form.pct_retencion" class="focr-in" placeholder="%"></td>
                            <td><input type="text" wire:model.blur="form.cuota_retencion" class="focr-in" placeholder="cuota"></td>
                            <td><input type="text" wire:model.blur="form.codigo_retencion" class="focr-in" placeholder="cód." style="width:3.2rem; text-align:left"></td>
                        </tr>
                    </table>

                    @if ($actual['estado'] !== 'validada')
                        <div class="flex gap-2" style="margin-top:.55rem; align-items:center">
                            @php
                                $pregunta = trim(($duplicados ? 'Parece DUPLICADA. ' : '').($noCuadra ? 'La factura NO CUADRA. ' : ''));
                            @endphp
                            {{-- La pregunta se lee del propio botón al pulsar (data-*): wire:confirm se quedaba con la de antes
                                 al cambiar los datos de la factura (p.ej. tras "Leer con OCR" ya cuadraba y seguía preguntando) --}}
                            <button type="button" wire:loading.attr="disabled" class="focr-btn b-verde" style="flex:1; justify-content:center"
                                    data-pregunta="{{ $pregunta ? $pregunta.' ¿Validarla igualmente?' : '' }}" data-forzar="{{ $duplicados ? '1' : '0' }}"
                                    x-data x-on:click="const m = $el.dataset.pregunta; if (m && !confirm(m)) return; $wire.validar($el.dataset.forzar === '1')">
                                <span wire:loading.remove wire:target="validar">✅ Validar</span>
                                <span wire:loading wire:target="validar">Guardando…</span>
                            </button>
                            <input type="text" wire:model.blur="motivo" class="focr-in" style="flex:1" placeholder="Motivo del rechazo (opcional)">
                            <button type="button" wire:click="rechazar" wire:loading.attr="disabled" class="focr-btn b-rojo">✖ Rechazar</button>
                        </div>
                        @if (in_array($actual['estado'], ['rechazada', 'ilegible'], true))
                            <div style="margin-top:.35rem">
                                <button type="button" wire:click="reabrir('{{ $actual['id'] }}')" class="focr-btn b-gris" style="font-size:.7rem; padding:.15rem .5rem">↺ Volver a pendiente</button>
                            </div>
                        @endif
                    @else
                        <div class="flex gap-2" style="margin-top:.55rem; align-items:center; flex-wrap:wrap">
                            <span class="focr-chip c-ok">✔ Validada el {{ $actual['validada_el'] ?? '' }}{{ ! empty($actual['fila_excel']) ? ' · fila '.$actual['fila_excel'].' de '.($actual['excel'] ?? '') : '' }}</span>
                            <button type="button" wire:loading.attr="disabled" class="focr-btn b-gris" style="font-size:.75rem; padding:.2rem .6rem"
                                    wire:click="reabrir('{{ $actual['id'] }}')"
                                    wire:confirm="Se quita su fila del Excel del mes y vuelve a pendientes para corregirla y validarla otra vez. Si ese Excel ya se importó en SAGE, bórrala también en SAGE. ¿Seguir?">
                                ✎ Corregir (volver a pendiente)
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

<script>
    // Subida de facturas en la web: huella SHA-1 en el navegador (los 12 primeros hex = id de la factura, como id_fichero() de Python)
    window.focrSubida = window.focrSubida || function () {
        const sha1 = async (file) => {
            const buf = await crypto.subtle.digest('SHA-1', await file.arrayBuffer());
            return [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2, '0')).join('').slice(0, 12);
        };
        return {
            archivos: [], encima: false,
            async elegir(lista) {
                const fs = [...lista].filter(f => /\.pdf$/i.test(f.name));
                if (!fs.length) return;
                const vistos = new Set();
                this.archivos = [];
                for (const f of fs) {
                    const a = { clave: Math.random().toString(36).slice(2), nombre: f.name, file: f, estado: 'huella', pct: 0, texto: '', id: '' };
                    this.archivos.push(a);
                }
                for (const a of this.archivos) {
                    try { a.id = await sha1(a.file); } catch (e) { a.estado = 'error'; a.texto = 'no puedo calcular la huella (¿https?)'; }
                }
                const ok = this.archivos.filter(a => a.estado === 'huella');
                const r = await this.$wire.huellasNuevas(ok.map(a => [a.id, a.nombre]));
                const subir = [];
                for (const a of ok) {
                    if (r.conocidas && r.conocidas[a.id]) { a.estado = 'ya'; a.texto = r.conocidas[a.id]; }
                    else if (vistos.has(a.id)) { a.estado = 'ya'; a.texto = 'repetida en esta subida'; }
                    else { vistos.add(a.id); a.estado = 'subiendo'; subir.push(a); }
                }
                if (!subir.length) return;
                await new Promise((fin, fallo) => this.$wire.uploadMultiple('pdfsSubidos', subir.map(a => a.file), fin, fallo,
                    (e) => subir.forEach(a => a.pct = e.detail.progress)));
                subir.forEach(a => { a.estado = 'subida'; });
                await this.$wire.recibirPdfs();
            },
        };
    };
</script>
</div>
