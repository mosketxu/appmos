@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $mesTxt = \Carbon\Carbon::create($anio, $mes, 1)->locale('en')->monthName;
    $icono = ['ok' => '✓', 'na' => '–', 'cent' => '≈'];
    $color = fn ($e) => $e === 'ok' ? 'text-green-700' : ($e === 'na' ? 'text-gray-500' : ($e === 'cent' ? 'text-yellow-700' : 'text-red-700'));
@endphp
<div class="p-3">
    <h2 class="flex flex-wrap items-center text-lg font-semibold text-gray-900 gap-x-3">
        <span>Validación OMC y Breuninger · contra el mayor</span>
        <span class="text-base font-normal text-gray-600">{{ $mesTxt }} {{ $anio }}</span>
    </h2>
    <p class="mt-1 mb-3 text-xs text-gray-500">Comprueba con el último mayor de SAGE (ficheros base) que cada compra de Plein apuntada en Appmos está en su cuenta de proveedor y de gasto (nº, importe, tienda) y que cada factura de venta emitida está en el cliente y en las ventas por tienda. Si todo cuadra, marca el check del mes.</p>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <x-button.secondary wire:click="validar" wire:loading.attr="disabled" wire:target="validar">
            <span wire:loading.remove wire:target="validar">▶ Validar {{ $mesTxt }}</span><span wire:loading wire:target="validar">⏳ Leyendo el mayor…</span>
        </x-button.secondary>
        @if ($mayor)<span class="text-xs text-gray-500">Mayor: «{{ $mayor['nombre'] }}» subido el {{ $mayor['subido'] }}{{ $mayor['origen'] ? ' ('.$mayor['origen'].')' : '' }}{{ $mayor['hasta'] ? ' · llega hasta el '.$mayor['hasta'] : '' }}</span>@endif
    </div>
    @if ($aviso)<p class="mt-2 text-sm text-yellow-800">{{ $aviso }}</p>@endif

    @if ($res)
        @if ($res['aviso_mayor'])<p class="mt-2 text-sm text-yellow-800">⚠️ {{ $res['aviso_mayor'] }}</p>@endif
        <p class="mt-3 text-sm font-semibold {{ $res['problemas'] === 0 ? 'text-green-700' : 'text-red-700' }}">
            {{ $res['problemas'] === 0 ? '✓ Todo cuadra con el mayor.' : '✗ '.$res['problemas'].' diferencia(s) con el mayor.' }}
            @if ($res['problemas'] === 0 && ! $res['aviso_mayor'])<span class="font-normal text-gray-500">Check marcado.</span>@endif
        </p>
        @foreach ($res['empresas'] as $emp => $x)
            <div class="pt-3 mt-4 border-t border-gray-200" wire:key="val-{{ $emp }}">
                <div class="mb-1 text-sm font-semibold text-gray-800">{{ $emp }}
                    <span class="font-normal {{ $x['problemas'] ? 'text-red-700' : 'text-green-700' }}">{{ $x['sin_nada'] ? '· nada que validar este mes' : ($x['problemas'] ? '· '.$x['problemas'].' diferencia(s)' : '· cuadra') }}</span>
                </div>
                @if (! $x['sin_nada'])
                    @if ($x['compras'] || $x['compras_extra'])
                        <div class="text-xs font-semibold text-gray-600">Compras de Plein</div>
                        <table class="text-sm text-gray-800">
                            @foreach ($x['compras'] as $c)
                                @php $l = $c['linea']; @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="pr-2 {{ $color($c['estado']) }}">{{ $icono[$c['estado']] ?? '✗' }}</td>
                                    <td class="pr-3 whitespace-nowrap">{{ $l['cuenta'] }} · {{ $l['numero'] }}</td>
                                    <td class="pr-3">{{ \App\Support\FiqIc::TIENDAS[$l['canal']] ?? $l['canal'] }}</td>
                                    <td class="pr-3 text-right">{{ $fmt($l['importe']) }}</td>
                                    <td class="pr-3 text-xs {{ $color($c['estado']) }}">{{ implode(' ', $c['detalle']) }}@if ($c['mayor_asiento']) <span class="text-gray-400">asiento {{ $c['mayor_asiento'] }}</span>@endif</td>
                                </tr>
                            @endforeach
                            @foreach ($x['compras_extra'] as $c)
                                <tr class="border-t border-gray-100">
                                    <td class="pr-2 text-red-700">✗</td>
                                    <td class="pr-3 whitespace-nowrap">{{ $c['cuenta'] }} · {{ $c['factura'] ?: $c['comentario'] }}</td>
                                    <td class="pr-3">{{ \App\Support\FiqIc::TIENDAS[$c['canal']] ?? $c['canal'] }}</td>
                                    <td class="pr-3 text-right">{{ $fmt($c['importe']) }}</td>
                                    <td class="pr-3 text-xs text-red-700">Está en el mayor (asiento {{ $c['asiento'] }}, {{ \Carbon\Carbon::parse($c['fecha'])->format('d/m/Y') }}) y no está apuntada en Appmos.</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    @if ($x['ventas'] || $x['ventas_extra'])
                        <div class="mt-2 text-xs font-semibold text-gray-600">Ventas emitidas</div>
                        @foreach ($x['ventas'] as $v)
                            <div class="mb-2" wire:key="val-v-{{ $v['emision']['id'] }}">
                                <div class="text-sm {{ $color($v['estado']) }}">{{ $icono[$v['estado']] ?? '✗' }} <b>{{ $v['etiqueta'] }}</b> · {{ $fmt($v['emision']['total']) }} €
                                    @if ($v['mayor_asiento'])<span class="text-xs text-gray-400">asiento {{ $v['mayor_asiento'] }} · cliente {{ $fmt($v['mayor_total']) }} €</span>@endif
                                    <span class="text-xs">{{ implode(' ', $v['detalle']) }}</span>
                                </div>
                                @if ($v['tiendas'])
                                    <table class="ml-4 text-xs text-gray-800">
                                        <tr class="text-gray-500"><th class="pr-3 text-left">Tienda</th><th class="pr-3 text-right">Appmos</th><th class="pr-3 text-right">Mayor</th><th class="text-right">Dif.</th></tr>
                                        @foreach ($v['tiendas'] as $t)
                                            <tr class="border-t border-gray-100"><td class="pr-3">{{ $t['tienda'] }}</td><td class="pr-3 text-right">{{ $fmt($t['appmos']) }}</td><td class="pr-3 text-right">{{ $fmt($t['mayor']) }}</td>
                                                <td class="text-right {{ $color($t['estado'] === 'mal' ? 'x' : $t['estado']) }}">{{ $t['estado'] === 'ok' ? '✓' : $fmt($t['dif']) }}</td></tr>
                                        @endforeach
                                    </table>
                                @endif
                            </div>
                        @endforeach
                        @foreach ($x['ventas_extra'] as $f)
                            <div class="text-sm text-red-700">✗ En el mayor y sin emitir en Appmos: {{ $f['comentario'] }} · {{ $fmt($f['debe']) }} € (asiento {{ $f['asiento'] }}).</div>
                        @endforeach
                    @endif
                @endif
            </div>
        @endforeach
    @endif
</div>
