<div class="">
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">
        <div class="flex justify-between space-x-1">
            <div class="flex">
                <h1 class="text-2xl font-semibold text-gray-900">Conceptos de facturación de {{ $entidad->entidad }} <span class="text-lg text-gray-500 "> ({{ $entidad->nif }})</span></h1>
            </div>
            <div class="flex flex-row-reverse">
                @livewire('facturacion-conceptos.conceptonuevo-modal',['entidad'=>$entidad])
            </div>
        </div>

        <div class="py-1 ">
            <div class="">
                @include('errores')
            </div>
            <div class="flex w-full pt-2 pb-0 pl-2 space-x-2 text-sm font-bold text-gray-500 bg-blue-100 rounded-t-md">
                <div class="flex w-4/12 space-x-1 rounded-l-xl">
                    <div class="w-5/12"><input type="text" value="Agrupación" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled/></div>
                    <div class="w-3/12"><input type="text" value="Ciclo" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled/></div>
                    <div class="w-3/12"><input type="text" value="Per.Ref." class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled/></div>
                    <div class="w-1/12"></div>
                </div>
                <div class="flex w-8/12 space-x-1">
                    <div class="w-1/12" ><input type="text" value="Orden" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-7/12"><input type="text" value="Concepto" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Uds" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Importe" class="w-full py-1 text-sm font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-2/12"></div>
                </div>
            </div>
            @forelse ($conceptos as $concepto)
                <form method="POST" action="{{ route('facturacionconcepto.update',$concepto->id) }}">
                @csrf
                @method('PUT')
                <div class="border border-gray-200 flex space-x-1 pt-0 mt-0 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}" wire:loading.class.delay="opacity-50">
                    <div class="flex w-4/12">
                        <div class="w-5/12 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            <input type="text" name="agrup" value="{{ $concepto->concepto }}"
                            class="w-full py-1 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }} text-sm font-thin text-gray-500 truncate border-0 rounded-md"/>
                        </div>
                        <div class="w-3/12 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            <select name="ciclo" id=""
                                class="w-full py-1 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }} text-sm font-thin text-gray-500 truncate border-0 rounded-md" >
                                @foreach ($ciclosfact as $ciclo )
                                    <option value="{{ $ciclo->id }}"  {{ $concepto->ciclo_id==$ciclo->id ? 'selected' : '' }}  >{{ $ciclo->ciclo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-3/12 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            <select name="corresponde" id=""
                                class="w-full py-1 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }} text-sm font-thin text-gray-500 truncate border-0 rounded-md" >
                                <option value="1"  {{ $concepto->ciclocorrespondiente=='1' ? 'selected' : '' }}  >Anterior</option>
                                <option value="0" {{ $concepto->ciclocorrespondiente=='0' ? 'selected' : '' }} >Corriente</option>
                                <option value="2" {{ $concepto->ciclocorrespondiente=='2' ? 'selected' : '' }} >Ninguno</option>
                            </select>
                        </div>
                        <div class="w-2/12 flex {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            <div class="mt-1">
                                <button type="button" class="text-center btn btn-primary" name="Guardar" onclick="form.submit()">
                                    <x-icon.save />
                                </button>
                            </div>
                </form>
                            <div class="mt-1">
                                <form role="form" method="post" action="{{ route('facturacionconcepto.destroy',$concepto->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="enlace">
                                        <x-icon.trash-can class="text-red-500 "/>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="w-8/12 ">
                        @livewire('facturacion-conceptos.concepto-detalles',['concepto'=>$concepto,'color'=>$loop->even ? 'yellow' : 'green'],key($concepto->id))
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center">
                    <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                    <span class="py-5 text-xl font-medium text-gray-500">
                        No se han encontrado registros...
                    </span>
                </div>
            @endforelse
        </div>
    <div class="flex mt-0 ml-2 space-x-1">
        <div class="space-x-3">
            <x-jet-secondary-button  onclick="location.href = '{{url()->previous()}}'">{{ __('Volver') }}</x-jet-secondary-button>
        </div>
    </div>
    <x-notification/>
</div>
