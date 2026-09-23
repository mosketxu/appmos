<div class="">
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.bancos'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.bancos'])

    <div class="p-4 space-y-6">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Bancos — conciliación para SAGE del cliente:</span>
        <select wire:model.live="cliente" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @forelse ($clientes as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @empty
                <option value="">(no hay clientes)</option>
            @endforelse
        </select>
    </h1>

    <p class="text-sm text-gray-500">
        Convierte los extractos bancarios de
        <code class="px-1 bg-gray-100 rounded">Contabilidad\Bancos\{{ $cliente ?: '<Cliente>' }}\Input</code>
        en <code class="px-1 bg-gray-100 rounded">Output\bancos.xlsx</code> listo para importar en SAGE,
        con partida y contrapartida asignadas según el <code class="px-1 bg-gray-100 rounded">Plan.xlsx</code> del cliente.
    </p>

    @if ($cliente !== '')
        <div class="grid gap-4 md:grid-cols-2">
            <div class="p-4 bg-white border rounded-lg shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">Extractos pendientes en Input</h2>
                @forelse ($pendientes as $f)
                    <div class="text-sm text-gray-800">{{ $f }}</div>
                @empty
                    <div class="text-sm text-gray-400">(ninguno)</div>
                @endforelse
            </div>
            <div class="p-4 bg-white border rounded-lg shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">Generados en Output</h2>
                @forelse ($generados as $f)
                    <div class="text-sm text-gray-800">{{ $f }}</div>
                @empty
                    <div class="text-sm text-gray-400">(ninguno)</div>
                @endforelse
            </div>
        </div>
    @endif

    </div>
</div>
