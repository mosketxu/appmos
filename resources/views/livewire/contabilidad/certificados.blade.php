<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.certificados'])
    <div class="p-4 space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">🔐 Certificados por caducar</h1>
            <span class="px-2 py-0.5 text-xs font-semibold text-indigo-900 bg-indigo-100 rounded">💻 Proceso mensual · se ejecuta en LOCAL</span>
            <a href="{{ route('contabilidad.seguimiento-mensual') }}" class="text-sm text-indigo-600 underline">← Seguimiento mensual</a>
        </div>

        @unless ($enLocal)
            <div class="px-4 py-3 text-sm font-semibold text-red-800 border border-red-300 rounded-md bg-red-50">
                🔒 Los certificados están instalados en los PCs: este proceso solo funciona desde AlexMiniPC o PortalExomen (http://localhost:8000/contabilidad/certificados).
            </div>
        @endunless

        @if ($salida)
            <div class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md whitespace-pre-line">{{ $salida }}</div>
        @endif

        {{-- PASO 1 --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h2 class="font-semibold text-gray-900">Paso 1 · Preparar la lista</h2>
            <p class="text-xs text-gray-600">
                Cada PC guarda sus certificados con «Escanear este PC» (hazlo en AlexMiniPC <b>y</b> en PortalExomen). Después se cruzan: los ya renovados
                en cualquiera de los dos salen de la lista, y si el nuevo está solo en uno de los PCs se avisa abajo (instálalo en el otro).
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="escanear" wire:loading.attr="disabled" wire:target="escanear" @disabled(! $enLocal)
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="escanear">🔎 Escanear este PC</span><span wire:loading wire:target="escanear">⏳ escaneando…</span>
                </button>
                <label class="text-sm">Caducan en los próximos
                    <input type="number" min="1" max="24" wire:model="meses" class="py-1 text-sm border-gray-300 rounded-md" style="width:60px"> meses</label>
                <button type="button" wire:click="calcular" wire:loading.attr="disabled" wire:target="calcular" @disabled(! $enLocal)
                    class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">↻ Calcular lista</button>
                <span class="text-xs text-gray-500">
                    Escaneos:
                    @forelse ($escaneos as $pc => $cuando) {{ $pc }} ({{ str_replace('T', ' ', $cuando) }}) · @empty ninguno @endforelse
                </span>
            </div>

            @if ($contradicciones)
                <div class="p-3 text-sm text-yellow-900 bg-yellow-300 border border-yellow-500 rounded-md">
                    <b>⚠ Contradicciones</b> (no salen en la lista; falta instalar el certificado nuevo):
                    <ul class="ml-5 list-disc">
                        @foreach ($contradicciones as $c)
                            <li>{{ $c['nombre'] }} ({{ $c['alias'] }}): el viejo ({{ $c['caduca'] }}) sigue en {{ implode(', ', $c['falta_nuevo_en']) }}; el nuevo ({{ $c['nuevo_caduca'] }}) solo está en {{ implode(', ', $c['renovado_en']) }}.</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="text-sm">
                    <thead class="text-xs text-left text-gray-500 bg-gray-50">
                        <tr><th class="px-2 py-1">Incluir</th><th class="px-2 py-1">Caduca</th><th class="px-2 py-1">Certificado</th><th class="px-2 py-1">Instalado en</th><th class="px-2 py-1">Nota</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($filas as $i => $f)
                            <tr wire:key="fila-{{ $i }}">
                                <td class="px-2 py-1 text-center"><input type="checkbox" wire:model.live="filas.{{ $i }}.incluir" class="rounded"></td>
                                <td class="px-2 py-1"><input type="date" wire:model.blur="filas.{{ $i }}.caduca" class="py-0 text-sm border-gray-300 rounded"></td>
                                <td class="px-2 py-1"><input type="text" wire:model.blur="filas.{{ $i }}.nombre" class="w-full py-0 text-sm border-gray-300 rounded" style="min-width:420px"></td>
                                <td class="px-2 py-1 text-xs text-gray-600">{{ $f['pcs'] }}</td>
                                <td class="px-2 py-1"><input type="text" wire:model.blur="filas.{{ $i }}.nota" class="py-0 text-sm border-gray-300 rounded" style="width:150px"></td>
                                <td class="px-2 py-1"><button type="button" wire:click="quitar({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar de la lista">✕</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-2 text-gray-500">{{ $calculada ? 'No hay certificados que caduquen en ese plazo.' : 'Sin calcular.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model="aNombre" placeholder="Añadir a mano: certificado…" class="text-sm border-gray-300 rounded-md" style="width:320px">
                <input type="date" wire:model="aCaduca" class="text-sm border-gray-300 rounded-md">
                <button type="button" wire:click="anadir" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">＋ Añadir</button>
            </div>

            @if ($renovados)
                <details class="text-xs text-gray-600">
                    <summary class="cursor-pointer">Ya renovados (no salen en la lista) · {{ count($renovados) }}</summary>
                    <ul class="ml-5 list-disc">
                        @foreach ($renovados as $c)
                            <li>{{ $c['nombre'] }} ({{ $c['alias'] }}): caducaba {{ $c['caduca'] }}; el nuevo ({{ $c['nuevo_caduca'] }}) está en {{ implode(' + ', $c['renovado_en']) }}.</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        {{-- PASO 2 --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h2 class="font-semibold text-gray-900">Paso 2 · Enviar el correo</h2>
            <div class="grid gap-2 md:grid-cols-2">
                <label class="text-xs font-semibold text-gray-600">Para
                    <input type="text" wire:model.blur="para" class="w-full text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600">CC
                    <input type="text" wire:model.blur="cc" class="w-full text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600 md:col-span-2">Asunto
                    <input type="text" wire:model.blur="asunto" class="w-full text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600 md:col-span-2">Texto de arriba (la lista se añade sola)
                    <textarea wire:model.blur="intro" rows="4" class="w-full text-sm font-normal border-gray-300 rounded-md"></textarea></label>
            </div>
            <div>
                <div class="mb-1 text-xs font-semibold text-gray-600">Así saldrá</div>
                <pre class="p-3 text-sm text-gray-800 whitespace-pre-wrap border border-gray-200 rounded-md bg-gray-50" style="font-family:inherit">{{ str_replace('{logo}', '[logo de Suma]', $vistaPrevia) }}</pre>
            </div>
            @if ($enviado)
                <div class="text-sm font-semibold text-green-700">✅ Enviado el {{ $enviado }}</div>
            @elseif (! $graphOk)
                <div class="text-sm text-red-700">No encuentro las credenciales de Microsoft Graph (FacturacionPDFyMail/config.json) en este PC.</div>
            @elseif ($confirmar)
                <div class="flex flex-wrap items-center gap-2 p-3 text-sm border border-red-300 rounded-md bg-red-50">
                    <b>¿Enviar ya a {{ $para }}{{ $cc ? ' (CC '.$cc.')' : '' }}?</b>
                    <button type="button" wire:click="enviar" wire:loading.attr="disabled" class="px-3 py-1 text-white bg-red-600 rounded-md hover:bg-red-700">Sí, enviar</button>
                    <button type="button" wire:click="$set('confirmar', false)" class="px-3 py-1 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                </div>
            @else
                <button type="button" wire:click="pedirEnvio" @disabled(! $enLocal) class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">✉ Enviar…</button>
            @endif
        </div>
    </div>
</div>
