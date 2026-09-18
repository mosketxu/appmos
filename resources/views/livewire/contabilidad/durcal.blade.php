<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button
                    type="button"
                    class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                    x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"
                >&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.durcal'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Durcal — activación de sueldos del mes:</span>
        <select wire:model="mes" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
    </h1>

    <p class="text-sm text-gray-500">
        Coge el fichero de nómina del mes directamente de
        <code class="px-1 bg-gray-100 rounded">OneDrive\_Clientes\2026\Durcal 2026\Laboral</code>,
        activa sueldos + SS.EMPRESA de los empleados marcados "ACTIVAR" en
        <code class="px-1 bg-gray-100 rounded">Datos\personal.xlsx</code> repartidos por proyecto, rellena la
        tabla de resultado en el propio fichero de nómina, y da de alta las filas de amortización a 36 meses en
        <code class="px-1 bg-gray-100 rounded">Amortizacion Alpify 2026.xlsx</code>. Escribe siempre sobre los
        ficheros reales.
    </p>

    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="flex flex-wrap items-center p-4 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <x-button.primary
                wire:click="ejecutar"
                wire:loading.attr="disabled"
                wire:target="ejecutar"
                onclick="return confirm('Esto escribe sobre el fichero de nómina del mes y sobre Amortizacion Alpify 2026.xlsx reales. ¿Seguro?')"
            >
                <span wire:loading.remove wire:target="ejecutar">▶ Ejecutar</span>
                <span wire:loading wire:target="ejecutar">⏳ Ejecutando…</span>
            </x-button.primary>

            @if (! empty($resultados))
                <div class="flex flex-col min-w-0 gap-y-1">
                    @foreach ($resultados as $r)
                        <x-contabilidad.resultado-fichero :r="$r" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    </div>{{-- /columna izquierda --}}

    <div class="w-full" style="flex:35 1 0;min-width:0">
        <div class="sticky top-4">
            <div class="flex justify-start mb-2">
                <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
            </div>
            <div class="p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}">
                <h2 class="mb-2 text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
                <pre class="overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" style="max-height:calc(100vh - 9rem)" wire:loading.class="opacity-50">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
                <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
            </div>
        </div>
    </div>

    </div>{{-- /flex 2 columnas --}}
    </div>
</div>
