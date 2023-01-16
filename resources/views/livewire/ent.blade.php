<div class="">
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">
        @if($contactoId)
        <h1 class="text-2xl font-semibold text-gray-900"> Nueva contacto para Entidad {{ $contacto->entidad }}</h1>
        <input type="hidden" wire:model="contactoId"/>
        @else
            @if($entidad)
                <h1 class="text-2xl font-semibold text-gray-900">Entidad: {{ $entidad->entidad }}
                    @if($entidad->nif) <span class="text-lg text-gray-500 "> ({{  $entidad->nif }})</span> @endif
                </h1>
            @else
            <h1 class="text-2xl font-semibold text-gray-900">Nueva Entidad</h1>
            @endif
        @endif
    </div>
    <div class="py-1 space-y-4">
        @include('errores')
    </div>
    <x-jet-validation-errors/>

    <div class="flex-col space-y-4 text-gray-500">
        <form wire:submit.prevent="save" class="">
            <div class="px-2 mx-2 my-1 rounded-md bg-blue-50">
                <h3 class="font-semibold ">Datos generales</h3>
                <x-jet-input  wire:model.defer="entidad.id" type="hidden"/>
                <x-jet-input  wire:model.defer="contacto" type="hidden"/>
                <hr>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="entidad">{{ __('Entidad') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.entidad" type="text" class="w-full " id="entidad" name="entidad" :value="old('entidad') "/>
                    <x-jet-input-error for="entidad" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="nif">{{ __('Nif') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.nif" type="text" id="nif" name="nif" :value="old('nif')" class="w-full"/>
                    <x-jet-input-error for="nif" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="emailgral">{{ __('Email Gral') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.emailgral" type="text" id="emailgral" name="emailgral" :value="old('emailgral')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="emailadm">{{ __('Email Adm') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.emailadm" type="text" id="emailadm" name="emailadm" :value="old('emailadm')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="web">{{ __('Web') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.web" type="text" id="web" name="web" :value="old('web')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="tfno">{{ __('Tfno.') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.tfno" type="text" id="tfno" name="tfno" :value="old('tfno')" class="w-full"/>
                </div>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="direccion">{{ __('Dirección') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.direccion" type="text" id="direccion" name="direccion" :value="old('direccion')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="codpostal">{{ __('C.P.') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.codpostal" type="text" id="codpostal" name="codpostal" :value="old('codpostal')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="localidad">{{ __('Localidad') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.localidad" type="text" id="localidad" name="localidad" :value="old('localidad')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="provincia">{{ __('Provincia') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.provincia_id" selectname="provincia_id" class="w-full">
                        <option value="">-- choose --</option>
                        @foreach ($provincias as $provincia)
                        <option value="{{ $provincia->id }}">{{ $provincia->provincia }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="pais">{{ __('Pais') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.pais_id" selectname="pais_id" class="w-full">
                        <option value="">-- choose --</option>
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->pais }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label class="inline-flex items-center mt-3">
                        <x-input.checkbox wire:model.defer="entidad.favorito" class="w-4 h-4 text-yellow-500 form-checkbox"/><span class="ml-2 text-gray-700">{{ __('Favorito') }}</span>
                    </x-jet-label>
                    <x-jet-label class="inline-flex items-center mt-3">
                        <x-input.checkbox wire:model.defer="entidad.estado" class="w-4 h-4 text-blue-500 form-checkbox"/><span class="ml-2 text-gray-700">{{ __('Activo') }}</span>
                    </x-jet-label>
                    <x-jet-label class="inline-flex items-center mt-3">
                        <x-input.checkbox wire:model.defer="entidad.facturar" class="w-4 h-4 text-pink-500 form-checkbox"/><span class="ml-2 text-gray-700">{{ __('Facturar') }}</span>
                    </x-jet-label>
                    <x-jet-label class="inline-flex items-center mt-3">
                        <x-input.checkbox wire:model.defer="entidad.cliente" class="w-4 h-4 text-green-500 form-checkbox"/><span class="ml-2 text-gray-700">{{ __('Cliente') }}</span>
                    </x-jet-label>
                    <x-jet-label class="inline-flex items-center mt-3">
                        <x-input.checkbox wire:model.defer="entidad.enviar" class="w-4 h-4 text-red-400 -500 form-checkbox"/><span class="ml-2 text-gray-700">{{ __('Enviar') }}</span>
                    </x-jet-label>
                </div>
            </div>
            <hr class="my-2">
            <div class="px-2 mx-2 my-1 rounded-md bg-blue-50">
                <h3 class="font-semibold ">Datos Bancarios</h3>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="banco1" >{{ __('Banco 1') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.banco1" type="text" id="banco1" name="banco1" :value="old('banco1')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="iban1" >{{ __('Iban 1') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.iban1" type="text" id="iban1" name="iban1" :value="old('iban1')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="banco2" >{{ __('Banco 2') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.banco2" type="text" id="banco2" name="banco2" :value="old('banco2')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="iban2" >{{ __('Iban 2') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.iban2" type="text" id="iban2" name="iban2" :value="old('iban2')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="banco3" >{{ __('Banco 3') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.banco3" type="text" id="banco3" name="banco3" :value="old('banco3')" class="w-full"/>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="iban3" >{{ __('Iban 3') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.iban3" type="text" id="iban3" name="iban3" :value="old('iban3')" class="w-full"/>
                </div>
            </div>
            <hr class="my-2">
            <div class="px-2 mx-2 mt-2 mb-1 rounded-md bg-blue-50">
                <h3 class="font-semibold ">Datos Facturación</h3>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="tipoiva">{{ __('Iva') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.tipoiva" class="w-full" selectname="tipoiva">
                        <option value="">-- choose --</option>
                        <option value="0.00">0%</option>
                        {{-- <option value="0.04">4%</option>
                        <option value="0.10">10%</option> --}}
                        <option value="0.21">21%</option>
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="cicloimpuesto_id" >{{ __('Ciclo Impuesto') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.cicloimpuesto_id" class="w-full" selectname="cicloimpuesto_id">
                        <option value="">-- choose --</option>
                        @foreach (App\Models\Entidad::CICLOS as $value=>$label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="ciclofacturacion_id" >{{ __('Ciclo Fact.') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.ciclofacturacion_id" class="w-full" selectname="ciclofacturacion_id">
                        <option value="">-- choose --</option>
                        @foreach (App\Models\Entidad::CICLOS as $value=>$label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="metodopago_id">{{ __('Método Pago') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.metodopago_id" class="w-full" selectname="metodopago_id">
                        <option value="">-- choose --</option>
                        @foreach ($metodopagos as $metodopago)
                        <option value="{{ $metodopago->id }}">{{ $metodopago->metodopagocorto }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="suma_id">{{ __('Rpble.Suma') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.suma_id" class="w-full" selectname="suma_id">
                        <option value="">-- choose --</option>
                        @foreach ($sumas as $suma)
                        <option value="{{ $suma->id }}">{{ $suma->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="idioma">{{ __('Idioma') }}</x-jet-label>
                    <x-select wire:model.defer="entidad.idioma" class="w-full" selectname="idioma">
                        <option value="">-- choose --</option>
                        <option value="ES">Español</option>
                        <option value="CA">Catalán</option>
                        <option value="EN">Inglés</option>
                    </x-select>
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="porcentajemarta">{{ __('% Marta') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.porcentajemarta" type="number" step="any" id="porcentajemarta" name="porcentajemarta" :value="old('porcentajemarta')" class="w-full"/>
                    <x-jet-input-error for="porcentajemarta" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="porcentajesusana">{{ __('% Susana') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.porcentajesusana" type="number"  step="any" id="porcentajesusana" name="porcentajesusana" :value="old('porcentajesusana')" class="w-full"/>
                    <x-jet-input-error for="porcentajesusana" class="mt-2" />
                </div>
            </div>
            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="cuentacontable" >{{ __('Cta.Contable') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.cuentacontable" type="number" id="cuentacontable" name="cuentacontable" :value="old('cuentacontable')" class="w-full"/>
                    <x-jet-input-error for="cuentacontable" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="referenciacliente" >{{ __('Ref.Cli') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.referenciacliente" type="text" id="referenciacliente" name="referenciacliente" :value="old('referenciacliente')" class="w-full"/>
                    <x-jet-input-error for="referenciacliente" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="diafactura" >{{ __('Dia Factura') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.diafactura" type="number" id="diafactura" name="diafactura" :value="old('diafactura')" class="w-full"/>
                    <x-jet-input-error for="diafactura" class="mt-2" />
                </div>
                <div class="w-full form-item">
                    <x-jet-label for="diavencimiento" >{{ __('Dia Vencimiento') }}</x-jet-label>
                    <x-jet-input  wire:model.defer="entidad.diavencimiento" type="number" id="diavencimiento" name="diavencimiento" :value="old('diavencimiento')" class="w-full"/>
                    <x-jet-input-error for="diavencimiento" class="mt-2" />
                </div>
            </div>

            <div class="flex flex-col mx-2 space-y-4 md:space-y-0 md:flex-row md:space-x-4">
                <div class="w-full form-item">
                    <x-jet-label for="observaciones">{{ __('Observaciones') }}</x-jet-label>
                    <textarea wire:model.defer="entidad.observaciones" class="w-full text-xs border-gray-300 rounded-md" rows="3">{{ old('observaciones') }} </textarea>
                    <x-jet-input-error for="observaciones" class="mt-2" />
                </div>
                @if($contactoId)
                    <div class="flex-col items-center w-6/12 ">
                        <x-jet-label class="p-0 m-0 ">{{ __('Observaciones del contacto') }}</x-jet-label>
                        <div class="mx-auto">
                            <x-jet-input  wire:model.defer="departamento" type="text" id="departamento" name="departamento" :value="old('departamento')" class="w-full " placeholder="{{ __('Departamento') }}"/>
                        </div>
                        <div class="mx-auto">
                            <x-jet-input  wire:model.defer="comentario" type="text" id="comentario" name="comentario" :value="old('comentario')" class="w-full" placeholder="{{ __('Comentarios') }}"/>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex mt-2 mb-2 ml-2 space-x-4">
                <div class="space-x-3">
                    <x-jet-button class="bg-blue-600">
                        {{ __('Guardar') }}
                    </x-jet-button>
                    <span
                        x-data="{ open: false }"
                        x-init="
                            @this.on('notify-saved', () => {
                                if (open === false) setTimeout(() => { open = false }, 2500);
                                open = true;
                            })
                        "
                    x-show.transition.out.duration.1000ms="open"
                    style="display: none;"
                    class="p-2 m-2 text-gray-500 rounded-lg bg-green-50"
                    >Saved!</span>
                    {{-- @if(session()->has('notify-saved'))
                    <span
                        x-data="{ open: true }"
                        x-init="setTimeout(()=>{open=false},2500)"
                        x-show.transition.duration.1000ms="open"
                        class="text-gray-500"
                        >Saved!</span>
                    @endif --}}
                    <x-jet-secondary-button  onclick="location.href = '{{route('entidades')}}'">{{ __('Volver') }}</x-jet-secondary-button>
                </div>
            </div>
        </form>
    </div>
</div>
