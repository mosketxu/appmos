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
        <code class="px-1 bg-gray-100 rounded">Amortizacion Alpify 2026.xlsm</code>. Escribe siempre sobre los
        ficheros reales de
        <code class="px-1 bg-gray-100 rounded">OneDrive\_Clientes\2026\Durcal 2026\Laboral</code>, desde el PC de trabajo —
        antes de ejecutar se ve lo que el PC encuentra (nómina del mes, Amortizacion cerrado).
        El Amortizacion tiene que estar <b>cerrado</b>: se escribe con el propio Excel para no perder la escala de
        tiempo, las tablas dinámicas ni la macro.
    </p>

    @include('livewire.contabilidad._pcs')

    @php
        $n = $this->nomina;
        $am = $estadoPc['amort'] ?? null;
        $sabeEstado = ! empty($estadoPc);
    @endphp
    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 space-y-1 text-sm border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap items-center gap-x-3">
                <span class="font-medium text-gray-700">Lo que ve el PC{{ ! empty($estadoPc['pc']) ? ' ('.$estadoPc['pc'].')' : '' }} en el OneDrive:</span>
                <button type="button" wire:click="sincronizarAhora" class="text-xs text-indigo-700 hover:underline">↻ Comprobar en el PC</button>
            </div>
            @if (! $sabeEstado)
                <p class="text-amber-700">Todavía no hay datos del PC (se están pidiendo). Pulsa «Comprobar en el PC» si tarda.</p>
            @else
                <p>
                    Nómina del mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}:
                    @if ($n)
                        <span class="text-green-700">✔ {{ $n['nombre'] }}</span>
                        <span class="text-gray-500">({{ number_format($n['bytes'] / 1024, 0, ',', '.') }} KB · guardada {{ $n['fecha'] }})</span>
                    @else
                        <span class="text-red-600">✘ no la encuentra en OneDrive (Durcal 2026\Laboral)</span>
                    @endif
                </p>
                <p>
                    Amortizacion Alpify 2026.xlsm:
                    @if ($am)
                        <span class="{{ ! empty($am['abierto']) ? 'text-red-600' : 'text-green-700' }}">{{ ! empty($am['abierto']) ? '⚠ está ABIERTO en Excel: ciérralo antes de ejecutar' : '✔ cerrado' }}</span>
                        <span class="text-gray-500">({{ number_format($am['bytes'] / 1024, 0, ',', '.') }} KB · {{ $am['fecha'] }})</span>
                    @else
                        <span class="text-red-600">✘ no lo encuentra</span>
                    @endif
                </p>
                <p class="text-xs text-gray-500">
                    personal.xlsx: {{ ! empty($estadoPc['personal']['fecha']) ? 'modificado '.$estadoPc['personal']['fecha'] : 'no encontrado' }}.
                    Las nóminas son datos personales: <b>no se suben a Appmos</b>; el PC trabaja con ellas en su OneDrive y aquí solo se ve esto.
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center p-4 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <x-button.primary
                wire:click="ejecutar"
                wire:loading.attr="disabled"
                wire:target="ejecutar"
                :disabled="! $n || ! empty($am['abierto'])"
                onclick="return confirm('Esto escribe sobre el fichero de nómina del mes y sobre Amortizacion Alpify 2026.xlsm reales, en el OneDrive del PC. ¿Seguro?')"
            >
                <span wire:loading.remove wire:target="ejecutar">▶ Ejecutar en el PC</span>
                <span wire:loading wire:target="ejecutar">⏳ Pidiendo…</span>
            </x-button.primary>

            @if (! empty($resultados['durcal']))
                <div class="flex flex-col min-w-0 gap-y-1 text-sm">
                    <span class="text-xs text-gray-500">Ficheros escritos (en el PC):</span>
                    @foreach ($resultados['durcal'] as $r)
                        <span class="font-mono text-xs break-all">📄 {{ $r['ruta'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>


    @include('livewire.contabilidad._salida')
    </div>
</div>
