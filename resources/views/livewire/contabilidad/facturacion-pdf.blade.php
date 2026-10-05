<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button
                    type="button"
                    class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                    x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"
                >&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.facturacion-pdf'])
    @include('livewire.contabilidad._subnav')

    <div class="px-4 pt-4">@include('livewire.contabilidad._pcs')</div>

    <div class="p-4 space-y-6">

    {{-- 2026-09-29: un proceso cada vez, elegido con los botones junto al título --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <h1 class="text-2xl font-semibold text-gray-900">Facturación PDF</h1>
        <div class="inline-flex overflow-hidden border border-indigo-600 rounded-md">
            @foreach (['Suma' => 'Suma', 'Balerga' => 'Balerga', 'Generico' => 'Genérico'] as $clave => $texto)
                <button type="button" wire:click="$set('proceso', '{{ $clave }}')"
                        style="{{ $proceso === $clave ? 'background:#4f46e5; color:#fff' : 'background:#fff; color:#4338ca' }}"
                        class="px-4 py-1.5 text-sm font-semibold {{ $loop->first ? '' : 'border-l border-indigo-600' }}">{{ $texto }}</button>
            @endforeach
        </div>
    </div>
    <p class="text-sm text-gray-500">
        @if ($proceso === 'Generico')
            Facturas de cualquier proveedor: uno o muchos ficheros (PDF o fotos), también escaneados.
            Parte los PDF que traen varias facturas, gira las páginas torcidas y pone delante proveedor y número. Sin correo.
        @else
            Dos fases separadas: primero <strong>Separar PDFs</strong> (parte el PDF-listado en facturas individuales,
            no toca el correo), y luego, ya con el resultado a la vista, <strong>Enviar correos</strong> (con confirmación).
        @endif
    </p>

    {{-- Tarjeta "ancha" (2026-09-29): ocupa toda la fila, con lo suyo a la izquierda y a la derecha
         la salida de su última ejecución (Suma/Balerga) o la página seleccionada en grande (Genérico).
         Estilos propios porque el app.css de Tailwind 2 está compilado y purgado. --}}
    <style>
        [x-cloak] { display:none !important; }
        .tarjeta-ancha { grid-column: 1 / -1; }
        .fila-tarjeta { display:flex; flex-direction:column; gap:1.5rem; }
        .tarjeta-ancha .col-der .visor { height:80vh; }
        @media (min-width:1024px) {
            .tarjeta-ancha .fila-tarjeta { flex-direction:row; align-items:flex-start; }
            .tarjeta-ancha .col-izq { flex:0 0 var(--izq, 30rem); min-width:0; }
            .tarjeta-ancha .col-der { flex:1 1 0; min-width:0; position:sticky; top:1rem; }
            .tarjeta-ancha .col-der .visor { height:calc(100vh - 2rem); }
        }
        .visor { container-type:size; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:.5rem; overflow:hidden; }
    </style>

    {{-- Genérico con carpeta (2026-09-29): File System Access API (Chrome/Edge). El navegador guarda el
         permiso de la carpeta; se suben COPIAS de los ficheros marcados, Appmos analiza y genera, y al
         final el navegador escribe en la carpeta: renombra los PDF que salen enteros y sin girar, escribe
         los nuevos (partidos, girados, imágenes) y mueve esos originales a "originales".
         Con "Elegir ficheros" (showOpenFilePicker) se cogen uno o varios sueltos; al generar se pide la
         carpeta donde están (el diálogo ya se abre en ella) para poder escribir, y sigue igual. --}}
    <script>
        window.genericoCarpeta = function () {
            let dir = null;       // FileSystemDirectoryHandle: fuera de lo reactivo (un Proxy de Alpine rompe los handles)
            let handles = {};     // nombre -> FileSystemFileHandle
            const EXT = /\.(pdf|jpe?g|jfif|png|tiff?|bmp|gif|webp)$/i;
            const existe = async (d, n) => { try { await d.getFileHandle(n); return true; } catch (e) { return false; } };
            const libre = async (d, n) => {
                if (! await existe(d, n)) return n;
                const m = n.match(/^(.*?)(\.[^.]*)?$/);
                for (let k = 2; ; k++) { const c = m[1] + ' (' + k + ')' + (m[2] || ''); if (! await existe(d, c)) return c; }
            };
            const escribir = async (d, n, blob) => {
                const h = await d.getFileHandle(n, { create: true });
                const w = await h.createWritable(); await w.write(blob); await w.close();
            };
            const mover = async (h, desde, destino, n) => {
                try { await h.move(destino, n); }
                catch (e) { await escribir(destino, n, await h.getFile()); await desde.removeEntry(h.name); }
            };
            return {
                sel: 0, soportado: 'showDirectoryPicker' in window, carpeta: '', sueltos: false, ficheros: [], estado: '', ayuda: false, ocupado: false,
                hayCarpeta() { return !! dir && this.carpeta !== ''; },
                get marcados() { return this.ficheros.filter(f => f.marcado); },
                marcar(v) { this.ficheros.forEach(f => f.marcado = v); },
                async elegirCarpeta() {
                    let d;
                    try { d = await window.showDirectoryPicker({ mode: 'readwrite' }); } catch (e) { return; }
                    dir = d; handles = {};
                    const lista = [];
                    for await (const [nombre, h] of d.entries()) {
                        if (h.kind === 'file' && EXT.test(nombre)) { handles[nombre] = h; lista.push({ nombre, marcado: true }); }
                    }
                    lista.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', { numeric: true }));
                    this.carpeta = d.name; this.sueltos = false; this.ficheros = lista; this.estado = '';
                    this.$wire.proponerClienteGenerico(d.name, lista.map(f => f.nombre));
                },
                async elegirFicheros() {
                    let hs;
                    try {
                        hs = await window.showOpenFilePicker({ id: 'generico', multiple: true, types: [{ description: 'PDF o imágenes',
                            accept: { 'application/pdf': ['.pdf'], 'image/*': ['.jpg', '.jpeg', '.jfif', '.png', '.tif', '.tiff', '.bmp', '.gif', '.webp'] } }] });
                    } catch (e) { return; }
                    dir = null; handles = {};
                    const lista = [];
                    for (const h of hs) if (EXT.test(h.name)) { handles[h.name] = h; lista.push({ nombre: h.name, marcado: true }); }
                    lista.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', { numeric: true }));
                    this.carpeta = ''; this.sueltos = true; this.ficheros = lista; this.estado = '';
                    this.$wire.proponerClienteGenerico('', lista.map(f => f.nombre));
                },
                // Ficheros sueltos: para renombrar hace falta permiso sobre su carpeta. Se pide al generar (necesita
                // el clic del usuario, por eso va antes de cualquier await), abriendo el diálogo ya en esa carpeta.
                async pedirCarpetaDeSueltos() {
                    const primero = Object.values(handles)[0];
                    if (! primero) return '';
                    let d;
                    try { d = await window.showDirectoryPicker({ id: 'generico', mode: 'readwrite', startIn: primero }); }
                    catch (e) { return 'Sin permiso sobre la carpeta: los PDF salen solo en el .zip.'; }
                    for (const [n, h] of Object.entries(handles)) {
                        let dentro = false;
                        try { const hd = await d.getFileHandle(n); dentro = await hd.isSameEntry(h); if (dentro) handles[n] = hd; } catch (e) {}
                        if (! dentro) return '⚠️ «' + n + '» no está en la carpeta ' + d.name + ': los PDF salen solo en el .zip.';
                    }
                    dir = d; this.carpeta = d.name;
                    return '';
                },
                async subirSueltos(files) {
                    dir = null; handles = {}; this.carpeta = ''; this.sueltos = false; this.ficheros = [];
                    this.ocupado = true;
                    const lista = Array.from(files).filter(f => EXT.test(f.name));
                    await this.$wire.proponerClienteGenerico('', lista.map(f => f.name));
                    for (let i = 0; i < lista.length; i++) {
                        this.estado = 'Subiendo ' + (i + 1) + ' de ' + lista.length + '…';
                        await new Promise(ok => this.$wire.upload('nuevoArchivoGenerico', lista[i], ok, ok));
                    }
                    this.estado = ''; this.ocupado = false;
                },
                async analizar() {
                    this.ocupado = true;
                    try {
                        if (this.soportado && this.ficheros.length) {
                            const lista = this.marcados;
                            if (! lista.length) { this.estado = 'Elige una carpeta o ficheros y marca al menos uno.'; return; }
                            await this.$wire.empezarLoteCarpeta();
                            for (let i = 0; i < lista.length; i++) {
                                this.estado = 'Subiendo ' + (i + 1) + ' de ' + lista.length + ': ' + lista[i].nombre;
                                const file = await handles[lista[i].nombre].getFile();
                                await new Promise(ok => this.$wire.upload('nuevoArchivoGenerico', file, ok, ok));
                            }
                        }
                        this.estado = 'Leyendo (OCR)…';
                        await this.$wire.analizarGenerico();
                        this.estado = ''; this.sel = 0;
                    } finally { this.ocupado = false; }
                },
                async generar() {
                    const aviso = this.sueltos && ! dir && this.ficheros.length ? await this.pedirCarpetaDeSueltos() : '';
                    this.ocupado = true;
                    try {
                        this.estado = 'Generando…';
                        const res = await this.$wire.generarGenerico();
                        this.estado = aviso;
                        if (! this.hayCarpeta() || ! res || ! res.length) return;
                        const log = [];
                        for (const r of res) {
                            try {
                                if (r.intacto) {
                                    const h = handles[r.archivo];
                                    if (! h || r.fichero === r.archivo) continue;
                                    const n = await libre(dir, r.fichero);
                                    await mover(h, dir, dir, n);
                                    log.push(r.archivo + '  →  ' + n);
                                } else {
                                    const resp = await fetch(r.url);
                                    if (! resp.ok) throw new Error('no se pudo bajar (' + resp.status + ')');
                                    const n = await libre(dir, r.fichero);
                                    await escribir(dir, n, await resp.blob());
                                    log.push('+ ' + n + '   (de ' + r.archivo + ')');
                                }
                            } catch (e) { log.push('⚠️ ' + r.fichero + ': ' + e.message); }
                        }
                        const cambiados = [...new Set(res.filter(r => ! r.intacto).map(r => r.archivo))];
                        if (cambiados.length) {
                            const orig = await dir.getDirectoryHandle('originales', { create: true });
                            for (const a of cambiados) {
                                const h = handles[a];
                                if (! h) continue;
                                try { const n = await libre(orig, a); await mover(h, dir, orig, n); log.push(a + '  →  originales/' + n); }
                                catch (e) { log.push('⚠️ ' + a + ' (a originales): ' + e.message); }
                            }
                        }
                        await this.$wire.anotarCarpeta('Carpeta ' + this.carpeta + ':\n' + log.join('\n'));
                        this.estado = '✅ Hecho en la carpeta ' + this.carpeta + ' (detalle en la Salida). Para otra tanda, «Empezar de nuevo».';
                        dir = null;   // ya aplicado: no se repite sobre ficheros que ya no están
                    } finally { this.ocupado = false; }
                },
                limpiar() { dir = null; handles = {}; this.carpeta = ''; this.sueltos = false; this.ficheros = []; this.estado = ''; this.sel = 0; },
            };
        };
    </script>

    <div>
        @foreach ($this->clientes as $id => $c)
            @continue($proceso !== $id)
            @php($e = $estado[$id] ?? ['fase' => 'vacio', 'nombreOriginal' => null])
            @php($d = $destinatarios[$id] ?? null)
            @php($suSalida = $salidaCliente[$id] ?? '')
            <div wire:key="cliente-{{ $id }}" class="p-4 bg-white border rounded-lg shadow tarjeta-ancha">
            <div class="fila-tarjeta">
            <div class="col-izq">
                <div x-data="{ porque: false }">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $c['label'] }}</h2>
                        <button type="button" x-on:click="porque = ! porque" class="text-lg font-bold text-indigo-700" title="¿Por qué no se puede usar en la web?">*</button>
                    </div>
                    <div x-show="porque" x-cloak class="p-2 mt-2 text-xs text-gray-700 border border-indigo-200 rounded bg-indigo-50">
                        <strong>¿Dónde se hace?</strong> El proceso necesita OneDrive ({{ $id === 'Suma' ? 'lee los destinatarios del Excel maestro (ToDO Alex) y ' : '' }}copia
                        las facturas a las carpetas de OneDrive: Facturas del mes y la de cada cliente), así que lo hace un PC de
                        trabajo (AlexMiniPC / PortalExomen). Desde la web, el PDF se le manda al PC, que lo procesa y devuelve
                        aquí el resultado; si ningún PC está encendido, la tarea espera (se puede cancelar).
                    </div>
                </div>
                <p class="mt-1 mb-3 text-xs text-gray-500">{{ $c['ayuda'] }}</p>

                @if ($e['fase'] === 'vacio')
                    {{-- Fase 0: elegir/subir el PDF --}}
                    <label class="block mb-2 text-xs font-medium text-gray-600">PDF-listado del mes</label>
                    <x-contabilidad.soltar-fichero model="archivo.{{ $id }}" accept="application/pdf,.pdf" :fichero="$archivo[$id] ?? null"
                        texto="Arrastra aquí el PDF o haz clic para elegirlo" />
                    @error("archivo.{$id}")
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div wire:loading wire:target="archivo.{{ $id }}" class="mt-1 text-xs text-gray-400">Subiendo…</div>

                    <div class="mt-3">
                        <x-button.primary
                            wire:click="separarPdf('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="separarPdf('{{ $id }}'), archivo.{{ $id }}"
                        >
                            <span wire:loading.remove wire:target="separarPdf('{{ $id }}')">Fase 1 · Separar PDFs</span>
                            <span wire:loading wire:target="separarPdf('{{ $id }}')">⏳ Separando…</span>
                        </x-button.primary>
                    </div>
                @else
                    {{-- Fase 1 hecha (o Fase 2 hecha): qué archivo se procesó, y las acciones que tocan --}}
                    <div class="p-2 mb-3 text-xs border rounded bg-gray-50 text-gray-700">
                        <div>📄 <span class="font-medium">{{ $e['nombreOriginal'] }}</span></div>
                        <div class="mt-1">
                            @if ($e['fase'] === 'separado')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">Separado, sin enviar</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-100 text-green-800">Enviado</span>
                            @endif
                        </div>
                        @if (! empty($resultados[$id]))
                            <div class="flex flex-col mt-2 gap-y-1">
                                @foreach ($resultados[$id] as $r)
                                    <x-contabilidad.resultado-fichero :r="$r" />
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($e['fase'] === 'separado')
                            <x-button.primary
                                wire:click="enviarCorreos('{{ $id }}')"
                                wire:loading.attr="disabled"
                                wire:target="enviarCorreos('{{ $id }}')"
                                onclick="return confirm('Esto manda los correos de {{ $c['label'] }} de verdad a los destinatarios reales. ¿Seguro?')"
                            >
                                <span wire:loading.remove wire:target="enviarCorreos('{{ $id }}')">Fase 2 · Enviar correos (REAL)</span>
                                <span wire:loading wire:target="enviarCorreos('{{ $id }}')">⏳ Enviando…</span>
                            </x-button.primary>
                        @endif
                        <x-button.secondary
                            wire:click="empezarDeNuevo('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="empezarDeNuevo('{{ $id }}')"
                        >
                            Empezar de nuevo (otro PDF)
                        </x-button.secondary>
                    </div>
                @endif

            </div>{{-- /col-izq --}}
            <div class="col-der" style="position:static">
                {{-- Destinatarios de este cliente: lista editable (cambios en lote), con filtro y enlace al Excel real --}}
                <div class="p-3 bg-white border rounded-lg">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <h3 class="text-sm font-semibold text-gray-800">Destinatarios</h3>
                        <x-button.primary
                            class="!py-1 !px-2 text-xs"
                            wire:click="cargarDestinatarios('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="cargarDestinatarios('{{ $id }}')"
                        >
                            <span wire:loading.remove wire:target="cargarDestinatarios('{{ $id }}')">{{ $d ? 'Recargar' : 'Cargar lista' }}</span>
                            <span wire:loading wire:target="cargarDestinatarios('{{ $id }}')">⏳ Cargando…</span>
                        </x-button.primary>
                        @php($nCambios = count($cambios[$id] ?? []))
                        <div class="flex items-center gap-2 ml-auto">
                            @if ($nCambios)
                                <button type="button" wire:click="descartarCambios('{{ $id }}')" wire:loading.attr="disabled"
                                    title="Deshace los cambios marcados en amarillo y vuelve a leer la lista tal como está en el Excel. No toca el Excel."
                                    class="text-xs text-gray-500 underline hover:text-gray-700">Descartar</button>
                            @endif
                            <button type="button" wire:click="guardarCambios('{{ $id }}')" wire:loading.attr="disabled" @disabled(! $nCambios)
                                title="Aquí se cambia si se envía a cada cliente (pulsa «sí/no»), su correo (escríbelo en la casilla) o se le da de baja (pulsa sobre «activo ✓»). Los cambios quedan marcados en amarillo y NO se escriben en el Excel ToDO Alex hasta que pulses este botón: entonces un PC abre el Excel, aplica todos los cambios de una vez y lo guarda. Hasta guardarlos, el envío de correos no los usa{{ $nCambios ? ' (ahora hay '.$nCambios.' pendiente(s))' : ' (ahora no hay nada pendiente)' }}."
                                class="px-3 py-1.5 text-sm font-semibold rounded-md shadow"
                                style="{{ $nCambios ? 'background:#f59e0b;color:#fff' : 'background:#e5e7eb;color:#9ca3af;cursor:default' }}">
                                💾 Guardar en TODO{{ $nCambios ? " ({$nCambios})" : '' }}
                            </button>
                        </div>
                    </div>

                    @if ($d && isset($d['error']))
                        <p class="text-xs text-red-600">⚠️ {{ $d['error'] }}</p>
                    @elseif ($d)
                        @foreach (($d['avisos'] ?? []) as $aviso)
                            <p class="text-xs text-amber-600">⚠️ {{ $aviso }}</p>
                        @endforeach

                        <div class="flex flex-wrap items-center gap-3 my-2 text-xs">
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="todos"> Todos ({{ count($this->destinatariosVisibles[$id]) }})
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="si"> Enviar = sí ({{ count(array_filter($this->destinatariosVisibles[$id], fn($f) => $f['enviar'])) }})
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="no"> Enviar = no ({{ count(array_filter($this->destinatariosVisibles[$id], fn($f) => ! $f['enviar'])) }})
                            </label>
                            @php($nInact = count(array_filter($d['filas'], fn($f) => ! in_array(mb_strtolower(trim($f['estado'] ?? '')), ['', 'activo', 'activa'], true))))
                            <label class="flex items-center gap-1 ml-auto text-gray-500" title="Clientes con Estado en el Excel (baja, inactivo, liquidada...). Por defecto no se enseñan.">
                                <input type="checkbox" wire:model.live="verInactivas.{{ $id }}"> Ver inactivas ({{ $nInact }})
                            </label>
                        </div>
                        @if (! empty($d['xlsxPathWindows']))
                            <div class="mb-2">
                                <x-contabilidad.resultado-fichero :r="['ruta' => $d['xlsxPathWindows']]" />
                            </div>
                        @endif

                        <div class="overflow-auto border rounded" style="max-height:calc(100vh - 17rem)">
                            <table class="min-w-full text-xs divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1 text-left">Cliente</th>
                                        <th class="px-2 py-1 text-left">Mail</th>
                                        <th class="px-2 py-1 text-left">Idioma</th>
                                        <th class="px-2 py-1 text-left">Enviar</th>
                                        <th class="px-2 py-1 text-left">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($this->destinatariosFiltrados[$id] as $fila)
                                        <tr @if (isset($cambios[$id][$fila['fila']])) style="background:#fef3c7" @elseif (! in_array(mb_strtolower(trim($fila['estado'] ?? '')), ['', 'activo', 'activa'], true)) style="opacity:.55" @endif>
                                            <td class="px-2 py-1">{{ $fila['cliente'] }}</td>
                                            <td class="px-2 py-1">
                                                <input type="text" value="{{ $fila['mail'] }}" wire:key="mail-{{ $id }}-{{ $fila['fila'] }}-{{ md5($fila['mail']) }}"
                                                    wire:change="editarDestinatario('{{ $id }}', {{ $fila['fila'] }}, 'mail', $event.target.value)"
                                                    class="w-full px-1 py-0.5 text-xs border border-transparent rounded hover:border-gray-300 focus:border-indigo-400" style="min-width:12rem" placeholder="(sin correo)">
                                            </td>
                                            <td class="px-2 py-1">{{ $fila['idioma'] ?: 'ES' }}</td>
                                            <td class="px-2 py-1">
                                                <button type="button" title="Pulsa para cambiar"
                                                    wire:click="editarDestinatario('{{ $id }}', {{ $fila['fila'] }}, 'enviar', '{{ $fila['enviar'] ? '' : '1' }}')"
                                                    class="px-2 rounded hover:bg-gray-100 {{ $fila['enviar'] ? 'text-green-700 font-semibold' : 'text-gray-400' }}">{{ $fila['enviar'] ? 'sí' : 'no' }}</button>
                                            </td>
                                            <td class="px-2 py-1 whitespace-nowrap">
                                                @php($activa = in_array(mb_strtolower(trim($fila['estado'] ?? '')), ['', 'activo', 'activa'], true))
                                                <button type="button" wire:click="bajaDestinatario('{{ $id }}', {{ $fila['fila'] }}, {{ $activa ? 'true' : 'false' }})"
                                                    title="{{ $activa ? 'Pulsa para dar de baja' : 'Pulsa para activar' }}"
                                                    class="px-2 rounded hover:bg-gray-100 {{ $activa ? 'text-green-700' : 'text-gray-500' }}">{{ $activa ? 'activo ✓' : $fila['estado'] }}</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-2 py-3 text-center text-gray-400">Sin filas para este filtro.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @if ($suSalida !== '')
                    @php($est = \App\Support\EstadoSalida::de($suSalida))
                    <div class="p-4 mt-4 bg-gray-900 rounded-lg shadow" style="border-left:6px solid {{ $est['color'] }}">
                        <h3 class="mb-2 text-sm font-semibold text-gray-300">{{ $est['icono'] }} Salida de {{ $c['label'] }}</h3>
                        <pre class="overflow-auto text-xs text-green-400 whitespace-pre-wrap" style="max-height:30vh">{{ $suSalida }}</pre>
                    </div>
                @endif
            </div>
            </div>{{-- /fila-tarjeta --}}
            </div>
        @endforeach

        {{-- Genérico: facturas de cualquier proveedor (separar_generico.py), sin correo --}}
        @php($g = $generico)
        @if ($proceso === 'Generico')
        @php($genAncha = $this->genericoPermitido && $g['fase'] !== 'vacio')
        <div wire:key="cliente-Generico" class="p-4 bg-white border rounded-lg shadow tarjeta-ancha"
             x-data="genericoCarpeta()" style="--izq:40rem">
        <div class="fila-tarjeta">
        <div class="col-izq">
            <h2 class="text-lg font-semibold text-gray-900">Genérico</h2>
            <p class="mt-1 mb-3 text-xs text-gray-500">
                Uno o muchos ficheros (PDF o fotos jpg/png…; también escaneados: se leen con OCR).
                Sale un PDF por factura, con proveedor y número en el nombre.
            </p>

            @if (! $this->genericoPermitido)
                <p class="text-xs text-amber-600">⚠️ No disponible en este servidor.</p>
            @elseif ($g['fase'] === 'vacio')
                <label class="block mb-1 text-xs font-medium text-gray-600">Cliente (a quien van las facturas) <span class="text-emerald-700">· recomendado</span></label>
                <input type="text" wire:model.blur="genericoCliente" list="gen-entidades" placeholder="Elígelo de Entidades (escribe para buscar)"
                       class="block w-full mb-1 text-sm border-gray-300 rounded-md shadow-sm">
                <datalist id="gen-entidades" wire:ignore>
                    @foreach (array_keys($this->entidadesCliente) as $opcion)
                        <option value="{{ $opcion }}"></option>
                    @endforeach
                </datalist>
                @if ($genericoCliente === '')
                    <p class="mb-1 text-xs text-amber-600">⚠️ Mejor elegirlo: sin cliente, se adivina (la empresa que más se repite) y puede
                        confundirlo con el proveedor si todas las facturas son del mismo.</p>
                @elseif (! isset($this->entidadesCliente[$genericoCliente]))
                    <p class="mb-1 text-xs text-amber-600">⚠️ No está en Entidades: elígelo de la lista para que use también su NIF
                        (así lo reconoce aunque el OCR lea mal el nombre).</p>
                @elseif ($genericoClienteOrigen !== '')
                    <p class="mb-1 text-xs text-emerald-700">✔ Propuesto por {{ $genericoClienteOrigen }}. Si no es, cámbialo.</p>
                @endif
                <p class="mb-3 text-xs text-gray-500">Sale de Entidades (nombre + NIF) y nunca se propone como proveedor. Al elegir la carpeta
                    o los ficheros se rellena solo si su nombre lo deja claro ("Sunbelt 2026").</p>

                <label class="block mb-2 text-xs font-medium text-gray-600">Facturas (PDF o imágenes; una, varias o toda la carpeta)</label>
                <template x-if="soportado">
                    <div>
                        <div class="flex items-center gap-2">
                            <x-button.secondary x-on:click="elegirCarpeta()">📁 Elegir carpeta…</x-button.secondary>
                            <x-button.secondary x-on:click="elegirFicheros()">📄 Elegir ficheros…</x-button.secondary>
                            <button type="button" x-on:click="ayuda = ! ayuda" class="text-lg font-bold text-indigo-700" title="¿Por qué no se pueden arrastrar?">*</button>
                        </div>
                        <div x-show="ayuda" x-cloak class="p-2 mt-2 text-xs text-gray-700 border border-indigo-200 rounded bg-indigo-50">
                            <strong>¿Por qué no se pueden arrastrar?</strong> Al arrastrar (o elegir con el botón normal de
                            subir ficheros) el navegador solo le da a la página una <em>copia</em> de cada fichero: ni sabe en
                            qué carpeta estaba ni puede escribir en ella, así que no se podrían renombrar allí. Eligiendo la
                            carpeta, Chrome/Edge piden permiso para editarla y así los PDF se renombran en su sitio.
                            Con arrastrar solo se podría descargar un .zip.
                        </div>
                        <template x-if="carpeta || sueltos">
                            <div class="mt-2">
                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                                    <span><span x-show="carpeta">📁 <strong x-text="carpeta"></strong> · </span><span x-show="sueltos && ! carpeta">📄 Ficheros sueltos · </span><span x-text="marcados.length + ' de ' + ficheros.length + ' marcados'"></span></span>
                                    <button type="button" x-on:click="marcar(true)" class="text-indigo-700 hover:underline">todos</button>
                                    <button type="button" x-on:click="marcar(false)" class="text-indigo-700 hover:underline">ninguno</button>
                                </div>
                                <div class="mt-1 overflow-auto border rounded" style="max-height:18rem">
                                    <template x-for="f in ficheros" :key="f.nombre">
                                        <label class="flex items-center gap-2 px-2 py-1 text-xs border-b cursor-pointer last:border-b-0">
                                            <input type="checkbox" x-model="f.marcado" class="border-gray-300 rounded">
                                            <span class="text-gray-700 break-all" x-text="f.nombre"></span>
                                        </label>
                                    </template>
                                    <p x-show="! ficheros.length" class="px-2 py-2 text-xs text-gray-400">No hay PDF ni imágenes en esta carpeta.</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="! soportado">
                    <p class="mt-1 mb-1 text-xs text-amber-700">Este navegador no deja escribir en tus carpetas (usa Chrome o Edge para renombrar en su sitio). Aquí el resultado sale en un .zip.</p>
                </template>
                {{-- Subir copias: sin File System Access API, o carpetas que Chrome bloquea (C:\ProgramData,
                     Archivos de programa, AppData...). El resultado va solo en el .zip. --}}
                <div class="mt-2" x-show="! carpeta && ! sueltos">
                    <label class="block text-xs text-gray-600">
                        <span x-show="soportado">…o <strong>subir una copia</strong> (desde cualquier carpeta, también las que Chrome no deja abrir; sale solo en .zip):</span>
                        <input type="file" multiple accept="application/pdf,.pdf,image/*" class="block mt-1 text-xs"
                               x-on:change="subirSueltos($event.target.files); $event.target.value = ''">
                    </label>
                    @if ($archivosGenerico)
                        <div class="mt-2 overflow-auto border rounded" style="max-height:16rem">
                            @foreach ($archivosGenerico as $i => $a)
                                <div wire:key="gen-arch-{{ $i }}-{{ md5($a['ruta']) }}" class="flex items-center justify-between gap-2 px-2 py-1 text-xs border-b last:border-b-0">
                                    <span class="text-gray-700 break-all">{{ $a['nombre'] }}</span>
                                    <button type="button" wire:click="quitarArchivoGenerico({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar">✕</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('nuevoArchivoGenerico')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex items-center gap-3 mt-3">
                    <x-button.primary x-on:click="analizar()" x-bind:disabled="ocupado">
                        <span x-show="! ocupado">Fase 1 · Analizar</span>
                        <span x-show="ocupado" x-cloak>⏳ Trabajando…</span>
                    </x-button.primary>
                    <span class="text-xs text-gray-500" x-text="estado"></span>
                </div>
            @else
                <div class="p-2 mb-3 text-xs border rounded bg-gray-50 text-gray-700">
                    <div>📄 <span class="font-medium">{{ $g['nombreOriginal'] }}</span></div>
                    @if ($g['destinatario'])
                        <div class="mt-1 text-gray-500">Destinatario (no se usa como proveedor): {{ $g['destinatario'] }}</div>
                    @endif
                    <div class="mt-1">
                        @if ($g['fase'] === 'analizado')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">Analizado, revisa la tabla</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-100 text-green-800">PDFs generados</span>
                        @endif
                    </div>
                    @if (! empty($resultados['Generico']))
                        <div class="flex flex-col mt-2 gap-y-1">
                            @foreach ($resultados['Generico'] as $r)
                                <x-contabilidad.resultado-fichero :r="$r" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <p class="mb-1 text-xs text-gray-500">
                    Páginas seguidas con el mismo número y proveedor salen en un solo PDF. Corrige lo que el OCR haya leído mal.
                    Clic en una fila para ver su página en grande a la derecha; ↻ la gira 90° (las torcidas ya vienen giradas).
                </p>
                <div class="mb-3 overflow-auto border rounded" style="max-height:calc(100vh - 16rem)">
                    <table class="min-w-full text-xs divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-1 text-left">Pág.</th>
                                <th class="px-1 py-1"></th>
                                <th class="px-1 py-1 text-left">Tipo</th>
                                <th class="px-1 py-1 text-left">Proveedor</th>
                                <th class="px-1 py-1 text-left">Número</th>
                                <th class="px-1 py-1"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($genericoPaginas as $i => $f)
                                @if (count($archivosGenerico) > 1 && ($f['archivo'] ?? '') !== ($genericoPaginas[$i - 1]['archivo'] ?? null))
                                    <tr wire:key="gen-arch-cab-{{ $i }}"><td colspan="6" class="px-1 pt-2 pb-1 text-xs font-semibold text-gray-600 bg-gray-50">📄 {{ $f['archivo'] ?? '' }}</td></tr>
                                @endif
                                <tr wire:key="gen-pag-{{ $i }}" x-on:click="sel = {{ $i }}" x-on:focusin="sel = {{ $i }}"
                                    :style="sel === {{ $i }} ? 'background:#e0e7ff' : ''" style="cursor:pointer">
                                    <td class="px-1 py-1 text-gray-500">{{ $f['pagina'] }}</td>
                                    <td class="px-1 py-1">
                                        @if ($g['id'])
                                            {{-- La miniatura es de la página tal cual viene; se gira aquí con el giro que se aplicará --}}
                                            @php($mini = route('contabilidad.facturacion-pdf.miniatura', [$g['id'], $f['pagina']]))
                                            @php($giro = (int) ($f['giro'] ?? 0))
                                            <div style="width:4rem; height:4rem; display:flex; align-items:center; justify-content:center">
                                                <img src="{{ $mini }}" alt="Página {{ $f['pagina'] }}" loading="lazy"
                                                     style="max-width:4rem; max-height:4rem; transform:rotate({{ $giro }}deg); border:1px solid #d1d5db; background:#fff">
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-1 py-1">
                                        <select wire:model="genericoPaginas.{{ $i }}.tipo" class="py-0.5 pl-1 pr-6 text-xs border-gray-300 rounded">
                                            <option value="Fra">Fra</option>
                                            <option value="Abo">Abo</option>
                                            <option value="Pre">Pre</option>
                                            <option value="Prof">Proforma</option>
                                        </select>
                                    </td>
                                    <td class="px-1 py-1">
                                        <input type="text" wire:model="genericoPaginas.{{ $i }}.proveedor"
                                               style="min-width:8rem" class="w-full py-0.5 px-1 text-xs border-gray-300 rounded {{ trim($f['proveedor'] ?? '') === '' ? 'bg-red-50' : '' }}">
                                    </td>
                                    <td class="px-1 py-1">
                                        <input type="text" wire:model="genericoPaginas.{{ $i }}.numero"
                                               style="min-width:6rem" class="w-full py-0.5 px-1 text-xs border-gray-300 rounded {{ trim($f['numero'] ?? '') === '' ? 'bg-red-50' : '' }}">
                                    </td>
                                    <td class="px-1 py-1 whitespace-nowrap">
                                        <button type="button" wire:click="girarPagina({{ $i }})"
                                                class="text-gray-400 hover:text-gray-700" title="Girar 90° (se aplica al generar los PDF)">↻</button>
                                        @if ($i > 0)
                                            <button type="button" wire:click="igualQueAnterior({{ $i }})"
                                                    class="text-gray-400 hover:text-gray-700" title="Igual que la anterior (misma factura)">↑=</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <label class="flex items-center gap-2 mb-2 text-xs text-gray-700">
                    <input type="checkbox" wire:model="genericoNombreOriginal" class="border-gray-300 rounded">
                    Poner el nombre original detrás (<span class="font-mono">Proveedor Fra 123 - nombre original.pdf</span>)
                </label>
                <div class="flex flex-wrap gap-2">
                    <x-button.primary x-on:click="generar()" x-bind:disabled="ocupado">
                        <span x-show="! ocupado" x-text="hayCarpeta() || sueltos ? 'Fase 2 · Generar y renombrar en la carpeta' : 'Fase 2 · Generar PDFs'"></span>
                        <span x-show="ocupado" x-cloak>⏳ Trabajando…</span>
                    </x-button.primary>
                    @if ($g['fase'] === 'generado' && $g['zip'])
                        <x-button.secondary wire:click="descargarZipGenerico">⬇️ Descargar .zip</x-button.secondary>
                    @endif
                    <x-button.secondary
                        wire:click="empezarDeNuevoGenerico" x-on:click="limpiar()"
                        wire:loading.attr="disabled"
                        wire:target="empezarDeNuevoGenerico"
                    >
                        Empezar de nuevo
                    </x-button.secondary>
                </div>
                <p class="mt-2 text-xs text-gray-600" x-show="estado" x-text="estado"></p>
                <p class="mt-2 text-xs text-gray-500" x-show="hayCarpeta() || sueltos">
                    En la carpeta: los PDF que salen enteros y sin girar solo se renombran; los que se parten, giran o
                    son imágenes se escriben nuevos y el original va a la subcarpeta <strong>originales</strong>.
                    <span x-show="sueltos && ! hayCarpeta()">Con ficheros sueltos, al generar se abre su carpeta: pulsa
                    «Seleccionar carpeta» y acepta el permiso de edición (si no, solo sale el .zip).</span>
                </p>
            @endif
        </div>{{-- /col-izq --}}
        @if ($genAncha && $g['id'])
            {{-- Página seleccionada en grande, con el alto de la pantalla y el giro que se aplicará --}}
            <div class="col-der">
                <div class="visor">
                    @foreach ($genericoPaginas as $i => $f)
                        @php($giro = (int) ($f['giro'] ?? 0))
                        <img wire:key="gen-grande-{{ $i }}" x-show="sel === {{ $i }}" x-cloak loading="lazy"
                             src="{{ route('contabilidad.facturacion-pdf.miniatura', [$g['id'], $f['pagina']]) }}" alt="Página {{ $f['pagina'] }}"
                             style="background:#fff; box-shadow:0 4px 16px rgba(0,0,0,.2); transform:rotate({{ $giro }}deg);
                                    {{ $giro % 180 ? 'max-width:100cqh; max-height:100cqw' : 'max-width:100cqw; max-height:100cqh' }}">
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-center text-gray-500" x-text="'Página ' + (sel + 1) + ' de {{ count($genericoPaginas) }}'"></p>
            </div>
        @endif
        </div>{{-- /fila-tarjeta --}}
        </div>
        @endif
    </div>


    @include('livewire.contabilidad._salida')
    </div>
</div>
