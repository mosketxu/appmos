<x-app-layout>
    <div class="p-2">
        <div class="max-w-full mx-auto">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                @livewire('menu',['entidad'=>$entidad,'ruta'=>'entidad.impuestos'],key('menu-'.$entidad->id))
                <div class="p-1 mx-2">
                    <h1 class="text-2xl font-semibold text-gray-900">Impuestos de {{ $entidad->entidad }} <span class="text-lg text-gray-500 "> ({{ $entidad->nif }})</span></h1>
                </div>
                @livewire('entidad-impuestos',['entidadId'=>$entidad->id],key('impuestos-'.$entidad->id))
            </div>
        </div>
    </div>
</x-app-layout>
