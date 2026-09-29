<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
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

    <div class="p-4 space-y-6">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Durcal — activación de sueldos del mes:</span>
        <select wire:model="mes" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
    </h1>

    <p class="text-sm text-gray-500">
        Activa sueldos + SS.EMPRESA de los empleados marcados "ACTIVAR" en
        <code class="px-1 bg-gray-100 rounded">Datos\personal.xlsx</code> repartidos por proyecto, rellena la
        tabla de resultado en el propio fichero de nómina, y da de alta las filas de amortización a 36 meses en
        <code class="px-1 bg-gray-100 rounded">Amortizacion Alpify 2026.xlsx</code>. Escribe siempre sobre los
        ficheros reales de
        <code class="px-1 bg-gray-100 rounded">OneDrive\_Clientes\2026\Durcal 2026\Laboral</code> —
        sube abajo el fichero de nómina del mes elegido solo para confirmar que es el correcto antes de ejecutar.
    </p>

    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <label class="block mb-2 text-xs font-medium text-gray-600">
                Fichero de nómina del mes (solo para confirmar, se sigue escribiendo sobre el real de OneDrive)
            </label>
            <x-contabilidad.soltar-fichero model="archivo" accept=".xls,.xlsx" :fichero="$archivo"
                texto="Arrastra aquí la nómina o haz clic para elegirla" />
            <div wire:loading wire:target="archivo" class="mt-1 text-xs text-gray-400">Subiendo…</div>
            @error('archivo')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @if ($archivo && $this->archivoCoincide === true)
                <p class="mt-1 text-xs text-green-700">✔ Coincide con el fichero esperado del mes.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center p-4 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <x-button.primary
                wire:click="ejecutar"
                wire:loading.attr="disabled"
                wire:target="ejecutar, archivo"
                :disabled="$this->archivoCoincide !== true"
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


    @include('livewire.contabilidad._salida')
    </div>
</div>
