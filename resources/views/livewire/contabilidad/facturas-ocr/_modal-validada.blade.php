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
                        {{-- Cómo queda contabilizada (16-oct-2026, Alex: «hacer clic en una validada y ver cómo la habías contabilizado») --}}
                        @php
                            $ls = array_values(array_filter($d['lineas'] ?? [], fn ($l) => ($l['base'] ?? '') !== '' && $l['base'] !== null));
                            $asiento = [];
                            foreach ($ls as $l) {
                                $asiento[] = [$d['contrapartida'] ?? '', 'Gasto (base'.((float) ($l['pct'] ?? 0) ? ' al '.$l['pct'].' %' : ', sin IVA').')', (float) $l['base'], null];
                                if ((float) ($l['cuota'] ?? 0) != 0) {
                                    $asiento[] = ['472…', 'IVA soportado '.$l['pct'].' %', (float) $l['cuota'], null];
                                }
                            }
                            if (! empty($d['cuota_retencion'])) {
                                $asiento[] = ['4751…', 'Retención '.($d['pct_retencion'] ?? '').' %', null, (float) $d['cuota_retencion']];
                            }
                            $asiento[] = [$d['cuenta'] ?? '', trim(($d['proveedor'] ?? '').' · '.($d['su_factura'] ?? ''), ' ·'), null, (float) ($d['total'] ?? 0)];
                            $sd = array_sum(array_map(fn ($x) => $x[2] ?? 0, $asiento));
                            $sh = array_sum(array_map(fn ($x) => $x[3] ?? 0, $asiento));
                        @endphp
                        <p class="mt-4 mb-1 text-xs font-semibold text-gray-700 uppercase">Cómo queda en SAGE</p>
                        <table class="w-full focr-tabla">
                            <thead><tr><th>Cuenta</th><th>Concepto</th><th style="text-align:right">Debe</th><th style="text-align:right">Haber</th></tr></thead>
                            @foreach ($asiento as $x)
                                <tr><td style="white-space:nowrap">{{ $x[0] }}</td><td class="text-xs">{{ $x[1] }}</td>
                                    <td style="text-align:right">{{ $x[2] !== null ? $eur($x[2]) : '' }}</td><td style="text-align:right">{{ $x[3] !== null ? $eur($x[3]) : '' }}</td></tr>
                            @endforeach
                            <tr><td colspan="2" style="text-align:right; font-weight:600">Sumas</td><td style="text-align:right; font-weight:600">{{ $eur($sd) }}</td><td style="text-align:right; font-weight:600">{{ $eur($sh) }}</td></tr>
                        </table>
                        <p class="mt-1 text-xs text-gray-500">
                            Cód. transacción <b>{{ ($d['codigo_transaccion'] ?? '') !== '' ? $d['codigo_transaccion'] : '—' }}</b>@if (($d['codigo_transaccion'] ?? '') === '38') (operación sin IVA: todo a la base)@endif
                            · Clave operación <b>{{ ($d['clave_operacion'] ?? '') !== '' ? $d['clave_operacion'] : '—' }}</b>
                            @if (! empty($d['canal'])) · Canal <b>{{ $d['canal'] }}</b>@endif
                            @if (abs($sd - $sh) >= 0.01) <span class="focr-chip c-falta">no cuadra ({{ $eur($sd - $sh) }})</span>@endif
                        </p>
                        <p class="mt-3 text-xs text-gray-500 break-all">{{ $v['ruta'] }}<br>
                            @if (str_starts_with($v['excel'] ?? '', 'Guardados/')) Excel guardado: {{ $v['excel'] }} @elseif (! empty($v['excel'])) En el Excel en curso @endif
                            @if (! empty($v['fila_excel'])) · fila {{ $v['fila_excel'] }} @endif</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
