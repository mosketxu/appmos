<div wire:poll.{{ $hayProcesando ? '3s' : '15s' }}>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    <div class="flex flex-wrap items-start gap-4 p-4">
        {{-- ============ Columna izquierda: subir y lista ============ --}}
        <div style="flex:0 1 24rem; min-width:min(100%, 20rem)">
            <h1 class="mb-1 text-lg font-semibold text-gray-800">🔒 Confidencialidad</h1>
            @if ($almacenCerrado)
                <div class="p-2 mb-2 text-sm text-red-800 bg-red-100 border border-red-300 rounded">{{ \App\Support\Confidencialidad::MENSAJE_CERRADO }}</div>
            @endif
            <p class="mb-3 text-xs text-gray-500">
                Sube un documento: el servidor hace una <b>primera anonimización automática</b> (los datos personales pasan a ser <b>Persona1, Empresa1, Mail1…</b>; la relación se guarda solo aquí).
                Verás el <b>original</b> junto al <b>anonimizado</b> para ocultar lo que falte. Al darlo por bueno el original se borra y entonces le dices a Claude qué hacer.
                Es seudonimización de mejor esfuerzo: <b>revisa siempre el resultado</b>.
            </p>

            @if ($aviso)
                <div class="p-2 mb-2 text-sm text-green-800 bg-green-100 border border-green-300 rounded">{{ $aviso }}</div>
            @endif

            <div class="p-3 mb-4 bg-white border rounded shadow-sm">
                <div class="mb-2 text-sm font-semibold text-gray-700">Nuevo documento</div>
                <div class="flex flex-wrap items-center text-xs text-gray-600 gap-x-2 gap-y-1">
                    <x-subir-fichero wire:model="subidas" multiple :elegido="$sel ? ($sel->titulo ?: 'Documento #'.$sel->id) : null" quitar="$wire.seleccionar(0)"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.bmp,.tif,.tiff,.txt,.csv,.md,.eml,.json,.html,.htm,.xml,.log,.xlsx,.xlsm,.docx"
                        texto="Subir documento" title="PDF, imágenes, Excel, Word, texto, CSV o correo (.eml). Se anonimiza solo al subirlo. Se pueden elegir o arrastrar varios; comparten las mismas etiquetas." />
                    <span wire:loading wire:target="subidas,anonimizar" class="text-amber-700">⏳ Subiendo y anonimizando… (los escaneados tardan)</span>
                    <span wire:loading.remove wire:target="subidas,anonimizar" class="text-gray-500">{{ $sel ? 'Abierto: #'.$sel->id : 'Se anonimiza solo al subirlo' }}</span>
                </div>
                @foreach ($errors->get('subidas*') as $msgs) @foreach ((array) $msgs as $m) <div class="text-xs text-red-600">{{ $m }}</div> @endforeach @endforeach
                <details wire:ignore.self class="mt-2 text-xs text-gray-600">
                    <summary class="cursor-pointer">Si ya sabes qué ocultar, indícalo ANTES de subir (opcional; se puede hacer después)</summary>
                    <label class="block mt-1 font-semibold">Personas (una por línea)</label>
                    <textarea wire:model.defer="nombres" rows="2" class="w-full text-sm border-gray-300 rounded"></textarea>
                    <label class="block mt-1 font-semibold">Empresas (una por línea)</label>
                    <textarea wire:model.defer="empresas" rows="2" class="w-full text-sm border-gray-300 rounded"></textarea>
                </details>
                <div class="flex flex-wrap items-center gap-2 mt-2 text-sm">
                    <select wire:model.defer="perfil" class="py-0.5 text-sm border-gray-300 rounded" title="Estricto también etiqueta los CIF de empresa">
                        <option value="estricto">Estricto</option>
                        <option value="contable">Contable (deja los CIF)</option>
                        <option value="mercantil">Sociedad (CIF, nombre y domicilio públicos visibles)</option>
                    </select>
                    <input type="text" wire:model.defer="titulo" maxlength="120" placeholder="Nombre (por defecto, el del fichero)" class="flex-1 min-w-0 py-0.5 text-sm border-gray-300 rounded">
                </div>
            </div>

            <div class="p-3 mb-4 bg-white border rounded shadow-sm">
                <div class="mb-1 text-sm font-semibold text-gray-700">✉ Traer un correo</div>
                <p class="mb-1 text-xs text-gray-500">Busca por asunto o texto: se trae el cuerpo y los adjuntos (también las imágenes incrustadas) y se anonimizan igual. También puedes subir arriba un <b>.eml</b>; los <b>.msg</b> de Outlook aún no se leen (usa esta búsqueda).</p>
                <form wire:submit.prevent="buscarCorreo" class="flex gap-2">
                    <input type="text" wire:model.defer="consultaCorreo" placeholder="Asunto o texto del correo" class="flex-1 min-w-0 py-0.5 text-sm border-gray-300 rounded">
                    <button type="submit" wire:loading.attr="disabled" wire:target="buscarCorreo" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Buscar</button>
                </form>
                <span wire:loading wire:target="buscarCorreo,usarCorreo" class="text-xs text-amber-700">⏳ Un momento…</span>
                @foreach ($resultadosCorreo as $c)
                    <div class="flex items-center gap-2 py-1 mt-1 text-sm border-t" wire:key="mail-{{ md5($c['id']) }}">
                        <div class="flex-1 min-w-0">
                            <div class="truncate">{{ $c['asunto'] ?: '(sin asunto)' }}</div>
                            <div class="text-xs text-gray-400">{{ substr($c['fecha'], 0, 10) }} · {{ $c['de'] }}{{ $c['adjuntos'] ? ' · 📎' : '' }}</div>
                        </div>
                        <button type="button" wire:click="usarCorreo({{ json_encode($c['id']) }})" wire:loading.attr="disabled" wire:target="usarCorreo" class="px-2 py-0.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Traer y anonimizar</button>
                    </div>
                @endforeach
            </div>

            <div class="p-3 bg-white border rounded shadow-sm">
                <div class="mb-1 text-sm font-semibold text-gray-700">Mis documentos</div>
                @forelse ($docs as $d)
                    <div class="flex items-center gap-2 py-1 border-t text-sm {{ $docSel === $d->id ? 'bg-indigo-50' : '' }}" wire:key="doc-{{ $d->id }}">
                        <button type="button" wire:click="seleccionar({{ $d->id }})" class="flex-1 text-left hover:underline">
                            #{{ $d->id }} · {{ $d->titulo ?: 'Documento' }}
                            <span class="text-xs text-gray-400">{{ $d->created_at->format('d/m H:i') }}</span>
                        </button>
                        <span class="text-xs {{ $d->estado === 'error' ? 'text-red-600' : (in_array($d->estado, ['borrador', 'procesando']) ? 'text-amber-600' : ($d->tarea_id ? 'text-indigo-600' : 'text-gray-500')) }}">
                            {{ $d->estado === 'error' ? 'error' : ($d->estado === 'procesando' ? '⏳ procesando' : ($d->estado === 'borrador' ? 'revisando' : ($d->tarea_id ? '🤖 con Claude' : 'listo'))) }}
                        </span>
                        <button type="button" wire:click="borrar({{ $d->id }})" wire:confirm="¿Borrar el documento y su mapa? Ya no se podrán recuperar los datos reales." class="text-xs text-red-600 hover:underline">borrar</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">Todavía no hay documentos.</div>
                @endforelse
            </div>

            @if ($sel && \App\Support\Confidencialidad::originalConservado($sel))
                <div class="p-3 mt-4 text-sm bg-amber-50 border border-amber-200 rounded">
                    📎 Original conservado en el servidor ({{ \App\Support\Confidencialidad::CONSERVA_DIAS }} días):
                    <a href="{{ route('confidencialidad.original', [$sel->id, 1]) }}" target="_blank" class="text-indigo-700 underline">abrir</a>
                </div>
            @endif

            @if ($sel && (count($quien) || count($visibles)))
                <details wire:ignore.self class="p-3 mt-4 text-sm bg-white border rounded shadow-sm" open>
                    <summary class="font-semibold text-gray-700 cursor-pointer">🔑 Quién es quién ({{ count($quien) }})</summary>
                    <div class="mt-1 text-xs text-gray-400">Solo lo ves tú; se lee del mapa guardado en el servidor. Claude no lo ve.</div>
                    <table class="mt-1 w-full text-xs">
                        @foreach ($quien as $et => $real)
                            <tr class="border-t" wire:key="q-{{ $et }}"><td class="py-0.5 pr-2 font-mono text-indigo-700 whitespace-nowrap">{{ $et }}</td><td class="py-0.5 break-all">{{ $real }}</td>
                                <td class="py-0.5 pl-1 text-right whitespace-nowrap">
                                    @if ($sel->estado === 'borrador')
                                        <button type="button" wire:click='destapar(@js($real))' class="text-indigo-600 hover:underline"
                                                title="{{ $this->enListas($real) ? 'Quitarlo de las listas y rehacer: se ve otra vez' : 'Lo detectó el programa solo: se deja a la vista y se rehace' }}">destapar</button>
                                    @endif
                                </td></tr>
                        @endforeach
                    </table>
                    @if (count($visibles))
                        <div class="mt-2 text-xs font-semibold text-gray-600">A la vista a propósito</div>
                        @foreach ($visibles as $v)
                            <div class="flex items-center gap-2 text-xs" wire:key="vis-{{ md5($v) }}"><span class="break-all">{{ $v }}</span>
                                <button type="button" wire:click='taparOtraVez(@js($v))' class="text-indigo-600 hover:underline">tapar otra vez</button></div>
                        @endforeach
                    @endif
                </details>
            @endif

                    @if ($sel && $sel->estado === 'borrador')
                        <div class="p-3 mt-4 bg-white border rounded shadow-sm">
                            <div class="mb-1 text-sm font-semibold text-gray-700">Qué ocultar</div>
                            <div class="flex flex-col gap-2">
                                <div >
                                    <label class="block text-xs font-semibold text-gray-600">Personas (una por línea)</label>
                                    <textarea wire:model.defer="edNombres" rows="4" class="w-full text-sm border-gray-300 rounded"></textarea>
                                </div>
                                <div >
                                    <label class="block text-xs font-semibold text-gray-600">Empresas (una por línea)</label>
                                    <textarea wire:model.defer="edEmpresas" rows="4" class="w-full text-sm border-gray-300 rounded"></textarea>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
                                <select wire:model.defer="edPerfil" class="py-0.5 text-sm border-gray-300 rounded">
                                    <option value="estricto">Estricto</option>
                                    <option value="contable">Contable (deja los CIF)</option>
                        <option value="mercantil">Sociedad (CIF, nombre y domicilio públicos visibles)</option>
                                </select>
                                <label class="inline-flex items-center gap-1 text-xs text-gray-600" title="Por seguridad, si no se reconoce un extracto bancario se tapa el 22 % de arriba de la página 1. Si el documento no es un extracto, márcalo y se verá la cabecera (lo detectado dentro se sigue etiquetando).">
                                    <input type="checkbox" wire:model.defer="edSinCabecera" class="border-gray-300 rounded"> no tapar la parte alta de la página 1
                                </label>
                                <button type="button" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar" class="px-3 py-1 text-white bg-indigo-600 rounded hover:bg-indigo-700">Actualizar el anonimizado</button>
                                <span wire:loading wire:target="actualizar" class="text-xs text-amber-700">⏳ Rehaciendo…</span>
                                <label class="inline-flex items-center gap-1 text-xs text-gray-600" title="Por defecto el original se borra. Si lo conservas, se queda en el servidor {{ \App\Support\Confidencialidad::CONSERVA_DIAS }} días.">
                                    <input type="checkbox" wire:model.defer="conservarOriginal" class="border-gray-300 rounded"> conservar el original ({{ \App\Support\Confidencialidad::CONSERVA_DIAS }} días)
                                </label>
                                <button type="button" wire:click="darPorBueno" wire:confirm="¿Dar por bueno? Se borra el original del servidor (salvo que hayas marcado «conservar») y ya no podrás afinar qué ocultar."
                                        class="px-3 py-1 text-white bg-green-600 rounded hover:bg-green-700">✔ Dar por bueno (borra el original)</button>
                            </div>

                            <div class="mt-3 text-xs font-semibold text-gray-600">Posibles nombres que siguen a la vista <span class="font-normal text-gray-400">(sugerencias locales sobre el original; pulsa para añadir y luego «Actualizar»)</span></div>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @forelse ($candidatos as $c)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 border rounded" wire:key="cand-{{ md5($c['texto']) }}">
                                        {{ $c['texto'] }}@if ($c['veces'] > 1) <span class="text-gray-400">×{{ $c['veces'] }}</span>@endif
                                        <button type="button" wire:click='agregar(@js($c['texto']), "persona")' class="text-indigo-600 hover:underline" title="Añadir a personas">+persona</button>
                                        <button type="button" wire:click='agregar(@js($c['texto']), "empresa")' class="text-indigo-600 hover:underline" title="Añadir a empresas">+empresa</button>
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400">Ninguna sugerencia.</span>
                                @endforelse
                            </div>

                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <button type="button" wire:click="revisionClaude" wire:loading.attr="disabled" wire:target="revisionClaude" class="px-3 py-1 text-sm text-indigo-700 bg-white border border-indigo-300 rounded hover:bg-indigo-50"
                                        title="Claude mira SOLO el documento ya anonimizado y dice qué datos personales siguen visibles. No ve el original.">🤖 Que Claude revise lo anonimizado</button>
                                <span class="text-xs text-gray-400">Claude solo ve lo anonimizado, nunca el original.</span>
                            </div>
                            @if ($deClaude)
                                <div class="mt-2 text-xs font-semibold text-gray-600">Claude ve sin ocultar:</div>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    @foreach ($deClaude as $c)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-indigo-50 border border-indigo-200 rounded" wire:key="dc-{{ md5($c) }}">
                                            {{ $c }}
                                            <button type="button" wire:click='agregar(@js($c), "persona")' class="text-indigo-600 hover:underline">+persona</button>
                                            <button type="button" wire:click='agregar(@js($c), "empresa")' class="text-indigo-600 hover:underline">+empresa</button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

        </div>

        {{-- ============ Zona de trabajo: original | anonimizado ============ --}}
        <div style="flex:1 1 40rem; min-width:min(100%, 24rem)">
            @if ($sel)
                @if ($sel->estado === 'error')
                    <div class="p-3 text-sm text-red-800 bg-red-100 border border-red-300 rounded">No se ha enviado nada y no se ha guardado nada: {{ $sel->error }}</div>
                @else
                    <div class="flex flex-wrap items-start gap-3">
                        @if (in_array($sel->estado, ['procesando', 'borrador']))
                            <div class="p-2 bg-white border rounded shadow-sm" style="flex:1 1 26rem; min-width:min(100%, 20rem)">
                                <div class="mb-1 text-sm font-semibold text-gray-700">Original <span class="font-normal text-amber-700">(se borra al darlo por bueno o a las {{ \App\Support\Confidencialidad::HORAS_ORIGINAL }} h)</span></div>
                                @foreach ($originales as $o)
                                    @if (count($originales) > 1)<div class="mt-1 text-xs text-gray-500">{{ $o['n'] }} · {{ $o['nombre'] }}</div>@endif
                                    @if ($o['tipo'] === 'pdf')
                                        <iframe src="{{ route('confidencialidad.original', [$sel->id, $o['n']]) }}" class="w-full border rounded" style="height:75vh"></iframe>
                                    @elseif ($o['tipo'] === 'imagen')
                                        <img src="{{ route('confidencialidad.original', [$sel->id, $o['n']]) }}" class="border rounded" style="max-width:100%">
                                    @else
                                        <pre class="p-2 overflow-auto text-xs bg-gray-50 border rounded" style="max-height:75vh; white-space:pre-wrap">{{ $o['texto'] ?? '(sin vista previa)' }}</pre>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <div class="p-2 bg-white border rounded shadow-sm" style="flex:1 1 26rem; min-width:min(100%, 20rem)">
                            <div class="mb-1 text-sm font-semibold text-gray-700">Anonimizado #{{ $sel->id }}</div>
                            @if ($sel->estado === 'procesando')
                                <div class="p-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded">
                                    ⏳ Anonimizando en segundo plano…
                                    @if (! empty($progreso['de']) && $progreso['de'] > 1) documento {{ $progreso['doc'] ?? 1 }} de {{ $progreso['de'] }}@endif
                                    @if (! empty($progreso['total'])) · página {{ $progreso['pagina'] ?? 1 }} de {{ $progreso['total'] }}@endif
                                    <div class="mt-1 text-xs text-amber-700">Un escaneado tarda más o menos un minuto por página. Puedes seguir mirando el original; esta pantalla se actualiza sola.</div>
                                </div>
                            @endif
                            <div class="mb-2 text-xs text-gray-500">
                                Etiquetado:
                                @foreach (collect($sel->informe ?? [])->except('avisos') as $k => $n) <span class="mr-2">{{ $k }} {{ $n }}</span> @endforeach
                                @foreach (($sel->informe['avisos'] ?? []) as $av) <div class="text-amber-700">⚠ {{ $av }}</div> @endforeach
                            </div>
                            @if ($sel->estado === 'borrador' && $recuadroHecho)
                                <div class="p-2 mb-2 text-sm bg-indigo-50 border border-indigo-200 rounded">
                                    <div class="text-xs text-gray-600">Bajo el recuadro (página {{ ($recuadroPagina ?? 0) + 1 }}) se lee lo siguiente. Corrígelo si hace falta: se tapará <b>en todo el documento</b> y pasará a «Quién es quién».</div>
                                    <input type="text" wire:model.defer="recuadroTexto" class="w-full mt-1 text-sm border-gray-300 rounded" placeholder="Lo que hay que tapar (si no se ha leído nada, escríbelo)">
                                    <div class="mt-1 text-xs text-gray-600">Etiqueta con la que sustituirlo <span class="text-gray-400">(opcional; si la dejas vacía será Persona1/Empresa1…)</span></div>
                                    <input type="text" wire:model.defer="recuadroEtiqueta" maxlength="20" class="w-full mt-1 text-sm border-gray-300 rounded" placeholder="p. ej. NifFashion  →  saldrá NifFashion1 en todo el documento">
                                    <div class="flex items-center gap-2 mt-1">
                                        <button type="button" wire:click='taparRecuadro("persona")' wire:loading.attr="disabled" class="px-2 py-0.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Tapar como persona</button>
                                        <button type="button" wire:click='taparRecuadro("empresa")' wire:loading.attr="disabled" class="px-2 py-0.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Tapar como empresa</button>
                                        <button type="button" wire:click="cancelarRecuadro" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-100">Cancelar</button>
                                    </div>
                                </div>
                            @endif
                            @if (count($imagenes) && $sel->estado !== 'procesando')
                                <div x-data="{ z: 100, d: null, puede: {{ $sel->estado === 'borrador' ? 'true' : 'false' }},
                                        ini(e, n) { if (!this.puede) return; const r = e.currentTarget.getBoundingClientRect(); const x = e.clientX - r.left, y = e.clientY - r.top; this.d = { n: n, el: e.currentTarget, w: r.width, h: r.height, x0: x, y0: y, x1: x, y1: y }; },
                                        mov(e) { if (!this.d) return; const r = this.d.el.getBoundingClientRect(); this.d.x1 = Math.min(Math.max(e.clientX - r.left, 0), r.width); this.d.y1 = Math.min(Math.max(e.clientY - r.top, 0), r.height); },
                                        fin() { const d = this.d; this.d = null; if (!d || Math.abs(d.x1 - d.x0) < 8 || Math.abs(d.y1 - d.y0) < 6) return; $wire.recuadro(d.n, d.x0 / d.w, d.y0 / d.h, d.x1 / d.w, d.y1 / d.h); } }"
                                     @mousemove.window="mov($event)" @mouseup.window="fin()">
                                    <div class="sticky top-0 z-10 flex flex-wrap items-center gap-2 py-1 mb-1 text-xs bg-white border-b">
                                        <span class="text-gray-500">{{ count($imagenes) }} {{ count($imagenes) === 1 ? 'página' : 'páginas' }}</span>
                                        <button type="button" @click="z = Math.max(40, z - 20)" class="px-2 border rounded hover:bg-gray-100" title="Alejar">−</button>
                                        <span x-text="z + ' %'" class="w-10 text-center"></span>
                                        <button type="button" @click="z = Math.min(300, z + 20)" class="px-2 border rounded hover:bg-gray-100" title="Acercar">+</button>
                                        <button type="button" @click="z = 100" class="px-2 border rounded hover:bg-gray-100" title="Ajustar al ancho">Ajustar</button>
                                        @if ($sel->estado === 'borrador')<span class="text-indigo-600">✏ Arrastra un recuadro sobre un nombre que siga a la vista para tapar</span>@endif
                                    </div>
                                    <div style="max-height:75vh; overflow:auto">
                                        @foreach ($imagenes as $i => $n)
                                            <div class="mb-1 text-xs text-gray-500">Página {{ $i + 1 }} de {{ count($imagenes) }}</div>
                                            <div style="position:relative; margin-bottom:.75rem" :style="{ width: z + '%', cursor: puede ? 'crosshair' : 'auto', userSelect: 'none' }" @mousedown.prevent="ini($event, {{ $n }})">
                                                <img src="{{ route('confidencialidad.archivo', [$sel->id, $n, 'v' => optional($sel->updated_at)->timestamp]) }}" class="block w-full border rounded" style="max-width:none; display:block; width:100%" loading="lazy" draggable="false">
                                                <div x-show="d && d.n === {{ $n }}" x-cloak style="position:absolute; pointer-events:none; border:2px solid #4f46e5; background:rgba(79,70,229,.25); z-index:5"
                                                     :style="d ? { left: Math.min(d.x0, d.x1) + 'px', top: Math.min(d.y0, d.y1) + 'px', width: Math.abs(d.x1 - d.x0) + 'px', height: Math.abs(d.y1 - d.y0) + 'px' } : {}"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if ($texto)
                                <pre class="p-2 overflow-auto text-xs bg-gray-50 border rounded" style="max-height:75vh; white-space:pre-wrap">{{ $texto }}</pre>
                            @endif
                        </div>
                    </div>

                    @if ($sel->estado === 'listo')
                        <div class="p-3 mt-3 bg-white border rounded shadow-sm">
                            <div class="mb-1 text-sm font-semibold text-gray-700">💬 Conversación con Claude <span class="font-normal text-gray-400">(solo ve lo anonimizado; aquí se muestra con los datos reales y no se guarda así)</span></div>
                            @foreach ($conversacion as $r)
                                <div class="p-2 mt-1 text-sm rounded border {{ $r['quien'] === 'yo' ? 'bg-gray-50 border-gray-200 ml-8' : 'bg-indigo-50 border-indigo-200 mr-8' }}" style="white-space:pre-wrap"><div class="text-xs text-gray-400">{{ $r['quien'] === 'yo' ? 'Tú' : 'Claude' }} · {{ $r['fecha'] }}</div>{{ $r['texto'] }}</div>
                            @endforeach
                            @if ($sel->tarea_id && (count($conversacion) === 0 || end($conversacion)['quien'] === 'yo'))
                                <div class="mt-1 text-sm text-gray-400">⏳ Claude está en ello (esta pantalla se actualiza sola; puede tardar unos minutos).</div>
                            @endif
                            <textarea wire:model.defer="instruccion" rows="3" class="w-full mt-2 text-sm border-gray-300 rounded"
                                      placeholder="{{ $sel->tarea_id ? 'Escribe tu siguiente mensaje…' : '¿Qué quieres que haga Claude con este documento? Se anonimiza igual que el documento (mismas etiquetas).' }}"></textarea>
                            @error('instruccion') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            <div class="flex items-center gap-3 mt-1">
                                <button type="button" wire:click="pedir" wire:loading.attr="disabled" wire:target="pedir" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Enviar</button>
                                <span wire:loading wire:target="pedir" class="text-xs text-amber-700">⏳</span>
                            </div>
                        </div>
                    @elseif ($sel->tarea_id && $respuestas)
                        <div class="p-3 mt-3 bg-white border rounded shadow-sm">
                            <div class="text-sm font-semibold text-gray-700">Respuestas de Claude</div>
                            @foreach ($respuestas as $r)
                                <div class="p-2 mt-1 text-sm bg-indigo-50 border border-indigo-200 rounded" style="white-space:pre-wrap"><div class="text-xs text-gray-400">{{ $r['fecha'] }}</div>{{ $r['texto'] }}</div>
                            @endforeach
                        </div>
                    @endif
                @endif
            @else
                <div class="p-6 text-sm text-gray-400 bg-white border border-dashed rounded">Elige un documento o sube uno nuevo.</div>
            @endif
        </div>
    </div>
</div>
