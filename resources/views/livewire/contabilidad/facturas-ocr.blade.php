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
        .focr-rev-form { width:min(660px, 52vw); background:#f9fafb; overflow-y:auto; padding:.75rem; border-left:1px solid #374151; }
        .focr-rev-form .fila { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.5rem; }
        .focr-rev-form .fila3 { display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:.35rem; margin-bottom:.35rem; align-items:end; }
        .focr-sec { font-size:.7rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#6b7280; margin:.75rem 0 .35rem; }
    </style>

    <div class="fixed flex flex-col gap-2 top-4 right-4" style="z-index:70; width:24rem; max-width:calc(100vw - 2rem)">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="flex items-start gap-2 p-3 bg-white border border-gray-300 rounded-lg shadow-lg">
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
                    if (!q || q === norm(this.etiqueta(this.valor()))) return this.lista.slice(0, 40);
                    const ps = q.split(/\s+/).filter(Boolean);
                    return this.lista.filter((r) => { const t = norm(r[0] + ' ' + r[1]); return ps.every((p) => t.includes(p)); }).slice(0, 40);
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

        window.visorPdf = function (url) {
            let pdf = null;   // fuera del objeto de Alpine: su proxy rompe los campos privados de PDF.js
            return {
                url, zoom: 'ancho', escala: 1, modo: null,
                init() {
                    try { this.zoom = localStorage.getItem('focr-zoom') || 'ancho'; } catch (e) {}
                    this.lib().then(() => pdfjsLib.getDocument(this.url).promise).then((d) => { pdf = d; this.pintar(); });
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
                    if (!pdf) return;
                    const cont = this.$refs.pags, dpr = window.devicePixelRatio || 1;
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

        @if ($cliente !== '')
            {{-- 1. Parámetros y lectura de la carpeta --}}
            <div class="p-4 focr-card">
                <div class="focr-grid">
                    <div style="grid-column:1/-1">
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
                        <span wire:loading.remove wire:target="analizar">📄 Leer las facturas de la carpeta</span>
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
                    Ficheros base: listado de proveedores y mayor de SAGE
                    @if ($base)
                        <span class="font-normal text-gray-500">— {{ $base['n'] }} proveedores, actualizado {{ $base['generado'] }}</span>
                    @endif
                </summary>
                <div class="mt-2 text-xs text-gray-600">
                    <p>El <b>listado de proveedores</b> (…lisProveedores….xlsx) da cuenta, CIF, contrapartida, código de IVA (910/921 CEE, 810/821 extranjero:
                        inversión del sujeto pasivo), transacción, retención, canal y nación. El <b>mayor</b> (Mayor….xlsx) sirve para comprobar las
                        contrapartidas (manda la más usada el último año; distinta del listado en {{ $base['difieren'] ?? 0 }} proveedores),
                        reconocer el formato del nº de factura y avisar de facturas ya contabilizadas.</p>
                    @if ($base)
                        <p class="mt-1">En uso: {{ implode(', ', $base['origen']) }}</p>
                    @endif
                    <input type="file" wire:model="subidas" multiple accept=".xlsx" class="mt-2 text-xs">
                </div>
            </details>

            {{-- Revisión / histórico --}}
            <div>
                <div class="focr-tabs">
                    <button type="button" wire:click="$set('vista','revisar')" class="{{ $vista === 'revisar' ? 'on' : '' }}">
                        Por revisar ({{ $cuenta['pendiente'] ?? 0 }} pendientes{{ ($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0) ? ', '.(($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0)).' al final' : '' }})
                    </button>
                    <button type="button" wire:click="$set('vista','historico')" class="{{ $vista === 'historico' ? 'on' : '' }}">
                        Validadas ({{ $cuenta['validada'] ?? 0 }})
                    </button>
                </div>

                <div class="overflow-auto focr-card" style="border-top-left-radius:0; max-height:70vh">
                    @if ($vista === 'revisar')
                        <table class="focr-tabla">
                            <thead><tr>
                                <th></th><th>Fichero</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th>F. registro</th>
                                <th style="text-align:right">Total</th><th>Lectura</th><th>Avisos</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($cola as $f)
                                @php $d = $f['datos'] ?? []; $e = $etiqEstado[$f['estado']] ?? ['', $f['estado'], 'c-gris']; @endphp
                                <tr class="clic" wire:click="abrir('{{ $f['id'] }}')" wire:key="c-{{ $f['id'] }}">
                                    <td><span class="focr-chip {{ $e[2] }}" style="white-space:nowrap">{{ $e[0] }} {{ $e[1] }}</span></td>
                                    <td style="max-width:260px; word-break:break-all">{{ basename($f['ruta']) }} @if (! empty($f['ocr'])) <span class="focr-chip c-revisar">OCR</span> @endif
                                        @if (! empty($f['editada'])) <span class="focr-chip c-gris" title="Tocada a mano el {{ $f['editada'] }}; se guarda sola">✎ a medias</span> @endif</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td style="white-space:nowrap">
                                        @foreach (($f['confianza'] ?? []) as $k => $v)
                                            <span class="focr-chip {{ $chip($v) }}" title="{{ $k }}: {{ $v }}">{{ ['proveedor' => 'P', 'su_factura' => 'Nº', 'fecha' => 'F', 'importes' => '€'][$k] ?? $k }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-xs text-gray-600">{{ implode(' · ', $f['avisos'] ?? []) }}
                                        @if (! empty($f['motivo_rechazo'])) <b>Rechazo:</b> {{ $f['motivo_rechazo'] }} @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-500">No hay facturas por revisar. Elige la carpeta y pulsa «Leer las facturas».</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="flex flex-wrap items-center gap-2 p-2 border-b border-gray-200">
                            <input type="search" wire:model.live.debounce.300ms="filtro" placeholder="Buscar proveedor, cuenta, nº, fichero…" class="focr-in" style="max-width:320px">
                            <select wire:model.live="filtroMes" class="focr-in" style="max-width:200px">
                                <option value="">Todos los meses de registro</option>
                                @foreach ($mesesReg as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                            <span class="ml-auto text-xs text-gray-500">Plantillas para SAGE:</span>
                            @forelse ($excels as $x)
                                <button type="button" wire:click="guardarExcel(@js($x))" wire:loading.attr="disabled" class="focr-btn b-gris" style="padding:.2rem .5rem; font-size:.75rem"
                                        title="Guardar una copia donde elijas (ventana de Windows). Se va completando al validar: FacturasOcr\{{ $cliente }}\Output">💾 {{ $x }}</button>
                            @empty
                                <span class="text-xs text-gray-400">(ninguna todavía)</span>
                            @endforelse
                        </div>
                        <table class="focr-tabla">
                            <thead><tr>
                                <th>F. registro</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th style="text-align:right">Total</th>
                                <th>Contrap.</th><th>Excel</th><th>Fichero</th><th>Validada</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($validadas as $f)
                                @php $d = $f['datos']; @endphp
                                <tr wire:key="v-{{ $f['id'] }}">
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td>{{ $d['contrapartida'] ?? '' }}</td>
                                    <td class="text-xs">{{ $f['excel'] ?? '' }} (fila {{ $f['fila_excel'] ?? '' }})</td>
                                    <td class="text-xs" style="max-width:340px; word-break:break-all">
                                        <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $f['id']]) }}" target="_blank" class="text-indigo-600 underline">{{ $f['ruta'] }}</a>
                                    </td>
                                    <td class="text-xs">{{ $f['validada_el'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-500">Ninguna factura validada{{ $filtro || $filtroMes ? ' con ese filtro' : '' }}.</td></tr>
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
                <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $actual['id']]) }}" target="_blank" class="focr-btn b-gris" style="padding:.2rem .6rem">↗ Abrir aparte</a>
                <button type="button" wire:click="cerrar" class="focr-btn b-gris" style="padding:.2rem .6rem">✕ Cerrar (Esc)</button>
            </div>
            <div class="focr-rev-body">
                <div class="focr-rev-pdf" wire:key="pdf-{{ $actual['id'] }}-{{ md5($actual['ruta']) }}" wire:ignore
                     x-data="visorPdf(@js(route('contabilidad.facturas-ocr.pdf', [$cliente, $actual['id']]).'?r='.md5($actual['ruta'])))"
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
                    @if (! empty($actual['avisos']))
                        <div class="focr-avisos">
                            @foreach ($actual['avisos'] as $a) <div>• {{ $a }}</div> @endforeach
                        </div>
                    @endif
                    @php $noCuadra = ($descuadre !== null && abs($descuadre) >= 0.015) || $lineasMal; @endphp
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
                        <div><label class="focr-lbl">CIF europeo</label><input type="text" wire:model.blur="form.cif" class="focr-in"></div>
                        <div><label class="focr-lbl">Nombre corto fichero</label><input type="text" wire:model.blur="form.nombre_fichero" class="focr-in" placeholder="p.ej. Aquaservice"></div>

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

                        <div style="grid-column:span 3"><label class="focr-lbl">Comentario SII</label><input type="text" wire:model.blur="form.comentario" maxlength="40" class="focr-in"></div>
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
                            <button type="button" wire:click="validar" wire:loading.attr="disabled" class="focr-btn b-verde" style="flex:1; justify-content:center"
                                    @if ($noCuadra) wire:confirm="La factura NO CUADRA. ¿Validarla igualmente?" @endif>
                                <span wire:loading.remove wire:target="validar">✅ Validar</span>
                                <span wire:loading wire:target="validar">Guardando…</span>
                            </button>
                            <input type="text" wire:model.blur="motivo" class="focr-in" style="flex:1" placeholder="Motivo del rechazo (opcional)">
                            <button type="button" wire:click="rechazar" wire:loading.attr="disabled" class="focr-btn b-rojo">✖ Rechazar</button>
                        </div>
                        <div class="flex gap-2" style="margin-top:.35rem; align-items:center; font-size:.7rem; color:#6b7280">
                            <button type="button" wire:click="releerOcr" wire:loading.attr="disabled" class="focr-btn b-gris" style="font-size:.7rem; padding:.15rem .5rem">
                                <span wire:loading.remove wire:target="releerOcr">🔍 Leer con OCR</span>
                                <span wire:loading wire:target="releerOcr">Leyendo…</span>
                            </button>
                            @if (in_array($actual['estado'], ['rechazada', 'ilegible'], true))
                                <button type="button" wire:click="reabrir('{{ $actual['id'] }}')" class="focr-btn b-gris" style="font-size:.7rem; padding:.15rem .5rem">↺ Volver a pendiente</button>
                            @endif
                            <span>Validar: fila al Excel del mes de registro, PDF a su carpeta y aprende.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
