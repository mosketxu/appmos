<div class="">
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">
        <h1 class="text-2xl font-semibold text-gray-900">Pre-Facturación {{ $entidad->id? 'de '. $entidad->entidad  :'' }} </h1>
        <div class="py-1 space-y-4">
            <div class="">
                @include('errores')
            </div>

            {{-- filtros y boton --}}
            <div>
                <div class="flex justify-between space-x-1">
                    <div class="inline-flex space-x-2">
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">&nbsp;</label>
                            <input type="search" wire:model.debounce.750ms="search" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Búsqueda Entidad/Factura" autofocus/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Año</label>
                            <input type="search" wire:model.debounce.750ms="filtroanyo" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Año"/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Mes</label>
                            <input type="search" wire:model.debounce.750ms="filtromes" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Mes (número)"/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Facturable</label>
                            <select wire:model="filtrofacturable" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                    </div>
                    <div class="inline-flex mt-3 space-x-2">
                        {{-- <x-button.button color="blue" onclick="location.href = '{{ route('facturacion.createprefactura',$entidad) }}'">Nueva</x-button.button> --}}
                        <x-button.button color="blue" onclick="location.href = '{{ route('facturacion.createprefactura',$entidad) }}'">Nueva</x-button.button>

                        <x-dropdown label="Actions">
                            @if ($entidad->id)
                                <x-dropdown.item type="button" wire:click="$toggle('showPlanModal')" class="flex items-center space-x-2">
                                    <x-icon.invoice class="text-yellow-400"></x-icon.invoice> <span>Plan de Facturación </span>
                                </x-dropdown.item>
                            @endif
                            <x-dropdown.item type="button" wire:click="generarSelected" class="flex items-center space-x-2">
                                <x-icon.invoice class="text-pink-400"></x-icon.invoice> <span>Generar Facturas </span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="exportXls"  class="flex items-center space-x-2">
                                <x-icon.xls class="text-green-800"></x-icon.xls> <span>Export XLS</span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="exportSelected" class="flex items-center space-x-2">
                                <x-icon.csv class="text-green-400"></x-icon.csv> <span>Export Csv</span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="$toggle('showDeleteModal')" class="flex items-center space-x-2">
                                <x-icon.trash class="text-red-400"></x-icon.trash> <span>Delete </span>
                            </x-dropdown.item>
                        </x-dropdown>
                    </div>
                </div>
            </div>
            {{-- tabla pre-facturas --}}
            <table class="min-w-full divide-y divide-gray-200">
                    <div class="min-w-full overflow-hidden overflow-x-auto align-middle shadow min-vh-100 sm:rounded-lg">
                    <thead class="text-xs leading-4 tracking-wider text-gray-500 bg-blue-50 ">
                        <tr class="">
                            <th class="w-5 py-3 pl-2 font-medium text-center"><x-input.checkbox wire:model="selectPage"/></th>
                            <th class="py-3 font-medium text-center ">#</th>
                            <th class="font-medium text-center w-28">{{ __('F.Factura') }}</th>
                            <th class="font-medium text-center w-28">{{ __('F.Vto') }}</th>
                            <th class="pl-4 font-medium text-left">{{ __('Entidad') }}</th>
                            <th class="pl-4 font-medium text-left">{{ __('Ciclo') }} </th>
                            <th class="pl-4 font-medium text-left">{{ __('Pago') }} </th>
                            <th class="pl-4 font-medium text-left">{{ __('Email') }}</th>
                            <th class="w-24 pl-4 font-medium text-left">{{ __('Ref.Cli') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Base (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Exenta (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Iva (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Total (€)') }}</th>
                            <th class="" title="Enviar"><x-icon.arroba/></th>
                            <th class="" title="Facturable"><x-icon.euro/></th>
                            <th colspan="2"></th>
                        </tr>
                    </thead>
                    <tbody class="text-xs bg-white divide-y divide-gray-200">
                        @if($selectPage)
                            <tr class="bg-gray-200" wire:key="row-message">
                                <td  class="py-3 pl-2 font-medium" colspan="16">
                                @unless($selectAll)
                                    <span>Has seleccionado <strong>{{ $facturaciones->count() }}</strong> prefacturas, ¿quieres seleccionar el total: <strong>{{ $facturaciones->total() }}</strong> ?</span>
                                    <x-button.link wire:click="selectAll" class="ml-1 text-blue-600">Select all</x-button.link>
                                @else
                                    <span>Has seleccionado <strong>todas</strong> las {{ $facturaciones->total() }} prefacturas</span>
                                @endif
                                </td>
                            </tr>
                        @endif
                        @forelse ($facturaciones as $facturacion)
                            <tr wire:loading.class.delay="opacity-10" wire:key="fila-{{ $facturacion->id }}">
                                <td  class="w-5 py-3 pl-2 font-medium text-center">
                                    <x-input.checkbox wire:model="selected" value="{{ $facturacion->id }}"/>
                                </td>
                                <td class="text-right">
                                    <a href="#" wire:click="edit" class="text-xs text-gray-200 transition duration-150 ease-in-out hover:outline-none hover:text-gray-800 hover:underline">
                                        {{ $facturacion->id }}
                                    </a>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->datefra }}" class="w-full text-xs font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->datevto }}" class="w-full text-xs font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->entidad }}" class="w-full text-xs font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->ciclo->ciclo }}" class="w-full text-xs font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td class="text-center">
                                    <span class="text-sm text-gray-500 ">{{$facturacion->metodopago->metodopagocorto ?? '-'}}</span>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->mail }}" class="w-full text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->refcliente }}" class="w-full text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ $facturacion->totales['t'][0] }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ $facturacion->totales['e'][0] }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ $facturacion->totales['t'][2] }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ $facturacion->totales['t'][1] }}</span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->enviar_est[0] }}-100 text-green-800">
                                        {{ $facturacion->enviar_est[1] }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->facturable_est[0] }}-100 text-green-800">
                                        {{ $facturacion->facturable_est[1] }}
                                    </span>
                                </td>
                                <td class="">
                                    <div class="flex items-center justify-center">
                                        <x-icon.invoice-a href="{{ route('facturacion.editprefactura',$facturacion) }}" title="Pre-Factura"/>
                                        <x-icon.delete-a wire:click.prevent="delete({{ $facturacion->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="pl-1 " title="Borrar"/>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="flex items-center justify-center">
                                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                        <span class="py-5 text-xl font-medium text-gray-500">
                                            No se han encontrado pre facturas...
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div>
                    {{ $facturaciones->links() }}
                </div>
            </div>
        </div>

        <!-- Plan facturacion Modal -->
        @if($showPlanModal=='1')
            <form wire:submit.prevent="generarplan">
                <x-modal.confirmation wire:model.defer="showPlanModal">
                    <x-slot name="title">Plan de facturacion de la empresa: {{ $entidad->id? $entidad->entidad  :'' }}</x-slot>
                    <x-slot name="content">
                        <x-jet-label for="anyoplan" value="{{ __('Introduce el Año del plan') }}" />
                        <x-jet-input  wire:model.defer="anyoplan" type="text"/>
                        @error('anyoplan') <span class="text-red-500">{{ $message }}</span>@enderror
                    </x-slot>
                    <x-slot name="footer">
                        <x-button.secondary wire:click="$set('showPlanModal', false)">Cancel</x-button.secondary>
                        <x-button.primary type="submit">Generar Plan</x-button.primary>
                    </x-slot>
                </x-modal.confirmation>
        </form>
        @endif
        <!-- Delete Transactions Modal -->
        @if($showDeleteModal)
        <form wire:submit.prevent="deleteSelected">
            <x-modal.confirmation wire:model.defer="showDeleteModal">
                <x-slot name="title">Borrar Prefactura</x-slot>

                <x-slot name="content">
                    <div class="py-8 text-gray-700">¿Esás seguro? Esta acción es irreversible.</div>
                </x-slot>

                <x-slot name="footer">
                    <x-button.secondary wire:click="$set('showDeleteModal', false)">Cancel</x-button.secondary>

                    <x-button.primary type="submit">Delete</x-button.primary>
                </x-slot>
            </x-modal.confirmation>
        </form>
        @endif
    </div>
</div>
