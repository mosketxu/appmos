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
                <div class="space-y-2 text-sm">
                    <div class="w-full form-item">
                        <x-jet-label for="id">Entidad </x-jet-label>
                        <x-input.text class="w-full py-1" wire:model="entidad" disabled/>
                    </div>
                    <div class="w-full form-item">
                        <x-jet-label for="agrupacion">{{ __('Agrupación') }}</x-jet-label>
                        <x-input.text  class="w-full py-1" wire:model="agrupacion" />
                    </div>
                    <div class="w-full form-item">
                        <x-jet-label for="ciclofacturacionid" >{{ __('Ciclo Facturación') }}</x-jet-label>
                        <select wire:model.defer="ciclofacturacionid"
                        class="block w-full p-1 transition duration-150 border border-blue-300 rounded-lg form-input hover:border-blue-300 focus:border-blue-300 active:border-blue-300" >
                            <option value="">-- choose --</option>
                            @foreach (App\Models\Entidad::CICLOS as $value=>$label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full form-item">
                        <x-jet-label for="ciclocorrespondiente" >{{ __('Ciclo Correspondiente') }}</x-jet-label>
                        <select wire:model.defer="ciclocorrespondiente"
                            class="block w-full p-1 transition duration-150 border border-blue-300 rounded-lg form-input hover:border-blue-300 focus:border-blue-300 active:border-blue-300'" >
                            <option value="">-- choose --</option>
                            <option value="1"  {{ $ciclocorrespondiente=='1' ? 'selected' : '' }}  >Anterior</option>
                            <option value="0" {{ $ciclocorrespondiente=='0' ? 'selected' : '' }} >Corriente</option>
                            <option value="2" {{ $ciclocorrespondiente=='2' ? 'selected' : '' }} >Ninguno</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 ">
                    <x-jet-secondary-button wire:click="cambianuevomodal()">
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
