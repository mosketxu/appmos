{{--
    Botón de subir con el mismo aspecto que x-subir-fichero (17-oct-2026), para las pantallas que ya tienen su propia subida
    (input oculto + $refs.input.click(), o <label> con el input dentro). Verde (vacio) = falta el fichero; neutro = ya está u opcional.
    Uso: <x-boton-subir :vacio="! $hay" cargando="subiendo" x-on:click="$refs.input.click()">Subir</x-boton-subir>
         <x-boton-subir como="label" target="subidas">Subir ficheros<input type="file" … class="hidden"></x-boton-subir>
--}}
@props(['vacio' => false, 'como' => 'button', 'cargando' => null, 'target' => null])
<{{ $como }} @if ($como === 'button') type="button" @endif {{ $attributes->class(['subir-btn', 'vacio' => $vacio]) }}>
    <x-subir.icono :cargando="$cargando" :target="$target" />
    {{ $slot }}
</{{ $como }}>
