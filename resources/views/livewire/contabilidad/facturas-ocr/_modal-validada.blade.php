{{-- Modal: ver una factura validada sin salir del listado (PDF + lo validado, solo lectura). ‹ › recorren la lista filtrada --}}
@if ($verValidada !== '')
    @php
        $ids = array_column($validadas, 'id');
        $pos = array_search($verValidada, $ids, true);
        $v = $pos === false ? null : $validadas[$pos];
    @endphp
    @if ($v)
        @php $d = $v['datos']; $im = $importes($d); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(17,24,39,.55)"
             x-data x-on:keydown.escape.window="$wire.cerrarValidada()"
             x-on:keydown.arrow-left.window="if (! ['INPUT','TEXTAREA'].includes($event.target.tagName)) $wire.vecinaValidada(-1)"
             x-on:keydown.arrow-right.window="if (! ['INPUT','TEXTAREA'].includes($event.target.tagName)) $wire.vecinaValidada(1)">
            <div class="flex flex-col w-full bg-white rounded-lg shadow-xl" style="max-width:76rem; height:90vh" wire:key="mv-{{ $v['id'] }}">
                <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200">
                    <button type="button" wire:click="vecinaValidada(-1)" @disabled($pos === 0) class="px-2 py-1 text-sm border rounded disabled:opacity-30" title="Anterior (←)">‹</button>
                    <button type="button" wire:click="vecinaValidada(1)" @disabled($pos >= count($ids) - 1) class="px-2 py-1 text-sm border rounded disabled:opacity-30" title="Siguiente (→)">›</button>
                    <span class="text-xs text-gray-500">{{ $pos + 1 }} / {{ count($ids) }}</span>
                    <span class="font-semibold text-gray-900">{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }} · {{ $d['su_factura'] ?? '' }}</span>
                    <span class="focr-chip c-ok">✔ Validada el {{ $v['validada_el'] ?? '' }}</span>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="corregirValidada('{{ $v['id'] }}')" class="focr-btn b-gris" style="padding:.25rem .7rem"
                            title="Abre la factura en la pantalla de revisión, ya como pendiente, para corregirla (sale del Excel; si ya estaba guardado para SAGE, corrígela también allí)">↩ Volver a pendiente y corregir</button>
                    <button type="button" wire:click="cerrarValidada" class="px-3 py-1 text-sm font-semibold border rounded" title="Esc">✕ Cerrar</button>
                </div>
                <div class="flex flex-1 min-h-0">
                    <iframe src="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $v['id']]) }}" class="flex-1 min-w-0 border-0" style="background:#f3f4f6"></iframe>
                    <div class="overflow-y-auto p-3 text-sm border-l border-gray-200" style="width:26rem; flex:none">
                        <table class="w-full">
                            @foreach ([
                                'F. registro' => $fmt($d['fecha_registro'] ?? ''), 'F. factura' => $fmt($d['fecha_expedicion'] ?? ''),
                                'Proveedor' => trim(($d['cuenta'] ?? '').' '.($d['proveedor'] ?? '')), 'CIF' => $d['cif'] ?? '', 'Nº factura' => $d['su_factura'] ?? '',
                                'Contrapartida' => $d['contrapartida'] ?? '', 'Cód. transacción' => $d['codigo_transaccion'] ?? '', 'Clave operación' => $d['clave_operacion'] ?? '',
                                'Comentario' => $d['comentario'] ?? '',
                            ] as $k => $val)
                                @if ($val !== '' && $val !== null)
                                    <tr><td class="py-1 pr-2 text-gray-500" style="white-space:nowrap">{{ $k }}</td><td class="py-1 font-medium break-words">{{ $val }}</td></tr>
                                @endif
                            @endforeach
                        </table>
                        <table class="w-full mt-3 focr-tabla">
                            <thead><tr><th>Base</th><th style="text-align:right">% IVA</th><th style="text-align:right">Cuota</th></tr></thead>
                            @foreach (array_filter($d['lineas'] ?? [], fn ($l) => ($l['base'] ?? '') !== '' && $l['base'] !== null) as $l)
                                <tr><td>{{ $eur($l['base']) }}</td><td style="text-align:right">{{ $l['pct'] ?? '' }}</td><td style="text-align:right">{{ $eur($l['cuota'] ?? null) }}</td></tr>
                            @endforeach
                            <tr><td colspan="2" style="text-align:right; font-weight:600">Total</td><td style="text-align:right; font-weight:600">{{ $eur($d['total'] ?? null) }}</td></tr>
                        </table>
                        @if (! empty($d['cuota_retencion']))
                            <p class="mt-2 text-xs text-gray-600">Retención: {{ $eur($d['base_retencion'] ?? null) }} al {{ $d['pct_retencion'] ?? '' }} % = {{ $eur($d['cuota_retencion']) }}</p>
                        @endif
                        <p class="mt-3 text-xs text-gray-500 break-all">{{ $v['ruta'] }}<br>
                            @if (str_starts_with($v['excel'] ?? '', 'Guardados/')) Excel guardado: {{ $v['excel'] }} @elseif (! empty($v['excel'])) En el Excel en curso @endif
                            @if (! empty($v['fila_excel'])) · fila {{ $v['fila_excel'] }} @endif</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
