<div>
    <div class="">
        {{-- @include('errores') --}}
    </div>
    @forelse($dconceptos as $concepto)
        <form method="POST" action="{{ route('facturaciondetalleconcepto.update',$concepto->id) }}">
        <input name="_method" type="hidden" value="PATCH">
        @csrf
        <input type="hidden" name="facturaciondetalleconcepto_id" value={{ $concepto->id }}>
        <div class="flex space-x-1" wire:loading.class.delay="opacity-50">
            <div class="w-16">
                <input type="number" name="orden" value="{{ $concepto->orden }}"
                class="w-full py-0.5 text-xs {{ $color }} font-thin text-gray-500  border-0 rounded-md"
                {{ $deshabilitado }}
                />
            </div>
            <div class="w-1/12 ">
                <select name="tipo" class="w-full mx-0 px-0 py-0.5 {{ $color }} text-left text-xs font-thin text-gray-500  border-0 rounded-md"
                {{ $deshabilitado }}>
                    @foreach ($tipos as $value=>$label)
                    <option value="{{ $value }}" {{ $value==$concepto->tipo ? 'selected' : ''}} >{{ $label }}</option>
                    @endforeach>
                </select>
            </div>
            <div class="w-1/12 ">
                <select name="subcuenta" class="w-full  mx-0 px-0 py-0.5 pl-2 {{ $color }} text-left text-xs font-thin text-gray-500  border-0 rounded-md"
                {{ $deshabilitado }}>
                    <option value="705000" {{ $concepto->subcuenta=='705000' ? 'selected' : ''}} >705000</option>
                    <option value="759000" {{ $concepto->subcuenta=='759000' ? 'selected' : ''}} >759000</option>
                </select>
            </div>
            <div class="w-2/12 "><input type="text" name="concepto" value="{{ $concepto->concepto }}" class="w-full break-normal py-0.5 text-xs {{ $color }} font-thin text-gray-500  border-0 rounded-md" {{ $deshabilitado }}/></div>
            <div class="w-1/12 "><input type="number" name="unidades" step="any" value="{{ $concepto->unidades }}" class="w-full {{ $color }} py-0.5 text-right text-xs font-thin text-gray-500  border-0 rounded-md" {{ $deshabilitado }}/></div>
            <div class="w-1/12 "><input type="number" name="importe" step="any" value="{{ $concepto->importe }}" class="w-full py-0.5 {{ $color }} text-right text-xs font-thin text-gray-500  border-0 rounded-md" {{ $deshabilitado }}/></div>
            <div class="w-1/12 ">
                <select name="iva" class="w-full mx-0 px-0 py-0.5 {{ $color }} text-center text-xs font-thin text-gray-500  border-0 rounded-md"
                {{ $deshabilitado }}>
                    <option value="0.00" {{ $concepto->iva=='0.00' ? 'selected' : ''}} >0%</option>
                    <option value="0.04" {{ $concepto->iva=='0.04' ? 'selected' : ''}} >4%</option>
                    <option value="0.10" {{ $concepto->iva=='0.10' ? 'selected' : ''}} >10%</option>
                    <option value="0.21" {{ $concepto->iva=='0.21' ? 'selected' : ''}} >21%</option>
                </select>
            </div>
            <div class="w-1/12 "><input type="number" name="totaliva" step="any" value="{{ $concepto->base + $concepto->exenta  }}" class="w-full py-0.5 bg-blue-50 text-right text-xs font-thin text-gray-500  border-0 rounded-md" disabled/></div>
            <div class="w-1/12 "><input type="number" name="totaliva" step="any" value="{{ $concepto->totaliva }}" class="w-full py-0.5 bg-blue-50 text-right text-xs font-thin text-gray-500  border-0 rounded-md" disabled/></div>
            <div class="w-1/12 "><input type="number" name="total" step="any" value="{{ $concepto->total }}" class="w-full py-0.5 bg-blue-50 text-right text-xs font-thin text-gray-500  border-0 rounded-md" disabled/></div>
            <div class="flex items-center justify-center w-1/12 ">
                @if($deshabilitado =='')
                <button type="submit"><x-icon.save/></button>
                <x-icon.delete-a wire:click.prevent="delete({{ $concepto->id }})"
                        onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()"
                        class="pl-1 " title="Borrar" />
                @endif
            </div>
        </div>
        </form>
    @empty
        <div class="flex items-center justify-center">
            <div class="w-full text-center">
                <x-icon.inbox class="w-5 h-5 text-gray-300"/>
                <span class="py-0.5 text-xl font-medium text-gray-500">
                    No se han encontrado registros...
                </span>
            </div>
        </div>
    @endforelse

    {{-- nuevo --}}
    @if($deshabilitado =='')
    <form wire:submit.prevent="save">
        <div class="flex space-x-1" wire:loading.class.delay="opacity-50">
            <div class="w-16 "><input type="number"  wire:model.lazy="orden" name="orden" value="{{ old('orden','0') }}" class="w-full py-0.5 text-xs font-thin text-gray-500  border-0 rounded-md"/></div>
            <div class="w-1/12 ">
                <x-select selectname="tipo" wire:model.lazy="tipo" class="w-full px-0 py-0.5 mx-0 text-xs font-thin text-left text-gray-500 border-0 rounded-md">
                    @foreach ($tipos as $value=>$label)
                    <option value="{{ $value }}" {{ $value==$tipo ? 'selected' : ''}} >{{ $label }}</option>
                    @endforeach>
                </x-select>
            </div>
            <div class="w-1/12 ">
                <x-select selectname="subcuenta"  wire:model.lazy="subcuenta" class="w-full px-0 py-0.5 pl-2 mx-0 text-xs font-thin text-left text-gray-500 border-0 rounded-md">
                    <option value="705000" {{ $subcuenta=='705000' ? 'selected' : ''}} >705000</option>
                    <option value="759000" {{ $subcuenta=='759000' ? 'selected' : ''}} >759000</option>
                </x-select>
            </div>
            <div class="w-2/12 "><input type="text" name="concepto"  wire:model.lazy="concepto" value="{{ old('concepto') }}" class="w-full py-0.5 text-xs font-thin text-gray-500  border-0 rounded-md" /></div>
            <div class="w-1/12 "><input type="number" name="Uds" step="any" wire:model.lazy='uds' value="{{ old('unidades','1') }}" class="w-full py-0.5 text-xs font-thin text-right text-gray-500  border-0 rounded-md"/></div>
            <div class="w-1/12 "><input type="number" name="importe" step="any" wire:model.lazy='importe' value="{{ old('importe','0') }}" class="w-full py-0.5 text-xs font-thin text-right text-gray-500  border-0 rounded-md"/></div>
            <div class="w-1/12 ">
                <x-select selectname="piva" wire:model.lazy='piva' class="w-full px-0 py-0.5 mx-0 text-xs font-thin text-center text-gray-500 border-0 rounded-md">
                    <option value="0.21" {{ $piva=='0.21' ? 'selected' : ''}} >21%</option>
                    <option value="0.10" {{ $piva=='0.10' ? 'selected' : ''}}>10%</option>
                    <option value="0.04" {{ $piva=='0.04' ? 'selected' : ''}}>4%</option>
                    <option value="0.00" {{ $piva=='0.00' ? 'selected' : ''}}>0%</option>
                </x-select>
            </div>
            <div class="w-1/12 "><input type="number" name="Subtotal" step="any" wire:model='subtotal' class="w-full py-0.5 text-xs bg-blue-50 font-thin text-right text-gray-500  border-0 rounded-md" disabled/></div>
            <div class="w-1/12 "><input type="number" name="Iva" step="any" wire:model='iva' class="w-full py-0.5 text-xs bg-blue-50 font-thin text-right text-gray-500  border-0 rounded-md" disabled/></div>
            <div class="w-1/12 "><input type="number" name="Total" step="any" wire:model='total' class="w-full py-0.5 text-xs bg-blue-50 font-thin text-right text-gray-500  border-0 rounded-md" disabled/></div>
            <div  class="w-1/12 mx-auto text-center text-blue-800">
                <button type="submit" class="text-center btn btn-primary" name="Guardar">
                    <x-icon.circle-plus class="mt-1"/>
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
