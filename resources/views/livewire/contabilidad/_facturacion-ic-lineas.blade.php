@php $fmt = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="overflow-x-auto">
<table class="text-sm text-gray-800">
    <thead>
        <tr class="text-xs text-left text-gray-500">
            <th class="pr-3">Proveedor</th><th class="pr-3">Nº factura</th><th class="pr-3">Fecha</th><th class="pr-3">Tienda</th>
            <th class="pr-3 text-right">Importe</th><th class="pr-3 text-right">+5 %</th><th class="pr-3 text-right">Total</th>
            <th class="pr-3" title="Contrapartida de la compra (gasto)">Compra</th><th class="pr-3" title="Cuenta del cliente">Cliente</th><th class="pr-3" title="Contrapartida de la venta">Venta</th><th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $l)
            @php $c = \App\Support\FiqIc::CUENTAS[$l->cuenta] ?? null; $tot = \App\Support\FiqIc::conRecargo((float) $l->importe); @endphp
            <tr class="border-t border-gray-100" wire:key="ic-l-{{ $l->id }}">
                <td class="pr-3 whitespace-nowrap" title="{{ $c['nombre'] ?? '' }}">{{ $l->cuenta }} {{ $c['origen'] ?? '' }}</td>
                <td class="pr-3">{{ $l->numero }}</td>
                <td class="pr-3 whitespace-nowrap">{{ $l->fecha ? \Carbon\Carbon::parse($l->fecha)->format('d/m/Y') : '' }}</td>
                <td class="pr-3">{{ \App\Support\FiqIc::TIENDAS[$l->canal] ?? $l->canal }}</td>
                <td class="pr-3 text-right {{ $l->importe < 0 ? 'text-red-700' : '' }}">{{ $fmt($l->importe) }}</td>
                <td class="pr-3 text-right text-gray-500">{{ $fmt($tot - $l->importe) }}</td>
                <td class="pr-3 text-right font-medium {{ $tot < 0 ? 'text-red-700' : '' }}">{{ $fmt($tot) }}</td>
                <td class="pr-3 text-gray-500">{{ $c['gasto'] ?? '' }}</td>
                <td class="pr-3 text-gray-500" title="{{ $c['cliente_nombre'] ?? '' }}">{{ $c['cliente'] ?? '' }}</td>
                <td class="pr-3 text-gray-500">{{ $c['ventas'] ?? '' }}</td>
                <td>@if ($borrable)<button type="button" wire:click="borrar({{ $l->id }})" class="text-red-500 hover:text-red-700" title="Borrar">✕</button>@endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
