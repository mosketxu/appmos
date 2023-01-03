<x-app-layout>
    <div class="p-2">
        <div class="max-w-full mx-auto">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                @livewire('facturacion.prefactura',['facturacion'=>$facturacion,'ruta'=>$ruta],key($facturacion->id))
                {{-- @livewire('prefactura',['facturacion'=>$facturacion],key($facturacion->id)) --}}
            </div>
        </div>
    </div>
</x-app-layout>
