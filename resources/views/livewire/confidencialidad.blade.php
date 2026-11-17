<div wire:poll.15s>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    <div class="flex flex-wrap items-start gap-4 p-4">
        <div style="flex:1 1 26rem; max-width:34rem">
            <h1 class="mb-1 text-lg font-semibold text-gray-800">🔒 Confidencialidad</h1>
            <p class="mb-3 text-xs text-gray-500">
                Sube un documento: el servidor lo anonimiza (los datos personales pasan a ser <b>Persona1, Empresa1, Mail1…</b>) y guarda aquí, solo aquí, la relación para recuperarlos.
                Luego le dices a Claude qué quieres hacer y trabaja con el documento ya anonimizado. El original no se guarda.
                <br>Es seudonimización de mejor esfuerzo: <b>mira siempre la vista previa</b>. Los nombres sin pista (sin «S.L.», «Bizum de»…) solo se ocultan si los escribes abajo.
            </p>

            @if ($aviso)
                <div class="p-2 mb-2 text-sm text-green-800 bg-green-100 border border-green-300 rounded">{{ $aviso }}</div>
            @endif

            <div class="p-3 mb-4 bg-white border rounded shadow-sm">
                <div class="mb-2 text-sm font-semibold text-gray-700">1 · Anonimizar un documento</div>
                {{-- Botón habitual de subir (x-subir-fichero): el estado va a la derecha --}}
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
                <p class="mt-1 text-xs text-gray-400">PDF, imágenes, Excel, Word, texto, CSV, correo (.eml). Varios a la vez comparten las mismas etiquetas.</p>
                <label class="block mt-2 text-xs font-semibold text-gray-600">Personas a ocultar (una por línea)</label>
                <textarea wire:model.defer="nombres" rows="2" class="w-full text-sm border-gray-300 rounded" placeholder="Maria Prueba Garcia"></textarea>
                <label class="block mt-1 text-xs font-semibold text-gray-600">Empresas a ocultar (una por línea)</label>
                <textarea wire:model.defer="empresas" rows="2" class="w-full text-sm border-gray-300 rounded" placeholder="Construcciones Ruiz"></textarea>
                <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
                    <label>Perfil
                        <select wire:model.defer="perfil" class="py-0.5 text-sm border-gray-300 rounded">
                            <option value="estricto">Estricto (también los CIF)</option>
                            <option value="contable">Contable (deja los CIF)</option>
                        </select>
                    </label>
                    <input type="text" wire:model.defer="titulo" maxlength="120" placeholder="Nombre para reconocerlo (sin datos reales)" class="flex-1 py-0.5 text-sm border-gray-300 rounded">
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
                        <span class="text-xs {{ $d->estado === 'error' ? 'text-red-600' : ($d->tarea_id ? 'text-indigo-600' : 'text-gray-500') }}">
                            {{ $d->estado === 'error' ? 'error' : ($d->tarea_id ? '🤖 con Claude' : 'listo') }}
                        </span>
                        <button type="button" wire:click="borrar({{ $d->id }})" wire:confirm="¿Borrar el documento y su mapa? Ya no se podrán recuperar los datos reales." class="text-xs text-red-600 hover:underline">borrar</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">Todavía no hay documentos.</div>
                @endforelse
            </div>
        </div>

        <div style="flex:1 1 30rem; min-width:min(100%, 24rem)">
            @if ($sel)
                <div class="p-3 bg-white border rounded shadow-sm">
                    <div class="mb-1 text-sm font-semibold text-gray-700">2 · Vista previa del documento anonimizado #{{ $sel->id }}</div>
                    @if ($sel->estado === 'error')
                        <div class="p-2 text-sm text-red-800 bg-red-100 border border-red-300 rounded">No se ha enviado nada: {{ $sel->error }}</div>
                    @else
                        <div class="mb-2 text-xs text-gray-500">
                            Etiquetado:
                            @foreach (collect($sel->informe ?? [])->except('avisos') as $k => $n) <span class="mr-2">{{ $k }} {{ $n }}</span> @endforeach
                            @foreach (($sel->informe['avisos'] ?? []) as $av) <div class="text-amber-700">⚠ {{ $av }}</div> @endforeach
                        </div>
                        @foreach ($imagenes as $n)
                            <img src="{{ route('confidencialidad.archivo', [$sel->id, $n]) }}" class="mb-2 border rounded" style="max-width:100%" loading="lazy">
                        @endforeach
                        @if ($texto)
                            <pre class="p-2 overflow-auto text-xs bg-gray-50 border rounded" style="max-height:24rem; white-space:pre-wrap">{{ $texto }}</pre>
                        @endif

                        <div class="mt-3 text-sm font-semibold text-gray-700">3 · {{ $sel->tarea_id ? 'Otra indicación para Claude' : '¿Qué quieres que haga Claude con este documento?' }}</div>
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

                        @if ($sel->tarea_id)
                            <div class="mt-4 text-sm font-semibold text-gray-700">Respuesta de Claude <span class="font-normal text-gray-400">(con los datos reales; no se guarda así)</span></div>
                            @forelse ($respuestas as $r)
                                <div class="p-2 mt-1 text-sm bg-indigo-50 border border-indigo-200 rounded" style="white-space:pre-wrap">
                                    <div class="text-xs text-gray-400">{{ $r['fecha'] }}</div>{{ $r['texto'] }}
                                </div>
                            @empty
                                <div class="mt-1 text-sm text-gray-400">Todavía no ha respondido (esta pantalla se actualiza sola).</div>
                            @endforelse
                        @endif
                    @endif
                </div>
            @else
                <div class="p-6 text-sm text-gray-400 bg-white border border-dashed rounded">Elige un documento o anonimiza uno nuevo.</div>
            @endif
        </div>
    </div>
</div>
