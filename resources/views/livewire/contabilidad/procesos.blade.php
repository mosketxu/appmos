<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    {{-- Avisos de fin de proceso: pedido explícito 2026-09-07 -- NO bloqueantes
         (nada de alert(), se puede seguir usando la pantalla mientras están
         puestos) y superpuestos (varios a la vez, apilados), cada uno con su
         propia (x) para cerrarlo a mano -- no desaparecen solos. --}}
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

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos'])

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Procesos de Fashion IQ del mes:</span>
        <select wire:model="mes" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
    </h1>

    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="flex flex-wrap items-center p-4 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <x-button.primary
                wire:click="ejecutarMarcados"
                wire:loading.attr="disabled"
                wire:target="ejecutarMarcados"
                onclick="return confirm('¿Ejecutar los procesos marcados (escriben sobre los ficheros reales), en orden, para el mes seleccionado?')"
            >
                <span wire:loading.remove wire:target="ejecutarMarcados">▶ Ejecutar marcados</span>
                <span wire:loading wire:target="ejecutarMarcados">⏳ Ejecutando…</span>
            </x-button.primary>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2"></th>
                    <th class="px-4 py-2 text-xs font-medium text-left text-gray-500 uppercase">Proceso</th>
                    <th class="px-4 py-2 text-xs font-medium text-left text-gray-500 uppercase">Ejecutar / resultado</th>
                    <th class="px-4 py-2 text-xs font-medium text-left text-gray-500 uppercase">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($this->procesos as $id => $p)
                    <tr wire:key="proceso-{{ $id }}">
                        <td class="px-4 py-2 align-top">
                            <input type="checkbox" wire:model="marcados" value="{{ $id }}" class="border-gray-300 rounded">
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900 align-top">
                            {{ $p['label'] }}
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div class="flex items-start gap-x-3">
                                <x-button.secondary
                                    wire:click="ejecutar('{{ $id }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="ejecutar('{{ $id }}')"
                                    onclick="return confirm('Esto escribe sobre los ficheros reales. ¿Seguro?')"
                                >
                                    <span wire:loading.remove wire:target="ejecutar('{{ $id }}')">Ejecutar</span>
                                    <span wire:loading wire:target="ejecutar('{{ $id }}')">⏳ Ejecutando…</span>
                                </x-button.secondary>
                                @if (! empty($resultados[$id]))
                                    <div class="flex flex-col min-w-0 gap-y-1 pt-1.5">
                                        @foreach ($resultados[$id] as $r)
                                            <x-contabilidad.resultado-fichero :r="$r" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500 align-top">{{ $p['ayuda'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="p-4 bg-white border rounded-lg shadow">
        <div class="flex flex-wrap gap-8">
            {{-- IZQUIERDA 35%: Cálculos + Turnover (usa el "Mes" del título) --}}
            <div style="flex:0 0 35%;min-width:280px">
                <div class="flex flex-wrap items-baseline mb-3 gap-x-3">
                    <h2 class="text-lg font-semibold text-gray-900">RentasVariables</h2>
                    <p class="flex-1 text-xs text-gray-500" style="min-width:200px">Rellena CalculosRentasVbles2026.xlsx del mes y el fichero del turnover de BCN y MAL.</p>
                </div>
                <div class="mt-1 space-y-1">
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="BCN" class="mr-2 border-gray-300 rounded"> Barcelona
                    </label>
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="MAL" class="mr-2 border-gray-300 rounded"> Málaga
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> La Roca <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> Las Rozas <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                </div>

                <div class="mt-3">
                    <x-button.secondary
                        wire:click="ejecutarRvCalculosYDeclaracion"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvCalculosYDeclaracion"
                        onclick="return confirm('Cálculos (4 tiendas) + Turnover de las tiendas marcadas, para el mes seleccionado. Escribe sobre los ficheros reales. ¿Seguro?')"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvCalculosYDeclaracion">Cálculos + Turnover</span>
                        <span wire:loading wire:target="ejecutarRvCalculosYDeclaracion">⏳ Ejecutando…</span>
                    </x-button.secondary>
                </div>

                @if (! empty($resultados['rv']))
                    <div class="flex flex-col mt-2 gap-y-1">
                        @foreach ($resultados['rv'] as $r)
                            <x-contabilidad.resultado-fichero :r="$r" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DERECHA: Envío del correo -- ocupa el resto que quede libre --}}
            <div style="flex:1 1 340px;min-width:340px">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h3 class="text-sm font-semibold text-gray-700">Envío del correo</h3>
                    <p class="flex-1 text-xs text-gray-500" style="min-width:220px">El botón <strong>Enviar</strong> de cada tienda manda YA a sus destinatarios reales. <strong>Enviar a correo de prueba</strong> manda los dos ficheros a la dirección de prueba. Destinatarios editables.</p>
                </div>

                {{-- tarjetas de producción --}}
                <div class="flex flex-col mt-2 gap-y-1">
                    @foreach ($this->rvTiendasArrendador as $k => $label)
                        {{-- To, input, CC, input y el botón en la MISMA fila;
                             "Corrección" debajo (w-full fuerza el salto de línea). --}}
                        <div wire:key="rv-envio-{{ $k }}" class="flex flex-wrap items-center p-2 border border-gray-200 rounded gap-x-2 gap-y-1">
                            <div class="mr-4 text-sm font-semibold text-gray-800 shrink-0 w-14">{{ $label }}</div>
                            <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.to" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.cc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <x-button.secondary
                                class="shrink-0"
                                style="padding-top:.25rem;padding-bottom:.25rem"
                                wire:click="ejecutarRvEnvio('{{ $k }}')"
                                wire:loading.attr="disabled"
                                wire:target="ejecutarRvEnvio('{{ $k }}')"
                                onclick="return confirm('¿Mandar el correo de {{ $label }} a sus destinatarios REALES?')"
                            >
                                <span wire:loading.remove wire:target="ejecutarRvEnvio('{{ $k }}')">Enviar</span>
                                <span wire:loading wire:target="ejecutarRvEnvio('{{ $k }}')">⏳ Enviando…</span>
                            </x-button.secondary>
                            <label class="flex items-center w-full text-xs text-gray-700">
                                <input type="checkbox" wire:model="rvEnvio.{{ $k }}.correccion" class="mr-1 border-gray-300 rounded"> Corrección
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- correo de prueba: debajo de las dos tiendas, separado, sin fondo gris --}}
                <div class="flex flex-wrap items-center pt-3 mt-3 border-t border-gray-200 gap-x-2 gap-y-2">
                    <label class="text-xs font-medium text-gray-600 shrink-0">Correo de prueba</label>
                    <input type="email" wire:model="rvEmailPrueba" placeholder="tucorreo@ejemplo.com" class="flex-1 text-sm border-gray-300 rounded-md shadow-sm" style="min-width:220px">
                    <x-button.secondary
                        wire:click="ejecutarRvEnvioPrueba"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvEnvioPrueba"
                        onclick="return confirm('¿Mandar los ficheros de Barcelona y Málaga al correo de prueba?')"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvEnvioPrueba">Enviar a correo de prueba</span>
                        <span wire:loading wire:target="ejecutarRvEnvioPrueba">⏳ Enviando…</span>
                    </x-button.secondary>
                </div>
            </div>
        </div>
    </div>

    </div>{{-- /columna izquierda --}}

    {{-- Salida: a la derecha del todo, arranca a la altura del título; color
         distinto según haya datos o no; con "Borrar salida" justo encima. --}}
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
