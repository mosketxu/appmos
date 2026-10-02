<div>
    @unless ($embebido)
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.certificados'])
    @endunless
    <div class="p-4 space-y-4">
        @unless ($embebido)
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">🔐 Certificados por caducar</h1>
            <span class="px-2 py-0.5 text-xs font-semibold text-indigo-900 bg-indigo-100 rounded">🌐 web + 💻 local · el escaneo lo hace cada PC</span>
            <a href="{{ route('contabilidad.seguimiento-mensual') }}" class="text-sm text-indigo-600 underline">← Seguimiento mensual</a>
        </div>
        @endunless

        @if ($enLocal)
            <div class="px-4 py-2 text-sm border rounded-md text-emerald-900 border-emerald-200 bg-emerald-50">
                💻 Estás en <b>{{ $pcLocal }}</b> (local): «Escanear este PC» lee los certificados de este PC y los sube a la web. Para escanear <b>los dos PCs a la vez</b>, entra en
                <a href="https://appmos.sumaempresa.com/contabilidad/certificados" class="underline" target="_blank">appmos.sumaempresa.com</a> y pulsa «Pedir escaneo a los PCs».
            </div>
        @else
            <div class="px-4 py-2 text-sm text-indigo-900 border border-indigo-200 rounded-md bg-indigo-50">
                🌐 Estás en la web: ves la última lista escaneada, puedes editarla y enviarla.
                @if (count($trabajadores))
                    Con «Pedir escaneo a los PCs» cada PC encendido escanea sus certificados solo; no hace falta abrir nada en ellos.
                @else
                    Para refrescar los certificados, pulsa «Escanear este PC» en el Appmos de AlexMiniPC y de PortalExomen (se suben aquí solos).
                @endif
            </div>
        @endif

        @if ($salida)
            <div class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md whitespace-pre-line">{{ $salida }}</div>
        @endif

        {{-- PASOS 1 y 2, lado a lado --}}
        <div class="grid items-start gap-4 xl:grid-cols-2">
        {{-- PASO 1 --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div x-data="{ info: false }">
                <div class="flex items-center gap-x-1">
                    <h2 class="font-semibold text-gray-900">Paso 1 · Preparar la lista</h2>
                    <button type="button" x-on:click="info = !info" class="text-indigo-500 hover:text-indigo-700" title="Detalle">ⓘ</button>
                </div>
                <p x-show="info" style="display:none" x-on:click.outside="info = false" class="p-2 mt-1 text-xs text-gray-500 border border-gray-200 rounded bg-gray-50">
                    Cada PC guarda sus certificados con «Escanear este PC» (hazlo en AlexMiniPC <b>y</b> en PortalExomen), o los dos a la vez con «Pedir escaneo a los PCs» desde la web.
                    Después se cruzan: los ya renovados en cualquiera de los dos salen de la lista, y si el nuevo está solo en uno de los PCs se avisa abajo (instálalo en el otro).
                </p>
            </div>
            @if (! $enLocal && count($trabajadores))
                <div class="flex flex-wrap items-center gap-2 p-2 text-sm border border-indigo-200 rounded-md bg-indigo-50" @if ($hayActivas || $habiaActivas) wire:poll.3s="actualizarCola" @endif>
                    <button type="button" wire:click="pedirEscaneoPCs" wire:loading.attr="disabled" wire:target="pedirEscaneoPCs"
                        class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">🛰 Pedir escaneo a los PCs</button>
                    <span class="text-xs text-gray-700">
                        @foreach ($trabajadores as $t) <b>{{ $t['nombre'] }}</b> {{ $t['conectado'] ? '🟢' : '⚪ sin conexión' }}@if (! $loop->last) · @endif @endforeach
                    </span>
                    @if (count($tareasCert))
                        <span x-data="{ info: false }" class="relative">
                            <button type="button" x-on:click="info = !info" class="text-indigo-500 hover:text-indigo-700" title="Últimas tareas">ⓘ</button>
                            <div x-show="info" style="display:none" x-on:click.outside="info = false" class="absolute left-0 z-10 p-2 mt-1 text-xs text-gray-600 bg-white border border-gray-200 rounded shadow whitespace-nowrap">
                                <b>Últimas tareas</b>
                                @foreach ($tareasCert as $tt)
                                    <div>#{{ $tt->id }} {{ $tt->destino }}: {{ ['pendiente' => '⏳ en cola', 'en_curso' => '⚙ en curso', 'ok' => '✅ ok', 'error' => '⚠ error', 'cancelada' => '✖ cancelada'][$tt->estado] ?? $tt->estado }}</div>
                                @endforeach
                            </div>
                        </span>
                    @endif
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="escanear" wire:loading.attr="disabled" wire:target="escanear" @disabled(! $enLocal) title="{{ $enLocal ? '' : 'Solo desde un PC' }}"
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="escanear">🔎 Escanear este PC</span><span wire:loading wire:target="escanear">⏳ escaneando…</span>
                </button>
                <label class="text-sm">Caducan en los próximos
                    <input type="number" min="1" max="24" wire:model="meses" class="py-1 text-sm border-gray-300 rounded-md" style="width:60px"> meses</label>
                <button type="button" wire:click="calcular" wire:loading.attr="disabled" wire:target="calcular"
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
                                <td class="px-2 py-1"><input type="date" wire:model.live.debounce.400ms="filas.{{ $i }}.caduca" class="py-0 text-sm border-gray-300 rounded"></td>
                                <td class="px-2 py-1"><input type="text" wire:model.live.debounce.400ms="filas.{{ $i }}.nombre" class="w-full py-0 text-sm border-gray-300 rounded" style="min-width:420px"></td>
                                <td class="px-2 py-1 text-xs text-gray-600">{{ $f['pcs'] }}</td>
                                <td class="px-2 py-1"><input type="text" wire:model.live.debounce.400ms="filas.{{ $i }}.nota" class="py-0 text-sm border-gray-300 rounded" style="width:150px"></td>
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
        <div class="p-3 space-y-2 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h2 class="font-semibold text-gray-900">Paso 2 · Enviar el correo</h2>
            <div class="grid gap-2 md:grid-cols-3">
                <label class="text-xs font-semibold text-gray-600">Para
                    <input type="text" wire:model.blur="para" class="w-full py-1 text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600">CC
                    <input type="text" wire:model.blur="cc" class="w-full py-1 text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600">Asunto
                    <input type="text" wire:model.live.debounce.300ms="asunto" class="w-full py-1 text-sm font-normal border-gray-300 rounded-md"></label>
                <label class="text-xs font-semibold text-gray-600 md:col-span-3">Texto de arriba (la lista se añade sola)
                    <textarea wire:model.live.debounce.300ms="intro" rows="2" class="w-full py-1 text-sm font-normal leading-snug border-gray-300 rounded-md"></textarea></label>
            </div>
            <div>
                <div class="text-xs font-semibold text-gray-600">Así saldrá</div>
                <pre class="p-2 overflow-y-auto text-xs leading-snug text-gray-800 whitespace-pre-wrap border border-gray-200 rounded-md bg-gray-50" style="font-family:inherit;max-height:14rem">{{ str_replace('{logo}', '[logo de Suma]', $vistaPrevia) }}</pre>
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
                <button type="button" wire:click="pedirEnvio" class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">✉ Enviar…</button>
            @endif
        </div>
        </div>

        {{-- ENVÍOS: detalle por meses, como el checklist de Fashion --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-900">Envíos de {{ $anio }}</h2>
                <button type="button" wire:click="cambiarAnio(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
                <button type="button" wire:click="cambiarAnio(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'] as $n => $mes)
                    @php $k = sprintf('%d-%02d', $anio, $n + 1); $e = $envios[$k] ?? collect(); @endphp
                    <div class="text-center" style="min-width:52px">
                        <div class="text-xs text-gray-500">{{ $mes }}</div>
                        @if ($e->isNotEmpty())
                            <button type="button" wire:click="ver({{ $e->last()->id }})" title="{{ $e->count() }} envío(s): clic para ver lo que se mandó"
                                class="inline-flex items-center justify-center w-6 h-6 text-xs text-white bg-green-500 border border-green-600 rounded">✓</button>
                        @else
                            <span class="inline-flex items-center justify-center w-6 h-6 bg-white border border-gray-300 rounded"></span>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($envioAbierto)
                <div class="p-3 space-y-1 text-sm border border-gray-200 rounded-md bg-gray-50">
                    <div><b>{{ \Carbon\Carbon::parse($envioAbierto->enviado_at)->format('d/m/Y H:i') }}</b> · {{ $envioAbierto->origen === 'manual' ? 'enviado a mano (fuera de Appmos)' : 'enviado desde Appmos' }}</div>
                    @if ($envioAbierto->para)<div>Para: {{ $envioAbierto->para }}{{ $envioAbierto->cc ? ' · CC '.$envioAbierto->cc : '' }}</div>@endif
                    @if ($envioAbierto->asunto)<div>Asunto: {{ $envioAbierto->asunto }}</div>@endif
                    @if ($envioAbierto->texto)<pre class="p-2 whitespace-pre-wrap bg-white border border-gray-200 rounded" style="font-family:inherit">{{ str_replace('{logo}', '[logo de Suma]', $envioAbierto->texto) }}</pre>@endif
                </div>
            @endif
        </div>
    </div>
</div>
