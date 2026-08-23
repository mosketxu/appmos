<div class="">
    @livewire('menu',['entidad'=>$ent,'ruta'=>$ruta],key($ent->id))

    <div class="p-1 mx-2">

        <h1 class="text-2xl font-semibold text-gray-900">Pus de {{ $ent->entidad }} <span class="text-lg text-gray-500 "> ({{ $ent->nif }})</span></h1>

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
                    <input type="text" wire:model.live.debounce.1000ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                </div>
                <x-button.primary wire:click="create"><x-icon.plus/> Nueva Pu</x-button.primary>

                {{-- <x-button.primary href="{{ route('pu.create') }}" class="py-0 my-0"><x-icon.plus/> Nueva</x-button.primary> --}}
            </div>

            {{-- tabla pus (movil: tarjeta de 3 lineas por registro) --}}
            <div class="space-y-2 md:hidden">
                @forelse ($pus as $pu)
                    <div class="p-3 space-y-2 bg-white border border-gray-200 rounded-lg shadow-sm" wire:loading.class.delay="opacity-50">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Destino') }}</span>
                                <span class="block font-medium text-gray-700 truncate">{{ $pu->destino }}</span>
                            </div>
                            <div class="flex items-center flex-shrink-0 pt-3 ml-2 space-x-3">
                                <x-icon.edit-a wire:click="edit({{ $pu->id }})" href="#"/>
                                <x-icon.delete-a wire:click.prevent="delete({{ $pu->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-2">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Url') }}</span>
                                <span class="block text-xs text-gray-600 truncate">{{ $pu->url ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('us') }}</span>
                                <span class="block text-xs text-gray-600 truncate">{{ $pu->us ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('us2') }}</span>
                                <span class="block text-xs text-gray-600 truncate">{{ $pu->us2 ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('ps') }}</span>
                                <span class="block text-xs text-gray-600 truncate">{{ $pu->ps ?: '—' }}</span>
                            </div>
                        </div>
                        @if($pu->observaciones)
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Observaciones') }}</span>
                                <span class="block text-xs text-gray-600 truncate">{{ $pu->observaciones }}</span>
                            </div>
                        @endif
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

            {{-- tabla pus (escritorio) --}}
            <div class="hidden md:block md:space-y-4">
                <x-table>
                    <x-slot name="head">
                        <x-table.heading class="pl-4 text-left">{{ __('Destino') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Url') }} </x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('us') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('us2') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('ps') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Observaciones') }}</x-table.heading>
                        <x-table.heading colspan="2"/>
                    </x-slot>
                    <x-slot name="body">
                        @forelse ($pus as $pu)
                            <x-table.row wire:loading.class.delay="opacity-50">
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->destino }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->url }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->us }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->us2 }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->ps }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->observaciones }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <div class="flex items-center justify-center space-x-3">
                                        <x-icon.edit-a wire:click="edit({{ $pu->id }})" href="#"/>
                                        <x-icon.delete-a wire:click.prevent="delete({{ $pu->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()"/>
                                    </div>
                                </x-table.cell>


                            </x-table.row>
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
                {{ $pus->links() }}
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
                <x-slot name="title">Editar Pu</x-slot>

                <x-slot name="content">
                    <x-input.group for="destino" label="destino" :error="$errors->first('editing.destino')">
                        <x-input.text wire:model="editing.destino" id="destino"  />
                    </x-input.group>

                    <x-input.group for="url" label="url" :error="$errors->first('editing.url')">
                        <x-input.text wire:model="editing.url" id="url" />
                    </x-input.group>

                    <x-input.group for="us" label="us" :error="$errors->first('editing.us')">
                        <x-input.text wire:model="editing.us" id="us" />
                    </x-input.group>

                    <x-input.group for="us2" label="us2" :error="$errors->first('editing.us2')">
                        <x-input.text wire:model="editing.us2" id="us2" />
                    </x-input.group>

                    <x-input.group for="ps" label="ps" :error="$errors->first('editing.ps')">
                        <x-input.text wire:model="editing.ps" id="ps" />
                    </x-input.group>

                    <x-input.group for="observaciones" label="observaciones" :error="$errors->first('editing.observaciones')">
                        <x-input.text wire:model="editing.observaciones" id="observaciones" />
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


