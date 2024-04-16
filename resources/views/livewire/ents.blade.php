<div class="">
    {{-- @livewire('navigation-menu')  --}}
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Entidades</h1>

        <div class="py-1">
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
                <div class="flex w-2/4 space-x-2">
                    <input type="text" wire:model.live.debounce.1500ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                    <div class="hidden px-1 text-xs sm:block">
                        <label class="px-1 text-gray-600">Clientes</label>
                        <select wire:model="filtrocliente" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="hidden px-1 text-xs sm:block">
                        <label class="px-1 text-gray-600">Activos</label>
                        <select wire:model="filtroactivo" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="hidden px-1 text-xs sm:block">
                        <label class="px-1 text-gray-600">Facturar</label>
                        <select wire:model="filtrofacturar" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <x-button.button  onclick="location.href = '{{ route('entidad.nueva',$ruta) }}'" color="blue"><x-icon.plus/>{{ __('Nueva Entidad') }}</x-button.button>
            </div>
            {{-- tabla entidades --}}
            <div class="flex w-full mt-1 bg-blue-100 rounded-t-md">
                <div class="hidden pl-2 md:w-10 md:flex">{{ __('Fav') }} </div>
                <div class="w-6/12 pl-2 md:w-3/12 ">{{ __('Entidad') }}</div>
                <div class="w-1/12 md:w-1/12">{{ __('Nif') }} </div>
                <div class="hidden md:w-1/12 md:flex">{{ __('Facturar') }}</div>
                <div class="hidden md:w-1/12 md:flex">{{ __('Forma Pago') }}</div>
                <div class="hidden md:w-1/12 md:flex">{{ __('C.Impuestos') }}</div>
                <div class="hidden md:w-1/12 md:flex">{{ __('C.Fact.') }}</div>
                <div class="hidden md:w-1/12 md:flex">{{ __('Estado') }}</div>
                <div class="w-6/12 md:w-2/12"></div>
            </div>
            @forelse ($entidades as $entidad)
            <div class="flex w-full py-0 space-x-1 space-y-1 text-sm font-thin text-gray-500 truncate" wire:loading.class.delay="opacity-50">
                <div class="items-center hidden ml-2 text-xs text-gray-200 md:w-10 md:flex">
                        {{-- {{ $entidad->id }} &nbsp; --}}
                        @if ($entidad->favorito)
                            <x-icon.star-solid class="text-yellow-500"></x-icon.star-solid>
                        @else
                            <x-icon.star class="text-gray-500 "></x-icon.star>
                        @endif
                    </span>
                </div>
                <div class="w-6/12 md:w-3/12">
                    <input type="text" value="{{ $entidad->entidad }}" class="w-full text-sm font-thin border-0 rounded-md"  readonly/>
                </div>
                <div class="w-1/12 p-1 m-1 md:w-1/12">
                    <input type="text" value="{{ $entidad->nif }}" class="w-full p-1 m-1 text-sm font-thin border-0 rounded-md"  readonly/>
                </div>
                <div class="hidden md:w-1/12 md:flex">
                    @if($entidad->facturar=="1")
                        <span class="px-2.5 py-0.5 font-bold text-green-400">&#10003;</span>
                    @endif
                </div>
                <div class="hidden md:w-1/12 md:flex">
                    <span class="text-sm text-gray-500 ">{{$entidad->metodopago->metodopagocorto ?? '-'}}</span>
                </div>
                <div class="hidden md:w-1/12 md:flex">
                    <span class="text-sm text-gray-500 ">{{$entidad->cicloimp->ciclo ?? '-'}}</span>
                </div>

                <div class="hidden md:w-1/12 md:flex">
                    <span class="text-sm text-gray-500 ">{{$entidad->ciclofac->ciclo ?? '-'}}</span>
                </div>
                <div class="hidden md:w-1/12 md:flex">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $entidad->status_color[0] }}-100 text-green-800">
                        {{ $entidad->status_color[1] }}
                    </span>
                </div>
                <div class="w-6/12 md:w-2/12">
                    <div class="flex items-center justify-center space-x-3">
                        <x-icon.key href="{{ route('entidad.pu',$entidad) }}" title="Pus"/>
                        <x-icon.usergroup href="{{ route('entidad.contacto',$entidad) }}"  title="Contactos"/>
                        <x-icon.edit-a href="{{ route('entidad.edit',$entidad) }}"  title="Editar"/>
                        <x-icon.bars-a href="{{ route('facturacionconcepto.entidad',$entidad)}}"  title="Conceptos"/>
                        <x-icon.ruble-sign-a href="{{ route('facturacion.prefacturasentidad',$entidad)}}"  title="Pre-Facturas"/>
                        <x-icon.euro-a href="{{ route('facturacion.show',$entidad)}}"  title="Facturas"/>
                        <x-icon.delete-a wire:click.prevent="delete({{ $entidad->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1"/>
                    </div>
                </div>
            </div>
            @empty
            <div class="flex items-center justify-center">
                <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                <span class="py-5 text-xl font-medium text-gray-500">
                    No se han encontrado entidades...
                </span>
            </div>
            @endforelse
            <div>
                {{ $entidades->links() }}
            </div>
        </div>
    </div>
</div>
