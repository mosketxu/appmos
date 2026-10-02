<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    {{-- Avisos de fin de proceso: pedido explícito 2026-09-07 -- NO bloqueantes
         (nada de alert(), se puede seguir usando la pantalla mientras están
         puestos) y superpuestos (varios a la vez, apilados), cada uno con su
         propia (x) para cerrarlo a mano -- no desaparecen solos. --}}
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

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4 space-y-6">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Procesos de Fashion IQ del mes:</span>
        <select wire:model.live="mes" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
        {{-- Saltos a cada zona de la pantalla (pedido 2026-09-25) --}}
        <span class="flex flex-wrap items-center text-sm font-normal gap-x-2 gap-y-1">
            @foreach (['rentas-variables' => 'Rentas Variables', 'cash-in-store' => 'Cash in store', 'pagos-fin-mes' => 'Pagos fin de mes'] as $ancla => $txt)
                <a href="#{{ $ancla }}"
                   onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('abrir-panel', {detail: '{{ $ancla }}'})); document.getElementById('{{ $ancla }}').scrollIntoView({behavior: 'smooth', block: 'start'})"
                   class="px-2 py-1 text-indigo-700 border border-indigo-200 rounded-md bg-indigo-50 hover:bg-indigo-100">{{ $txt }}</a>
            @endforeach
        </span>
    </h1>

    {{-- Procesos del mes + checklist de cierre (pedido 2026-10-01): una fila por
         proceso de monthlyFIQ/checklist.json, ⓘ con el detalle, botón si se lanza
         desde aquí y un check por mes (se marca solo al terminar bien; a mano:
         clic = ✓ → «no toca» → vacío). Las marcas viven en OneDrive. --}}
    <div id="procesos-mes" class="bg-white border rounded-lg shadow">
        <div class="flex flex-wrap items-center px-3 py-2 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <span class="text-xs text-gray-500">Los que se ejecutan desde aquí se marcan solos al terminar bien; el resto, con «Marcar» (mes del título, resaltado). Clic en un check de cualquier mes: ✓ → «no toca» → vacío. ⠿ = arrastrar para cambiar el orden.</span>
        </div>
        @php
            $marcas = $this->checklistMarcas;
            $mesSel = sprintf('2026-%02d', $mes);
            $botones = $this->procesos;
        @endphp
        <div class="overflow-x-auto">
        <table class="text-sm">
            <thead class="bg-gray-50">
                <tr class="text-xs font-medium text-left text-gray-500">
                    <th class="px-1 py-1"></th>
                    <th class="px-2 py-1">Proceso</th>
                    <th class="px-2 py-1">Acción</th>
                    @foreach ($this->checklistMeses as $k => $n)
                        <th class="px-1 py-1 text-center {{ $k === $mesSel ? 'bg-indigo-100 text-indigo-800' : '' }}" style="width:34px">{{ $n }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"
                x-data="{ arrastrando: null, sobre: null, debajo: false }"
                x-on:dragend.window="arrastrando = null; sobre = null">
                @forelse ($this->checklist as $p)
                    @php $id = $p['id']; $auto = $p['auto'] ?? null; @endphp
                    <tr wire:key="chk-{{ $id }}" @if ($id === 'cash_in_store') id="cash-in-store" @endif class="align-top hover:bg-gray-50"
                        x-on:dragover.prevent="if (arrastrando && arrastrando !== '{{ $id }}') { sobre = '{{ $id }}'; debajo = $event.clientY > $el.getBoundingClientRect().top + $el.offsetHeight / 2 }"
                        x-on:drop.prevent="if (arrastrando && arrastrando !== '{{ $id }}') $wire.moverChecklist(arrastrando, '{{ $id }}', debajo); arrastrando = null; sobre = null"
                        x-bind:style="(sobre === '{{ $id }}' ? (debajo ? 'box-shadow: inset 0 -2px 0 #6366f1;' : 'box-shadow: inset 0 2px 0 #6366f1;') : '') + (arrastrando === '{{ $id }}' ? 'opacity:.4' : '')">
                        <td class="px-1 py-1 text-gray-400 cursor-move select-none hover:text-gray-700" title="Arrastra para cambiar el orden"
                            draggable="true"
                            x-on:dragstart="arrastrando = '{{ $id }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $id }}'); $event.dataTransfer.setDragImage($el.parentElement, 10, 10)">⠿</td>
                        <td class="px-2 py-1" x-data="{ info: false }" style="width:340px;max-width:340px">
                            <div class="flex items-start gap-x-1">
                                <button type="button" x-on:click="info = !info" class="text-indigo-500 shrink-0 hover:text-indigo-700" title="Detalle">ⓘ</button>
                                <span class="{{ $auto ? 'font-medium text-gray-900' : 'text-gray-700' }}">{{ $p['nombre'] }}</span>
                            </div>
                            <div x-show="info" style="display:none;max-width:420px" x-on:click.outside="info = false" class="p-2 mt-1 text-xs text-gray-700 border border-indigo-200 rounded bg-indigo-50">{{ $p['detalle'] ?? '' }}</div>
                        </td>
                        <td class="px-2 py-1">
                            @if ($auto === 'tabla' && isset($botones[$id]))
                                <div class="flex items-start gap-x-2">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50"
                                        wire:click="ejecutar('{{ $id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="ejecutar('{{ $id }}')"
                                        onclick="return confirm('Esto escribe sobre los ficheros reales. ¿Seguro?')"
                                    >
                                        <span wire:loading.remove wire:target="ejecutar('{{ $id }}')">▶ Ejecutar</span>
                                        <span wire:loading wire:target="ejecutar('{{ $id }}')">⏳…</span>
                                    </button>
                                    @if (! empty($resultados[$id]))
                                        <div class="flex flex-col min-w-0 gap-y-1 pt-1">
                                            @foreach ($resultados[$id] as $r)
                                                <x-contabilidad.resultado-fichero :r="$r" />
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @elseif ($auto === 'cash-in-store')
                                <div class="flex flex-wrap items-center gap-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="buscarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore">
                                        <span wire:loading.remove wire:target="buscarCashInStore">🔎 Buscar</span>
                                        <span wire:loading wire:target="buscarCashInStore">⏳…</span>
                                    </button>
                                    @if ($this->cisFaltan)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="pedirCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            onclick="return confirm('¿Mandar YA el correo pidiendo el efectivo a {{ implode(', ', $this->cisFaltan) }}?')">
                                            ✉ Pedir ({{ implode(', ', $this->cisFaltan) }})
                                        </button>
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="recordarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            title="Responder a la petición ya enviada: deja el borrador en Outlook para revisarlo y enviarlo">
                                            🔔 Recordatorio (borrador)
                                        </button>
                                    @endif
                                    @if ($cisFilas)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-white bg-indigo-600 border border-indigo-600 rounded shadow-sm hover:bg-indigo-700 disabled:opacity-50" wire:click="grabarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            onclick="return confirm('¿Escribir estos importes en Cash End Month de Ctrol Dinamico? (cierra antes el Excel si lo tienes abierto)')">
                                            💾 Grabar
                                        </button>
                                    @endif
                                    @if ($cisFilas)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="$toggle('cisAbierto')">{{ $cisAbierto ? '▴ Plegar' : '▾ Ver importes' }}</button>
                                    @endif
                                    <span wire:loading wire:target="pedirCashInStore,recordarCashInStore,grabarCashInStore" class="text-xs text-gray-500">⏳…</span>
                                    @foreach ($resultados['cis'] ?? [] as $r)
                                        <x-contabilidad.resultado-fichero :r="$r" />
                                    @endforeach
                                </div>
                            @elseif ($auto && $auto !== 'pendiente')
                                <button type="button" x-on:click="$dispatch('abrir-panel', '{{ $auto }}')" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50">abrir ↓</button>
                            @else
                                {{-- manuales y los «pendiente de montar»: mientras tanto, se marcan a mano --}}
                                @php $hecho = ($marcas[$mesSel][$id]['estado'] ?? '') === 'ok'; @endphp
                                <button type="button" wire:click="marcarMesActual('{{ $id }}')" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" title="Marca (o desmarca) el check del mes {{ $this->checklistMeses[$mesSel] }}">
                                    {{ $hecho ? '↺ Desmarcar' : '✓ Marcar' }}
                                </button>
                                @if ($auto === 'pendiente')
                                    <span class="ml-1 text-xs text-gray-400">pendiente de montar</span>
                                @endif
                            @endif
                        </td>
                        @foreach ($this->checklistMeses as $k => $n)
                            @php $m = $marcas[$k][$id] ?? null; @endphp
                            <td class="px-1 py-1 text-center {{ $k === $mesSel ? 'bg-indigo-50' : '' }}">
                                <button type="button" wire:click="alternarChecklist('{{ $id }}', '{{ $k }}')"
                                    title="{{ $m ? (($m['estado'] === 'ok' ? 'Hecho' : 'No toca') . ($m['cuando'] ? ' · ' . $m['cuando'] : '') . ' · ' . $m['como']) : 'Sin hacer' }}"
                                    class="inline-flex items-center justify-center w-5 h-5 text-xs border rounded {{ ($m['estado'] ?? '') === 'ok' ? 'bg-green-500 border-green-600 text-white' : (($m['estado'] ?? '') === 'na' ? 'bg-gray-200 border-gray-300 text-gray-500' : 'bg-white border-gray-300') }}">
                                    {{ ($m['estado'] ?? '') === 'ok' ? '✓' : (($m['estado'] ?? '') === 'na' ? '–' : '') }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                    @if ($id === 'cash_in_store' && $cisFilas && $cisAbierto)
                        <tr wire:key="chk-cis-detalle">
                            <td colspan="{{ 3 + count($this->checklistMeses) }}" class="px-2 pb-2">
                                <table class="text-sm text-gray-800 border border-collapse border-gray-300">
                                    <tr class="text-xs text-left text-gray-600 bg-gray-50">
                                        <th class="px-2 py-0.5 border">Tienda</th>
                                        <th class="px-2 py-0.5 border">Cash (Prosegur)</th>
                                        <th class="px-2 py-0.5 border">Petty Cash</th>
                                        <th class="px-2 py-0.5 border">Correo ({{ \Carbon\Carbon::create(2026, $mes, 1)->locale('es')->monthName }})</th>
                                    </tr>
                                    @foreach ($cisFilas as $t => $r)
                                        <tr wire:key="cis-{{ $t }}" class="align-top {{ $r['encontrado'] ? '' : 'bg-red-50' }}">
                                            <td class="px-2 py-0.5 font-semibold border">{{ $t }}</td>
                                            <td class="px-2 py-0.5 border">
                                                <input type="text" wire:model.blur="cisFilas.{{ $t }}.cash" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:100px">
                                            </td>
                                            <td class="px-2 py-0.5 border">
                                                <input type="text" wire:model.blur="cisFilas.{{ $t }}.petty" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:90px">
                                            </td>
                                            <td class="px-2 py-0.5 text-xs border">
                                                @if ($r['encontrado'])
                                                    <div class="font-medium">«{{ $r['asunto'] }}» · {{ $r['recibido'] }}</div>
                                                    <pre class="text-xs text-gray-600 whitespace-pre-wrap" style="max-height:6rem;overflow:auto">{{ $r['texto'] }}</pre>
                                                @else
                                                    <span class="text-red-700">Sin dato: no ha mandado el efectivo → «Pedir»</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="17" class="px-3 py-2 text-sm text-gray-500">No encuentro monthlyFIQ/checklist.json en este equipo.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div id="rentas-variables" class="border-t border-gray-200" x-data="{ open: false }"
             x-on:abrir-panel.window="if ($event.detail === 'rentas-variables') { open = true; $nextTick(() => $el.scrollIntoView({behavior: 'smooth', block: 'start'})) }">
            <button type="button" x-on:click="open = !open" class="flex items-center w-full px-3 py-2 text-sm font-semibold text-left text-gray-800 gap-x-2 hover:bg-gray-50">
                <span x-text="open ? '▾' : '▸'">▸</span> Rentas Variables · cálculos del alquiler variable, turnover y envío a los arrendadores (BCN, MAL)
            </button>
            <div x-show="open" style="display:none">
    <div class="p-3">
        <div class="flex flex-wrap gap-8">
            {{-- IZQUIERDA 35%: Cálculos + Turnover (usa el "Mes" del título) --}}
            <div style="flex:0 0 35%;min-width:280px">
                <h2 class="text-lg font-semibold text-gray-900">Rentas Variables</h2>
                <p class="mt-1 mb-3 text-xs text-gray-500">Rellena CalculosRentasVbles2026.xlsx del mes y el fichero del turnover de BCN y MAL.</p>
                <div class="mt-1 space-y-1">
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="BCN" class="mr-2 border-gray-300 rounded"> Barcelona
                    </label>
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="MAL" class="mr-2 border-gray-300 rounded"> Málaga
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> La Roca <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> Las Rozas <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                </div>

                <div class="mt-3">
                    <x-button.secondary
                        wire:click="ejecutarRvCalculosYDeclaracion"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvCalculosYDeclaracion"
                        onclick="return confirm('Cálculos (4 tiendas) + Turnover de las tiendas marcadas, para el mes seleccionado. Escribe sobre los ficheros reales. ¿Seguro?')"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvCalculosYDeclaracion">Cálculos + Turnover</span>
                        <span wire:loading wire:target="ejecutarRvCalculosYDeclaracion">⏳ Ejecutando…</span>
                    </x-button.secondary>
                </div>

                @if (! empty($resultados['rv']))
                    <div class="flex flex-col mt-2 gap-y-1">
                        @foreach ($resultados['rv'] as $r)
                            <x-contabilidad.resultado-fichero :r="$r" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DERECHA: Envío del correo -- ocupa el resto que quede libre --}}
            <div style="flex:1 1 340px;min-width:340px">
                <h3 class="text-sm font-semibold text-gray-700">Envío del correo</h3>

                {{-- tarjetas de producción --}}
                <div class="flex flex-col mt-2 gap-y-1">
                    @foreach ($this->rvTiendasArrendador as $k => $label)
                        {{-- To, input, CC, input y el botón en la MISMA fila;
                             "Corrección" debajo (w-full fuerza el salto de línea). --}}
                        <div wire:key="rv-envio-{{ $k }}" class="flex flex-wrap items-center p-2 border border-gray-200 rounded gap-x-2 gap-y-1">
                            <div class="mr-4 text-sm font-semibold text-gray-800 shrink-0 w-14">{{ $label }}</div>
                            <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.to" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.cc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <x-button.secondary
                                class="shrink-0"
                                style="padding-top:.25rem;padding-bottom:.25rem"
                                wire:click="ejecutarRvEnvio('{{ $k }}')"
                                wire:loading.attr="disabled"
                                wire:target="ejecutarRvEnvio('{{ $k }}')"
                                onclick="return confirm('¿Mandar el correo de {{ $label }} a sus destinatarios REALES?')"
                            >
                                <span wire:loading.remove wire:target="ejecutarRvEnvio('{{ $k }}')">Enviar</span>
                                <span wire:loading wire:target="ejecutarRvEnvio('{{ $k }}')">⏳ Enviando…</span>
                            </x-button.secondary>
                            <label class="flex items-center w-full text-xs text-gray-700">
                                <input type="checkbox" wire:model="rvEnvio.{{ $k }}.correccion" class="mr-1 border-gray-300 rounded"> Corrección
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- correo de prueba: debajo de las dos tiendas, sin línea ni fondo --}}
                <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
                    <label class="text-xs font-medium text-gray-600 shrink-0">Correo de prueba</label>
                    <input type="email" wire:model="rvEmailPrueba" placeholder="tucorreo@ejemplo.com" class="flex-1 text-sm border-gray-300 rounded-md shadow-sm" style="min-width:220px">
                    <x-button.secondary
                        wire:click="ejecutarRvEnvioPrueba"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvEnvioPrueba"
                        onclick="return confirm('¿Mandar los ficheros de Barcelona y Málaga al correo de prueba?')"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvEnvioPrueba">Enviar a correo de prueba</span>
                        <span wire:loading wire:target="ejecutarRvEnvioPrueba">⏳ Enviando…</span>
                    </x-button.secondary>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
        <div id="pagos-fin-mes" class="border-t border-gray-200" x-data="{ open: false }"
             x-on:abrir-panel.window="if ($event.detail === 'pagos-fin-mes') { open = true; $nextTick(() => $el.scrollIntoView({behavior: 'smooth', block: 'start'})) }">
            <button type="button" x-on:click="open = !open" class="flex items-center w-full px-3 py-2 text-sm font-semibold text-left text-gray-800 gap-x-2 hover:bg-gray-50">
                <span x-text="open ? '▾' : '▸'">▸</span> Pagos fin de mes · correo a Plein con el saldo de BBVA, lo que hay que subir y los cargos previstos
            </button>
            <div x-show="open" style="display:none">
    {{-- Correo mensual a Plein con los cargos de fin/principio de mes en BBVA
         (pedido 2026-09-25). Solo cambian estos datos; el resto sale de
         monthlyFIQ/pagosFinMes.json. --}}
    <div class="p-3">
        <h2 class="flex flex-wrap items-center text-lg font-semibold text-gray-900 gap-x-3">
            <span>Pagos fin de mes · correo a Plein</span>
            {{-- mismo mes que el del título (pedido 2026-10-01) --}}
            <span class="text-base font-normal text-gray-600">{{ \Carbon\Carbon::create(2026, $pfMes, 1)->locale('en')->monthName }}</span>
            @if ($pfEnviado)
                <span class="text-xs font-normal text-green-700">✓ enviado a Plein el {{ $pfEnviado }}</span>
            @endif
        </h2>
        <div class="flex flex-wrap gap-6">
        {{-- IZQUIERDA: datos, texto, destinatarios y botones --}}
        <div style="flex:1 1 380px;min-width:0">
        <p class="mt-1 mb-3 text-xs text-gray-500">"End and begining of month payments &lt;mes&gt;." Importes en K (vale 85,9 o 85.912,39). "Buscar importes" rellena VAT TAX (PDF del 303 del mes anterior en _Impuestos), Social Security (correo de Jordi en Outlook, carpeta Laboral) y Payrolls (remesa RM*.xml); todo se puede cambiar a mano. Si se envía con VAT o SS vacíos, se buscan solos. Día de cargo vacío = último día hábil del mes.</p>
        <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
            @foreach (['pfSaldo' => 'Saldo BBVA hoy', 'pfIva' => 'VAT TAX', 'pfSs' => 'Social Security', 'pfNominas' => 'Payrolls', 'pfCargo' => 'Día de cargo'] as $campo => $label)
                <label class="flex flex-col text-xs font-medium text-gray-600">
                    {{ $label }}
                    <input type="text" wire:model.blur="{{ $campo }}" class="mt-1 text-sm border-gray-300 rounded shadow-sm" style="width:{{ $campo === 'pfCargo' ? '150px' : '100px' }}"
                        placeholder="{{ $campo === 'pfNominas' ? 'remesa' : ($campo === 'pfCargo' ? 'último hábil' : '') }}">
                </label>
            @endforeach
        </div>
        <div class="flex flex-col mt-3 gap-y-2">
            <label class="flex flex-col text-xs font-medium text-gray-600">
                Texto antes de la tabla (línea en blanco = párrafo nuevo)
                <textarea wire:model="pfTexto" rows="4" class="mt-1 text-sm border-gray-300 rounded shadow-sm"></textarea>
            </label>
            <div class="flex flex-wrap items-center gap-x-2">
                <label class="flex items-center text-xs font-medium text-gray-600 shrink-0">
                    <input type="checkbox" wire:model="pfIncluirDestacado" class="mr-1 border-gray-300 rounded"> Frase en negrita
                </label>
                <input type="text" wire:model="pfDestacado" class="flex-1 min-w-0 text-sm font-semibold border-gray-300 rounded shadow-sm">
            </div>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                <input type="text" wire:model="pfTo" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                <input type="text" wire:model="pfCc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
            </div>
            <p class="text-xs text-gray-500">Al "Enviar a Plein", el texto, la frase y los destinatarios quedan guardados como base del mes siguiente.</p>
        </div>
        <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
            <x-button.secondary wire:click="buscarImportesPagosFinMes" wire:loading.attr="disabled" wire:target="buscarImportesPagosFinMes,ejecutarPagosFinMes">
                <span wire:loading.remove wire:target="buscarImportesPagosFinMes">🔎 Buscar importes</span>
                <span wire:loading wire:target="buscarImportesPagosFinMes">⏳ Buscando…</span>
            </x-button.secondary>
            <x-button.secondary wire:click="ejecutarPagosFinMes('vista')" wire:loading.attr="disabled" wire:target="ejecutarPagosFinMes">
                Vista previa
            </x-button.secondary>
            <label class="text-xs font-medium text-gray-600 shrink-0">Correo de prueba</label>
            <input type="email" wire:model="pfEmailPrueba" class="text-sm border-gray-300 rounded-md shadow-sm" style="min-width:220px">
            <x-button.secondary wire:click="ejecutarPagosFinMes('prueba')" wire:loading.attr="disabled" wire:target="ejecutarPagosFinMes">
                Enviar a prueba
            </x-button.secondary>
            <x-button.primary
                wire:click="ejecutarPagosFinMes('real')"
                wire:loading.attr="disabled"
                wire:target="ejecutarPagosFinMes"
                onclick="return confirm('¿Mandar el correo de pagos a los destinatarios REALES (To/CC de arriba)?')"
            >
                Enviar a Plein
            </x-button.primary>
            <span wire:loading wire:target="ejecutarPagosFinMes" class="text-sm text-gray-500">⏳ …</span>
        </div>
        @if (! empty($resultados['pf']))
            <div class="flex flex-col mt-2 gap-y-1">
                @foreach ($resultados['pf'] as $r)
                    <x-contabilidad.resultado-fichero :r="$r" />
                @endforeach
            </div>
        @endif
        </div>

        {{-- DERECHA: las líneas de importes tal como saldrán en el correo --}}
        <div style="flex:1 1 380px;min-width:0">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Importes del correo (K)</h3>
            @php $pl = $this->pfLineas; @endphp
            <table class="text-sm text-gray-800">
                @foreach ($pl['cabecera'] as [$t, $v, $u])
                    <tr>
                        <td class="py-0.5 pr-3 {{ $loop->first ? '' : 'font-semibold' }}">{{ $t }}</td>
                        <td class="py-0.5 px-2 text-right font-semibold">{{ $v }}</td>
                        <td class="py-0.5 px-2">{{ $u }}</td>
                    </tr>
                @endforeach
            </table>
            <table class="w-full mt-3 text-sm text-gray-800 border border-collapse border-gray-400">
                @foreach ($pl['filas'] as [$a, $b, $c, $d])
                    <tr>
                        <td class="px-2 py-0.5 border border-gray-400">{{ $a }}</td>
                        <td class="px-2 py-0.5 border border-gray-400">{{ $b }}</td>
                        <td class="px-2 py-0.5 text-right border border-gray-400">{{ $c }}</td>
                        <td class="px-2 py-0.5 border border-gray-400">{{ $d }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="px-2 py-0.5 border border-gray-400">Total</td>
                    <td class="px-2 py-0.5 border border-gray-400"></td>
                    <td class="px-2 py-0.5 text-right border border-gray-400">{{ $pl['total'] }}</td>
                    <td class="px-2 py-0.5 border border-gray-400">K</td>
                </tr>
            </table>
            <p class="mt-1 text-xs text-gray-500">"—" = vacío (VAT y SS se buscan solos al enviar). Se actualiza al salir de cada campo. Las filas fijas están en monthlyFIQ/pagosFinMes.json.</p>
        </div>
        </div>

        {{-- Recordatorio "Kindly reminder and update" sobre el correo ya enviado
             ese mes (pedido 2026-09-28). No guarda nada: el mes que viene sale
             con los importes de siempre. --}}
        <div class="pt-3 mt-4 border-t">
            <h3 class="flex flex-wrap items-center text-sm font-semibold text-gray-700 gap-x-3">
                <span>Recordatorio · "Kindly reminder and update"</span>
                <x-button.secondary wire:click="leerEnviadoPagosFinMes" wire:loading.attr="disabled" wire:target="leerEnviadoPagosFinMes,ejecutarRecordatorioPagosFinMes">
                    <span wire:loading.remove wire:target="leerEnviadoPagosFinMes">📥 Cargar correo enviado</span>
                    <span wire:loading wire:target="leerEnviadoPagosFinMes">⏳ Leyendo Outlook…</span>
                </x-button.secondary>
                @if ($pfRecOriginal)
                    <span class="text-xs font-normal text-gray-500">Responde a: {{ $pfRecOriginal }}</span>
                @endif
            </h3>
            <p class="mt-1 text-xs text-gray-500">Lee de Enviados de Outlook el correo de pagos del mes elegido arriba. Marca qué está pagado, retoca importes y pon el saldo de hoy. "Preparar en Outlook" deja un "Responder a todos" en Borradores (con el original citado debajo) para revisarlo y enviarlo desde allí. Los cambios de importes no se guardan.</p>
            @if ($pfRecFilas)
                @php $rt = $this->pfRecTotales; @endphp
                <div class="flex flex-wrap gap-6 mt-2">
                    <div style="flex:1 1 380px;min-width:0">
                        <label class="flex flex-col text-xs font-medium text-gray-600">
                            Texto antes de la tabla (línea en blanco = párrafo nuevo)
                            <textarea wire:model="pfRecTexto" rows="5" class="mt-1 text-sm border-gray-300 rounded shadow-sm"></textarea>
                        </label>
                        <div class="flex flex-wrap items-end mt-2 gap-x-4 gap-y-2">
                            <label class="flex flex-col text-xs font-medium text-gray-600">
                                Saldo BBVA hoy
                                <input type="text" wire:model.blur="pfRecSaldo" class="mt-1 text-sm border-gray-300 rounded shadow-sm" style="width:100px">
                            </label>
                            <table class="text-sm text-gray-800">
                                <tr><td class="pr-3">Pending payments</td><td class="px-2 font-semibold text-right">{{ $rt['pendiente'] }}</td><td>K</td></tr>
                                <tr><td class="pr-3">Upload for pending taxes + payrolls</td><td class="px-2 font-semibold text-right">{{ $rt['subirImp'] }}</td><td>K</td></tr>
                                <tr><td class="pr-3">Total amount to upload</td><td class="px-2 font-semibold text-right">{{ $rt['subirTotal'] }}</td><td>K</td></tr>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
                            <x-button.secondary wire:click="ejecutarRecordatorioPagosFinMes('vista')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Vista previa
                            </x-button.secondary>
                            <x-button.secondary wire:click="ejecutarRecordatorioPagosFinMes('prueba')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Enviar a prueba
                            </x-button.secondary>
                            <x-button.primary wire:click="ejecutarRecordatorioPagosFinMes('real')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Preparar en Outlook
                            </x-button.primary>
                            <span wire:loading wire:target="ejecutarRecordatorioPagosFinMes" class="text-sm text-gray-500">⏳ …</span>
                        </div>
                        @if (! empty($resultados['pfRec']))
                            <div class="flex flex-col mt-2 gap-y-1">
                                @foreach ($resultados['pfRec'] as $r)
                                    <x-contabilidad.resultado-fichero :r="$r" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div style="flex:1 1 380px;min-width:0">
                        <table class="w-full text-sm text-gray-800 border border-collapse border-gray-400">
                            @foreach ($pfRecFilas as $i => $f)
                                <tr wire:key="pfrec-{{ $i }}" class="{{ $f[4] === 'paid' ? 'bg-green-50' : '' }}">
                                    <td class="px-2 py-0.5 border border-gray-400">{{ $f[0] }}</td>
                                    <td class="px-2 py-0.5 border border-gray-400">{{ $f[1] }}</td>
                                    <td class="px-1 py-0.5 border border-gray-400">
                                        <input type="text" wire:model.blur="pfRecFilas.{{ $i }}.2" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:70px">
                                    </td>
                                    <td class="px-1 py-0.5 border border-gray-400">
                                        <select wire:model.live="pfRecFilas.{{ $i }}.4" class="py-0 pl-1 pr-6 text-sm border-gray-300 rounded {{ $f[4] === 'paid' ? 'text-green-700' : 'text-red-700' }}">
                                            <option value="pending">Pending</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
            </div>
        </div>
    </div>




    @include('livewire.contabilidad._salida')
    </div>
</div>
