@php $fmt = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="mt-2 text-sm">
    <div class="mb-1 text-xs font-semibold text-gray-600">Ventas por tienda</div>
    <table class="text-sm text-gray-800">
        <thead><tr class="text-xs text-left text-gray-500"><th class="pr-4">Tienda</th><th class="pr-4 text-right">Fras</th><th class="pr-4 text-right">Importe</th><th class="pr-4 text-right">+5 %</th><th class="text-right">Total</th></tr></thead>
        <tbody>
            @foreach ($r['tiendas'] as $t)
                <tr class="border-t border-gray-100">
                    <td class="pr-4">{{ $t['tienda'] }}</td><td class="pr-4 text-right">{{ $t['n'] }}</td>
                    <td class="pr-4 text-right {{ $t['base'] < 0 ? 'text-red-700' : '' }}">{{ $fmt($t['base']) }}</td>
                    <td class="pr-4 text-right text-gray-500">{{ $fmt($t['recargo']) }}</td>
                    <td class="text-right {{ $t['total'] < 0 ? 'text-red-700' : '' }}">{{ $fmt($t['total']) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold border-t border-gray-300">
                <td class="pr-4" colspan="2">Total factura (sin IVA, exenta art. 25 LIVA)</td>
                <td class="pr-4 text-right">{{ $fmt($r['base']) }}</td><td class="pr-4 text-right">{{ $fmt($r['recargo']) }}</td><td class="text-right">{{ $fmt($r['total']) }}</td>
            </tr>
        </tbody>
    </table>
</div>
