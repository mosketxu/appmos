<div>
    @unless ($embebido)
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.certificados'])
    @endunless
    <div class="p-4 space-y-4">
        @unless ($embebido)
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">🔐 Certificados por caducar</h1>
            <span class="px-2 py-0.5 text-xs font-semibold text-indigo-900 bg-indigo-100 rounded">🌐 web + 💻 local · el escaneo lo hace cada PC</span>
            <a href="{{ route('contabilidad.procesos-mensuales') }}" class="px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50">← Volver a Procesos mensuales</a>
        </div>
        @endunless

        <style>
            .cm-modal { position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center; background:rgba(17,24,39,.55); padding:1rem }
            .cm-caja { background:#fff; border-radius:.75rem; max-width:34rem; width:100%; max-height:92vh; overflow:auto; padding:1.5rem; box-shadow:0 20px 50px rgba(0,0,0,.3) }
            .cm-paso { display:flex; gap:.75rem; align-items:flex-start; margin-top:.9rem }
            .cm-num { flex:none; width:1.75rem; height:1.75rem; border-radius:9999px; background:#4f46e5; color:#fff; font-weight:600; display:flex; align-items:center; justify-content:center }
            .cm-grid { display:grid; gap:1rem; align-items:start }
            .cm-rojo { background:#fecaca } .cm-amarillo { background:#fef08a }
            .cm-pag { display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap }
            .cm-lat { flex:0 0 15rem; max-width:100% }
            .cm-cont { flex:1 1 0; min-width:min(100%, 640px) }
            .cm-mes { cursor:pointer; display:flex; justify-content:space-between; width:100%; padding:.35rem .6rem; margin-top:.25rem; border-radius:.375rem; font-size:.85rem; text-align:left }
            .cm-mes span { font-weight:600 }
            .cm-mes, .cm-caja { color:#111827 }
            /* modo oscuro (la página se invierte con un filtro): sin color explícito, campos y botones salían negro sobre negro */
            html.dark .cm-pag :is(input, select, textarea):not([type=checkbox]):not([type=radio]), html.dark .cm-caja :is(input, select, textarea) { background-color:#e5e7eb; color:#111827 }
            .cm-mes:hover { filter:brightness(.96) } .cm-cero { opacity:.55 }
            .cm-sel { outline:2px solid #4f46e5; font-weight:600 }
            @media (min-width:1500px) { .cm-grid { grid-template-columns:1fr 1fr } }
            .cm-btn { display:inline-block; padding:.45rem .9rem; border-radius:.375rem; color:#fff; font-size:.875rem; cursor:pointer }
        </style>

        {{-- Recuadro central «escaneando…» (el de siempre) mientras se espera a que los PCs lean sus certificados --}}
        <x-contabilidad.procesando target="escanear, pedirEscaneoPCs, pedirMiEscaneo" :visible="$esperandoMio || $activasRecientes"
            :titulo="$activasRecientes ? 'Escaneando los certificados de los PCs…' : 'Escaneando los certificados de tu PC…'"
            :nota="$activasRecientes ? 'Cada PC encendido lee los suyos y la lista se actualiza sola. Suele tardar entre medio minuto y dos minutos; no cierres la página.' : 'Suele tardar unos segundos (hasta un minuto). Si el navegador pregunta si abre «Appmos certificados», di que sí.'" />

        @if ($salida)
            <div class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md whitespace-pre-line">{{ $salida }}</div>
        @endif

        <div class="cm-pag">
        <aside class="p-3 bg-white border border-gray-200 rounded-lg shadow-sm cm-lat">
            <h2 class="font-semibold text-gray-900">Caducidad por mes</h2>
            <div class="mt-1 text-xs font-medium text-indigo-700">👇 Pulsa un mes para ver sus certificados a la derecha</div>
            <div class="mt-1 text-xs text-gray-500"><span class="px-2 cm-rojo">&nbsp;</span> rojo: este mes y el próximo</div>
            <div class="text-xs text-gray-500"><span class="px-2 cm-amarillo">&nbsp;</span> amarillo: 3.º y 4.º mes</div>
            <button type="button" wire:click="elegirMes('todos')" class="cm-mes {{ $mesSel === 'todos' ? 'cm-sel' : '' }}" style="background:#f3f4f6">Todos <span>{{ count($todosCerts) }}</span></button>
            @foreach ($resumenMeses as $m)
                <button type="button" wire:click="elegirMes('{{ $m['clave'] }}')" wire:key="mes-{{ $m['clave'] }}"
                    class="cm-mes {{ $m['color'] ? 'cm-'.$m['color'] : '' }} {{ $mesSel === $m['clave'] ? 'cm-sel' : '' }} {{ $m['n'] ? '' : 'cm-cero' }}">{{ $m['etiqueta'] }} <span>{{ $m['n'] }}</span></button>
            @endforeach
        </aside>
        <div class="space-y-4 cm-cont">
        {{-- Escanear el PC del propio usuario: lo único que ven los usuarios que no son de los PCs trabajadores --}}
        <div class="p-3 text-sm border border-gray-200 rounded-md bg-gray-50" @if ($miEscaneo && $miEscaneo->solicitado_at) wire:poll.3s="actualizarMio" @endif>
            <div class="flex flex-wrap items-center gap-2">
                @if ($miEscaneo)
                    <a href="appmos-certificados://escanear" wire:click="pedirMiEscaneo" role="button" style="cursor:pointer;text-decoration:none"
                        class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ $esperandoMio ? '⏳ escaneando… (pulsa para repetir)' : '💻 Escanear mi PC ahora' }}</a>
                    <span class="text-xs text-gray-700">Tu PC{{ $miEscaneo->equipo ? ' ('.$miEscaneo->equipo.')' : '' }}: último escaneo {{ str_replace('T', ' ', $miEscaneo->escaneado) }}</span>
                    <button type="button" wire:click="abrirInstalar" class="text-xs text-indigo-600 underline">Reinstalar / desinstalar</button>
                    @if ($sinRespuesta)
                        <div class="w-full mt-1 text-xs" style="color:#b91c1c">⚠ Tu PC no ha contestado en 1 minuto. Mira si el navegador está esperando que aceptes abrir «Appmos certificados» (aviso arriba, junto a la barra de direcciones) y vuelve a pulsar. Si sigue igual, usa «Reinstalar».</div>
                    @endif
                @else
                    <button type="button" wire:click="abrirInstalar" class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">💻 Escanear mi PC</button>
                    <span class="text-xs text-gray-700">La primera vez hay que instalar una pequeña tarea en tu PC; te lo explico paso a paso.</span>
                @endif
            </div>
            @if ($miEscaneo)
                @php
                    $misCerts = $this->misCertificados();
                    $textoCopia = collect($misCerts)->map(fn ($c) => $c['nombre'].' · '.$c['cif'].' · caduca '.$c['caduca'])->implode("\n");
                @endphp
                <div class="mt-2 text-xs text-gray-700">
                    <b>Certificados encontrados en tu PC: {{ count($misCerts) }}</b>
                    @if (count($misCerts))
                        <div class="flex flex-wrap items-center gap-2 mt-2" x-data="{ copiado: false }">
                            <button type="button" wire:click="descargarMiLista" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100">⬇ Descargar mi lista</button>
                            <button type="button" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                data-texto="{{ $textoCopia }}"
                                x-on:click="navigator.clipboard.writeText($el.dataset.texto).then(() => { copiado = true; setTimeout(() => copiado = false, 2500) })">📋 Copiar</button>
                            <span x-show="copiado" style="display:none" class="text-emerald-700">✓ Copiado</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if ($modalInstalar)
            <div class="cm-modal" wire:poll.5s="comprobarInstalacion" wire:click.self="cerrarInstalar">
                <div class="cm-caja">
                    <div class="flex items-start justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">💻 Instalar el escáner en tu PC</h2>
                        <button type="button" wire:click="cerrarInstalar" class="text-gray-500 hover:text-gray-800" title="Cerrar">✕</button>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Solo se hace una vez y tarda un minuto. No hace falta ser administrador.</p>

                    <div class="cm-paso"><span class="cm-num">1</span><div class="text-sm text-gray-800">
                        Descarga el instalador.<br>
                        <button type="button" wire:click="descargarScript" class="cm-btn" style="display:block;width:100%;background:#059669;margin-top:.5rem;padding:1rem 1.25rem;font-size:1.2rem;font-weight:700;border-radius:.5rem">⬇ Pulsar aquí para descargar</button>
                    </div></div>
                    <div class="cm-paso"><span class="cm-num">2</span><div class="text-sm text-gray-800">
                        Ábrelo: en la carpeta de Descargas, <b>doble clic</b> sobre <b>Instalar-escaner-certificados</b> (tipo «Windows Command Script»). Si el navegador dice que no es habitual, elige <b>«Conservar»</b>.
                    </div></div>
                    <div class="cm-paso"><span class="cm-num">3</span><div class="text-sm text-gray-800">
                        Si Windows avisa de que el fichero no es conocido («Windows protegió su PC»), pulsa <b>«Más información»</b> y luego <b>«Ejecutar de todas formas»</b>.
                    </div></div>
                    <div class="cm-paso"><span class="cm-num">4</span><div class="text-sm text-gray-800">
                        Cuando la ventana diga <b>«LISTO»</b>, pulsa Intro para cerrarla. Esta pantalla se actualizará sola. Desde ahora, cada vez que pulses «Escanear mi PC ahora» el navegador puede preguntar si abre «Appmos certificados»: di que sí y marca «permitir siempre».
                    </div></div>

                    <p class="p-2 mt-4 text-xs text-gray-600 rounded bg-gray-50">🔒 No queda nada funcionando en segundo plano: el escáner solo se lanza cuando pulsas el botón. Solo se envía el nombre, el CIF/NIF y la fecha de caducidad de tus certificados; nunca la clave privada.
                        Para quitarlo del PC: ejecuta el mismo fichero con <code>-Desinstalar</code>.</p>
                    <div class="mt-3 text-right"><button type="button" wire:click="cerrarInstalar" class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">Cerrar</button></div>
                </div>
            </div>
        @endif

        {{-- Certificados del mes elegido a la izquierda --}}
        <div class="p-3 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h2 class="font-semibold text-gray-900">{{ $tituloDetalle }} <span class="text-xs font-normal text-gray-500">({{ count($detalle) }}{{ $mesSel === 'todos' ? '' : ', por fecha de caducidad' }})</span></h2>
            @if ($nOcultos)
                <div class="mt-1 text-xs"><button type="button" wire:click="$toggle('verOcultos')" class="text-indigo-700 underline" style="color:#4338ca">{{ $verOcultos ? 'Ocultar de nuevo los vistos' : 'Hay '.$nOcultos.' marcado(s) como visto: mostrarlos' }}</button></div>
            @endif
            @if ($mesSel === 'todos')
                <div class="mt-1 text-xs text-gray-600">Ordenados por urgencia: primero los que hay que renovar ya, después los próximos y al final los que no tienen prisa.</div>
            @endif
            <table class="w-full mt-2 text-sm">
                <thead><tr class="text-left text-gray-500"><th class="px-2 py-1">Caduca</th><th class="px-2">Certificado</th><th class="px-2">CIF/NIF</th><th class="px-2">Instalado en</th><th></th></tr></thead>
                <tbody>
                    @forelse ($grupos as $g)
                        @if ($g['titulo'])
                            <tr><td colspan="5" class="px-2 pt-3 pb-1 text-xs font-semibold tracking-wide text-gray-700 uppercase" style="border-bottom:1px solid #d1d5db">{{ $g['titulo'] }} ({{ count($g['filas']) }})</td></tr>
                        @endif
                        @foreach ($g['filas'] as $tc)
                            <tr class="{{ $tc['color'] ? 'cm-'.$tc['color'] : '' }}" @if ($tc['renovado']) style="color:#9ca3af" title="Ya hay uno más nuevo de la misma empresa/persona" @endif>
                                <td class="px-2 py-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($tc['caduca'])->format('d/m/Y') }}@if (! $tc['renovado'] && $tc['caduca'] < now()->format('Y-m-d')) <b class="text-xs">caducado</b>@endif</td>
                                <td class="px-2">{{ $tc['nombre'] }}@if ($tc['alias']) <span class="text-gray-500">({{ $tc['alias'] }})</span>@endif @if ($tc['renovado']) <span class="text-xs">renovado</span>@endif</td>
                                <td class="px-2">{{ $tc['cif'] }}</td>
                                <td class="px-2">{{ implode(' + ', $tc['pcs']) }}</td>
                                <td class="px-2 text-right whitespace-nowrap">
                                    @if ($tc['visto'])
                                        <button type="button" wire:click="deshacerVisto('{{ $tc['huella'] }}')" class="text-xs text-indigo-700 underline" style="color:#4338ca">↩ Volver a mostrar</button>
                                    @elseif (! $tc['renovado'] && $tc['caduca'] < now()->format('Y-m-d'))
                                        <button type="button" wire:click="marcarVisto('{{ $tc['huella'] }}')" title="Ya lo sé: no me interesa seguir viéndolo" class="px-2 py-0.5 text-xs bg-white border border-gray-400 rounded hover:bg-gray-100" style="color:#374151">👁 Visto</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-gray-500">{{ $mesSel === 'todos' ? 'Todavía no hay certificados escaneados.' : 'No hay certificados pendientes en este mes.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($verTodos)
        @if ($enLocal)
            <div class="px-4 py-2 text-sm border rounded-md text-emerald-900 border-emerald-200 bg-emerald-50">
                💻 Estás en <b>{{ $pcLocal }}</b> (local): «Escanear y preparar la lista» lee los certificados de este PC y los sube a la web. Para escanear <b>los dos PCs a la vez</b>, entra en
                <a href="https://appmos.sumaempresa.com/contabilidad/certificados" class="underline" target="_blank">appmos.sumaempresa.com</a> y pulsa el mismo botón.
            </div>
        @else
            <div class="px-4 py-2 text-sm text-indigo-900 border border-indigo-200 rounded-md bg-indigo-50">
                🌐 Estás en la web: ves la última lista escaneada, puedes editarla y enviarla.
                @if (count($trabajadores))
                    Con «Escanear mi PC ahora» (arriba) se escanea tu PC y cada PC trabajador encendido, y la lista se calcula al terminar.
                @else
                    Para refrescar los certificados, pulsa el botón de escanear en el Appmos de AlexMiniPC y de PortalExomen (se suben aquí solos).
                @endif
            </div>
        @endif

        {{-- PASOS 1 y 2, lado a lado --}}
        <div class="cm-grid">
        {{-- PASO 1 --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm xl:col-start-1 xl:row-start-1">
            <div x-data="{ info: false }">
                <div class="flex items-center gap-x-1">
                    <h2 class="font-semibold text-gray-900">Paso 1 · Lista de certificados a enviar</h2>
                    <button type="button" x-on:click="info = !info" class="text-indigo-500 hover:text-indigo-700" title="Detalle">ⓘ</button>
                </div>
                <p x-show="info" style="display:none" x-on:click.outside="info = false" class="p-2 mt-1 text-xs text-gray-500 border border-gray-200 rounded bg-gray-50">
                    El botón «Escanear mi PC ahora» de arriba hace todo de una vez: escanea tu PC y pide a cada PC trabajador que escanee el suyo; la lista se calcula sola al terminar.
                    Se cruzan los dos PCs: los ya renovados en cualquiera de los dos salen de la lista, y si el nuevo está solo en uno de los PCs se avisa abajo (instálalo en el otro).
                </p>
            </div>
            @php $porCola = ! $enLocal && count($trabajadores); @endphp
            <div class="flex flex-wrap items-center gap-2" @if ($porCola && ($hayActivas || $habiaActivas)) wire:poll.3s="actualizarCola" @endif>
                @if ($porCola)
                    @if ($hayActivas) <span class="text-sm text-gray-700">⏳ escaneando los PCs…</span> @endif
                @elseif ($enLocal)
                    <button type="button" wire:click="escanear" wire:loading.attr="disabled" wire:target="escanear"
                        class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="escanear">🔎 Escanear y preparar la lista</span><span wire:loading wire:target="escanear">⏳ escaneando…</span>
                    </button>
                @else
                    <span class="text-xs text-gray-600">Sin PCs trabajadores dados de alta: escanea desde el Appmos de cada PC.</span>
                @endif
                <label class="text-sm">Caducan en los próximos
                    <input type="number" min="1" max="24" wire:model.live.debounce.500ms="meses" class="py-1 text-sm border-gray-300 rounded-md" style="width:60px"> meses</label>
                @if ($porCola)
                    <span class="text-xs text-gray-700">
                        @foreach ($trabajadores as $t) <b>{{ $t['nombre'] }}</b> {{ $t['conectado'] ? '🟢' : '⚪ sin conexión' }}@if (! $loop->last) · @endif @endforeach
                    </span>
                @endif
                <span x-data="{ info: false }" class="relative">
                    <button type="button" x-on:click="info = !info" class="text-indigo-500 hover:text-indigo-700" title="Escaneos y últimas tareas">ⓘ</button>
                    <div x-show="info" style="display:none" x-on:click.outside="info = false" class="absolute left-0 z-10 p-2 mt-1 text-xs text-gray-600 bg-white border border-gray-200 rounded shadow whitespace-nowrap">
                        <b>Último escaneo</b>
                        @forelse ($escaneos as $pc => $cuando) <div>{{ $pc }}: {{ str_replace('T', ' ', $cuando) }}</div> @empty <div>ninguno</div> @endforelse
                        @if (count($tareasCert))
                            <div class="mt-1"><b>Últimas tareas</b></div>
                            @foreach ($tareasCert as $tt)
                                <div>#{{ $tt->id }} {{ $tt->destino }}: {{ ['pendiente' => '⏳ en cola', 'en_curso' => '⚙ en curso', 'ok' => '✅ ok', 'error' => '⚠ error', 'cancelada' => '✖ cancelada'][$tt->estado] ?? $tt->estado }}</div>
                            @endforeach
                        @endif
                    </div>
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
        <div class="p-3 space-y-2 bg-white border border-gray-200 rounded-lg shadow-sm xl:col-start-2 xl:row-start-1 xl:row-span-2">
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

        {{-- ENVÍOS: historial del correo mensual a Marta --}}
        <div class="p-4 space-y-3 bg-white border border-gray-200 rounded-lg shadow-sm xl:col-start-1 xl:row-start-2">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-900">Correos enviados a Marta en {{ $anio }}</h2>
                <button type="button" wire:click="cambiarAnio(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
                <button type="button" wire:click="cambiarAnio(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
            </div>
            <p class="text-xs text-gray-600">Historial del correo mensual: <b style="color:#16a34a">✓ verde</b> = ese mes ya se envió. Púlsalo para ver, en una ventana, lo que se mandó (esta pantalla no cambia).</p>
            <div class="flex flex-wrap gap-2">
                @foreach (['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'] as $n => $mes)
                    @php $k = sprintf('%d-%02d', $anio, $n + 1); $e = $envios[$k] ?? collect(); @endphp
                    <div class="text-center" style="min-width:52px">
                        <div class="text-xs {{ $envioMes === $k ? 'font-bold' : 'text-gray-500' }}" @if ($envioMes === $k) style="color:#4f46e5;text-decoration:underline;text-underline-offset:3px" title="Mes que estás mirando" @endif>{{ $mes }}</div>
                        @if ($e->isNotEmpty())
                            <button type="button" wire:click="verMes('{{ $k }}')" title="{{ $e->count() }} envío(s): clic para ver lo que se mandó"
                                class="inline-flex items-center justify-center w-6 h-6 text-xs text-white bg-green-500 border border-green-600 rounded">✓</button>
                        @else
                            <span title="No se envió nada este mes" class="inline-flex items-center justify-center w-6 h-6 bg-white border border-gray-200 rounded" style="opacity:.6"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        </div>

        @if ($envioVentana)
            <div class="cm-modal" wire:click.self="cerrarEnvio">
                <div class="cm-caja" style="max-width:46rem">
                    <div class="flex items-start justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">📨 Correo de {{ $etiquetaEnvioMes }} a Marta</h2>
                        <button type="button" wire:click="cerrarEnvio" class="text-gray-500 hover:text-gray-800" title="Cerrar">✕</button>
                    </div>
                    @if (! $envioAbierto)
                        <p class="mt-3 text-sm text-gray-700">En {{ $etiquetaEnvioMes }} no se envió ningún correo.</p>
                    @else
                        @if ($enviosMes->count() > 1)
                            <div class="flex flex-wrap gap-2 mt-2 text-xs">Hubo {{ $enviosMes->count() }} correos ese mes:
                                @foreach ($enviosMes as $em)
                                    <button type="button" wire:click="elegirEnvio({{ $em->id }})" wire:key="env-{{ $em->id }}" class="px-2 py-0.5 border rounded {{ $em->id === $envioAbierto->id ? 'bg-indigo-100 border-indigo-400' : 'bg-white border-gray-300' }}">{{ \Carbon\Carbon::parse($em->enviado_at)->format('d/m/Y H:i') }}</button>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-2 text-sm font-semibold text-green-700">✅ Enviado el {{ \Carbon\Carbon::parse($envioAbierto->enviado_at)->format('d/m/Y H:i') }}{{ $envioAbierto->origen === 'manual' ? ' (a mano, fuera de Appmos)' : '' }}</div>
                        <dl class="mt-2 text-sm text-gray-800">
                            @if ($envioAbierto->para) <div><b>Para:</b> {{ $envioAbierto->para }}</div> @endif
                            @if ($envioAbierto->cc) <div><b>CC:</b> {{ $envioAbierto->cc }}</div> @endif
                            @if ($envioAbierto->asunto) <div><b>Asunto:</b> {{ $envioAbierto->asunto }}</div> @endif
                        </dl>
                        <pre class="p-2 mt-2 overflow-y-auto text-xs leading-snug text-gray-800 whitespace-pre-wrap border border-gray-200 rounded-md bg-gray-50" style="font-family:inherit;max-height:50vh">{{ $envioAbierto->texto ?: '(sin texto guardado)' }}</pre>
                    @endif
                    <div class="mt-3 text-right"><button type="button" wire:click="cerrarEnvio" class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">Cerrar</button></div>
                </div>
            </div>
        @endif
        @endif
        </div>
        </div>
    </div>
</div>
