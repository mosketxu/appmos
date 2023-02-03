<div class="">
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))
    <div class="p-1 mx-2">
        <h1 class="text-2xl font-semibold text-gray-900">Facturación  {{ $entidad->id? 'de '. $entidad->entidad  :'' }} </h1>

        <div class="">
            @include('errores')
        </div>

            <x-jet-validation-errors/>

            {{-- filtros y boton --}}
            <div>
                <div class="flex justify-between space-x-1">
                    <div class="inline-flex space-x-2">
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">&nbsp;</label>
                            <input type="search" wire:model="search" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Búsqueda Entidad/Factura" autofocus/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Año</label>
                            <input type="search" wire:model="filtroanyo" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Año"/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Mes</label>
                            <input type="search" wire:model="filtromes" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none" placeholder="Mes (número)"/>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Facturado</label>
                            <select wire:model="filtrofacturado" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Contabilizado</label>
                            <select wire:model="filtrocontabilizado" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Enviar</label>
                            <select wire:model="filtroenviar" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Enviadas</label>
                            <select wire:model="filtroenviada" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="inline-block text-xs form-group">
                            <label class="px-1 text-gray-600">Pagadas</label>
                            <select wire:model="filtropagada" class="w-full py-2 text-xs text-gray-600 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="text-xs">
                            <label class="px-1 text-gray-600">Fecha Remesa</label>
                            <input type="date" wire:model="filtroremesa" class="w-full py-2 text-xs text-gray-600 placeholder-gray-300 bg-white border-blue-300 rounded-md shadow-sm appearance-none hover:border-gray-400 focus:outline-none"/>
                        </div>
                    </div>
                    <div class="inline-flex mt-3 space-x-2">
                        <x-dropdown label="Actions">
                            <x-dropdown.item type="button" wire:click="zipSelected" class="flex items-center space-x-2">
                                <x-icon.download class="text-gray-400"></x-icon.download> <span>Generar Zip </span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="mailSelected" class="flex items-center space-x-2">
                                <x-icon.arroba class="text-gray-400"></x-icon.arroba> <span>Enviar Mail </span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="exportSelected" class="flex items-center space-x-2">
                                <x-icon.csv class="text-green-400"></x-icon.csv><span>Export </span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="exportRemesa" class="flex items-center space-x-2">
                                <x-icon.xls class="text-yellow-400"></x-icon.xls> <span>Remesa XLS</span>
                            </x-dropdown.item>
                            <x-dropdown.item type="button" wire:click="$toggle('showDeleteModal')" class="flex items-center space-x-2">
                                <x-icon.trash class="text-red-400"></x-icon.trash> <span>Delete </span>
                            </x-dropdown.item>
                        </x-dropdown>

                        <div class="text-xs">
                            <x-button.button color="blue" onclick="location.href = '{{ route('facturacion.create') }}'">Nueva</x-button.button>
                            {{-- <input type="button" onclick="location.href = '{{ route('facturacion.create') }}'" class="w-full px-2 py-2 text-xs text-white bg-blue-700 rounded-md shadow-sm hover:bg-blue-500 " value="{{ __('Nueva Factura') }}"/> --}}
                        </div>
                    </div>
                </div>
            </div>
            <x-jet-nav-link href="{{ route('facturacion.import') }}" >
                {{ __('Importfac') }}
            </x-jet-nav-link>
            {{-- tabla facturaciones --}}

            <div class="min-w-full min-h-screen align-middle shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="text-xs leading-4 tracking-wider text-gray-500 bg-blue-50 ">
                        <tr class="">
                            <th class="w-5 py-3 pl-2 font-medium text-center"><x-input.checkbox wire:model="selectPage"/></th>
                            <th class="py-3 font-medium text-center ">#</th>
                            <th class="font-medium text-left">{{ __('Factura') }}</th>
                            <th class="pl-4 font-medium text-left ">{{ __('F.Factura') }}</th>
                            <th class="pl-4 font-medium text-left">{{ __('F.Vto') }}</th>
                            <th class="pl-4 font-medium text-left">{{ __('Entidad') }}</th>
                            <th class="pl-4 font-medium text-left">{{ __('Pago') }} </th>
                            <th class="pl-4 font-medium text-left">{{ __('Email') }}</th>
                            <th class="w-24 pl-4 font-medium text-left">{{ __('Ref.Cli') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Base (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Exenta (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Iva (€)') }}</th>
                            <th class="w-24 pr-4 font-medium text-right">{{ __('Total (€)') }}</th>
                            <th class="" title="Enviar"><x-icon.arroba/></th>
                            <th class="" title="Enviada"><x-icon.plane/></th>
                            <th class="" title="Facturado"><x-icon.edit/></th>
                            <th class="" title="Pagada"><x-icon.money/></th>
                            <th class="" title="Contabilizado"><x-icon.sage/></th>
                            <th colspan="2"></th>
                        </tr>
                    </thead>
                    <tbody class="text-xs bg-white divide-y divide-gray-200">
                        @if($selectPage)
                            <tr class="bg-gray-200" wire:key="row-message">
                                <td  class="py-3 pl-2 font-medium" colspan="18">
                                @unless($selectAll)
                                    <span>Has seleccionado <strong>{{ $facturaciones->count() }}</strong> facturas, ¿quieres seleccionar el total: <strong>{{ $facturaciones->total() }}</strong> ?</span>
                                    <x-button.link wire:click="selectAll" class="ml-1 text-blue-600">Select all</x-button.link>
                                @else
                                    <span>Has seleccionado <strong>todas</strong> las {{ $facturaciones->total() }} facturas</span>
                                @endif
                                </td>
                            </tr>
                        @endif
                        @forelse ($facturaciones as $facturacion)
                            <tr wire:loading.class.delay="opacity-50" wire:key="fila-{{ $facturacion->id }}">
                                <td  class="w-5 py-3 pl-2 font-medium text-center">
                                    <x-input.checkbox wire:model="selected" value="{{ $facturacion->id }}"/>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('facturacion.edit',$facturacion) }}"  class="text-xs text-gray-200 transition duration-150 ease-in-out hover:outline-none hover:text-gray-800 hover:underline">
                                        {{ $facturacion->id }}
                                    </a>
                                </td>
                                <td class="text-right">
                                    @if($facturacion->numfactura)
                                        <input type="text" value="{{ $facturacion->numfactura }}" class="w-full text-xs font-thin text-left text-gray-500 truncate border-0 rounded-md"  readonly/>
                                    @endif
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->dateFra }}" class="w-full text-xs font-thin text-left text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->dateVto }}" class="w-full text-xs font-thin text-left text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </td>
                                <td>
                                    <input type="text" value="{{ $facturacion->entidad }}" class="w-full text-xs font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
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
                                    <span class="pr-4 text-xs text-blue-500">{{ number_format($facturacion->totalesbase,2) }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ number_format($facturacion->totalesexenta,2) }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ number_format($facturacion->totalesiva,2) }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="pr-4 text-xs text-blue-500">{{ number_format($facturacion->totalestotal,2) }}</span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->enviar_est[0] }}-100 text-green-800">
                                        {{ $facturacion->enviar_est[1] }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->enviada_est[0] }}-100 text-green-800">
                                        {{ $facturacion->enviada_est[1] }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->facturado[0] }}-100 text-green-800">
                                        {{ $facturacion->facturado[1] }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->pagada_est[0] }}-100 text-green-800">
                                        {{ $facturacion->pagada_est[1] }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 bg-{{ $facturacion->contabilizada[0] }}-100 text-green-800">
                                        {{ $facturacion->contabilizada[1] }}
                                    </span>
                                </td>
                                <td class="">
                                    <div class="flex items-center justify-center w-full">
                                        <x-icon.edit-a href="{{ route('facturacion.edit',$facturacion) }}" title="Factura"/>
                                        <a href = '{{asset('storage/'.$facturacion->rutafichero)}}'  target='_blank'  class="" title="PDF"><x-icon.pdf class=""></x-icon.pdf></a>
                                        <a href="{{route('facturacion.pdffactura',[$facturacion->id])}}" target="_blank" title="Imprimir factura"><x-icon.pdf class="text-green-500 hover:text-red-700"/></a>
                                        <x-icon.copy-a wire:click="replicateFactura({{ $facturacion->id }})" onclick="confirm('¿Estás seguro de querer copiar la factura?') || event.stopImmediatePropagation()" class="text-purple-500" title="Copiar Factura" />
                                        <x-icon.delete-a wire:click.prevent="delete({{ $facturacion->id }})" onclick="confirm('¿Estás seguro?') || event.stopImmediatePropagation()" class="" title="Borrar"/>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="flex items-center justify-center">
                                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                        <span class="py-5 text-xl font-medium text-gray-500">
                                            No se han encontrado facturas...
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
