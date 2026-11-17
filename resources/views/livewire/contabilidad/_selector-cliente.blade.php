{{-- Selector de cliente con búsqueda (Facturas OCR y Bancos). Cambia $cliente; las entidades sin carpeta llevan «e:<id>». Usa el componente común x-buscador-select (teclado: flechas + Intro). --}}
<span class="font-normal" style="display:inline-block">
    <x-buscador-select model="cliente" :valor="$cliente" :opciones="$clientes" ancho="20rem" clase="text-base" placeholder="— elige una entidad —" :max="80" vacio="Ninguna entidad coincide" />
</span>
