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

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" wire:model.live.debounce.1000ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                    <div class="px-1 text-xs">
                        <label class="px-1 text-gray-600">Clientes</label>
                        <select wire:model.live="filtrocliente" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="1">Activo</option>
                            <option value="0">Baja</option>
                            <option value="2">Inactivo</option>
                            <option value="3">Liquidada</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="px-1 text-xs">
                        <label class="px-1 text-gray-600">Estado</label>
                        <select wire:model.live="filtroactivo" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="px-1 text-xs">
                        <label class="px-1 text-gray-600">Facturar</label>
                        <select wire:model.live="filtrofacturar" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="px-1 text-xs">
                        <label class="px-1 text-gray-600">Responsable</label>
                        <select wire:model.live="filtroresponsable" class="py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                            <option value="">Todos</option>
                            <option value="0">— sin responsable —</option>
                            @foreach ($sumas as $s) <option value="{{ $s->id }}">{{ $s->nombre }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <x-button.button  onclick="location.href = '{{ route('entidad.nueva',$ruta) }}'" color="blue"><x-icon.plus/>{{ __('Nueva Entidad') }}</x-button.button>
            </div>
            {{-- tabla entidades: en móvil, nombre + acciones y debajo los controles; en pantalla grande, columnas --}}
            <div class="flex w-full mt-1 text-sm bg-blue-100 rounded-t-md">
                <div class="hidden pl-2 lg:w-10 lg:flex">{{ __('Fav') }} </div>
                <div class="w-7/12 pl-2 md:w-4/12 lg:w-3/12 xl:w-2/12">{{ __('Entidad') }}</div>
                <div class="hidden xl:block xl:w-1/12">{{ __('Nif') }} </div>
                <div class="hidden md:flex md:w-2/12 lg:w-1/12">{{ __('Responsable') }}</div>
                <div class="hidden md:flex md:w-2/12 lg:w-1/12">{{ __('Otro Resp.') }}</div>
                <div class="hidden lg:w-1/12 lg:flex" title="Cliente / Proveedor / Contacto (clic para cambiar)">{{ __('Relación') }}</div>
                <div class="hidden lg:w-1/12 lg:flex">{{ __('Facturar') }}</div>
                <div class="hidden lg:w-1/12 lg:flex">{{ __('C.Impuestos') }}</div>
                <div class="hidden lg:w-1/12 lg:flex">{{ __('C.Fact.') }}</div>
                <div class="hidden lg:w-1/12 lg:flex">{{ __('Estado') }}</div>
                <div class="w-5/12 md:w-4/12 lg:w-2/12"></div>
            </div>
            @forelse ($entidades as $entidad)
            <div class="w-full py-0 text-sm font-thin text-gray-500 border-b border-gray-100 lg:border-0" wire:key="ent-{{ $entidad->id }}" wire:loading.class.delay="opacity-50">
            <div class="flex items-center w-full space-x-1">
                <div class="items-center hidden ml-2 text-xs text-gray-200 lg:w-10 lg:flex">
                    @if ($entidad->favorito)
                        <x-icon.star-solid class="text-yellow-500"></x-icon.star-solid>
                    @else
                        <x-icon.star class="text-gray-500 "></x-icon.star>
                    @endif
                </div>
                <div class="w-7/12 md:w-4/12 lg:w-3/12 xl:w-2/12">
                    <input type="text" value="{{ $entidad->entidad }}" class="w-full text-sm font-thin border-0 rounded-md"  readonly/>
                </div>
                <div class="hidden p-1 m-1 xl:block xl:w-1/12">
                    <input type="text" value="{{ $entidad->nif }}" class="w-full p-1 m-1 text-sm font-thin border-0 rounded-md"  readonly/>
                </div>
                {{-- Responsable: solo en pantalla media o grande --}}
                <div class="items-center hidden gap-1 md:flex md:w-2/12 lg:w-1/12">
                    @include('livewire.ents._control', ['e' => $entidad, 'c' => 'responsable'])
                    @can('entidades.editar')
                        <button type="button" wire:click="abrirCoResp({{ $entidad->id }})" title="Añadir o quitar otros responsables" class="px-1.5 text-sm font-bold text-indigo-600 border border-indigo-200 rounded hover:bg-indigo-50">+</button>
                    @endcan
                </div>
                <div class="items-center hidden md:flex md:w-2/12 lg:w-1/12">@include('livewire.ents._coresp', ['e' => $entidad, 'modo' => 'etiquetas'])</div>
                <div class="items-center hidden lg:w-1/12 lg:flex">@include('livewire.ents._control', ['e' => $entidad, 'c' => 'relacion'])</div>
                <div class="items-center hidden lg:w-1/12 lg:flex">@include('livewire.ents._control', ['e' => $entidad, 'c' => 'facturar'])</div>
                <div class="items-center hidden lg:w-1/12 lg:flex">@include('livewire.ents._control', ['e' => $entidad, 'c' => 'ciclo'])</div>
                <div class="hidden lg:w-1/12 lg:flex">
                    <span class="text-sm text-gray-500 ">{{$entidad->ciclofac->ciclo ?? '-'}}</span>
                </div>
                <div class="items-center hidden lg:w-1/12 lg:flex">@include('livewire.ents._control', ['e' => $entidad, 'c' => 'estado'])</div>
                <div class="w-5/12 md:w-4/12 lg:w-2/12">
                    <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 lg:flex-nowrap lg:space-x-3">
                        <x-icon.key href="{{ route('entidad.pu',$entidad) }}" title="Pus"/>
                        <x-icon.usergroup href="{{ route('entidad.contacto',$entidad) }}"  title="Contactos"/>
                        <x-icon.clock-a href="{{ route('entidad.historial',$entidad) }}"  title="Historial"/>
                        <x-icon.impuestos href="{{ route('entidad.impuestos',$entidad) }}" title="Impuestos" class="hidden sm:inline-block"/>
                        <x-icon.edit-a href="{{ route('entidad.edit',$entidad) }}"  title="Editar"/>
                        {{-- Ocultos a petición: botones Prefactura y Factura
                        <x-icon.ruble-sign-a href="{{ route('facturacion.prefacturasentidad',$entidad)}}"  title="Pre-Facturas"/>
                        <x-icon.euro-a href="{{ route('facturacion.show',$entidad)}}"  title="Facturas"/>
                        --}}
                        <x-icon.delete-a wire:click.prevent="delete({{ $entidad->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1"/>
                    </div>
                </div>
            </div>
            {{-- Móvil / tablet: los controles debajo del nombre --}}
            <div class="flex flex-wrap items-center gap-2 px-2 pb-2 lg:hidden">
                @include('livewire.ents._control', ['e' => $entidad, 'c' => 'relacion'])
                <span class="text-xs text-gray-400">Fact.</span>@include('livewire.ents._control', ['e' => $entidad, 'c' => 'facturar'])
                @include('livewire.ents._control', ['e' => $entidad, 'c' => 'ciclo'])
                @include('livewire.ents._control', ['e' => $entidad, 'c' => 'estado'])
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
            @include('livewire.ents._coresp', ['modo' => 'modal'])
            @include('livewire.ents._cambio_estado')
        </div>
    </div>
</div>
