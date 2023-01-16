<div class="p-1 mx-2">

    <div class="p-1 mx-2">
        <div class="flex justify-between space-x-1">
            {{-- totales --}}
            <div class="flex w-full">
                <div class="w-4/12">
                    <h1 class="text-2xl font-semibold text-gray-900">Detalle de la factura</h1>
                    {{-- <p>Show crear: {{ $showcrear }}</p> --}}
                </div>
                <div class="w-6/12 flex mt-1.5 text-sm space-x-3 text-gray-900">
                    {{-- <div class="w-3/12 border ">
                        <div class="flex">
                            <div class="w-3/4 ml-2">Base 4%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $base04 }}</div>
                        </div>
                        <div class="flex">
                            <div class="w-3/4 ml-2">Iva 4%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $iva04 }}</div>
                        </div>
                    </div>
                    <div class="w-3/12 border ">
                        <div class="flex">
                            <div class="w-3/4 ml-2">Base 10%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $base10 }}</div>
                        </div>
                        <div class="flex">
                            <div class="w-3/4 ml-2">Iva 10%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $iva10 }}</div>
                        </div>
                    </div> --}}
                    <div class="w-3/12 border ">
                        <div class="flex">
                            <div class="w-3/4 ml-2">Base 21%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $base21}}</div>
                        </div>
                        <div class="flex">
                            <div class="w-3/4 ml-2">Iva 21%:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $iva21 }}</div>
                        </div>
                    </div>
                    <div class="w-3/12 border ">
                        <div class="flex">
                            <div class="w-3/4 ml-2">Exento:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $exenta }}</div>
                        </div>
                        <div class="flex">
                            <div class="w-3/4 ml-2">Suplidos:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $suplido }}</div>
                        </div>
                    </div>
                    <div class="w-3/12 font-bold border">
                        <div class="flex">
                            <div class="w-3/4 ml-2">Total:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $total }}</div>
                        </div>
                        <div class="flex">
                            <div class="w-3/4 ml-2">Iva:</div>
                            <div class="w-1/4 mr-2 text-right">{{ $totaliva }}</div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-row-reverse w-2/12">
                    @if($deshabilitado=='')
                        @livewire('facturacion.factura-detallenuevo-modal',['factura'=>$factura])
                    @endif
                </div>
            </div>
        </div>
        <div class="py-0.5 ">
            <div class="flex w-full pt-2 pb-0 pl-2 space-x-2 text-xs font-bold text-gray-500 bg-blue-100 rounded-t-md">
                <div class="flex w-1/12 space-x-1 rounded-l-xl">
                    <div class="w-10/12"><input type="text" value="Agrup." class="w-full py-0.5 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled/></div>
                    <div class="w-2/12"></div>
                </div>
                <div class="flex w-11/12 space-x-1">
                    <div class="w-16" ><input type="text" value="Ord." class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Tipo" class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="subcta." class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-2/12"><input type="text" value="Concepto" class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Uds" class="w-full py-1 text-xs font-bold text-right text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Importe" class="w-full py-1 text-xs font-bold text-right text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="% Iva" class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Subtotal" class="w-full py-1 text-xs font-bold text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Iva" class="w-full py-1 text-xs font-bold text-right text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"><input type="text" value="Total" class="w-full py-1 text-xs font-bold text-right text-gray-500 truncate bg-blue-100 border-0 rounded-md" disabled /></div>
                    <div class="w-1/12"></div>
                </div>
            </div>
            @forelse ($detalles as $detalle)
                <form method="POST" action="{{ route('facturaciondetalle.update',$detalle->id) }}" class="p-0 m-0">
                @csrf
                @method('PUT')
                <div class="flex w-full pl-2 text-xs text-gray-500 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                    <div class=" w-1/12 flex space-x-1 pt-0 mt-0 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}" wire:loading.class.delay="opacity-50">
                        <div class="w-8/12 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            <input type="text" name="agrup" value="{{ $detalle->concepto }}"
                            class="w-full py-0.5 {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }} text-xs font-thin text-gray-500 truncate border-0 rounded-md"
                            {{ $deshabilitado }}/>
                        </div>
                        <div class="w-4/12 flex {{ $loop->even ? 'bg-yellow-50' : 'bg-green-50' }}">
                            @if($deshabilitado=='')
                            <div class="mt-1">
                            <button type="button" class="text-center btn btn-primary" name="Guardar" onclick="form.submit()">
                                <x-icon.save />
                            </button>
                            </div>
                            @endif
                </form>
                            @if($deshabilitado=='')
                            <div class="mt-1">
                            <form role="form" method="post" action="{{ route('facturaciondetalle.destroy',$detalle->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="enlace">
                                    <x-icon.trash-can class="text-red-500 "/>
                                </button>
                            </form>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="w-11/12 pl-2">
                        @livewire('facturacion.factura-detalle-conceptos',['detalle'=>$detalle,'deshabilitado'=>$deshabilitado,'color'=>$loop->even ? 'yellow' : 'green'],key($detalle->id))
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
    </div>
</div>
