@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $mesTxt = \Carbon\Carbon::create($anio, $mes, 1)->locale('en')->monthName;
    $tablaTiendas = function ($r) use ($fmt) { return $r; };
@endphp
<div class="p-3">
    <h2 class="flex flex-wrap items-center text-lg font-semibold text-gray-900 gap-x-3">
        <span>Facturación {{ $empresa }} · venta a CdC Switzerland</span>
        <span class="text-base font-normal text-gray-600">{{ $mesTxt }} {{ $anio }}</span>
        @if ($listo)<span class="text-sm font-normal text-indigo-700">próxima factura: <b>{{ $siguiente }}</b></span>@endif
    </h2>
    <p class="mt-1 mb-3 text-xs text-gray-500">Apunta las facturas de compra de Plein del mes (abonos en negativo). Appmos suma el 5 % y te da el nº de la factura y el total por tienda; el PDF y los asientos de compra y venta se hacen en SAGE.</p>

    @unless ($listo)
        <p class="text-sm text-yellow-700">Falta la migración de las tablas fiq_ic_*.</p>
    @else
        {{-- Filtro: ver una factura de venta concreta (cualquier mes) --}}
        <div class="flex flex-wrap items-center mb-3 gap-x-2">
            <label class="text-xs font-medium text-gray-600" for="ic-fac-{{ $empresa }}">Factura de venta</label>
            <select id="ic-fac-{{ $empresa }}" wire:model.live="factura" class="text-sm border-gray-300 rounded shadow-sm">
                <option value="">Mes elegido ({{ $mesTxt }}): pendientes y emitidas</option>
                @foreach ($facturas as $f)
                    <option value="{{ $f->id }}">{{ \App\Support\FiqIc::etiqueta($f->serie, $f->numero) }} · {{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }} · {{ $fmt($f->total) }} €{{ $f->tipo === 'abono' ? ' (abono)' : '' }}</option>
                @endforeach
            </select>
            @if ($factura !== '')<button type="button" wire:click="$set('factura', '')" class="text-xs text-gray-500 hover:text-gray-800">✕ quitar</button>@endif
        </div>

        {{-- Alta de una compra --}}
        <form wire:submit.prevent="anadir" class="flex flex-wrap items-end gap-x-3 gap-y-2">
            <label class="flex flex-col text-xs font-medium text-gray-600">Proveedor
                <select wire:model="cuenta" class="mt-1 text-sm border-gray-300 rounded shadow-sm">
                    <option value="">—</option>
                    @foreach ($cuentas as $cod => $c)<option value="{{ $cod }}">{{ $cod }} · {{ $c['nombre'] }}</option>@endforeach
                </select>
            </label>
            <label class="flex flex-col text-xs font-medium text-gray-600">Nº factura
                <input type="text" wire:model="numero" class="mt-1 text-sm border-gray-300 rounded shadow-sm" style="width:120px">
            </label>
            <label class="flex flex-col text-xs font-medium text-gray-600">Fecha
                <input type="date" wire:model="fecha" class="mt-1 text-sm border-gray-300 rounded shadow-sm">
            </label>
            <label class="flex flex-col text-xs font-medium text-gray-600">Importe sin IVA
                <input type="text" wire:model="importe" class="mt-1 text-sm text-right border-gray-300 rounded shadow-sm" style="width:110px">
            </label>
            <label class="flex flex-col text-xs font-medium text-gray-600">Tienda
                <select wire:model="canal" class="mt-1 text-sm border-gray-300 rounded shadow-sm">
                    <option value="">—</option>
                    @foreach (\App\Support\FiqIc::TIENDAS as $cn => $tn)<option value="{{ $cn }}">{{ $tn }}</option>@endforeach
                </select>
            </label>
            <x-button.secondary type="submit" wire:loading.attr="disabled" wire:target="anadir">+ Añadir</x-button.secondary>
        </form>
        @if ($aviso)<p class="mt-2 text-sm {{ str_starts_with($aviso, '✓') ? 'text-green-700' : 'text-yellow-800' }}">{{ $aviso }}</p>@endif

        {{-- Pendientes de emitir --}}
        <div class="mt-4" @if ($factura !== '') style="display:none" @endif>
            <div class="mb-1 text-sm font-semibold text-gray-800">Pendientes de emitir ({{ $pendientes->count() }})</div>
            @if ($pendientes->isEmpty())
                <p class="text-xs text-gray-500">No hay compras pendientes en {{ $mesTxt }}.</p>
            @else
                @include('livewire.contabilidad._facturacion-ic-lineas', ['lineas' => $pendientes, 'borrable' => true])
                @include('livewire.contabilidad._facturacion-ic-resumen', ['r' => $resumenPend])
                <div class="flex flex-wrap items-center mt-3 gap-x-3 gap-y-2">
                    <label class="flex items-center text-xs font-medium text-gray-600 gap-x-1">Fecha de la factura
                        <input type="date" wire:model="fechaEmision" class="text-sm border-gray-300 rounded shadow-sm">
                    </label>
                    <x-button.secondary wire:click="emitir" wire:loading.attr="disabled" wire:target="emitir"
                        wire:confirm="Se crea la factura {{ $siguiente }} con las {{ $pendientes->count() }} compras pendientes de {{ $empresa }} ({{ $mesTxt }}), por {{ $fmt($resumenPend['total']) }} €. ¿Seguro?">
                        ✓ Emitir {{ $siguiente }} · {{ $fmt($resumenPend['total']) }} €
                    </x-button.secondary>
                </div>
            @endif
        </div>

        {{-- Facturas ya emitidas del mes --}}
        @foreach ($emisiones as $e)
            @php $x = $resumenes[$e->id]; @endphp
            <div class="mt-5 pt-3 border-t border-gray-200" wire:key="ic-em-{{ $e->id }}">
                <div class="flex flex-wrap items-center mb-1 text-sm gap-x-3">
                    <span class="font-semibold text-gray-800">{{ $e->tipo === 'abono' ? 'Abono' : 'Factura' }} {{ \App\Support\FiqIc::etiqueta($e->serie, $e->numero) }}</span>
                    <span class="text-gray-600">{{ \Carbon\Carbon::parse($e->fecha)->format('d/m/Y') }} · <b>{{ $fmt($e->total) }} €</b></span>
                    @if ($e->origen === 'excel')<span class="text-xs text-gray-400">del Excel</span>@endif
                    @if ($e->nota)<span class="text-xs text-gray-500">{{ $e->nota }}</span>@endif
                    @if ($e->origen === 'web')
                        <button type="button" wire:click="deshacer({{ $e->id }})" wire:confirm="¿Deshacer {{ \App\Support\FiqIc::etiqueta($e->serie, $e->numero) }}? Las compras vuelven a pendientes." class="text-xs text-red-600 hover:underline">↺ deshacer</button>
                    @endif
                </div>
                @include('livewire.contabilidad._facturacion-ic-lineas', ['lineas' => $x['lineas'], 'borrable' => false])
                @include('livewire.contabilidad._facturacion-ic-resumen', ['r' => $x['r']])
            </div>
        @endforeach
    @endunless
</div>
