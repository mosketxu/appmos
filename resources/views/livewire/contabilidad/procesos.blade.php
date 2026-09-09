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

    <div class="p-4 space-y-6">

    <h1 class="text-2xl font-semibold text-gray-900">Procesos de Contabilidad (Fashion IQ)</h1>

    <div class="flex items-end gap-4 p-4 bg-white border rounded-lg shadow">
        <div>
            <label class="block text-sm font-medium text-gray-700">Mes</label>
            <select wire:model="mes" class="mt-1 border-gray-300 rounded-md shadow-sm">
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
        </div>
        <x-button.primary
            wire:click="ejecutarMarcados"
            wire:loading.attr="disabled"
            wire:target="ejecutarMarcados"
            onclick="return confirm('¿Ejecutar los procesos marcados (escriben sobre los ficheros reales), en orden, para el mes seleccionado?')"
        >
            <span wire:loading.remove wire:target="ejecutarMarcados">▶ Ejecutar marcados</span>
            <span wire:loading wire:target="ejecutarMarcados">⏳ Ejecutando…</span>
        </x-button.primary>
        <x-button.secondary wire:click="limpiarSalida">Limpiar salida</x-button.secondary>
    </div>

    <div class="overflow-hidden bg-white border rounded-lg shadow">
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
                                            <a href="{{ $r['url'] }}"
                                               class="text-xs text-blue-700 underline break-all font-mono"
                                               title="Enlace al fichero. Si el navegador no lo abre, clic derecho → Copiar dirección del enlace y pégala en el explorador de Windows.">📄 {{ $r['ruta'] }}</a>
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
        <h2 class="mb-3 text-lg font-semibold text-gray-900">RentasVariables</h2>

        <div class="flex flex-wrap gap-8">
            {{-- IZQUIERDA: Cálculos + Declaración --}}
            <div style="flex:0 0 40%;min-width:300px">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Cálculos + Declaración a arrendador</h3>
                <div class="flex flex-wrap items-start gap-4">
                    <div>
                        <span class="block text-xs font-medium text-gray-600">Tiendas con arrendador (Declaración)</span>
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
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Mes</label>
                        <select wire:model="rvMes" class="mt-1 border-gray-300 rounded-md shadow-sm">
                            @foreach (range(1, 12) as $m)<option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <x-button.secondary
                        wire:click="ejecutarRvCalculosYDeclaracion"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvCalculosYDeclaracion"
                        onclick="return confirm('Cálculos (4 tiendas) + Declaración a arrendador de las marcadas, para el mes seleccionado. Escribe sobre los ficheros reales. ¿Seguro?')"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvCalculosYDeclaracion">Cálculos + Declaración a arrendador</span>
                        <span wire:loading wire:target="ejecutarRvCalculosYDeclaracion">⏳ Ejecutando…</span>
                    </x-button.secondary>
                </div>
                <p class="mt-1 text-xs text-gray-500">Rellena <strong>CalculosRentasVbles2026.xlsx</strong> (siempre las 4 tiendas) del mes, y luego el fichero del arrendador de las marcadas. Siempre real.</p>

                @if (! empty($resultados['rv']))
                    <div class="flex flex-col mt-2 gap-y-1">
                        @foreach ($resultados['rv'] as $r)
                            <a href="{{ $r['url'] }}"
                               class="text-xs text-blue-700 underline break-all font-mono"
                               title="Enlace al fichero. Si el navegador no lo abre, clic derecho → Copiar dirección del enlace y pégala en el explorador.">📄 {{ $r['ruta'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DERECHA: Envío del correo --}}
            <div style="flex:0 0 40%;min-width:320px">
                <h3 class="mb-1 text-sm font-semibold text-gray-700">Envío del correo</h3>
                <p class="mb-2 text-xs text-gray-500">El botón <strong>Enviar</strong> de cada tienda manda YA a sus destinatarios reales (único aviso: el confirm). <strong>Enviar a correo de prueba</strong> manda los dos ficheros a la dirección de prueba. Los destinatarios son editables.</p>

                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Correo de prueba</label>
                        <input type="email" wire:model="rvEmailPrueba" placeholder="tucorreo@ejemplo.com" class="mt-1 text-sm border-gray-300 rounded-md shadow-sm">
                    </div>
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

                <div class="flex flex-col mt-3 gap-y-1">
                    @foreach ($this->rvTiendasArrendador as $k => $label)
                        <div wire:key="rv-envio-{{ $k }}" class="flex flex-wrap items-end p-2 border border-gray-200 rounded gap-x-2 gap-y-1 bg-gray-50">
                            <div class="pb-1 text-sm font-semibold text-gray-800 w-14">{{ $label }}</div>
                            <div style="flex:3 1 0;min-width:130px">
                                <label class="block text-xs font-medium text-gray-600">Para</label>
                                <input type="text" wire:model="rvEnvio.{{ $k }}.to" class="w-full text-sm border-gray-300 rounded shadow-sm">
                            </div>
                            <div style="flex:4 1 0;min-width:170px">
                                <label class="block text-xs font-medium text-gray-600">CC</label>
                                <input type="text" wire:model="rvEnvio.{{ $k }}.cc" class="w-full text-sm border-gray-300 rounded shadow-sm">
                            </div>
                            <label class="flex items-center pb-1 text-xs text-gray-700 whitespace-nowrap">
                                <input type="checkbox" wire:model="rvEnvio.{{ $k }}.correccion" class="mr-1 border-gray-300 rounded"> corr.
                            </label>
                            <x-button.secondary
                                wire:click="ejecutarRvEnvio('{{ $k }}')"
                                wire:loading.attr="disabled"
                                wire:target="ejecutarRvEnvio('{{ $k }}')"
                                onclick="return confirm('¿Mandar el correo de {{ $label }} a sus destinatarios REALES?')"
                            >
                                <span wire:loading.remove wire:target="ejecutarRvEnvio('{{ $k }}')">Enviar {{ $label }}</span>
                                <span wire:loading wire:target="ejecutarRvEnvio('{{ $k }}')">⏳ Enviando…</span>
                            </x-button.secondary>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 bg-gray-900 rounded-lg shadow">
        <h2 class="mb-2 text-sm font-semibold text-gray-300">Salida</h2>
        <pre class="overflow-x-auto text-xs text-green-400 whitespace-pre-wrap max-h-96" wire:loading.class="opacity-50">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
        <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
    </div>

    </div>
</div>
