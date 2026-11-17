<div wire:poll.15s>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    <div class="flex flex-wrap items-start gap-4 p-4">
        {{-- ============ Columna izquierda: subir y lista ============ --}}
        <div style="flex:0 1 24rem; min-width:min(100%, 20rem)">
            <h1 class="mb-1 text-lg font-semibold text-gray-800">🔒 Confidencialidad</h1>
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
                    <x-subir-fichero wire:model="subidas" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.bmp,.tif,.tiff,.txt,.csv,.md,.eml,.json,.html,.htm,.xml,.log,.xlsx,.xlsm,.docx"
                        texto="Documento a anonimizar" title="PDF, imágenes, Excel, Word, texto, CSV o correo (.eml). Se pueden elegir o arrastrar varios; comparten las mismas etiquetas." />
                    @if (count(array_filter((array) $subidas)))
                        <span class="text-green-700">✓ {{ count(array_filter((array) $subidas)) }} documento(s) listo(s)</span>
                        <button type="button" wire:click="$set('subidas', [])" class="text-gray-500 underline">quitar</button>
                    @else
                        <span class="text-gray-500">Falta subir un documento</span>
                    @endif
                </div>
                @error('subidas') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                @error('subidas.*') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                <details class="mt-2 text-xs text-gray-600">
                    <summary class="cursor-pointer">Si ya sabes qué ocultar (opcional; se puede hacer después)</summary>
                    <label class="block mt-1 font-semibold">Personas (una por línea)</label>
                    <textarea wire:model.defer="nombres" rows="2" class="w-full text-sm border-gray-300 rounded"></textarea>
                    <label class="block mt-1 font-semibold">Empresas (una por línea)</label>
                    <textarea wire:model.defer="empresas" rows="2" class="w-full text-sm border-gray-300 rounded"></textarea>
                </details>
                <div class="flex flex-wrap items-center gap-2 mt-2 text-sm">
                    <select wire:model.defer="perfil" class="py-0.5 text-sm border-gray-300 rounded" title="Estricto también etiqueta los CIF de empresa">
                        <option value="estricto">Estricto</option>
                        <option value="contable">Contable (deja los CIF)</option>
                    </select>
                    <input type="text" wire:model.defer="titulo" maxlength="120" placeholder="Nombre (sin datos reales)" class="flex-1 min-w-0 py-0.5 text-sm border-gray-300 rounded">
                    <button type="button" wire:click="anonimizar" wire:loading.attr="disabled" wire:target="anonimizar,subidas"
                            class="px-3 py-1 text-white bg-indigo-600 rounded hover:bg-indigo-700">Anonimizar</button>
                    <span wire:loading wire:target="anonimizar" class="text-xs text-amber-700">⏳ Anonimizando… (los escaneados tardan)</span>
                </div>
            </div>

            <div class="p-3 bg-white border rounded shadow-sm">
                <div class="mb-1 text-sm font-semibold text-gray-700">Mis documentos</div>
                @forelse ($docs as $d)
                    <div class="flex items-center gap-2 py-1 border-t text-sm {{ $docSel === $d->id ? 'bg-indigo-50' : '' }}" wire:key="doc-{{ $d->id }}">
                        <button type="button" wire:click="seleccionar({{ $d->id }})" class="flex-1 text-left hover:underline">
                            #{{ $d->id }} · {{ $d->titulo ?: 'Documento' }}
                            <span class="text-xs text-gray-400">{{ $d->created_at->format('d/m H:i') }}</span>
                        </button>
                        <span class="text-xs {{ $d->estado === 'error' ? 'text-red-600' : ($d->estado === 'borrador' ? 'text-amber-600' : ($d->tarea_id ? 'text-indigo-600' : 'text-gray-500')) }}">
                            {{ $d->estado === 'error' ? 'error' : ($d->estado === 'borrador' ? 'revisando' : ($d->tarea_id ? '🤖 con Claude' : 'listo')) }}
                        </span>
                        <button type="button" wire:click="borrar({{ $d->id }})" wire:confirm="¿Borrar el documento y su mapa? Ya no se podrán recuperar los datos reales." class="text-xs text-red-600 hover:underline">borrar</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">Todavía no hay documentos.</div>
                @endforelse
            </div>
        </div>

        {{-- ============ Zona de trabajo: original | anonimizado ============ --}}
        <div style="flex:1 1 40rem; min-width:min(100%, 24rem)">
            @if ($sel)
                @if ($sel->estado === 'error')
                    <div class="p-3 text-sm text-red-800 bg-red-100 border border-red-300 rounded">No se ha enviado nada y no se ha guardado nada: {{ $sel->error }}</div>
                @else
                    <div class="flex flex-wrap items-start gap-3">
                        @if ($sel->estado === 'borrador')
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
                            <div class="mb-2 text-xs text-gray-500">
                                Etiquetado:
                                @foreach (collect($sel->informe ?? [])->except('avisos') as $k => $n) <span class="mr-2">{{ $k }} {{ $n }}</span> @endforeach
                                @foreach (($sel->informe['avisos'] ?? []) as $av) <div class="text-amber-700">⚠ {{ $av }}</div> @endforeach
                            </div>
                            @foreach ($imagenes as $n)
                                <img src="{{ route('confidencialidad.archivo', [$sel->id, $n]) }}" class="mb-2 border rounded" style="max-width:100%" loading="lazy">
                            @endforeach
                            @if ($texto)
                                <pre class="p-2 overflow-auto text-xs bg-gray-50 border rounded" style="max-height:75vh; white-space:pre-wrap">{{ $texto }}</pre>
                            @endif
                        </div>
                    </div>

                    @if ($sel->estado === 'borrador')
                        <div class="p-3 mt-3 bg-white border rounded shadow-sm">
                            <div class="mb-1 text-sm font-semibold text-gray-700">Qué ocultar</div>
                            <div class="flex flex-wrap gap-3">
                                <div style="flex:1 1 14rem">
                                    <label class="block text-xs font-semibold text-gray-600">Personas (una por línea)</label>
                                    <textarea wire:model.defer="edNombres" rows="4" class="w-full text-sm border-gray-300 rounded"></textarea>
                                </div>
                                <div style="flex:1 1 14rem">
                                    <label class="block text-xs font-semibold text-gray-600">Empresas (una por línea)</label>
                                    <textarea wire:model.defer="edEmpresas" rows="4" class="w-full text-sm border-gray-300 rounded"></textarea>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
                                <select wire:model.defer="edPerfil" class="py-0.5 text-sm border-gray-300 rounded">
                                    <option value="estricto">Estricto</option>
                                    <option value="contable">Contable (deja los CIF)</option>
                                </select>
                                <button type="button" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar" class="px-3 py-1 text-white bg-indigo-600 rounded hover:bg-indigo-700">Actualizar el anonimizado</button>
                                <span wire:loading wire:target="actualizar" class="text-xs text-amber-700">⏳ Rehaciendo…</span>
                                <button type="button" wire:click="darPorBueno" wire:confirm="¿Dar por bueno? Se borra el original del servidor y ya no podrás afinar qué ocultar."
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

                    @if ($sel->estado === 'listo')
                        <div class="p-3 mt-3 bg-white border rounded shadow-sm">
                            <div class="text-sm font-semibold text-gray-700">{{ $sel->tarea_id ? 'Otra indicación para Claude' : '¿Qué quieres que haga Claude con este documento?' }}</div>
                            <textarea wire:model.defer="instruccion" rows="3" class="w-full text-sm border-gray-300 rounded"
                                      placeholder="Se anonimiza igual que el documento (mismas etiquetas). Escribe con normalidad o usa las etiquetas."></textarea>
                            @error('instruccion') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            <div class="flex items-center gap-3 mt-1">
                                <button type="button" wire:click="pedir" wire:loading.attr="disabled" wire:target="pedir" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Pedir a Claude</button>
                                <span wire:loading wire:target="pedir" class="text-xs text-amber-700">⏳</span>
                                @if ($sel->tarea_id)
                                    <a href="{{ route('todo') }}?t={{ $sel->tarea_id }}" class="text-xs text-indigo-600 hover:underline">ver la tarea en el TO-DO →</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($sel->tarea_id)
                        <div class="p-3 mt-3 bg-white border rounded shadow-sm">
                            <div class="text-sm font-semibold text-gray-700">Respuestas de Claude <span class="font-normal text-gray-400">(con los datos reales; no se guardan así)</span></div>
                            @forelse ($respuestas as $r)
                                <div class="p-2 mt-1 text-sm bg-indigo-50 border border-indigo-200 rounded" style="white-space:pre-wrap"><div class="text-xs text-gray-400">{{ $r['fecha'] }}</div>{{ $r['texto'] }}</div>
                            @empty
                                <div class="mt-1 text-sm text-gray-400">Todavía no ha respondido (esta pantalla se actualiza sola).</div>
                            @endforelse
                        </div>
                    @endif
                @endif
            @else
                <div class="p-6 text-sm text-gray-400 bg-white border border-dashed rounded">Elige un documento o sube uno nuevo.</div>
            @endif
        </div>
    </div>
</div>
