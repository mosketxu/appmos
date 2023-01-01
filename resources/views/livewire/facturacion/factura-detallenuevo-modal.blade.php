<div>
    <div class="flex space-x-3">
        <button wire:click="cambianuevomodal()"
            class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" type="button" data-modal-toggle="defaultModal">
            Nuevo concepto
        </button>
    </div>
    {{-- Modal --}}
    <x-modal.datos wire:model="muestranuevomodal" >
        <x-slot name="title">
            {{ __('Nuevo concepto') }}
        </x-slot>
        <x-slot name="content">
            <form wire:submit.prevent="save" class="text-sm">
                <div class="flex space-x-2 text-sm">
                    <div class="w-2/12 form-item">
                        <x-jet-label for="orden">{{ __('Orden') }}</x-jet-label>
                        <x-input.text type="number" class="w-full py-1" wire:model="orden" />
                    </div>
                    <div class="w-10/12 form-item">
                        <x-jet-label for="concepto">{{ __('Concepto') }}</x-jet-label>
                        <x-input.text  class="w-full py-1" wire:model="concepto" />
                    </div>
                </div>
                <div class="mt-5 ">
                    <x-jet-secondary-button wire:click="cancelarnuevomodal()">
                        {{ __('Cancelar') }}
                    </x-jet-secondary-button>
                    <x-jet-button type="submit" class="bg-blue-700 hover:bg-blue-900" >
                        {{ __('Guardar') }}
                    </x-jet-button>
                </div>
            </form>
        </x-slot>
        <x-slot name="footer">
            <div class="text-left">
                @include('errores')
            </div>
        </x-slot>
    </x-modal.datos>
</div>
