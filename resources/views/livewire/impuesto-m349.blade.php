{{-- Impuestos → M349 operaciones intracomunitarias (ImpuestoM349, 16-oct-2026). Solo NAE de momento; el resto, «en construcción». --}}
<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_impuestos', ['activa' => 'm349'])

    <div class="p-4" style="max-width:1100px" @if ($esperandoCarpetas || $tareaGuardar || $tareaTraer) wire:poll.3s="revisar" @endif>
    <style>
        .m3-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:12px }
        .m3-h { font-weight:600; color:#111827; margin-bottom:8px }
        .li-btn, .m3-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer; display:inline-block }
        .m3-btn.on { background:#4f46e5; border-color:#4f46e5; color:#fff }
        .m3-btn[disabled] { opacity:.5; cursor:default }
        .m3-zona { border:1.5px dashed #c7d2fe; border-radius:8px; padding:10px; background:#fafaff }
        .m3-zona.encima { background:#eef2ff; border-color:#6366f1 }
    </style>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">M349 <span class="text-sm font-normal text-gray-500">declaración recapitulativa de operaciones intracomunitarias</span></h1>

    @if ($error)<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#991b1b">⚠️ {{ $error }}</div>@endif
    @if ($msg)<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#166534">✅ {{ $msg }}</div>@endif

    <div class="m3-card">
        <div class="m3-h">1 · Cliente y periodo</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="28rem"/>
            @if ($esOperativo)
                <button type="button" wire:click="cambiarEjercicio(-1)" class="m3-btn">◀</button><b>{{ $ejercicio }}</b><button type="button" wire:click="cambiarEjercicio(1)" class="m3-btn">▶</button>
                <select wire:model.live="periodo" class="border-gray-300 rounded-md text-sm" style="padding-top:4px;padding-bottom:4px">
                    @foreach ($periodosLista as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach
                </select>
            @endif
            @if (\App\Support\Impuestos::esGestor())
                <label class="text-xs text-gray-600" style="margin-left:auto"><input type="checkbox" wire:model.live="verTodos" class="border-gray-300 rounded"> ver todos los clientes</label>
            @endif
        </div>
        @if ($decl)
            <div class="text-sm" style="display:flex;gap:18px;flex-wrap:wrap;margin-top:8px;padding:6px 10px;background:#f9fafb;border-radius:6px">
                <span><span class="text-xs text-gray-500">Nombre</span> <b><x-copiar :texto="$decl['nombre']" titulo="Copiar el nombre" /></b></span>
                <span><span class="text-xs text-gray-500">NIF</span> <b><x-copiar :texto="$decl['nif']" titulo="Copiar el NIF" /></b></span>
            </div>
        @endif
        @if (! $empresas)<div class="mt-2 text-sm text-gray-500">No tienes clientes con el modelo 349 en Seguimiento de impuestos (se definen en Entidades → zona «Impuestos»).</div>@endif
    </div>

    @if ($entidadId && ! $esOperativo)
        <div class="m3-card" style="text-align:center;padding:40px 16px">
            <div style="font-size:40px">🚧</div>
            <div class="text-lg font-semibold text-gray-700">En construcción</div>
            <div class="text-sm text-gray-500">El proceso del 349 de momento solo está preparado para NAE Comunicacions.</div>
        </div>
    @elseif ($entidadId)
        <div class="m3-card">
            <div class="m3-h">2 · Carpeta de trabajo</div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;font-size:13px">{{ $carpeta ? 'OneDrive\\'.str_replace('/', '\\', $carpeta) : '— sin carpeta de trabajo —' }}</code>
                @if ($arbol['dirs'])
                    <x-explorador-carpetas model="carpetaElegida" :dirs="$arbol['dirs']" :marcas="[]" :actual="$carpeta" />
                @endif
                <button type="button" wire:click="actualizarCarpetas" class="m3-btn" @disabled($esperandoCarpetas)
                    title="Un PC vuelve a leer las carpetas de OneDrive (unos 30 s)">🔄 {{ $arbol['dirs'] ? 'Actualizar lista' : 'Leer carpetas de OneDrive' }}</button>
                @if ($esperandoCarpetas)<span class="text-xs text-indigo-700">⏳ un PC está leyendo las carpetas…</span>@endif
            </div>
            <div class="text-xs text-gray-500" style="margin-top:6px">Navega por OneDrive (o escribe para buscar) y elige la carpeta del 349; se recuerda para este cliente.{{ $arbol['fecha'] ? ' Lista leída el '.$arbol['fecha'].'.' : '' }}</div>
        </div>

        <div class="m3-card">
            <div class="m3-h">3 · Fichero a tratar <span class="text-xs font-normal text-gray-500">— {{ $periodosLista[$periodo] ?? $periodo }} {{ $ejercicio }}</span></div>
            <div class="m3-zona" x-data="{ encima: false }" :class="encima && 'encima'"
                 x-on:dragover.prevent="encima = true" x-on:dragleave="encima = false"
                 x-on:drop.prevent="encima = false; $wire.upload('subida', $event.dataTransfer.files[0])">
                @forelse ($ficherosLista as $f)
                    <div class="text-sm" style="display:flex;gap:8px;align-items:center">📄 <span style="flex:1;word-break:break-all">{{ $f['nombre'] }}</span>
                        <span class="text-xs text-gray-500">{{ number_format($f['tam'] / 1024, 0, ',', '.') }} KB · {{ date('d/m/Y H:i', $f['fecha']) }}</span>
                        <button type="button" wire:click="quitar(@js($f['nombre']))" class="text-gray-400 hover:text-red-600" title="Quitar">✕</button></div>
                @empty
                    @if ($aTratar)
                        <div class="text-sm text-gray-600">📄 Se usa «{{ $aTratar['nombre'] }}», subido en {{ $aTratar['periodo'] }} (trae el año entero). Sube otro si hay uno más nuevo.</div>
                    @else
                        <div class="text-sm text-gray-500">Arrastra aquí el Excel del 349 (pestañas de ventas y compras intracomunitarias, todo el año) o pulsa «Elegir…».</div>
                    @endif
                @endforelse
                <label class="m3-btn" style="margin-top:8px">＋ {{ $ficherosLista ? 'Cambiar fichero…' : 'Elegir…' }}<input type="file" wire:model="subida" accept=".xlsx,.xls,.csv,.txt,.pdf" style="display:none"></label>
                <span wire:loading wire:target="subida" class="text-xs text-indigo-700">subiendo…</span>
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-top:10px" class="text-sm">
                <span class="text-xs text-gray-500">Cabecera del 349 · contacto</span>
                <input type="text" wire:model.blur="aj.contacto" class="border-gray-300 rounded-md text-sm" style="width:20rem;padding:3px 8px">
                <label>teléfono <input type="text" wire:model.blur="aj.telefono" class="border-gray-300 rounded-md text-sm" style="width:7rem;padding:3px 8px"></label>
            </div>
        </div>

        @if ($res)
            @php $eur = fn ($v) => number_format((float) $v, 2, ',', '.'); $claves = ['S' => 'servicios prestados', 'I' => 'servicios adquiridos', 'E' => 'entregas', 'A' => 'adquisiciones']; @endphp
            <div class="m3-card">
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
                    <div class="m3-h" style="margin:0">4 · Fichero para Hacienda · {{ $periodo }}/{{ $ejercicio }}</div>
                    @if (! empty($res['fichero']))
                        <button type="button" wire:click="descargar" class="m3-btn" style="background:#059669;border-color:#059669;color:#fff;font-weight:700">⬇ {{ $res['fichero'] }}</button>
                        <button type="button" wire:click="guardarEnCarpeta" class="m3-btn" @disabled($tareaGuardar || ! $carpeta) title="Un PC lo deja en la carpeta de trabajo de OneDrive">📁 Guardar en la carpeta de trabajo</button>
                        @if ($tareaGuardar)<span class="text-xs text-indigo-700">⏳ un PC lo está guardando…</span>@endif
                    @endif
                </div>
                <div class="text-xs text-gray-500" style="margin-bottom:6px">Sede AEAT → modelo 349 → presentación mediante fichero: importa el .349 y revisa el borrador antes de presentar.</div>
                @foreach ($res['errores'] ?? [] as $e)<div class="text-sm" style="color:#b91c1c">⛔ {{ $e }}</div>@endforeach
                @foreach ($res['avisos'] ?? [] as $a)<div class="text-sm" style="color:#b45309">⚠️ {{ $a }}</div>@endforeach

                @if ($res['operadores'] ?? [])
                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:8px">
                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb"><th style="padding:3px 8px">Clave</th><th>País</th><th>NIF</th><th>Operador</th><th style="text-align:right">Facturas</th><th style="text-align:right;padding-right:8px">Base imponible</th></tr>
                        @foreach ($res['operadores'] as $o)
                            <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:3px 8px" title="{{ $claves[$o['clave']] ?? '' }}"><b>{{ $o['clave'] }}</b> <span class="text-xs text-gray-500">{{ $claves[$o['clave']] ?? '' }}</span></td>
                                <td>{{ $o['pais'] }}</td><td><x-copiar :texto="$o['nif']" titulo="Copiar el NIF" /></td><td><x-copiar :texto="$o['nombre']" titulo="Copiar el nombre" /></td><td style="text-align:right">{{ $o['facturas'] }}</td>
                                <td style="text-align:right;padding-right:8px;font-variant-numeric:tabular-nums"><x-copiar :texto="$eur($o['importe'])" titulo="Copiar el importe" /></td></tr>
                        @endforeach
                        <tr style="font-weight:700"><td colspan="5" style="padding:3px 8px">{{ $res['n_operadores'] }} operadores · total</td><td style="text-align:right;padding-right:8px">{{ $eur($res['total']) }}</td></tr>
                    </table>
                @endif

                @if ($res['lineas'] ?? [])
                    <details style="margin-top:10px"><summary class="text-sm text-gray-600" style="cursor:pointer">Ver las {{ count($res['lineas']) }} facturas del mes</summary>
                        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px">
                            @foreach ($res['lineas'] as $l)
                                <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:2px 8px">{{ $l['clave'] }}</td><td>{{ $l['fecha'] }}</td><td>{{ $l['factura'] }}</td><td>{{ $l['nif'] }}</td><td>{{ $l['nombre'] }}</td>
                                    <td style="text-align:right;padding-right:8px">{{ $eur($l['importe']) }}</td><td class="text-gray-400">fila {{ $l['fila'] }}</td></tr>
                            @endforeach
                        </table>
                    </details>
                @endif
            </div>
        @endif

        <div class="m3-card">
            <div class="m3-h">5 · Borrador de la AEAT y correo a Marta</div>
            <div class="m3-zona" x-data="{ encima: false }" :class="encima && 'encima'"
                 x-on:dragover.prevent="encima = true" x-on:dragleave="encima = false"
                 x-on:drop.prevent="encima = false; $wire.upload('subidaBorrador', $event.dataTransfer.files[0])">
                @if ($borradorInfo)
                    <div class="text-sm" style="display:flex;gap:8px;align-items:center">📄 <span style="flex:1;word-break:break-all">{{ $borradorInfo['nombre'] }}</span>
                        <span class="text-xs text-gray-500">{{ $borradorInfo['origen'] === 'onedrive' ? 'traído de la carpeta de trabajo' : 'subido' }} · {{ date('d/m/Y H:i', $borradorInfo['fecha']) }}</span>
                        <button type="button" wire:click="quitarBorrador" class="text-gray-400 hover:text-red-600" title="Quitar">✕</button></div>
                @else
                    <div class="text-sm text-gray-500">Sube aquí el PDF del borrador, o déjalo en la carpeta de trabajo y al pulsar «Enviar a Marta…» lo traigo yo
                        (un PDF con «borrador» y «{{ $periodo }}» en el nombre, o el descargado en los últimos 2 días).</div>
                @endif
                <label class="m3-btn" style="margin-top:8px">＋ {{ $borradorInfo ? 'Cambiar borrador…' : 'Subir borrador…' }}<input type="file" wire:model="subidaBorrador" accept=".pdf" style="display:none"></label>
                <span wire:loading wire:target="subidaBorrador" class="text-xs text-indigo-700">subiendo…</span>
            </div>

            <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                @if ($mailArmado === '')
                    <button type="button" wire:click="prepararMail" class="m3-btn" @disabled($tareaTraer)>✉ Enviar a Marta…</button>
                    @if ($tareaTraer)<span class="text-xs text-indigo-700">⏳ un PC está buscando el borrador en la carpeta de trabajo…</span>
                        <button type="button" wire:click="cancelarMail" class="m3-btn" style="padding:0 8px">dejar de esperar</button>@endif
                @endif
            </div>
            @if ($mailArmado !== '' && $borradorInfo)
                <div style="margin-top:10px;border:1px solid #fcd34d;background:#fffbeb;border-radius:8px;padding:10px 12px" class="text-sm">
                    <div><span class="text-gray-500">De:</span> {{ auth()->user()->email }} · <span class="text-gray-500">Para:</span> {{ \App\Support\ComunicarImpuestos::CC_DEFECTO }}</div>
                    <div><span class="text-gray-500">Asunto:</span> {{ $this->asuntoMail() }}</div>
                    <div><span class="text-gray-500">Adjunto:</span> 📎 {{ $borradorInfo['nombre'] }}</div>
                    <div style="white-space:pre-line;margin:8px 0;padding:8px;background:#fff;border-radius:6px">{{ \App\Http\Livewire\ImpuestoM349::TEXTO_MARTA }}</div>
                    <button type="button" wire:click="enviarMail" wire:confirm="¿Envío el correo a Marta con el borrador?" class="m3-btn" style="background:#059669;border-color:#059669;color:#fff;font-weight:700">✉ Confirmar y enviar</button>
                    <button type="button" wire:click="cancelarMail" class="m3-btn">Cancelar</button>
                </div>
            @endif
        </div>
    @endif
    </div>
</div>
