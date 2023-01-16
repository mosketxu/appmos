<x-app-layout>
    <div class="p-2">
        <div class="max-w-full mx-auto">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                @if($entidad)
                    @livewire('facturacion.prefactura',['entidad'=>$entidad],key($entidad->id))
                @else
                    @livewire('facturacion.prefactura')
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
