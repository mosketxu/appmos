<div class="">
    @livewire('menu',['entidad'=>$ent,'ruta'=>$ruta],key($ent->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Contactos de {{ $ent->entidad }} <span class="text-lg text-gray-500 "> ({{ $ent->nif }})</span></h1>

        <div class="py-1 space-y-4">
            <div class="flex justify-between">
                <div class="flex w-2/4 space-x-2">
                    <input type="text" wire:model.live.debounce.1000ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                </div>
                {{-- <x-button.primary href="#" class="py-1"><x-icon.plus/> Nueva</x-button.primary> --}}
            </div>
            {{-- tabla contactos --}}
            <div class="max-h-96 min-w-full overflow-x-auto overflow-y-auto align-middle shadow sm:rounded-b-lg">
                <x-table>
                    <x-slot name="head">
                        <x-table.head class="pl-2">{{ __('Entidad') }}</x-table.head>
                        <x-table.head class="pl-2">{{ __('Nif') }} </x-table.head>
                        <x-table.head class="pl-2">{{ __('Tfno') }}</x-table.head>
                        <x-table.head class="pl-2">{{ __('Email Gral') }}</x-table.head>
                        <x-table.head class="pl-2">{{ __('Departamento') }}</x-table.head>
                        <x-table.head class="pl-2">{{ __('Obs.Contacto') }}</x-table.head>
                        <x-table.head colspan="2"/>
                    </x-slot>
                    <x-slot name="body">
                        @forelse ($contactos as $index=>$contacto)
                            <x-table.row wire:loading.class.delay="opacity-50">
                                <x-table.cell class="w-2/12">
                                    <input type="text" value="{{ $contacto->entidad }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="w-1/12">
                                    <input type="text" value="{{ $contacto->nif }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell  class="w-1/12">
                                    <input type="text" value="{{ $contacto->tfno }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="w-2/12">
                                    <input type="text" value="{{ $contacto->emailgral }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="w-2/12">
                                    <input type="text"value="{{ $contacto->departamento }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="w-3/12">
                                    <input type="text"value="{{ $contacto->comentarios }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell class="w-1/12 pr-2 text-right">
                                    <div class="flex">
                                        <x-icon.edit-a href="{{ route('entidad.edit',$contacto->contacto_id) }}"  title="Editar"/>
                                        <x-icon.delete-a wire:click.prevent="delete({{ $contacto['id'] }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1"  title="Eliminar contacto"/>
                                    </div>
                                </x-table.cell>
                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="10">
                                    <div class="flex items-center justify-center">
                                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                        <span class="py-5 text-xl font-medium text-gray-500">
                                            No se han encontrado contactos...
                                        </span>
                                    </div>
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-slot>
                </x-table>
            </div>
        </div>
        <div class="min-w-full overflow-hidden overflow-x-auto align-middle shadow sm:rounded-lg">
            <x-jet-validation-errors/>
            <div class="px-2 mx-2 my-1 rounded-md bg-blue-50">
                <h3 class="font-semibold ">Selecciona un contacto o pulsa <a href="{{ route('entidad.createcontacto',$ent->id) }}"><span class="text-blue-600 underline ">AQUÍ</span></a> aqui para crear uno nuevo.</h3>
                <x-jet-input  wire:model.defer="entidad.id" type="hidden"/>
                <hr>
            </div>
            <form wire:submit.prevent="savecontacto">
                <div class="flex flex-col mx-2 my-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                    <div class="w-full form-item">
                        <x-jet-label for="contacto" >{{ __('Contactos') }}</x-jet-label>
                        <x-select wire:model.defer="contacto"  selectname="contacto" class="w-full">
                            <option value="">-- Elige un contacto --</option>
                            @foreach ($entidades as $contacto)
                            <option value="{{ $contacto->id }}">{{ $contacto->entidad }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="w-full form-item">
                        <x-jet-label for="departamento">{{ __('Departamento') }}</x-jet-label>
                        <x-jet-input  wire:model.defer="departamento" type="text" id="departamento" class="w-full" :value="old('departamento') "/>
                        <x-jet-input-error for="departamento" class="mt-2" />
                    </div>
                    <div class="w-full form-item">
                        <x-jet-label for="comentarios">{{ __('Comentario') }}</x-jet-label>
                        <x-jet-input  wire:model.defer="comentario" type="text" id="comentarios"  class="w-full" :value="old('comentarios')" />
                        <x-jet-input-error for="comentarios" class="mt-2" />
                    </div>
                    <div class="w-full form-item">
                        <x-jet-button class="mt-5 bg-blue-600">
                            {{ __('Añadir contacto') }}
                        </x-jet-button>
                    </div>
                </div>
            </form>
        </div>
        <div class="flex mt-2 ml-2 space-x-4">
            <div class="space-x-3">
                <x-jet-secondary-button  onclick="location.href = '{{route('entidades')}}'">{{ __('Volver') }}</x-jet-secondary-button>
            </div>
        </div>
    </div>

</div>
