<x-app-layout>
    <div class="p-2">
        <div class="max-w-full mx-auto">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                @livewire('facturacion.factura',['facturacion'=>$facturacion,'pre'=>'no'],key($facturacion->id))
                {{-- @livewire('factura',['facturacion'=>$facturacion,'pre'=>'no'],key($facturacion->id)) --}}
            </div>
        </div>
    </div>
</x-app-layout>
