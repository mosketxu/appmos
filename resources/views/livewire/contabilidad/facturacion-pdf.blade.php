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

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.facturacion-pdf'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4 space-y-6">
    {{-- Fila de arriba: primer bloque + Salida a su altura; lo de debajo, a todo el ancho. --}}
    <div class="fila-salida">
    <div class="space-y-6 col-principal" style="--g:75">

    <h1 class="text-2xl font-semibold text-gray-900">Facturación PDF</h1>
    <p class="text-sm text-gray-500">
        Suma y Balerga son procesos independientes. <strong>Genérico</strong>: PDF con facturas de cualquier
        proveedor (también escaneado o fotografiado), sin correo.
        Suma y Balerga: Dos fases separadas: primero
        <strong>Separar PDFs</strong> (parte el PDF-listado en facturas individuales, no toca el correo
        en absoluto), y luego, ya con el resultado a la vista, <strong>Enviar correos</strong> (acción
        aparte, con confirmación).
    </p>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
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

        {{-- Genérico: facturas de cualquier proveedor (separar_generico.py), sin correo --}}
        @php($g = $generico)
        <div wire:key="cliente-Generico" class="p-4 bg-white border rounded-lg shadow">
            <h2 class="text-lg font-semibold text-gray-900">Genérico</h2>
            <p class="mt-1 mb-3 text-xs text-gray-500">
                PDF con varias facturas de cualquier proveedor (también escaneado o foto: se lee con OCR).
                Un PDF por factura con proveedor y número. Sin correo.
            </p>

            @if (! $this->genericoPermitido)
                <p class="text-xs text-amber-600">⚠️ No disponible en este servidor.</p>
            @elseif ($g['fase'] === 'vacio')
                <label class="block mb-2 text-xs font-medium text-gray-600">PDF con las facturas</label>
                <input type="file" wire:model="archivoGenerico" accept="application/pdf"
                       class="block w-full text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">
                @error('archivoGenerico')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div wire:loading wire:target="archivoGenerico" class="mt-1 text-xs text-gray-400">Subiendo…</div>

                <div class="mt-3">
                    <x-button.primary
                        wire:click="analizarGenerico"
                        wire:loading.attr="disabled"
                        wire:target="analizarGenerico, archivoGenerico"
                    >
                        <span wire:loading.remove wire:target="analizarGenerico">Fase 1 · Analizar</span>
                        <span wire:loading wire:target="analizarGenerico">⏳ Leyendo (OCR)…</span>
                    </x-button.primary>
                </div>
            @else
                <div class="p-2 mb-3 text-xs border rounded bg-gray-50 text-gray-700">
                    <div>📄 <span class="font-medium">{{ $g['nombreOriginal'] }}</span></div>
                    @if ($g['destinatario'])
                        <div class="mt-1 text-gray-500">Destinatario (no se usa como proveedor): {{ $g['destinatario'] }}</div>
                    @endif
                    <div class="mt-1">
                        @if ($g['fase'] === 'analizado')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">Analizado, revisa la tabla</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-100 text-green-800">PDFs generados</span>
                        @endif
                    </div>
                    @if (! empty($resultados['Generico']))
                        <div class="flex flex-col mt-2 gap-y-1">
                            @foreach ($resultados['Generico'] as $r)
                                <x-contabilidad.resultado-fichero :r="$r" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <p class="mb-1 text-xs text-gray-500">
                    Páginas seguidas con el mismo número y proveedor salen en un solo PDF. Corrige lo que el OCR haya leído mal.
                </p>
                <div class="mb-3 overflow-auto border rounded" style="max-height:22rem">
                    <table class="min-w-full text-xs divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-1 text-left">Pág.</th>
                                <th class="px-1 py-1 text-left">Tipo</th>
                                <th class="px-1 py-1 text-left">Proveedor</th>
                                <th class="px-1 py-1 text-left">Número</th>
                                <th class="px-1 py-1"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($genericoPaginas as $i => $f)
                                <tr wire:key="gen-pag-{{ $i }}">
                                    <td class="px-1 py-1 text-gray-500">{{ $f['pagina'] }}</td>
                                    <td class="px-1 py-1">
                                        <select wire:model="genericoPaginas.{{ $i }}.tipo" class="py-0.5 pl-1 pr-6 text-xs border-gray-300 rounded">
                                            <option value="Fra">Fra</option>
                                            <option value="Abo">Abo</option>
                                            <option value="Pre">Pre</option>
                                        </select>
                                    </td>
                                    <td class="px-1 py-1">
                                        <input type="text" wire:model="genericoPaginas.{{ $i }}.proveedor"
                                               style="min-width:8rem" class="w-full py-0.5 px-1 text-xs border-gray-300 rounded {{ trim($f['proveedor'] ?? '') === '' ? 'bg-red-50' : '' }}">
                                    </td>
                                    <td class="px-1 py-1">
                                        <input type="text" wire:model="genericoPaginas.{{ $i }}.numero"
                                               style="min-width:6rem" class="w-full py-0.5 px-1 text-xs border-gray-300 rounded {{ trim($f['numero'] ?? '') === '' ? 'bg-red-50' : '' }}">
                                    </td>
                                    <td class="px-1 py-1 whitespace-nowrap">
                                        @if ($i > 0)
                                            <button type="button" wire:click="igualQueAnterior({{ $i }})"
                                                    class="text-gray-400 hover:text-gray-700" title="Igual que la anterior (misma factura)">↑=</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-button.primary
                        wire:click="generarGenerico"
                        wire:loading.attr="disabled"
                        wire:target="generarGenerico"
                    >
                        <span wire:loading.remove wire:target="generarGenerico">Fase 2 · Generar PDFs</span>
                        <span wire:loading wire:target="generarGenerico">⏳ Generando…</span>
                    </x-button.primary>
                    @if ($g['fase'] === 'generado' && $g['zip'])
                        <x-button.secondary wire:click="descargarZipGenerico">⬇️ Descargar .zip</x-button.secondary>
                    @endif
                    <x-button.secondary
                        wire:click="empezarDeNuevoGenerico"
                        wire:loading.attr="disabled"
                        wire:target="empezarDeNuevoGenerico"
                    >
                        Empezar de nuevo (otro PDF)
                    </x-button.secondary>
                </div>
            @endif
        </div>
    </div>

    </div>{{-- /col-principal --}}
    @include('livewire.contabilidad._salida', ['ancho' => 25])
    </div>{{-- /fila-salida --}}
    </div>
</div>
