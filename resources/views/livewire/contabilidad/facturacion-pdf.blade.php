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

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.facturacion-pdf'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

    <h1 class="text-2xl font-semibold text-gray-900">Facturación PDF</h1>
    <p class="text-sm text-gray-500">
        Suma y Balerga son procesos independientes. Dos fases separadas: primero
        <strong>Separar PDFs</strong> (parte el PDF-listado en facturas individuales, no toca el correo
        en absoluto), y luego, ya con el resultado a la vista, <strong>Enviar correos</strong> (acción
        aparte, con confirmación).
    </p>

    <div class="grid gap-6 sm:grid-cols-2">
        @foreach ($this->clientes as $id => $c)
            @php($e = $estado[$id] ?? ['fase' => 'vacio', 'nombreOriginal' => null])
            @php($d = $destinatarios[$id] ?? null)
            <div wire:key="cliente-{{ $id }}" class="p-4 bg-white border rounded-lg shadow">
                <h2 class="text-lg font-semibold text-gray-900">{{ $c['label'] }}</h2>
                <p class="mt-1 mb-3 text-xs text-gray-500">{{ $c['ayuda'] }}</p>

                @if ($e['fase'] === 'vacio')
                    {{-- Fase 0: elegir/subir el PDF --}}
                    <label class="block mb-2 text-xs font-medium text-gray-600">PDF-listado del mes</label>
                    <input type="file" wire:model="archivo.{{ $id }}" accept="application/pdf"
                           class="block w-full text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">
                    @error("archivo.{$id}")
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div wire:loading wire:target="archivo.{{ $id }}" class="mt-1 text-xs text-gray-400">Subiendo…</div>

                    <div class="mt-3">
                        <x-button.primary
                            wire:click="separarPdf('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="separarPdf('{{ $id }}'), archivo.{{ $id }}"
                        >
                            <span wire:loading.remove wire:target="separarPdf('{{ $id }}')">Fase 1 · Separar PDFs</span>
                            <span wire:loading wire:target="separarPdf('{{ $id }}')">⏳ Separando…</span>
                        </x-button.primary>
                    </div>
                @else
                    {{-- Fase 1 hecha (o Fase 2 hecha): qué archivo se procesó, y las acciones que tocan --}}
                    <div class="p-2 mb-3 text-xs border rounded bg-gray-50 text-gray-700">
                        <div>📄 <span class="font-medium">{{ $e['nombreOriginal'] }}</span></div>
                        <div class="mt-1">
                            @if ($e['fase'] === 'separado')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">Separado, sin enviar</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-100 text-green-800">Enviado</span>
                            @endif
                        </div>
                        @if (! empty($resultados[$id]))
                            <div class="flex flex-col mt-2 gap-y-1">
                                @foreach ($resultados[$id] as $r)
                                    <x-contabilidad.resultado-fichero :r="$r" />
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($e['fase'] === 'separado')
                            <x-button.primary
                                wire:click="enviarCorreos('{{ $id }}')"
                                wire:loading.attr="disabled"
                                wire:target="enviarCorreos('{{ $id }}')"
                                onclick="return confirm('Esto manda los correos de {{ $c['label'] }} de verdad a los destinatarios reales. ¿Seguro?')"
                            >
                                <span wire:loading.remove wire:target="enviarCorreos('{{ $id }}')">Fase 2 · Enviar correos (REAL)</span>
                                <span wire:loading wire:target="enviarCorreos('{{ $id }}')">⏳ Enviando…</span>
                            </x-button.primary>
                        @endif
                        <x-button.secondary
                            wire:click="empezarDeNuevo('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="empezarDeNuevo('{{ $id }}')"
                        >
                            Empezar de nuevo (otro PDF)
                        </x-button.secondary>
                    </div>
                @endif

                {{-- Destinatarios de este cliente: solo lectura, con filtro y enlace al Excel real --}}
                <div class="pt-4 mt-4 border-t">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <h3 class="text-sm font-semibold text-gray-800">Destinatarios</h3>
                        <x-button.secondary
                            class="!py-1 !px-2 text-xs"
                            wire:click="cargarDestinatarios('{{ $id }}')"
                            wire:loading.attr="disabled"
                            wire:target="cargarDestinatarios('{{ $id }}')"
                        >
                            <span wire:loading.remove wire:target="cargarDestinatarios('{{ $id }}')">{{ $d ? 'Recargar' : 'Cargar lista' }}</span>
                            <span wire:loading wire:target="cargarDestinatarios('{{ $id }}')">⏳ Cargando…</span>
                        </x-button.secondary>
                    </div>
                    <p class="mb-2 text-xs text-gray-500">
                        Solo lectura -- para corregir un dato se abre el Excel real (enlace tras cargar la lista).
                    </p>

                    @if ($d && isset($d['error']))
                        <p class="text-xs text-red-600">⚠️ {{ $d['error'] }}</p>
                    @elseif ($d)
                        @foreach (($d['avisos'] ?? []) as $aviso)
                            <p class="text-xs text-amber-600">⚠️ {{ $aviso }}</p>
                        @endforeach

                        <div class="flex flex-wrap items-center gap-3 my-2 text-xs">
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="todos"> Todos ({{ count($d['filas']) }})
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="si"> Enviar = sí ({{ count(array_filter($d['filas'], fn($f) => $f['enviar'])) }})
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" wire:model.live="filtroEnviar.{{ $id }}" value="no"> Enviar = no ({{ count(array_filter($d['filas'], fn($f) => ! $f['enviar'])) }})
                            </label>
                        </div>
                        @if (! empty($d['xlsxPathWindows']))
                            <div class="mb-2">
                                <x-contabilidad.resultado-fichero :r="['ruta' => $d['xlsxPathWindows']]" />
                            </div>
                        @endif

                        <div class="overflow-auto border rounded" style="max-height:22rem">
                            <table class="min-w-full text-xs divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1 text-left">Cliente</th>
                                        <th class="px-2 py-1 text-left">Mail</th>
                                        <th class="px-2 py-1 text-left">Idioma</th>
                                        <th class="px-2 py-1 text-left">Enviar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($this->destinatariosFiltrados[$id] as $fila)
                                        <tr>
                                            <td class="px-2 py-1">{{ $fila['cliente'] }}</td>
                                            <td class="px-2 py-1 break-all">{{ $fila['mail'] }}</td>
                                            <td class="px-2 py-1">{{ $fila['idioma'] ?: 'ES' }}</td>
                                            <td class="px-2 py-1">
                                                @if ($fila['enviar'])
                                                    <span class="text-green-700">sí</span>
                                                @else
                                                    <span class="text-gray-400">no</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-2 py-3 text-center text-gray-400">Sin filas para este filtro.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
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
