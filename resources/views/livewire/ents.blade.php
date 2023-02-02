<div class="">
    {{-- @livewire('navigation-menu')  --}}
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Entidades</h1>

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
                <div class="flex w-2/4 space-x-2">
                    <input type="text" wire:model.debounce.500ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                    <div class="hidden sm:block px-1 text-xs">
                        <label class="px-1 text-gray-600">Clientes</label>
                        <select wire:model="filtrocliente" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="hidden sm:block px-1 text-xs">
                        <label class="px-1 text-gray-600">Activos</label>
                        <select wire:model="filtroactivo" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="hidden sm:block px-1 text-xs">
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
            <div class="flex-col space-y-4">
                <x-table>
                    <x-slot name="head">
                        {{-- <x-table.heading class="p-0 m-0 text-right w-min">{{ __('#') }}</x-table.heading> --}}
                        <x-table.heading class="hidden md:block text-center">{{ __('Fav') }} </x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Entidad') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Nif') }} </x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-left">{{ __('Mail') }} </x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('Cliente') }}</x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('Tipo') }}</x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('Facturar') }}</x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('C.Impuestos') }}</x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('C.Fact.') }}</x-table.heading>
                        <x-table.heading class="hidden md:table-cell pl-4 text-center">{{ __('Estado') }}</x-table.heading>
                        <x-table.heading colspan="2"/>
                    </x-slot>
                    <x-slot name="body">
                        @forelse ($entidades as $entidad)
                            <x-table.row wire:loading.class.delay="opacity-50">
                                <x-table.cell class="hidden md:block text-right">
                                    {{-- <a href="{{ route('entidad.edit',$entidad) }}" wire:click="edit" class="text-xs text-gray-700 transition duration-150 ease-in-out focus:outline-none focus:text-gray-800 focus:underline"> --}}
                                        <span class="inline-flex text-gray-200 align-baseline">
                                            {{ $entidad->id }} &nbsp;
                                            @if ($entidad->favorito)
                                                <x-icon.star-solid class="text-yellow-500"></x-icon.star-solid>
                                            @else
                                                <x-icon.star class="text-gray-500 "></x-icon.star>
                                            @endif
                                        </span>
                                    {{-- </a> --}}
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $entidad->entidad }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $entidad->nif }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $entidad->emailgral }}" class="hidden md:w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    @if($entidad->cliente=="1")
                                        <span class="px-2.5 py-0.5 font-bold text-green-400">&#10003;</span>
                                    @endif
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    <span class="text-sm text-gray-500 ">{{$entidad->entidadtipo->entidadtipo ?? '-'}}</span>
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    @if($entidad->facturar=="1")
                                        <span class="px-2.5 py-0.5 font-bold text-green-400">&#10003;</span>
                                    @endif
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    <span class="text-sm text-gray-500 ">{{$entidad->cicloimp->ciclo ?? '-'}}</span>
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    <span class="text-sm text-gray-500 ">{{$entidad->ciclofac->ciclo ?? '-'}}</span>
                                </x-table.cell>
                                <x-table.cell class="hidden md:table-cell text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $entidad->status_color[0] }}-100 text-green-800">
                                        {{ $entidad->status_color[1] }}
                                    </span>
                                </x-table.cell>
                                <x-table.cell class="px-4">
                                    <div class="flex items-center justify-center space-x-3">
                                        <x-icon.key href="{{ route('entidad.pu',$entidad) }}" title="Pus"/>
                                        <x-icon.usergroup href="{{ route('entidad.contacto',$entidad) }}"  title="Contactos"/>
                                        <x-icon.edit-a href="{{ route('entidad.edit',$entidad) }}"  title="Editar"/>
                                        {{-- <x-icon.bars-a href="{{ route('entidad.facturacionconceptos',$entidad)}}"  title="Conceptos"/> --}}
                                        <x-icon.bars-a href="{{ route('facturacionconcepto.entidad',$entidad)}}"  title="Conceptos"/>
                                        <x-icon.ruble-sign-a href="{{ route('facturacion.prefacturasentidad',$entidad)}}"  title="Pre-Facturas"/>
                                        <x-icon.euro-a href="{{ route('facturacion.show',$entidad)}}"  title="Facturas"/>
                                        <x-icon.delete-a wire:click.prevent="delete({{ $entidad->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1"/>
                                    </div>
                                </x-table.cell>
                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="10">
                                    <div class="flex items-center justify-center">
                                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                        <span class="py-5 text-xl font-medium text-gray-500">
                                            No se han encontrado entidades...
                                        </span>
                                    </div>
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-slot>
                </x-table>
                <div>
                    {{ $entidades->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
