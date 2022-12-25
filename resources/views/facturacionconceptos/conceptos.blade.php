<div class="">
    {{-- @livewire('menu',['entidad'=>$ent,'ruta'=>$ruta],key($ent->id)) --}}
    @livewire('menu',['entidad'=>$entidad],key($entidad->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Conceptos de facturación de {{ $entidad->entidad }} <span class="text-lg text-gray-500 "> ({{ $entidad->nif }})</span></h1>

        <div class="py-1 space-y-4">
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
            <div class="flex justify-between">
                <x-button.primary><x-icon.plus/> Nuevo Concepto</x-button.primary>
            </div>

            <x-jet-validation-errors/>

            @if (session()->has('success'))
            <div id="alert" class="relative px-6 py-2 mb-2 text-white bg-green-200 border-green-500 rounded border-1">
                <span class="inline-block mx-8 align-middle">
                    {{Session::get('success')}}
                </span>
                <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
                    <span>×</span>
                </button>
            </div>
        @endif

            {{-- tabla conceptos --}}
            <x-table>
                <x-slot name="head">
                    <x-table.heading width=15% class="pl-4 text-left">{{ __('Agrupacion') }}</x-table.heading>
                    <x-table.heading width=10% class="pl-4 text-left">{{ __('Ciclo') }}</x-table.heading>
                    <x-table.heading width=5% class="pl-4 text-left">{{ __('Per.Ref.:') }}</x-table.heading>
                    <x-table.heading width=50% class="pl-4 text-left">{{ __('Concepto') }}</x-table.heading>
                    <x-table.heading width=10% class="pl-4 pr-5 text-right">{{ __('Importe') }} </x-table.heading>
                    <x-table.heading width=5% class="pl-4 text-left">{{ __('Orden') }} </x-table.heading>
                    <x-table.heading width=5%/>
                </x-slot>
                <x-slot name="body">
                    @forelse ($conceptos as $concepto)
                        @forelse($concepto->detalles as $detalle)
                        <x-table.row wire:loading.class.delay="opacity-50">
                            @if ($loop->first)
                            <form method="POST" action="{{ route('facturacionconcepto.update',$concepto->id) }}">
                                @csrf
                                <x-table.cell><input type="text" name="concepto" value="{{ $concepto->concepto }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  /></x-table.cell>
                                <x-table.cell><input type="text" name="ciclo" value="{{ $concepto->ciclo->ciclo }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  /></x-table.cell>
                                <x-table.cell><input type="text" name="corresponde" value="{{ $concepto->corresponde }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  /></x-table.cell>
                            </form>
                            @else
                                <x-table.cell><input type="text" value="" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                <x-table.cell><input type="text" value="" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                <x-table.cell><input type="text" value="" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                            @endif
                            <form method="POST" action="{{ route('facturacionconceptodetalle.update',$detalle->id) }}">
                                @csrf
                                <input name="_method" type="hidden" value="PATCH">
                                <input type="hidden" name="facturacionconcepto_id" value={{ $concepto->id }}>
                                <x-table.cell><input type="text" name="concepto" value="{{ $detalle->concepto }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md" /></x-table.cell>
                                <x-table.cell><input type="number" name="importe" step="any" value="{{ $detalle->importe }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md"/></x-table.cell>
                                <x-table.cell><input type="number" name="orden" value="{{ $detalle->orden }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md" /></x-table.cell>
                                <x-table.cell>
                                    <div class="flex items-center justify-center space-x-3">
                                        {{-- <input type="hidden" name="_token" value="{{ csrf_token()}}" id="token"> --}}
                                        <button type="submit"><x-icon.edit/></button>
                                        {{-- <x-icon.edit-a href="{{route('facturacionconceptodetalle.edit', $detalle->id )}}"/> --}}
                            </form>
                            <form  action="{{route('facturacionconceptodetalle.destroy',$detalle->id)}}" method="post">
                                @method('DELETE')
                                @csrf
                                    <button type="submit"><x-icon.delete-a/></button>
                                    </div>
                                </x-table.cell>
                            </form>
                        </x-table.row>
                        @if ($loop->last)
                            <form action="{{route('facturacionconceptodetalle.store')}}" method="post">
                            @csrf
                            <x-table.row wire:loading.class.delay="opacity-50">
                                <input type="hidden" name="entidadId" value="{{ $entidad->id }}">
                                <input type="hidden" name="facturacionconcepto_id" value="{{ $concepto->id }}">
                                <x-table.cell  class=""><input type="text" value="" class="w-full py-1 my-1 ml-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                <x-table.cell  class=""><input type="text" value="" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md "  readonly/></x-table.cell>
                                <x-table.cell  class="text-right text-blue-800 "><button type="button" class="btn btn-primary" name="Guardar" onclick="form.submit()">Nuevo <x-icon.plus/></button></x-table.cell>
                                <x-table.cell  class="bg-blue-200 rounded-md rounded-r-none"><input type="text" name="concepto" value="{{ old('concepto') }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"/></x-table.cell>
                                <x-table.cell  class="bg-blue-200 "><input type="number" name="importe" step="any" value="{{ old('importe') }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md"/></x-table.cell>
                                <x-table.cell  class="bg-blue-200 rounded-md rounded-l-none"><input type="number" name="orden" value="{{ old('orden') }}" class="w-full py-1 text-sm font-thin text-right text-gray-500 truncate border-0 rounded-md"/></x-table.cell>
                                <x-table.cell  colspan="2" class="text-center"><button type="button" class="text-blue-800 btn btn-primary" name="Guardar" onclick="form.submit()"><x-icon.plus/></button></x-table.cell>
                                </x-table.row>
                            </form>
                            <tr class="bg-blue-300"><td colspan="7"></td></tr>
                        @endif
                        @empty
                        @endforelse
                    @empty
                        <x-table.row>
                            <x-table.cell colspan="7">
                                <div class="flex items-center justify-center">
                                    <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                    <span class="py-5 text-xl font-medium text-gray-500">
                                        No se han encontrado registros...
                                    </span>
                                </div>
                            </x-table.cell>
                        </x-table.row>
                    @endforelse
                </x-slot>
            </x-table>
        </div>
        <div class="flex mt-0 ml-2 space-x-4">
            <div class="space-x-3">
                <x-jet-secondary-button  onclick="location.href = '{{url()->previous()}}'">{{ __('Volver') }}</x-jet-secondary-button>
            </div>
        </div>
        <x-notification/>
    </div>

{{-- Modal --}}
</html>
</div>


