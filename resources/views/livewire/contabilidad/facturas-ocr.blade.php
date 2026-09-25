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
        .focr-rev-pdf iframe { width:100%; height:100%; border:0; }
        .focr-rev-form { width:min(470px, 42vw); background:#f9fafb; overflow-y:auto; padding:.75rem; border-left:1px solid #374151; }
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
                            <button type="button" wire:click="explorar" class="focr-btn b-gris">📁 {{ $explorando ? 'Cerrar' : 'Buscar' }}</button>
                        </div>
                        @error('carpeta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        @if ($explorando)
                            <div class="p-2 mt-1 overflow-y-auto border border-gray-200 rounded" style="max-height:220px">
                                <button type="button" wire:click="entrar('..')" class="block w-full px-2 py-1 text-sm text-left rounded hover:bg-gray-100">⬆ ..</button>
                                @forelse ($subcarpetas as $s)
                                    <button type="button" wire:click="entrar(@js($s))" class="block w-full px-2 py-1 text-sm text-left rounded hover:bg-gray-100">📁 {{ $s }}</button>
                                @empty
                                    <div class="px-2 py-1 text-xs text-gray-400">(sin subcarpetas)</div>
                                @endforelse
                            </div>
                        @endif
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
                        <label class="focr-lbl">IVA del periodo anterior</label>
                        <select wire:model.live="presentado" class="focr-in">
                            <option value="0">Sin presentar</option>
                            <option value="1">Ya presentado</option>
                        </select>
                        <div class="mt-1 text-xs text-gray-500">Presentado: ya no se registra nada en ese periodo.</div>
                    </div>
                    <div>
                        <label class="focr-lbl">Cierre mensual: registrar a partir de</label>
                        <select wire:model.live="desde" class="focr-in">
                            <option value="">Sin cierre mensual</option>
                            @foreach ($mesesDesde as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-gray-500">Para clientes con cierres mensuales aunque el IVA sea trimestral.</div>
                    </div>
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
                        <span class="text-sm">Fecha de registro a partir del <b>{{ $primeraAbierta->format('d/m/Y') }}</b>
                            <span class="text-gray-500">(hoy {{ now()->format('d/m/Y') }})</span></span>
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
                                    <td style="max-width:260px; word-break:break-all">{{ basename($f['ruta']) }} @if (! empty($f['ocr'])) <span class="focr-chip c-revisar">OCR</span> @endif</td>
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
                                <button type="button" wire:click="descargar(@js('Output/'.$x))" class="focr-btn b-gris" style="padding:.2rem .5rem; font-size:.75rem">⬇ {{ $x }}</button>
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
                <div class="focr-rev-pdf" wire:key="pdf-{{ $actual['id'] }}-{{ md5($actual['ruta']) }}">
                    <iframe src="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $actual['id']]) }}?r={{ md5($actual['ruta']) }}#view=FitH"></iframe>
                </div>
                <div class="focr-rev-form">
                    @if (! empty($actual['avisos']))
                        <div class="p-2 mb-2 text-xs border rounded" style="background:#fffbeb; border-color:#fcd34d; color:#78350f">
                            @foreach ($actual['avisos'] as $a) <div>• {{ $a }}</div> @endforeach
                        </div>
                    @endif
                    @if (! empty($actual['motivo_proveedor']))
                        <div class="mb-1 text-xs text-gray-500">Proveedor reconocido por: {{ $actual['motivo_proveedor'] }}</div>
                    @endif
                    @error('validar') <pre class="p-2 mb-2 text-xs text-red-700 whitespace-pre-wrap border border-red-300 rounded bg-red-50">{{ $message }}</pre> @enderror

                    <div class="focr-sec">Proveedor</div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Cuenta
                                @if ($esNuevo) <span class="focr-chip c-revisar">proveedor nuevo</span> @endif
                            </label>
                            <div class="flex gap-1" style="align-items:center">
                                <input type="text" list="focr-provs" wire:model.change="form.cuenta" class="focr-in {{ $cl('proveedor') }}" placeholder="410…">
                                <button type="button" wire:click="cuentaNueva" class="focr-btn b-gris" style="padding:.3rem .45rem; font-size:.7rem; white-space:nowrap" title="Proveedor nuevo: siguiente 410 libre">+ Nueva</button>
                            </div>
                            <datalist id="focr-provs">
                                @foreach ($proveedores as $cta => $p)
                                    <option value="{{ $cta }}">{{ $p['razon'] ?? '' }} · {{ $p['nif'] ?? '' }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="focr-lbl">CIF europeo</label>
                            <input type="text" wire:model.blur="form.cif" class="focr-in">
                        </div>
                    </div>
                    <div class="fila">
                        <div style="grid-column:1/-1">
                            <label class="focr-lbl">Nombre</label>
                            <input type="text" wire:model.blur="form.proveedor" class="focr-in">
                        </div>
                    </div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Nombre corto para el fichero</label>
                            <input type="text" wire:model.blur="form.nombre_fichero" class="focr-in" placeholder="p.ej. Aquaservice">
                        </div>
                        <div>
                            <label class="focr-lbl">Contrapartida</label>
                            <input type="text" wire:model.blur="form.contrapartida" class="focr-in {{ ($form['contrapartida'] ?? '') === '' ? 'falta' : '' }}">
                        </div>
                    </div>

                    <div class="focr-sec">Factura</div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Nº factura (Su factura)</label>
                            <input type="text" wire:model.blur="form.su_factura" class="focr-in {{ $cl('su_factura') }}">
                        </div>
                        <div>
                            <label class="focr-lbl">Serie</label>
                            <input type="text" wire:model.blur="form.serie" class="focr-in">
                        </div>
                    </div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Fecha expedición</label>
                            <input type="date" wire:model.blur="form.fecha_expedicion" class="focr-in {{ $cl('fecha') }}">
                        </div>
                        <div>
                            <label class="focr-lbl">Fecha operación</label>
                            <input type="date" wire:model.blur="form.fecha_operacion" class="focr-in">
                        </div>
                    </div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Fecha registro</label>
                            <input type="date" wire:model.blur="form.fecha_registro" class="focr-in">
                        </div>
                        <div>
                            <label class="focr-lbl">Código transacción</label>
                            <input type="text" wire:model.blur="form.codigo_transaccion" class="focr-in">
                        </div>
                    </div>
                    <div class="fila">
                        <div style="grid-column:1/-1">
                            <label class="focr-lbl">Comentario SII</label>
                            <input type="text" wire:model.blur="form.comentario" maxlength="40" class="focr-in">
                        </div>
                    </div>

                    <div class="focr-sec">Importes {{ $isp ? '— inversión del sujeto pasivo: el total es la base' : '' }}</div>
                    <div class="fila">
                        <div>
                            <label class="focr-lbl">Importe factura (total)</label>
                            <input type="text" wire:model.blur="form.total" class="focr-in {{ $cl('importes') }}" style="text-align:right">
                        </div>
                        <div style="align-self:end">
                            @if ($descuadre !== null)
                                @if (abs($descuadre) < 0.015)
                                    <span class="focr-chip c-ok">✔ Cuadra</span>
                                @else
                                    <span class="focr-chip c-falta">Descuadre {{ $eur($descuadre) }}</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    @foreach ([0, 1, 2] as $i)
                        <div class="fila3">
                            <div><label class="focr-lbl">Base {{ $i + 1 }}</label><input type="text" wire:model.blur="form.lineas.{{ $i }}.base" class="focr-in" style="text-align:right"></div>
                            <div><label class="focr-lbl">% IVA</label><input type="text" wire:model.blur="form.lineas.{{ $i }}.pct" class="focr-in" style="text-align:right"></div>
                            <div><label class="focr-lbl">Cuota</label><input type="text" wire:model.blur="form.lineas.{{ $i }}.cuota" class="focr-in" style="text-align:right"></div>
                            <button type="button" wire:click="cuota({{ $i }})" class="focr-btn b-gris" style="padding:.3rem .45rem" title="Calcular la cuota con base y %">=</button>
                        </div>
                    @endforeach
                    <div class="fila3">
                        <div><label class="focr-lbl">Base retención</label><input type="text" wire:model.blur="form.base_retencion" class="focr-in" style="text-align:right"></div>
                        <div><label class="focr-lbl">% retención</label><input type="text" wire:model.blur="form.pct_retencion" class="focr-in" style="text-align:right"></div>
                        <div><label class="focr-lbl">Cuota retención</label><input type="text" wire:model.blur="form.cuota_retencion" class="focr-in" style="text-align:right"></div>
                        <div><label class="focr-lbl">Cód.</label><input type="text" wire:model.blur="form.codigo_retencion" class="focr-in" style="width:3.5rem"></div>
                    </div>
                    @if ($analitica)
                        <div class="fila">
                            <div>
                                <label class="focr-lbl">Código canal (analítica)</label>
                                <input type="text" wire:model.blur="form.canal" class="focr-in">
                            </div>
                        </div>
                    @endif

                    <div class="pt-3 mt-3 border-t border-gray-300">
                        @if ($actual['estado'] !== 'validada')
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="validar" wire:loading.attr="disabled" class="focr-btn b-verde" style="flex:1; justify-content:center">
                                    <span wire:loading.remove wire:target="validar">✅ Validar</span>
                                    <span wire:loading wire:target="validar">Guardando…</span>
                                </button>
                                <button type="button" wire:click="rechazar" wire:loading.attr="disabled" class="focr-btn b-rojo">✖ Rechazar</button>
                            </div>
                            <input type="text" wire:model.blur="motivo" class="mt-2 focr-in" placeholder="Motivo del rechazo (opcional)">
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button type="button" wire:click="releerOcr" wire:loading.attr="disabled" class="focr-btn b-gris" style="font-size:.75rem">
                                    <span wire:loading.remove wire:target="releerOcr">🔍 Volver a leer con OCR</span>
                                    <span wire:loading wire:target="releerOcr">Leyendo con OCR…</span>
                                </button>
                                @if (in_array($actual['estado'], ['rechazada', 'ilegible'], true))
                                    <button type="button" wire:click="reabrir('{{ $actual['id'] }}')" class="focr-btn b-gris" style="font-size:.75rem">↺ Volver a pendiente</button>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Validar: añade la fila a Output/PluginFacturas_Recibidas_&lt;mes de registro&gt;.xlsx, pone el nombre del proveedor
                                delante del fichero y lo mueve a la carpeta del mes de registro; aprende de lo corregido para las siguientes.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
