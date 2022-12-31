<div class="p-1 mx-2">

    <div class="py-1 space-y-2">
        <div class="">
            @include('errores')
        </div>
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
                        <th colspan="2" class="w-1/12 py-3 pr-2 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 bg-yellow-50"> </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($detalles as $index=>$detalle)
                        <x-table.row wire:loading.class.delay="opacity-50">
                            {{-- orden --}}
                            <x-table.cell>
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.orden')
                                    <input type="number"
                                        @click.away="$wire.editedDetalleField === '{{ $index }}.orden' ? $wire.saveDetalle({{ $index }}) : null"
                                        wire:model.defer="detalles.{{ $index }}.orden"
                                        class="w-16 text-xs p-2 border border-blue-300 transition rounded-lg duration-150 hover:border-blue-300 focus:border-blue-300  active:border-blue-300
                                        {{ $errors->has('detalles.' . $index . '.orden') ? 'border-red-500' : 'border-blue-300' }}"/>
                                    @if ($errors->has('detalles.' . $index . '.orden'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.orden') }}</div>
                                    @endif
                                @else
                                    <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'orden')">
                                        {{ $detalle['orden'] }}
                                    </div>
                                @endif
                            </x-table.cell>
                            {{-- tipo --}}
                            <x-table.cell>
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.tipo')
                                    <x-select @click.away="$wire.editedDetalleField === '{{ $index }}.tipo' ? $wire.saveDetalle({{ $index }}) : null"
                                        wire:model.defer="detalles.{{ $index }}.tipo"
                                        selectname="tipo"
                                        class="w-full text-xs
                                        {{ $errors->has('detalles.' . $index . '.iva') ? 'border-red-500' : 'border-blue-300' }}">
                                        <option value="">-- choose --</option>
                                        @foreach (App\Models\FacturacionDetalle::TIPOS as $value=>$label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach>
                                    </x-select>
                                    @if ($errors->has('detalles.' . $index . '.tipo'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.tipo') }}</div>
                                    @endif
                                @else
                                    <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'tipo')">
                                        @if($detalle['tipo']=='0')
                                            Gral
                                        @elseif ($detalle['tipo']=='1')
                                            Suplido
                                        @else
                                            Otros
                                        @endif
                                    </div>
                                @endif
                            </x-table.cell>
                            {{-- concepto --}}
                            <x-table.cell class="">
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.concepto')
                                    <input type="text"
                                        @click.away="$wire.editedDetalleField === '{{ $index }}.concepto' ? $wire.saveDetalle({{ $index }}) : null"
                                        wire:model.defer="detalles.{{ $index }}.concepto"
                                        class="w-full text-xs p-2 border border-blue-300 transition rounded-lg duration-150 hover:border-blue-300 focus:border-blue-300  active:border-blue-300
                                        {{ $errors->has('detalles.' . $index . '.concepto') ? 'border-red-500' : 'border-blue-300' }}"/>
                                    @if ($errors->has('detalles.' . $index . '.concepto'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.concepto') }}</div>
                                    @endif
                                @else
                                <div class="flex-1 p-2 text-xs text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'concepto')">
                                    {{ $detalle['concepto'] }}
                                    </div>
                                @endif
                            </x-table.cell>
                            {{-- unidades --}}
                            <x-table.cell class="">
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.unidades')
                                    <input type="number"
                                        @click.away="$wire.editedDetalleField === '{{ $index }}.unidades' ? $wire.saveDetalle({{ $index }}) : null"
                                        wire:model.defer="detalles.{{ $index }}.unidades"
                                        class="w-full text-xs text-right p-2 border border-blue-300 transition rounded-lg duration-150 hover:border-blue-300 focus:border-blue-300  active:border-blue-300
                                        {{ $errors->has('detalles.' . $index . '.unidades') ? 'border-red-500' : 'border-blue-300' }}"/>
                                    @if ($errors->has('detalles.' . $index . '.unidades'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.unidades') }}</div>
                                    @endif
                                @else
                                <div class="flex-1 p-2 text-xs text-right text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'unidades')">
                                    {{ $detalle['unidades'] }}
                                    </div>
                                @endif
                            </x-table.cell>
                            {{-- importe --}}
                            <x-table.cell class="">
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.importe')
                                    <input type="number" step="any"
                                        @click.away="$wire.editedDetalleField === '{{ $index }}.importe' ? $wire.saveDetalle({{ $index }}) : null"
                                        wire:model.defer="detalles.{{ $index }}.importe"
                                        class="w-full text-xs text-right p-2 border border-blue-300 transition rounded-lg duration-150 hover:border-blue-300 focus:border-blue-300  active:border-blue-300
                                        {{ $errors->has('detalles.' . $index . '.importe') ? 'border-red-500' : 'border-blue-300' }}"/>
                                    @if ($errors->has('detalles.' . $index . '.importe'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.importe') }}</div>
                                    @endif
                                @else
                                    <div class="flex-1 p-2 pr-10 text-xs text-right text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'importe')">
                                        {{ $detalle['importe'] }}
                                    </div>
                                @endif
                            </x-table.cell>
                            {{-- %IVA --}}
                            <x-table.cell class="">
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.iva')
                                    <x-select @click.away="$wire.editedDetalleField === '{{ $index }}.iva' ? $wire.saveDetalle({{ $index }}) : null"
                                        selectname="iva"
                                        wire:model.defer="detalles.{{ $index }}.iva"
                                        class="w-full text-xs pl-12 text-left
                                        {{ $errors->has('detalles.' . $index . '.iva') ? 'border-red-500' : 'border-blue-300' }}">
                                        <option value="0">0%</option>
                                        <option value="0.04">4%</option>
                                        <option value="0.10">10%</option>
                                        <option value="0.21">21%</option>
                                    </x-select>
                                    @if ($errors->has('detalles.' . $index . '.iva'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.iva') }}</div>
                                    @endif
                                @else
                                <div class="flex-1 p-2 pl-10 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'iva')">
                                    {{ $detalle['iva'] * 100 }}%
                                </div>
                                @endif
                            </x-table.cell>
                            {{-- base --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 rounded-lg bg-blue-50">
                                    @if(is_numeric($detalle['unidades']) && is_numeric($detalle['importe'])&& $detalle['iva']!='0')
                                    {{ number_format(round($detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- exenta --}}
                            <x-table.cell>
                                <div class="flex-1 py-1 pr-10 text-sm font-bold text-right text-gray-900 rounded-lg bg-blue-50">
                                    @if(is_numeric($detalle['unidades']) && is_numeric($detalle['importe'])&& $detalle['iva']=='0')
                                    {{ number_format(round($detalle['unidades']*$detalle['importe'], 2),2,',','.') }}
                                    @endif
                                </div>
                            </x-table.cell>
                            {{-- iva --}}
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
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.subcuenta')
                                    <x-select @click.away="$wire.editedDetalleField === '{{ $index }}.subcuenta' ? $wire.saveDetalle({{ $index }}) : null"
                                        selectname="subcuenta"
                                        wire:model.defer="detalles.{{ $index }}.subcuenta"
                                        class="w-full text-xs text-left
                                        {{ $errors->has('detalles.' . $index . '.subcuenta') ? 'border-red-500' : 'border-blue-300' }}">
                                        <option value="">ND</option>
                                        <option value="705000">705000</option>
                                        <option value="759000">759000</option>
                                        {{-- 0;"NP";1;"Marta";2;"Susana" --}}
                                    </x-select>
                                    @if ($errors->has('detalles.' . $index . '.subcuenta'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.subcuenta') }}</div>
                                    @endif
                                @else
                                <div class="flex-1 p-2 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'subcuenta')">
                                    {{ $detalle['subcuenta'] }}
                                    </div>
                                @endif
                            </x-table.cell>

                            <x-table.cell class="">
                                @if ($editedDetalleIndex === $index || $editedDetalleField === $index . '.pagadopor')
                                <x-select @click.away="$wire.editedDetalleField === '{{ $index }}.pagadopor' ? $wire.saveDetalle({{ $index }}) : null"
                                    selectname="pagadopor"
                                    wire:model.defer="detalles.{{ $index }}.pagadopor"
                                    class="w-full text-xs text-left
                                    {{ $errors->has('detalles.' . $index . '.pagadopor') ? 'border-red-500' : 'border-blue-300' }}">
                                    <option value="0">NP</option>
                                    <option value="1">Marta</option>
                                    <option value="2">Susana</option>
                                </x-select>
                                    @if ($errors->has('detalles.' . $index . '.pagadopor'))
                                        <div class="text-red-500">{{ $errors->first('detalles.' . $index . '.pagadopor') }}</div>
                                    @endif
                                @else
                                    <div class="flex-1 p-2 text-xs text-left text-gray-600 cursor-pointer" wire:click="editDetalleField({{ $index }}, 'pagadopor')">
                                        @if($detalle['pagadopor']=='1')
                                            Marta-
                                        @elseif ($detalle['pagadopor']=='2')
                                            Susana
                                        @else
                                            NP
                                        @endif
                                    </div>
                                @endif
                            </x-table.cell>

                            <x-table.cell class="pr-2 text-right ">
                                @if($editedDetalleIndex === $index || (isset($editedDetalleField) && (int)(explode('.',$editedDetalleField)[0])===$index))
                                    <x-icon.save-a wire:click.prevent="saveDetalle({{$index}})" title="Actualizar"/>
                                @else
                                    <x-icon.edit-a wire:click.prevent="editDetalle({{$index}})" title="Editar"/>
                                @endif
                                <x-icon.delete-a wire:click.prevent="delete({{ $detalle['id'] }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1"  title="Eliminar detalle"/>
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
                <tfoot class="font-bold ">
                    <tr>
                        <td class="w-20 pl-2"></td>
                        <td class="w-1/12 pl-2"></td>
                        <td class="w-3/12 pl-2"></td>
                        <td class="w-20 pr-2 text-right"></td>
                        <td class="w-16 pl-10 text-left border"></td>
                        <td class="text-center border w-28">Base</td>
                        <td class="text-center border w-28">Exenta/Sup.</td>
                        <td class="text-center border w-28">I.V.A.</td>
                        <td class="text-center border w-28">Total</td>
                        <td class="pl-2 text-left w-28"></td>
                        <td class="pr-2 text-left w-28"></td>
                        <td colspan="2" class="w-1/12"></td>
                    </tr>
                    <tr>
                        <td class="w-20 pl-2"></td>
                        <td class="w-1/12 pl-2"></td>
                        <td class="w-3/12 pl-2"></td>
                        <td class="w-20 pr-2 text-right"></td>
                        <td class="w-16 pl-10 text-left border">Total</td>
                        <td class="text-center border w-28">{{ number_format($base,2,',','.') }}</td>
                        <td class="text-center border w-28">{{ number_format($exenta,2,',','.') }}</td>
                        <td class="text-center border w-28">{{ number_format($totaliva,2,',','.') }}</td>
                        <td class="text-center border w-28">{{ number_format($total,2,',','.') }}</td>
                        <td class="pl-2 text-left w-28"></td>
                        <td class="pr-2 text-left w-28"></td>
                        <td colspan="2" class="w-1/12"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($showcrear)
            @livewire('facturacion.factura-detalle-create',['facturacion'=>$factura],key($factura->id))
        @endif
    </div>
</div>
