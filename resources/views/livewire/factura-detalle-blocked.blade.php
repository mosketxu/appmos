<div class="p-1 mx-2">

    <div class="py-1 space-y-2">
        @if (session()->has('message'))
            <div id="alert" class="relative px-6 py-2 mb-2 text-white bg-red-200 border-red-500 rounded border-1">
                <span class="inline-block mx-8 align-middle">
                    {{ session('message') }}
                </span>
                <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
                    <span>×</span>
                </button>
            </div>
        @endif
        <div class="bg-yellow-100 rounded-md">
            <h3 class="ml-2 font-semibold ">Detalle Factura</h3>
        </div>
        {{-- tabla detalles --}}

        <div class="flex-col">
            <table table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="w-20 py-3 pl-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50 ">{{ __('Orden') }}</th>
                        <th class="w-1/12 py-3 pl-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50">{{ __('Tipo') }} </th>
                        <th class="w-3/12 py-3 pl-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50">{{ __('Concepto') }}</th>
                        <th class="w-20 py-3 pr-2 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50">{{ __('Uds.') }}</th>
                        <th class="py-3 pr-10 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50 w-28">{{ __('Importe') }}</th>
                        <th class="w-16 py-3 pl-10 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50">{{ __('% IVA') }}</th>
                        <th class="py-3 pr-10 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50 w-28">{{ __('Base (€)') }}</th>
                        <th class="py-3 pr-10 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50 w-28">{{ __('Exenta (€)') }}</th>
                        <th class="py-3 pr-10 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50 w-28">{{ __('IVA (€)') }}</th>
                        <th class="py-3 pr-10 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 bg-yellow-50 w-28">{{ __('Total (€)') }}</th>
                        <th class="py-3 pl-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50 w-28">{{ __('Subcta') }}</th>
                        <th class="py-3 pr-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50 w-28">{{ __('Pagado Por') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($detalles as $index=>$detalle)
                        <x-table.row wire:loading.class.delay="opacity-50">
                            {{-- orden --}}
                            <x-table.cell>
                                <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'orden')">
                                    {{ $detalle['orden'] }}
                                </div>
                            </x-table.cell>
                            {{-- tipo --}}
                            <x-table.cell>
                                <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'tipo')">
                                    @if($detalle['tipo']=='0')
                                        Gral
                                    @elseif ($detalle['tipo']=='1')
                                        Suplido
                                    @else
                                        Otros
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- concepto --}}
                            <x-table.cell class="">
                                <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'concepto')">
                                    {{ $detalle['concepto'] }}
                                </div>
                            </x-table.cell>
                            {{-- unidades --}}
                            <x-table.cell class="">
                                <div class="flex-1 p-2 text-xs text-right text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'unidades')">
                                    {{ $detalle['unidades'] }}
                                </div>
                            </x-table.cell>
                            {{-- importe --}}
                            <x-table.cell class="">
                                <div class="flex-1 p-2 pr-10 text-xs text-right text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'importe')">
                                    {{ $detalle['importe'] }}
                                </div>
                            </x-table.cell>
                            {{-- %IVA --}}
                            <x-table.cell class="">
                                <div class="flex-1 p-2 pl-10 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'iva')">
                                    {{ $detalle['iva'] * 100 }}%
                                </div>
                            </x-table.cell>
                            {{-- base --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 rounded-lg bg-blue-50">
                                    @if(is_numeric($detalle['unidades']) && is_numeric($detalle['importe']) && $detalle['iva']!='0')
                                        {{ number_format(round($detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- exenta --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 rounded-lg bg-blue-50">
                                    @if(is_numeric($detalle['unidades']) && is_numeric($detalle['importe'] && $detalle['iva']=='0'))
                                        {{ number_format(round($detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- Iva --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 bg-blue-100 rounded-lg">
                                    @if(is_numeric($detalle['iva']) && is_numeric($detalle['unidades']) && is_numeric($detalle['importe']))
                                        {{ number_format(round($detalle['iva']*$detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- total --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 bg-blue-200 rounded-lg">
                                    @if(is_numeric($detalle['iva']) && is_numeric($detalle['unidades']) && is_numeric($detalle['importe']))
                                        {{ number_format(round((1+$detalle['iva'])*$detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>

                            <x-table.cell class="">
                                <div class="flex-1 p-2 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'subcuenta')">
                                    {{ $detalle['subcuenta'] }}
                                </div>
                            </x-table.cell>

                            <x-table.cell class="">
                                <div class="flex-1 p-2 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'pagadopor')">
                                    @if($detalle['subcuenta']=='0')
                                        NP
                                    @elseif ($detalle['subcuenta']=='1')
                                        Marta
                                    @else
                                        Susana
                                    @endif
                                </div>
                            </x-table.cell>
                        </x-table.row>
                    @empty
                        <x-table.row>
                            <x-table.cell colspan="10">
                                <div class="flex items-center justify-center">
                                    <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                    <span class="py-5 text-xl font-medium text-gray-500">
                                        No se han encontrado detalles...
                                    </span>
                                </div>
                            </x-table.cell>
                        </x-table.row>
                    @endforelse
                </tbody>
                <tfoot class="font-bold divide-y divide-gray-200">
                    <tr>
                        <td class="w-20 pl-2"></td>
                        <td class="w-1/12 pl-2"></td>
                        <td class="w-3/12 pl-2"></td>
                        <td class="w-20 pr-2 text-right"></td>
                        <td class="pr-10 text-right w-28"></td>
                        <td class="w-16 pl-10 text-left border">Total</td>
                        <td class="pr-10 text-right border w-28">{{ number_format($base,2,',','.') }}</td>
                        <td class="pr-10 text-right border w-28">{{ number_format($exenta,2,',','.') }}</td>
                        <td class="pr-10 text-right border w-28">{{ number_format($totaliva,2,',','.') }}</td>
                        <td class="pr-10 text-right border w-28">{{ number_format($total,2,',','.') }}</td>
                        <td class="pl-2 text-left w-28"></td>
                        <td class="pr-2 text-left w-28"></td>
                        <td colspan="2" class="w-1/12"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
