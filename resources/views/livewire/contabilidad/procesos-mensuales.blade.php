<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos-mensuales'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.procesos-mensuales'])

    <div class="p-4 space-y-4">
        <h1 class="text-2xl font-semibold text-gray-900">Procesos mensuales</h1>

        @if (! $procesos)
            <div class="p-6 text-sm text-center text-gray-500 bg-white border border-gray-200 border-dashed rounded-lg">
                Aquí se agruparán los procesos que se hacen cada mes. Todavía no hay ninguno.
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($procesos as $clave => $p)
                    <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $p['icono'] ?? '' }} {{ $p['titulo'] }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $p['descripcion'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
