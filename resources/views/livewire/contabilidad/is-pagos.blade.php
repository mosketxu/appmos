<div>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" wire:click="$toggle('abierto')" class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
            💰 {{ $abierto ? 'Cerrar pagos a cuenta' : 'Preparar pagos a cuenta (modelo 202)' }}
        </button>
    </div>

    @if ($abierto)
        @php $euros = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '.'); @endphp
        <div class="mt-3 space-y-3 bg-white border border-gray-300 rounded-lg shadow-sm">
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
                <label class="text-sm text-gray-600">Mostrar
                    <select wire:model.live="alcance" class="ml-1 border-gray-300 rounded-md shadow-sm">
                        <option value="cliente">solo el cliente elegido arriba</option>
                        <option value="todos">todos los clientes con 202</option>
                    </select>
                </label>
                @if ($alcance === 'todos')
                    <label class="flex items-center gap-1 text-sm text-gray-600">
                        <input type="checkbox" wire:model.live="verTodos" class="border-gray-300 rounded"> incluir los que no tienen que presentarlo / ya presentados
                    </label>
                @endif
                <span class="ml-auto text-xs text-gray-500">Clientes con el 202 en la pestaña Impuestos. Modalidad 40.2 (18 % de la cuota del IS {{ $ejercicio - 1 }}).</span>
            </div>

            <div class="px-4 text-xs text-gray-600">
                Sube el <b>IS {{ $ejercicio - 1 }} presentado</b> (PDF) de cada cliente; de ahí salen la cuota, el CNAE y la cifra de negocios. Si el CNAE
                debe ser el de {{ $ejercicio }} (CNAE-2025), sube también un 202 de este año o escríbelo en la casilla. Se genera el fichero <code>.202</code>
                para <b>importar en el formulario de la AEAT</b>: allí sacas el borrador y presentas. Aquí no se presenta nada.
                Las <b>grandes empresas</b> (más de 6 M€ de cifra de negocios, modalidad 40.3) no se calculan aquí.
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left"></th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-left">IS {{ $ejercicio - 1 }} (PDF)</th>
                            <th class="px-3 py-2 text-left">202 de {{ $ejercicio }} (opc.)</th>
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
                            <tr wire:key="pg-{{ $c['id'] }}" class="align-top">
                                <td class="px-3 py-2">
                                    @if ($alcance === 'todos')<input type="checkbox" wire:model="marcados.{{ $c['id'] }}" @disabled(! $c['tieneIs']) class="border-gray-300 rounded">@endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ $c['entidad'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $c['nif'] }} · {{ $c['estado'] }}</div>
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
                                    <input type="text" wire:model="cnae.{{ $c['id'] }}" maxlength="4" placeholder="{{ $r['cnae'] ?? '' }}" class="w-16 text-sm border-gray-300 rounded-md shadow-sm">
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $euros($r['cuota_is'] ?? null) }}</td>
                                <td class="px-3 py-2 font-semibold text-right whitespace-nowrap">{{ $euros($r['a_ingresar'] ?? null) }}</td>
                                <td class="px-3 py-2 text-xs">
                                    @if ($r)
                                        @foreach ($r['errores'] ?? [] as $e)<div class="text-red-700">⛔ {{ $e }}</div>@endforeach
                                        @foreach ($r['avisos'] ?? [] as $a)<div class="text-amber-700">⚠ {{ $a }}</div>@endforeach
                                        @if (empty($r['errores']))<div class="text-green-700">✓ {{ $r['porcentaje'] }} % de {{ $euros($r['base']) }}</div>@endif
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($c['fichero'])
                                        <button type="button" wire:click="descargar({{ $c['id'] }})" class="text-sm font-semibold text-blue-700 underline hover:text-blue-900">⬇ .202</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">
                                @if ($alcance === 'cliente')El cliente elegido arriba no tiene el 202 en la pestaña Impuestos. Elige otro o pasa a «todos los clientes con 202».
                                @else No hay clientes con el 202 pendiente en {{ $periodo }} {{ $ejercicio }}.@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-t bg-gray-50">
                @if ($alcance === 'todos')
                    <button type="button" wire:click="marcarTodos" class="text-sm text-indigo-700 underline">Marcar todos</button>
                    <button type="button" wire:click="desmarcarTodos" class="text-sm text-gray-600 underline">Ninguno</button>
                @endif
                <button type="button" wire:click="preparar" wire:loading.attr="disabled" wire:target="preparar" class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="preparar">{{ $alcance === 'cliente' ? 'Preparar' : 'Preparar los marcados' }}</span><span wire:loading wire:target="preparar">Preparando…</span>
                </button>
                <button type="button" wire:click="descargarZip" class="text-sm font-semibold text-blue-700 underline hover:text-blue-900">⬇ Descargar todos (.zip)</button>
            </div>
            @if ($salida)
                <pre class="px-4 pb-3 font-sans text-xs text-red-700 whitespace-pre-wrap">{{ $salida }}</pre>
            @endif
        </div>
    @endif
</div>
