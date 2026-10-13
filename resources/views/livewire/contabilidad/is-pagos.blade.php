<div x-data="{ avisos: [] }" x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 p-3 bg-white border border-gray-300 rounded-lg shadow-lg">
                <pre class="flex-1 font-sans text-sm text-gray-800 whitespace-pre-wrap" x-text="aviso.mensaje"></pre>
                <button type="button" class="text-lg leading-none text-gray-400 shrink-0 hover:text-gray-700" x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)">&times;</button>
            </div>
        </template>
    </div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'impuestos.pago-cuenta'])
    @include('livewire._subnav_impuestos', ['activa' => 'pago202'])
    <div class="p-4">
        @php $euros = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '.'); @endphp
        <div class="space-y-3 bg-white border border-gray-300 rounded-lg shadow-sm">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Pagos a cuenta del IS (modelo 202)</h2>
                <label class="text-sm text-gray-600">Ejercicio
                    <input type="number" wire:model.live.debounce.600ms="ejercicio" min="2024" max="2099" class="w-24 ml-1 border-gray-300 rounded-md shadow-sm">
                </label>
                <label class="text-sm text-gray-600">Periodo
                    <select wire:model.live="periodo" class="ml-1 border-gray-300 rounded-md shadow-sm">
                        <option value="1P">1P (abril)</option>
                        <option value="2P">2P (octubre)</option>
                        <option value="3P">3P (diciembre)</option>
                    </select>
                </label>
                <span class="text-sm text-gray-600">Cliente
                    <x-buscador-select model="clienteId" :valor="$clienteId" :opciones="$opciones" ancho="24rem" />
                </span>
                @if ($this->puedeTodas())
                    <button type="button" wire:click="$toggle('todasEmpresas')" class="px-3 py-1 text-sm border rounded-md {{ $todasEmpresas ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700' }}"
                        title="Por defecto solo ves las empresas que llevas tú">{{ $todasEmpresas ? '👥 Viendo todas' : '👤 Solo las mías · ver todas' }}</button>
                @endif
                @if ($clienteId === 'todos')
                    <label class="flex items-center gap-1 text-sm text-gray-600">Estado
                        <select wire:model.live="filtroEstado" class="py-1 text-sm border-gray-300 rounded-md">
                            <option value="activos">A presentar</option>
                            <option value="todos">Todos</option>
                            <option value="sin">Sin marcar</option>
                            @foreach (\App\Support\Impuestos::ESTADOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <span class="ml-auto text-xs text-gray-500">Clientes con el 202 en la pestaña Impuestos. Modalidad 40.2 (18 % de la cuota del IS {{ $ejercicio - 1 }}).</span>
            </div>

            <div class="px-4 text-xs text-gray-600">
                <b>Modalidad 40.2</b> (hasta 6 M€): el pago se calcula con la <b>casilla 00599</b> del IS {{ $ejercicio - 1 }} (cuota a ingresar, ya descontadas deducciones, bonificaciones y retenciones) <b>× 18 %</b>; es el mismo importe en 1P, 2P y 3P. Si la casilla 00599 no es positiva, no hay pago.
                Sube el <b>IS {{ $ejercicio - 1 }} presentado</b> (PDF) de cada cliente; de ahí salen la cuota, el CNAE y la cifra de negocios. Si el CNAE
                debe ser el de {{ $ejercicio }} (CNAE-2025), sube también un 202 de este año o escríbelo en la casilla. Se genera el fichero <code>.202</code>
                para <b>importar en el formulario de la AEAT</b>: allí sacas el borrador y presentas. Aquí no se presenta nada.
                Las empresas de <b>más de 6 M€ de cifra de negocios</b> van por la <b>modalidad 40.3</b> (base imponible acumulada del año): además hace falta el
                <b>sumas y saldos de SAGE acumulado desde el 1 de enero</b> a 31-mar (1P), 30-sep (2P) o 30-nov (3P) — .xlsx o .csv —, que se sube en la columna «Sumas y saldos» (también vale la Cuenta de Explotación, PL; o escribe el resultado
                contable a mano en «ajustes 40.3»). Los pagos anteriores (1P) se sacan solos del 202 de este año si lo subes. <b>Revisa siempre el borrador en la AEAT.</b>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left"></th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-left">IS {{ $ejercicio - 1 }} (PDF)</th>
                            <th class="px-3 py-2 text-left">202 de {{ $ejercicio }} (opc.)</th>
                            <th class="px-3 py-2 text-left">Sumas y saldos a {{ ['1P' => '31-mar', '2P' => '30-sep', '3P' => '30-nov'][$periodo] ?? '' }} <span class="normal-case">(solo 40.3)</span></th>
                            <th class="px-3 py-2 text-left">CNAE</th>
                            <th class="px-3 py-2 text-right">Cuota IS</th>
                            <th class="px-3 py-2 text-right">A pagar {{ $periodo }}</th>
                            <th class="px-3 py-2 text-left">Resultado</th>
                            <th class="px-3 py-2 text-left"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clientes as $c)
                            @php $r = $c['resultado']; @endphp
                            <tr wire:key="pg-{{ $c['id'] }}" class="align-top" @if (isset(\App\Support\Impuestos::COLOR[$c['estado']])) style="background-color: {{ \App\Support\Impuestos::COLOR[$c['estado']] }}1f" @endif>
                                <td class="px-3 py-2">
                                    @if ($clienteId === 'todos')<input type="checkbox" wire:model="marcados.{{ $c['id'] }}" @disabled(! $c['tieneIs']) class="border-gray-300 rounded">@endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ $c['entidad'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $c['nif'] }} · @php $col = \App\Support\Impuestos::COLOR[$c['estado']] ?? null; @endphp<span @if ($col) class="font-medium" style="color: {{ $col }}" @endif>{{ \App\Support\Impuestos::ESTADOS[$c['estado']] ?? $c['estado'] }}</span></div>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($c['tieneIs'])<span class="text-green-700">✓ {{ $c['fechaIs'] }}</span>@else<span class="text-red-700">falta</span>@endif
                                    <input type="file" wire:model="subidaIs.{{ $c['id'] }}" accept="application/pdf" class="block w-48 mt-1 text-xs">
                                    @error('subidaIs.'.$c['id'])<div class="text-xs text-red-700">{{ $message }}</div>@enderror
                                </td>
                                <td class="px-3 py-2">
                                    @if ($c['tiene202'])<span class="text-green-700">✓</span>@endif
                                    <input type="file" wire:model="subida202.{{ $c['id'] }}" accept="application/pdf" class="block w-48 mt-1 text-xs">
                                    @error('subida202.'.$c['id'])<div class="text-xs text-red-700">{{ $message }}</div>@enderror
                                </td>
                                <td class="px-3 py-2">
                                    @if ($c['tienePyg'])<span class="text-green-700">✓ {{ $c['fechaPyg'] }}</span>@endif
                                    <input type="file" wire:model="subidaPyg.{{ $c['id'] }}" accept=".xlsx,.csv" class="block w-48 mt-1 text-xs">
                                    <div class="mt-1 text-xs text-gray-500" style="width:12rem">Sube el <b>sumas y saldos de SAGE acumulado desde el 1-ene</b> (xlsx). Vale también la Cuenta de Explotación (PL), con menos control.</div>
                                    @error('subidaPyg.'.$c['id'])<div class="text-xs text-red-700">{{ $message }}</div>@enderror
                                    <details class="mt-1 text-xs text-gray-600">
                                        <summary class="cursor-pointer text-indigo-700">ajustes 40.3</summary>
                                        <div class="mt-1 space-y-1" style="width:15rem">
                                            @foreach (\App\Http\Livewire\Contabilidad\IsPagos::CAMPOS_403 as $campo => $etq)
                                                <label class="flex items-center justify-between gap-2">{{ $etq }}
                                                    <input type="text" wire:model="ajustes403.{{ $c['id'] }}.{{ $campo }}" placeholder="{{ isset($c['ajustes'][$campo]) ? number_format((float) $c['ajustes'][$campo], 2, ',', '.') : '' }}" class="w-24 text-xs text-right border-gray-300 rounded-md">
                                                </label>
                                            @endforeach
                                            <div class="text-gray-500">Lo que escribas se guarda al pulsar «Preparar»; en gris, lo ya guardado. Vacío = no se toca.</div>
                                        </div>
                                    </details>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" wire:model="cnae.{{ $c['id'] }}" maxlength="4" placeholder="{{ $r['cnae'] ?? '' }}" class="w-16 text-sm border-gray-300 rounded-md shadow-sm">
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $euros($r['cuota_is'] ?? null) }}</td>
                                <td class="px-3 py-2 font-semibold text-right whitespace-nowrap">{{ $euros($r['a_ingresar'] ?? null) }}</td>
                                <td class="px-3 py-2 text-xs">
                                    @if ($r)
                                        @foreach ($r['errores'] ?? [] as $e)<div class="text-red-700">⛔ {{ $e }}</div>@endforeach
                                        @foreach ($r['avisos'] ?? [] as $a)<div class="text-amber-700">⚠ {{ $a }}</div>@endforeach
                                        @foreach ($r['info'] ?? [] as $i)<div class="text-gray-600">ℹ {{ $i }}</div>@endforeach
                                        @if (! empty($r['no_aplicado']))
                                            <details class="mt-1"><summary class="cursor-pointer text-indigo-700">Lo que NO se ha aplicado ({{ count($r['no_aplicado']) }})</summary>
                                                @foreach ($r['no_aplicado'] as $n)<div class="text-gray-700">· {{ $n }}</div>@endforeach
                                            </details>
                                        @endif
                                        @if (empty($r['errores']))
                                            <div class="text-green-700">✓ {{ ($r['modalidad'] ?? '40.2') === '40.3' ? '40.3 · ' : '' }}{{ $r['porcentaje'] }} % de {{ $euros($r['base']) }}</div>
                                            @if (! empty($r['detalle']))
                                                <div class="text-gray-600">Resultado contable {{ $euros($r['detalle']['04'] ?? null) }} → base previa {{ $euros($r['detalle']['13'] ?? null) }} · cuota {{ $euros($r['detalle']['18'] ?? null) }} · mínimo {{ $euros($r['detalle']['33'] ?? null) }} · pagos ant. {{ $euros($r['detalle']['30'] ?? null) }}</div>
                                            @endif
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($c['fichero'])
                                        <button type="button" wire:click="descargar({{ $c['id'] }})" class="text-sm font-semibold text-blue-700 underline hover:text-blue-900">⬇ .202</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-3 py-6 text-center text-gray-500">
                                @if ($clienteId !== 'todos')Este cliente no tiene el 202 en la pestaña Impuestos.
                                @else No hay clientes con el 202 pendiente en {{ $periodo }} {{ $ejercicio }}.@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-t bg-gray-50">
                @if ($clienteId === 'todos')
                    <button type="button" wire:click="marcarTodos" class="text-sm text-indigo-700 underline">Marcar todos</button>
                    <button type="button" wire:click="desmarcarTodos" class="text-sm text-gray-600 underline">Ninguno</button>
                @endif
                <button type="button" wire:click="preparar" wire:loading.attr="disabled" wire:target="preparar" class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="preparar">{{ $clienteId !== 'todos' ? 'Preparar' : 'Preparar los marcados' }}</span><span wire:loading wire:target="preparar">Preparando…</span>
                </button>
                <label class="flex items-center gap-1 text-sm text-gray-600" title="Crea un borrador (no se envía) con el cálculo y lo no aplicado, y el .202 adjunto">
                    <input type="checkbox" wire:model="borradorOutlook" class="border-gray-300 rounded"> dejar borrador en Outlook al preparar
                </label>
                <button type="button" wire:click="descargarZip" class="text-sm font-semibold text-blue-700 underline hover:text-blue-900">⬇ Descargar todos (.zip)</button>
            </div>
            @if ($salida)
                <pre class="px-4 pb-3 font-sans text-xs text-red-700 whitespace-pre-wrap">{{ $salida }}</pre>
            @endif
        </div>
    </div>
</div>
