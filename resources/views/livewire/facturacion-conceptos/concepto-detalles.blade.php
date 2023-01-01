<div>
    @forelse($detalles as $detalle)
        <form method="POST" action="{{ route('facturacionconceptodetalle.update',$detalle) }}">
        <input name="_method" type="hidden" value="PATCH">
        @csrf
        <input type="hidden" name="facturacionconcepto_id" value={{ $detalle->id }}>

            <div class="flex space-x-1" wire:loading.class.delay="opacity-50">
                <div class="w-1/12 "><input type="number" name="orden" value="{{ $detalle->orden }}" class="w-full py-1 text-sm {{ $color }} font-thin text-gray-500 truncate border-0 rounded-md"/></div>
                <div class="w-7/12 "><input type="text" name="concepto" value="{{ $detalle->concepto }}" class="w-full py-1 text-sm {{ $color }} font-thin text-gray-500 truncate border-0 rounded-md" /></div>
                <div class="w-1/12 "><input type="number" name="unidades" step="any" value="{{ $detalle->unidades }}" class="w-full {{ $color }} py-1 text-right text-sm font-thin text-gray-500 truncate border-0 rounded-md"/></div>
                <div class="w-1/12 "><input type="number" name="importe" step="any" value="{{ $detalle->importe }}" class="w-full py-1 {{ $color }} text-right text-sm font-thin text-gray-500 truncate border-0 rounded-md"/></div>
                <div class="flex items-center justify-center w-2/12 ">
                    <button type="submit"><x-icon.save/></button>
                    <button type="submit"><x-icon.save/></button>
                        <x-icon.delete-a wire:click.prevent="delete({{ $detalle->id }})"
                            onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()"
                            class="pl-1 " title="Borrar" />
                </div>
            </div>
        </form>
    @empty
    <div class="flex items-center justify-center">
        <div class="w-full text-center">
            <x-icon.inbox class="w-5 h-5 text-gray-300"/>
            <span class="py-1 text-xl font-medium text-gray-500">
                No se han encontrado registros...
            </span>
        </div>
    </div>
    @endforelse
    <form action="{{route('facturacionconceptodetalle.store')}}" method="post">
        @csrf
        <input type="hidden" name="facturacionconcepto_id" value="{{ $conceptoid }}">
        <div class="flex space-x-1" wire:loading.class.delay="opacity-50">
            <div class="w-1/12 {{ $color }}"><input type="number" name="orden" value="{{ old('orden','0') }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"/></div>
            <div class="w-7/12 {{ $color }}"><input type="text" name="concepto" value="{{ old('concepto') }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md" /></div>
            <div class="w-1/12 {{ $color }}"><input type="number" name="unidades" step="any" value="{{ old('unidades','1') }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md"/></div>
            <div class="w-1/12 {{ $color }}"><input type="number" name="importe" step="any" value="{{ old('importe','0') }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md"/></div>
            <div  class="w-2/12 mx-auto text-center text-blue-800">
                <button type="button" class="text-center btn btn-primary" name="Guardar" onclick="form.submit()">
                    <x-icon.circle-plus class="mt-1"/>
                </button>
            </div>
        </div>
    </form>
</div>
