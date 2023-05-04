<!doctype html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Factura {{$factura->serie}}/{{ $factura->numfactura }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Styles -->
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">

        {{-- sobreescribo margenes de pdf.css --}}
        <style>

            header {
            position: fixed;
            top: -5px;
            left: 0px;
            right: 0px;
            height: 60px;

            /** Extra personal styles **/
            /* background-color: #03a9f4; */
            color: white;
            text-align: center;
            /* line-height: 35px; */
            }

            footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            /* height: 25px; */

            /** Extra personal styles **/
            /* background-color: #03a9f4; */
            color: white;
            text-align: center;
            /* line-height: 35px; */
            }
            @page {margin: 10px 20px 10px 0px;}
        </style>
    </head>
    <body class="text-sm">
        <header>
            {{-- cabecera del formulario --}}
            <div class="flex justify-center mt-10">
                <img src="{{asset('img/LOGOSUMA_2.jpg')}}" width="200"/>
            </div>
        </header>

        <footer>
            <div class="flex justify-center">
                <img src="{{asset('img/PieSuma.png')}}" width="500"/>
            </div>
        </footer>

        {{-- datos generales del presupuesto --}}
        <div class="mt-40">
            <div class="flex justify-end mx-20 ">
                <div class="w-7/12 text-left"></div>
                <div class="w-5/12 text-left ">{{ $factura->entidad->entidad  }}</div>
            </div>
            <div class="flex justify-end mx-20 ">
                <div class="w-7/12 text-left"></div>
                <div class="w-5/12 text-left ">{{ $factura->entidad->direccion  }}</div>
            </div>
            <div class="flex justify-end mx-20 ">
                <div class="w-7/12 text-left"></div>
                <div class="w-5/12 text-left ">{{ $factura->entidad->codpostal }} {{ $factura->entidad->localidad }}</div>
            </div>
            <br>
            <div class="flex justify-end mx-20 ">
                <div class="w-7/12 text-left"></div>
                <div class="w-5/12 text-left ">CIF/NIF:{{ $factura->entidad->nif  }}</div>
            </div>
        </div>

        <div class="flex">
            <div class="flex-initial pl-5">
                <img src="{{asset('img/Reg mercantilSUMAVER2.jpg')}}" width="85"/>
            </div>
            <div  class="">
                {{-- Fecha y Factura --}}
                <div class="grid justify-end grid-cols-1 grid-rows-2 mt-10 ml-20">
                    <div>FECHA FACTURA: {{ $factura->fechafactura->format('d-m-Y') }}</div>
                    <div>FACTURA: {{ $factura->serie }}/{{ $factura->numfactura }}</div>
                </div>
                @if(count($factura->facturadetalles)>0)
                    {{-- Detalles  --}}
                    <div class="mt-10 ml-10">
                        @foreach($factura->facturadetalles as $detalle)
                            <div class="flex justify-end mx-20">
                                <div class="w-8/12 text-left">
                                    @if ($detalle->tipo=='1')
                                    Suplido:
                                    @endif
                                    {{$detalle->concepto}}
                                </div>
                                <div class="w-4/12 text-right">{{number_format($detalle->base,2,',','.')}} €</div>
                            </div>
                        @endforeach
                    </div>

                    {{-- totales --}}
                    <div class="mt-10 ml-10 font-bold">
                        <div class="flex justify-end mx-20 ">
                            <div class="w-8/12 text-left">Base imponible:</div>
                            <div class="w-4/12 text-right ">{{number_format($base,2,',','.')}} €</div>
                        </div>
                        @if($suplidos)
                            <div class="flex justify-end mx-20 ">
                                <div class="w-8/12 text-left">Suplidos:</div>
                                <div class="w-4/12 text-right ">{{number_format($suplidos,2,',','.')}} €</div>
                            </div>
                        @endif
                        <div class="flex justify-end mx-20 ">
                            <div class="w-8/12 text-left">IVA 21%:</div>
                            <div class="w-4/12 text-right ">{{number_format($totaliva,2,',','.')}} €</div>
                        </div>
                        <div class="flex justify-end mx-20 mt-5">
                            <div class="w-8/12 text-left">total:</div>
                            <div class="w-4/12 text-right ">{{number_format($total,2,',','.')}} €</div>
                        </div>
                    </div>

                    {{-- Condiciones pago --}}
                    <div class="mt-10">
                        <div class="flex ml-20 ">
                            <div class="text-left">Condiciones de pago: {{ $factura->metodopago->metodopago }}</div>
                        </div>
                        <div class="flex ml-20 ">
                            <div class="text-left">Vencimiento: {{ $factura->fechavencimiento->format('d-m-Y') }}</div>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </body>

</html>

