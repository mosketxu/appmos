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
        <div class="flex items-center">
            <input type="checkbox" wire:model="modoReal" id="modoReal" class="border-gray-300 rounded">
            <label for="modoReal" class="ml-2 text-sm text-gray-700">Modo real (si no, genera copias de prueba)</label>
        </div>
        <x-button.primary
            wire:click="ejecutarMarcados"
            wire:loading.attr="disabled"
            wire:target="ejecutarMarcados"
            onclick="return confirm('¿Ejecutar los procesos marcados, en orden, para el mes seleccionado?')"
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
                    <th class="px-4 py-2 text-xs font-medium text-left text-gray-500 uppercase">Detalle</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($this->procesos as $id => $p)
                    <tr wire:key="proceso-{{ $id }}">
                        <td class="px-4 py-2">
                            <input type="checkbox" wire:model="marcados" value="{{ $id }}" class="border-gray-300 rounded">
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">
                            {{ $p['label'] }}
                            @if ($p['siempreReal'])
                                <span class="px-2 py-0.5 ml-2 text-xs font-semibold text-red-800 bg-red-100 rounded">SIEMPRE REAL</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ $p['ayuda'] }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($p['siempreReal'])
                                <x-button.secondary
                                    wire:click="ejecutar('{{ $id }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="ejecutar('{{ $id }}')"
                                    onclick="return confirm('Esto escribe SIEMPRE sobre el fichero real (con backup automático). ¿Seguro?')"
                                >
                                    <span wire:loading.remove wire:target="ejecutar('{{ $id }}')">Ejecutar</span>
                                    <span wire:loading wire:target="ejecutar('{{ $id }}')">⏳ Ejecutando…</span>
                                </x-button.secondary>
                            @else
                                <x-button.secondary
                                    wire:click="ejecutar('{{ $id }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="ejecutar('{{ $id }}')"
                                >
                                    <span wire:loading.remove wire:target="ejecutar('{{ $id }}')">Ejecutar</span>
                                    <span wire:loading wire:target="ejecutar('{{ $id }}')">⏳ Ejecutando…</span>
                                </x-button.secondary>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="p-4 bg-white border rounded-lg shadow">
        <h2 class="mb-3 text-lg font-semibold text-gray-900">RentasVariables</h2>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Tienda</label>
                <select wire:model="rvTienda" class="mt-1 border-gray-300 rounded-md shadow-sm">
                    <option value="BCN">Barcelona</option>
                    <option value="MAL">Málaga</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mes inicio</label>
                <select wire:model="rvMesInicio" class="mt-1 border-gray-300 rounded-md shadow-sm">
                    @foreach (range(1, 12) as $m)<option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mes fin</label>
                <select wire:model="rvMesFin" class="mt-1 border-gray-300 rounded-md shadow-sm">
                    @foreach (range(1, 12) as $m)<option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-center">
                <input type="checkbox" wire:model="rvReal" id="rvReal" class="border-gray-300 rounded">
                <label for="rvReal" class="ml-2 text-sm text-gray-700">Proceso real (declaración/cálculos escriben de verdad)</label>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-4 mt-4">
            <x-button.secondary wire:click="ejecutarRvCalculos" wire:loading.attr="disabled" wire:target="ejecutarRvCalculos">
                <span wire:loading.remove wire:target="ejecutarRvCalculos">Cálculos (usa "Mes" de arriba)</span>
                <span wire:loading wire:target="ejecutarRvCalculos">⏳ Ejecutando…</span>
            </x-button.secondary>
            <x-button.secondary
                wire:click="ejecutarRvDeclaracion"
                wire:loading.attr="disabled"
                wire:target="ejecutarRvDeclaracion"
                onclick="return confirm('¿Rellenar la declaración de {{ $rvTienda }} para el rango de meses indicado?')"
            >
                <span wire:loading.remove wire:target="ejecutarRvDeclaracion">Declaración a arrendador</span>
                <span wire:loading wire:target="ejecutarRvDeclaracion">⏳ Ejecutando…</span>
            </x-button.secondary>
        </div>

        <div class="pt-4 mt-4 border-t border-gray-200">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Envío del correo (independiente del proceso de arriba)</h3>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Correo de prueba</label>
                    <input type="email" wire:model="rvEmailPrueba" placeholder="tucorreo@ejemplo.com" class="mt-1 border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" wire:model="rvCorreccion" id="rvCorreccion" class="border-gray-300 rounded">
                    <label for="rvCorreccion" class="ml-2 text-sm text-gray-700">Es una corrección</label>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" wire:model="rvEnviarReal" id="rvEnviarReal" class="border-gray-300 rounded">
                    <label for="rvEnviarReal" class="ml-2 text-sm font-medium text-gray-700">Enviar de verdad (a los destinatarios reales)</label>
                </div>
                <x-button.secondary
                    wire:click="ejecutarRvEnvio"
                    wire:loading.attr="disabled"
                    wire:target="ejecutarRvEnvio"
                    onclick="return confirm('{{ $rvEnviarReal ? '¿Mandar el correo a los destinatarios REALES?' : '¿Mandar el correo de prueba?' }}')"
                >
                    <span wire:loading.remove wire:target="ejecutarRvEnvio">Enviar correo</span>
                    <span wire:loading wire:target="ejecutarRvEnvio">⏳ Enviando…</span>
                </x-button.secondary>
            </div>
            <p class="mt-2 text-xs text-gray-500">Este envío es un paso aparte: puedes ejecutar Cálculos/Declaración en modo real cuantas veces quieras sin que eso mande ningún correo, y lanzar el envío después (o repetirlo) sin volver a ejecutar el proceso. Si "Enviar de verdad" no está marcado, el envío va solo al correo de prueba indicado.</p>
        </div>
    </div>

    <div class="p-4 bg-gray-900 rounded-lg shadow">
        <h2 class="mb-2 text-sm font-semibold text-gray-300">Salida</h2>
        <pre class="overflow-x-auto text-xs text-green-400 whitespace-pre-wrap max-h-96" wire:loading.class="opacity-50">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
        <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
    </div>

    </div>
</div>
