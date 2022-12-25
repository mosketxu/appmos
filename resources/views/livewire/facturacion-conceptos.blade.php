<div class="">
    @livewire('menu',['entidad'=>$ent,'ruta'=>$ruta],key($ent->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Conceptos de facturación de {{ $ent->entidad }} <span class="text-lg text-gray-500 "> ({{ $ent->nif }})</span></h1>

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
                <x-button.primary wire:click="create"><x-icon.plus/> Nuevo Concepto</x-button.primary>
            </div>

            {{-- tabla conceptos --}}
            <div class="flex-col space-y-4">
                <x-table>
                    <x-slot name="head">
                        <x-table.heading class="pl-4 text-left">{{ __('Agrupacion') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Ciclo') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Corresponde a:') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Concepto') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Importe') }} </x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Orden') }} </x-table.heading>
                        <x-table.heading colspan="2"/>
                    </x-slot>
                    <x-slot name="body">
                        @forelse ($conceptos as $concepto)
                            @forelse($concepto->detalles as $detalles)
                                <x-table.row wire:loading.class.delay="opacity-50">
                                    <x-table.cell><input type="text" value="{{ $concepto->concepto }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell><input type="text" value="{{ $concepto->ciclo->ciclo }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell><input type="text" value="{{ $concepto->corresponde }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell><input type="text" value="{{ $detalles->concepto }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell><input type="number" step="any" value="{{ $detalles->importe }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell><input type="number" value="{{ $detalles->orden }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/></x-table.cell>
                                    <x-table.cell>
                                        <div class="flex items-center justify-center space-x-3">
                                            <x-icon.edit-a wire:click="edit({{ $detalles->id }})" href="#"/>
                                            <x-icon.delete-a wire:click.prevent="delete({{ $concepto->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()"/>
                                        </div>
                                    </x-table.cell>
                                </x-table.row>
                            @empty
                                <x-table.row>
                                    <x-table.cell colspan="7">
                                </x-table.cell>
                                </x-table.row>
                            @endforelse
                            @if ($loop->last)
                                <tr><td><hr></td></tr>
                            @endif
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
            <div>
            </div>
            <div class="flex mt-0 ml-2 space-x-4">
                <div class="space-x-3">
                    <x-jet-secondary-button  onclick="location.href = '{{url()->previous()}}'">{{ __('Volver') }}</x-jet-secondary-button>
                </div>
            </div>
            <x-notification/>
        </div>
        <!-- Save Transaction Modal -->
        <form wire:submit.prevent="save">
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
            <x-jet-validation-errors/>
            <x-modal.dialog wire:model.defer="showEditModal">
                <x-slot name="title">Editar Concepto</x-slot>

                <x-slot name="content">
                    <x-input.group for="concepto" label="Agrupación" :error="$errors->first('editing.concepto')">
                        <x-input.text wire:model.defer="editing.concepto" id="concepto"  />
                    </x-input.group>

                    <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:border-t sm:border-gray-200 sm:py-5">
                        <label for="ciclo_id" class="block text-sm font-medium leading-5 text-gray-700 sm:mt-px sm:pt-2">
                            Ciclo
                        </label>
                        <div class="mt-1 rounded-md shadow-sm sm:mt-0 sm:col-span-2">
                            <select wire:model.defer="editing.ciclo_id"
                                class="block w-full p-2 transition duration-150 border border-blue-300 rounded-lg form-input hover:border-blue-300 focus:border-blue-300 active:border-blue-300'" >
                                <option value="">-- choose --</option>
                                @foreach ($ciclos as $ciclo)
                                    <option value="{{ $ciclo->id }}">{{ $ciclo->ciclo }}</option>
                                @endforeach
                            </select>
                            @error('editing.ciclo_id') <span class="text-red-500">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:border-t sm:border-gray-200 sm:py-5">
                        <label for="ciclocorrespondiente" class="block text-sm font-medium leading-5 text-gray-700 sm:mt-px sm:pt-2">
                            Ciclo Correspondiente
                        </label>
                        <div class="mt-1 rounded-md shadow-sm sm:mt-0 sm:col-span-2">
                            <select wire:model.defer="editing.ciclocorrespondiente"
                                class="block w-full p-2 transition duration-150 border border-blue-300 rounded-lg form-input hover:border-blue-300 focus:border-blue-300 active:border-blue-300'" >
                                <option value="">-- choose --</option>
                                <option value="1"  {{ $editing->ciclocorrespondiente=='1' ? 'selected' : '' }}  >Anterior</option>
                                <option value="0" {{ $editing->ciclocorrespondiente=='0' ? 'selected' : '' }} >Corriente</option>
                                <option value="2" {{ $editing->ciclocorrespondiente=='2' ? 'selected' : '' }} >Ninguno</option>
                            </select>
                            @error('editing.ciclocorrespondiente') <span class="text-red-500">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <x-input.group for="concepto" label="Concepto" :error="$errors->first('editing.concepto')">
                        <x-input.text wire:model.defer="concepto" id="concepto"  />
                    </x-input.group>

                    <x-input.group for="importe" label="Importe" :error="$errors->first('editing.importe')">
                        <x-input.text wire:model.defer="importe" id="importe" />
                    </x-input.group>

                    <x-input.group for="orden" label="orden" :error="$errors->first('editing.orden')">
                        <x-input.text wire:model.defer="orden" id="orden" />
                    </x-input.group>

                </x-slot>
                <x-slot name="footer">
                    <x-button.secondary wire:click="$set('showEditModal', false)">Cancel</x-button.secondary>
                    <x-button.primary type="submit">Save</x-button.primary>
                </x-slot>
            </x-modal.dialog>
        </form>
    </div>
</div>


