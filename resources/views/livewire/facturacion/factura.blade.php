<div class="">
    @if($factura->entidad && $factura->numfactura)
        @livewire('menu',['entidad'=>$factura->entidad,'ruta'=>$ruta],key($factura->entidad->id))
    @endif

    <div class="flex justify-between mx-5 mt-2">
        <div class="w-8/12">
            <h1 class="text-2xl font-semibold text-gray-900">Factura  {{ $nf }}</h1>
        </div>
        <div class="flex flex-row-reverse w-4/12">
            <div class="">
                <x-button.button color="blue" onclick="location.href = '{{ route('facturacion.create') }}'">Nueva</x-button.button>
            </div>
            <div class="">
                @if($existe=='1')
                    <a href = '{{asset('storage/'.$factura->rutafichero)}}'  target='_blank'><x-icon.pdf class="w-6 text-gray-400" title="PDF"/></a>
                @else
                    <x-icon.eye-slash class="mt-1 text-red-500 w-7" title="No hay pdf"></x-icon.eye-slash>
                @endif
            </div>
        </div>
    </div>

    <div class="">
        @include('errores')
    </div>

    <div class="flex-col mx-5 mt-2 text-gray-500 rounded-lg">
        <form wire:submit.prevent="save" >
            <div class="flex">
                {{-- datos factura --}}
                <div class="flex-initial w-8/12 py-2 mr-1 bg-white rounded-lg shadow-md">
                    <div class="px-2 mx-2 my-1 bg-blue-100 rounded-md">
                        <h3 class="font-semibold ">Datos Factura</h3>
                        <x-jet-input  wire:model.defer="factura.id" type="hidden"  id="id" name="id" :value="old('id')"/>
                    </div>
                    <div class="flex flex-col mx-2 space-y-2 md:space-y-0 md:flex-row md:space-x-1">
                        <div class="w-3/12 form-item">
                            <x-jet-label for="entidad_id">{{ __('Entidad') }} </x-jet-label>
                            @if($factura->entidad_id)
                                <x-input.text class="w-full py-1.5 text-sm" value="{{ $factura->entidad->entidad }}" disabled/>
                            @else
                                <x-select wire:model.lazy="factura.entidad_id" selectname="entidad_id" class="w-full" disabled="{{ $bloqueado }}">
                                    <option value="">--Selecciona--</option>
                                    @foreach ($entidades as $entidad)
                                        <option value="{{ $entidad->id }}">{{ $entidad->entidad }}</option>
                                    @endforeach
                                </x-select>
                            @endif
                        </div>
                        <div class="w-1/12 form-item">
                            <x-jet-label for="serie">{{ __('Serie') }}</x-jet-label>
                            <x-select wire:model.defer="factura.serie" selectname="serie" class="w-full"  disabled="{{ $bloqueado }}">
                                <option value="">--Serie--</option>
                                <option value="21">21</option>
                                <option value="22">22</option>
                                <option value="23">23</option>
                                <option value="24">24</option>
                                <option value="25">25</option>
                            </x-select>
                        </div>
                        <div class="w-2/12 form-item">
                            <x-jet-label for="numfactura">{{ __('Nº Factura') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="nf" type="text"  id="numfactura" name="numfactura" :value="old('numfactura') " class="w-full bg-gray-100" readonly/>
                            <x-jet-input-error for="numfactura" class="mt-2" />
                        </div>
                        <div class="w-2/12 form-item">
                            <x-jet-label for="fechafactura">{{ __('F.Factura') }}</x-jet-label>
                            <x-jet-input  wire:model.lazy="factura.fechafactura" type="date"  id="fechafactura" name="fechafactura" :value="old('fechafactura') " class="w-full "  disabled="{{ $bloqueado }}"/>
                            <x-jet-input-error for="fechafactura" class="mt-2" />
                        </div>
                        <div class="w-2/12 form-item">
                            <x-jet-label for="fechavencimiento">{{ __('F.Vto.') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.fechavencimiento" type="date"  id="fechavencimiento" name="fechavencimiento" :value="old('fechavencimiento')" class="w-full"  disabled="{{ $bloqueado }}"/>
                            <x-jet-input-error for="fechavencimiento" class="mt-2" />
                        </div>
                        <div class="w-2/12 form-item">
                            <x-jet-label for="metodopago_id">{{ __('M.Pago') }}</x-jet-label>
                            <x-select wire:model.defer="factura.metodopago_id" selectname="metodopago_id" class="w-full" disabled="{{ $bloqueado }}">
                                <option value="">-- choose --</option>
                                @foreach ($pagos as $pago)
                                    <option value="{{ $pago->id }}">{{ $pago->metodopagocorto }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </div>
                    <div class="flex flex-col mx-2 space-y-2 md:space-y-0 md:flex-row md:space-x-1">
                        <div class="w-3/6 form-item">
                            <x-jet-label for="mail">{{ __('Mail') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.mail" type="text"  id="mail" name="mail" :value="old('mail') " class="w-full" disabled="{{ $bloqueado }}"/>
                            <x-jet-input-error for="mail" class="mt-2" />
                        </div>
                        <div class="w-1/6 form-item">
                            <x-jet-label for="refcliente">{{ __('Ref.Cliente') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.refcliente" type="text"  id="refcliente" name="refcliente" :value="old('refcliente') " class="w-full" disabled="{{ $bloqueado }}"/>
                            <x-jet-input-error for="refcliente" class="mt-2" />
                        </div>
                        <div class="w-2/6 form-item">
                            <x-jet-label for="observaciones">{{ __('Observaciones') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.observaciones" type="text"  id="observaciones" name="observaciones" :value="old('observaciones') " class="w-full" disabled="{{ $bloqueado }}"/>
                            <x-jet-input-error for="observaciones" class="mt-2" />
                        </div>
                    </div>
                </div>
                <div class="flex-initial w-4/12 py-2 mr-1 bg-white rounded-lg shadow-md">
                    <div class="px-2 mx-2 my-1 bg-red-100 rounded-md">
                        <h3 class="font-semibold ">Datos Control</h3>
                    </div>
                    <div class="flex flex-col mx-4 space-y-2 md:space-y-0 md:flex-row md:space-x-2 ml-14">
                        <div class="flex-auto pb-3 form-item">
                            <label for="enviar" title="Enviar email"><x-icon.arroba/></label>
                            <input type="checkbox" wire:model.defer="factura.enviar" checked class="mx-auto"/>
                        </div>
                        <div class="flex-auto pb-3 form-item">
                            <label for="enviada" title="Email enviado"><x-icon.plane/></label>
                            <input type="checkbox" wire:model.defer="factura.enviada" checked class="mx-auto"/>
                        </div>
                        <div class="flex-auto pb-3 form-item">
                            <label for="facturada"  title="Facturada"><x-icon.invoice/></label>
                            <input type="checkbox" wire:model="factura.facturada" checked class="mx-auto" title="Facturada"/>
                        </div>
                        <div class="flex-auto pb-3 form-item">
                            <label for="pagada" title="Pagada"><x-icon.money/></label>
                            <input type="checkbox" wire:model.defer="factura.pagada" checked class="mx-auto"/>
                        </div>
                        <div class="flex-auto pb-3 form-item">
                            <label for="contabilizada" title="Contabilizada"><x-icon.sage/></label>
                            <input type="checkbox" {{ $factura->contabilizada[1]=="No" ? '' : 'checked' }} class="mx-auto"/>
                        </div>
                        <div class="flex-auto pb-3 form-item">
                            <label for="facturable" title="Facturable"><x-icon.euro/></label>
                            <input type="checkbox" wire:model.defer="factura.facturable" checked class="mx-auto"/>
                        </div>
                    </div>
                    <div class="flex flex-col mx-2 space-y-2 md:space-y-0 md:flex-row md:space-x-1">
                        <div class="w-1/6 form-item">
                            <x-jet-label for="asiento">{{ __('Asiento') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.asiento" type="number"  id="asiento" name="asiento" :value="old('asiento') " class="w-full"/>
                            <x-jet-input-error for="asiento" class="mt-2" />
                        </div>
                        <div class="w-5/6 form-item">
                            <x-jet-label for="Notas">{{ __('Notas') }}</x-jet-label>
                            <x-jet-input  wire:model.defer="factura.notas" type="text"  id="notas" name="notas" :value="old('notas') " class="w-full"/>
                            <x-jet-input-error for="notas" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex mt-2 ml-4 space-x-4">
                @if($factura->facturada==False)
                    <div class="space-x-3">
                        <x-jet-button class="bg-blue-600">{{ __('Guardar') }}</x-jet-button>
                    </div>
                @endif
                <div class="space-x-3">
                    <x-jet-secondary-button  onclick="location.href = '{{route('facturacion.index')}}'">{{ __('Volver') }}</x-jet-secondary-button>
                </div>
            </div>
        </form>
    </div>

    <hr class="my-2">

    @livewire('facturacion.factura-detalle',['facturacion'=>$factura,'showcrear'=>$factura->facturada],key($factura->id))

</div>

