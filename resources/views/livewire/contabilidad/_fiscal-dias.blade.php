{{-- Modal «Fiscal Report y Cashier día a día» (18-oct-2026). Lo abre el check del mes (o «📅 Ver días») de la fila del Fiscal Report.
     Estado: lo calcula miniServ (FiscalReport/estadoDias.py) y lo sube con cada tarea; los botones piden a miniServ lanzar a mano cada paso. --}}
@if ($fiscalDiasMes)
    @php
        $fd = $this->fiscalDias;
        $mm = substr($fiscalDiasMes, 5);
        $marcaMes = ($this->checklistMarcas[$fiscalDiasMes]['fiscal_report']['estado'] ?? '') === 'ok';
        $color = fn ($e, $nt, $aun) => match (true) {
            $e === 'ok' => 'background:#22c55e;color:#fff',
            $e === 'dif' => 'background:#facc15;color:#713f12',
            $e === 'cero' => 'background:#bfdbfe;color:#1e3a8a',
            $e === 'pend' => 'background:#fde68a;color:#713f12',
            ($e === 'falta') && ($nt || $aun) => 'background:#e5e7eb;color:#6b7280',
            $e === 'falta' => 'background:#ef4444;color:#fff',
            default => 'background:#fff;color:#9ca3af;border:1px solid #e5e7eb',
        };
        $eur = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '.').' €';
        $boton = 'inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50';
    @endphp
    <div wire:key="fiscal-dias-modal" x-data="{ tab: 'fiscal' }" x-on:keydown.escape.window="$wire.cerrarFiscalDias()"
         style="position:fixed;inset:0;z-index:9990;background:rgba(17,24,39,.55);display:flex;align-items:flex-start;justify-content:center;padding:1.5rem;overflow:auto">
        <div class="bg-white rounded-lg shadow-xl" style="width:100%;max-width:62rem">
            <div class="flex flex-wrap items-center px-4 py-3 border-b border-gray-200 gap-x-3 gap-y-2">
                <h2 class="text-lg font-semibold text-gray-900">Fiscal Report y Cashier · {{ $mm }}/{{ substr($fiscalDiasMes, 0, 4) }}</h2>
                <span class="text-xs text-gray-500">{{ $fd ? 'estado de las '.\Carbon\Carbon::parse($fd['generado'])->format('H:i d/m') : 'sin estado todavía (se ha pedido a miniServ)…' }}</span>
                <span class="flex-1"></span>
                <button type="button" wire:click="alternarChecklist('fiscal_report', '{{ $fiscalDiasMes }}')" class="{{ $boton }}" title="El check del mes en el checklist">{{ $marcaMes ? '↺ Quitar el ✓ del mes' : '✓ Marcar mes completo' }}</button>
                <button type="button" wire:click="cerrarFiscalDias" class="text-2xl leading-none text-gray-400 hover:text-gray-700" title="Cerrar (Esc)">&times;</button>
            </div>
            <div class="flex flex-wrap items-center px-4 py-2 border-b border-gray-100 gap-x-2 gap-y-1 bg-gray-50">
                <span class="text-xs font-semibold text-gray-600">Lanzar a mano (en miniServ):</span>
                <button type="button" wire:click="lanzarFiscal('correos')" wire:loading.attr="disabled" class="{{ $boton }}" title="Baja los PDF y Excel de las tiendas y los semanales de Plein, y mueve los correos a Fiscal Cashier">📧 Leer correos</button>
                <button type="button" wire:click="lanzarFiscal('ctrol_fiscal')" wire:loading.attr="disabled" wire:confirm="Esto escribe en Ctrol Dinamico (tarda unos minutos y no debe estar abierto). ¿Seguro?" class="{{ $boton }}" title="Pasa el último Excel de cada tienda a su hoja «* FIS» y actualiza las dinámicas">📒 Fiscal → Ctrol</button>
                <button type="button" wire:click="lanzarFiscal('ctrol_cash')" wire:loading.attr="disabled" wire:confirm="Esto escribe en las pestañas *_Cash de Ctrol Dinamico (tarda unos minutos y no debe estar abierto). ¿Seguro?" class="{{ $boton }}" title="Crea las filas de los días con X-out y vuelca los semanales de Cashier; marca en amarillo lo que no coincida">📒 Cash → Ctrol</button>
                <button type="button" wire:click="pedirEstadoFiscal" wire:loading.attr="disabled" class="{{ $boton }}">↻ Actualizar estado</button>
                @if ($fd)
                    <span class="text-xs text-gray-500">
                        Excel pendientes de pasar: {{ $fd['pendientes_excel'] }} · semanales pendientes: {{ $fd['pendientes_cash'] }} · último paso a Ctrol: {{ $fd['ultimo_ctrol'] ?: '—' }} · reclamaciones: {{ $fd['modo_reclamar'] === 'enviar' ? 'se envían' : 'quedan en Borradores' }}
                    </span>
                @endif
            </div>
            @if ($fiscalMsg)
                <div class="px-4 py-1 text-xs text-indigo-800 bg-indigo-50">{{ $fiscalMsg }}</div>
            @endif
            @if (! $fd)
                <div class="p-6 text-sm text-gray-500">Todavía no hay estado de este mes. Se ha pedido a miniServ; aparecerá solo en unos segundos (si miniServ está apagado, cuando arranque).</div>
            @else
                <div class="flex items-center px-4 pt-3 gap-x-2">
                    <button type="button" x-on:click="tab = 'fiscal'" x-bind:class="tab === 'fiscal' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'" class="px-3 py-1 text-sm border border-gray-300 rounded">Fiscal Report</button>
                    <button type="button" x-on:click="tab = 'cash'" x-bind:class="tab === 'cash' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'" class="px-3 py-1 text-sm border border-gray-300 rounded">Cashier (pestañas *_Cash)</button>
                    <span class="flex flex-wrap items-center ml-3 text-xs text-gray-500 gap-x-2">
                        <span style="{{ $color('ok', false, false) }};padding:0 5px;border-radius:3px">hecho</span>
                        <span style="{{ $color('falta', false, false) }};padding:0 5px;border-radius:3px">falta</span>
                        <span style="{{ $color('falta', true, false) }};padding:0 5px;border-radius:3px">no se espera / aún pronto</span>
                        <span style="{{ $color('pend', false, false) }};padding:0 5px;border-radius:3px">pendiente de pasar</span>
                        <span style="{{ $color('cero', false, false) }};padding:0 5px;border-radius:3px">sin ventas</span>
                        <span style="{{ $color('dif', false, false) }};padding:0 5px;border-radius:3px">difiere (amarillo en Ctrol)</span>
                    </span>
                </div>
                <div class="p-4 overflow-x-auto">
                    <div x-show="tab === 'fiscal'">
                        <p class="mb-1 text-xs text-gray-500"><b>X</b> = X-out del día · <b>E</b> = día en el Excel del mes · <b>C</b> = ya en Ctrol Dinamico. Pasa el ratón por encima para ver importes.</p>
                        <table class="text-sm border-collapse">
                            <thead><tr class="text-xs text-left text-gray-600 bg-gray-50">
                                <th class="px-2 py-1 border">Día</th>
                                @foreach ($fd['tiendas'] as $t)<th class="px-2 py-1 text-center border">{{ $t }}</th>@endforeach
                                <th class="px-2 py-1 border"></th>
                            </tr></thead>
                            <tbody>
                            @foreach (array_reverse($fd['dias']) as $f)
                                @php
                                    $falta = false;
                                    foreach ($fd['tiendas'] as $t) { $c = $f['fiscal'][$t]; if (! $c['nt'] && ! $c['aun'] && ($c['x'] === 'falta' || $c['e'] === 'falta')) { $falta = true; } }
                                @endphp
                                <tr wire:key="fd-f-{{ $f['fecha'] }}" class="{{ in_array($f['dsem'], ['sáb', 'dom']) ? 'bg-gray-50' : '' }}">
                                    <td class="px-2 py-1 border whitespace-nowrap">{{ substr($f['fecha'], 8) }}/{{ $mm }} <span class="text-xs text-gray-500">{{ $f['dsem'] }}</span></td>
                                    @foreach ($fd['tiendas'] as $t)
                                        @php $c = $f['fiscal'][$t]; @endphp
                                        <td class="px-1 py-1 text-center border" title="{{ $t }} · X-out {{ $c['x'] === 'ok' ? $eur($c['xv']) : ($c['nt'] ? 'no se espera ('.$c['nt'].')' : 'falta') }} · Excel {{ $c['e'] === 'ok' ? $eur($c['ev']) : ($c['e'] === 'cero' ? 'sin ventas' : 'falta') }} · Ctrol {{ $c['c'] === 'ok' ? 'pasado' : ($c['c'] === 'pend' ? 'pendiente' : '—') }}">
                                            <span style="display:inline-flex;gap:2px">
                                                <span style="{{ $color($c['x'], $c['nt'], $c['aun']) }};padding:0 4px;border-radius:3px;font-size:11px">X</span>
                                                <span style="{{ $color($c['e'], $c['nt'], $c['aun']) }};padding:0 4px;border-radius:3px;font-size:11px">E</span>
                                                <span style="{{ $color($c['c'] === '' ? 'x' : $c['c'], false, false) }};padding:0 4px;border-radius:3px;font-size:11px">C</span>
                                            </span>
                                        </td>
                                    @endforeach
                                    <td class="px-1 py-1 border">
                                        @if ($falta)
                                            <button type="button" wire:click="lanzarFiscal('reclamar', '{{ $f['fecha'] }}')" wire:loading.attr="disabled"
                                                wire:confirm="Se escribe a las tiendas a las que falte algo del {{ substr($f['fecha'], 8) }}/{{ $mm }} ({{ $fd['modo_reclamar'] === 'enviar' ? 'SE ENVÍA' : 'quedará en Borradores' }}). ¿Seguro?"
                                                class="{{ $boton }}" title="Reclamar a las tiendas lo que falte de este día">✉ Reclamar</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @if (array_sum($fd['imagenes']))
                            <p class="mt-1 text-xs text-yellow-800">📷 X-out recibidos como imagen (no se pueden leer): {{ collect($fd['imagenes'])->filter()->map(fn ($n, $t) => "$t $n")->implode(' · ') }}</p>
                        @endif
                    </div>
                    <div x-show="tab === 'cash'" style="display:none">
                        <p class="mb-1 text-xs text-gray-500"><b>X</b> = X-out del día · <b>S</b> = día en un semanal de Cashier · <b>C</b> = fila en la pestaña *_Cash de Ctrol (amarillo: el semanal y el X-out no coinciden; el comentario de la celda lo explica). Los días anteriores a la primera pasada automática no consta en Ctrol.</p>
                        <table class="text-sm border-collapse">
                            <thead><tr class="text-xs text-left text-gray-600 bg-gray-50">
                                <th class="px-2 py-1 border">Día</th>
                                @foreach ($fd['tiendas'] as $t)<th class="px-2 py-1 text-center border">{{ $t }}</th>@endforeach
                            </tr></thead>
                            <tbody>
                            @foreach (array_reverse($fd['dias']) as $f)
                                <tr wire:key="fd-c-{{ $f['fecha'] }}" class="{{ in_array($f['dsem'], ['sáb', 'dom']) ? 'bg-gray-50' : '' }}">
                                    <td class="px-2 py-1 border whitespace-nowrap">{{ substr($f['fecha'], 8) }}/{{ $mm }} <span class="text-xs text-gray-500">{{ $f['dsem'] }}</span></td>
                                    @foreach ($fd['tiendas'] as $t)
                                        @php $c = $f['cash'][$t]; @endphp
                                        <td class="px-1 py-1 text-center border" title="{{ $t }} · X-out {{ $c['x'] === 'ok' ? 'sí' : 'no' }} · semanal {{ $c['s'] === 'ok' ? 'sí' : 'no' }} · Ctrol {{ ['ok' => 'fila creada'.($c['o'] ? ' ('.$c['o'].')' : ''), 'dif' => 'fila con diferencias marcadas', 'pend' => 'pendiente de pasar'][$c['c']] ?? '—' }}">
                                            <span style="display:inline-flex;gap:2px">
                                                <span style="{{ $color($c['x'], $c['nt'], $c['aun']) }};padding:0 4px;border-radius:3px;font-size:11px">X</span>
                                                <span style="{{ $color($c['s'], $c['nt'], $c['aun']) }};padding:0 4px;border-radius:3px;font-size:11px">S</span>
                                                <span style="{{ $color($c['c'] === '' ? 'x' : $c['c'], false, false) }};padding:0 4px;border-radius:3px;font-size:11px">C</span>
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
