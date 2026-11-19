<div wire:poll.{{ ($doc && $doc->estado === 'procesando') || $esperaClaude ? '3s' : '30s' }}>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    <div class="p-3">
        <div class="flex items-baseline gap-3 mb-2">
            <h1 class="text-lg font-semibold text-gray-800">✉ Correo</h1>
            <span class="text-xs text-gray-500">Tu bandeja de entrada ({{ auth()->user()->email }}). El correo se ve aquí en claro; a Claude solo le llega <b>anonimizado</b> y tras tu visto bueno.</span>
        </div>
        @if ($cerrado)
            <div class="p-2 mb-2 text-sm text-red-800 bg-red-100 border border-red-300 rounded">El correo (Graph) no está configurado en este servidor.</div>
        @endif
        @if ($sinPermiso)
            <div class="p-3 mb-2 text-sm text-red-800 bg-red-50 border border-red-300 rounded" style="white-space:pre-line">{{ $sinPermiso }}</div>
        @endif
        @if ($aviso)
            <div class="p-2 mb-2 text-sm text-green-800 bg-green-100 border border-green-300 rounded">{{ $aviso }}</div>
        @endif

        <div id="correo-grid" wire:ignore.self style="display:grid; align-items:stretch; overflow-x:auto; grid-template-columns: var(--w1,208px) 8px var(--w2,360px) 8px minmax(18rem, 1fr) 8px var(--w4,400px)">
            {{-- ============ Carpetas ============ --}}
            <div class="bg-white border rounded shadow-sm" style="min-width:0; overflow-y:auto; height:calc(100vh - 11rem); min-height:22rem">
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 border-b">Carpetas</div>
                @foreach ($carpetas['plana'] as $f)
                    @php $activa = $carpeta === $f['id'] || ($carpeta === 'inbox' && $f['id'] === $carpetas['inbox']); @endphp
                    <div wire:key="f-{{ md5($f['id']) }}" class="flex items-center text-sm border-b border-gray-50 {{ $activa ? 'bg-indigo-100 font-semibold' : 'hover:bg-indigo-50' }}"
                         style="padding-left:{{ 0.25 + $f['nivel'] * 0.9 }}rem" data-carpeta="{{ $f['id'] }}">
                        @if ($f['hijos'] > 0)
                            <button type="button" wire:click="alternar('{{ $f['id'] }}')" class="w-4 text-xs text-gray-400">{{ $f['abierta'] ? '▾' : '▸' }}</button>
                        @else
                            <span class="w-4"></span>
                        @endif
                        <button type="button" wire:click="irACarpeta('{{ $f['id'] }}')" class="flex items-baseline justify-between flex-1 gap-1 py-1 pr-2 text-left" style="min-width:0">
                            <span class="truncate">{{ $f['nombre'] }}</span>
                            @if ($f['noLeidos'] > 0)<span class="text-xs font-semibold text-blue-700">{{ $f['noLeidos'] }}</span>@endif
                        </button>
                    </div>
                @endforeach
                <div class="px-3 py-2 text-xs text-gray-400">Arrastra un correo a una carpeta para moverlo.</div>
            </div>

            <div class="col-res" data-var="--w1" data-sign="1" style="cursor:col-resize; display:flex; justify-content:center" title="Arrastra para cambiar el ancho (doble clic: restablecer)"><div style="width:3px; height:100%; background:#e5e7eb; border-radius:2px"></div></div>
            {{-- ============ Izquierda: lista de correos ============ --}}
            <div class="bg-white border rounded shadow-sm" style="min-width:0; display:flex; flex-direction:column; height:calc(100vh - 11rem); min-height:22rem">
                <div class="flex gap-1 p-2 border-b">
                    <input type="text" wire:model.live.debounce.600ms="busqueda" placeholder="Buscar en el buzón…" class="flex-1 text-sm border-gray-300 rounded" style="min-width:0">
                    <button type="button" wire:click="refrescar" class="px-2 text-sm border rounded hover:bg-gray-50" title="Actualizar">↻</button>
                </div>
                <div style="overflow-y:auto; flex:1">
                    @forelse ($lista as $c)
                        @php $f = \Carbon\Carbon::parse($c['fecha'])->timezone('Europe/Madrid'); @endphp
                        <div draggable="true" role="button" tabindex="0" data-correo="{{ $c['id'] }}" wire:key="c-{{ md5($c['id']) }}" wire:click="seleccionar('{{ $c['id'] }}')"
                                class="block w-full px-3 py-2 text-left border-b cursor-pointer hover:bg-indigo-50 {{ $sel === $c['id'] ? 'bg-indigo-100' : '' }}" style="user-select:none; -webkit-user-drag:element">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-sm truncate {{ $c['leido'] ? 'text-gray-700' : 'font-bold text-blue-700' }}">{{ $c['nombre'] }}</span>
                                <span class="text-xs text-gray-400 whitespace-nowrap">{{ $f->isToday() ? $f->format('H:i') : ($f->isCurrentYear() ? $f->format('d/m H:i') : $f->format('d/m/Y')) }}</span>
                            </div>
                            <div class="text-xs truncate {{ $c['leido'] ? 'text-gray-600' : 'font-semibold text-blue-700' }}">@if ($c['adjuntos'])📎 @endif{{ $c['asunto'] ?: '(sin asunto)' }}</div>
                            <div class="text-xs text-gray-400 truncate">{{ $c['extracto'] }}</div>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-gray-400">{{ $busqueda ? 'No hay correos con ese texto.' : 'Sin correos.' }}</div>
                    @endforelse
                    @if (count($lista) >= $max && $max < 200)
                        <button type="button" wire:click="masCorreos" class="block w-full p-2 text-sm text-indigo-700 hover:bg-indigo-50">Cargar más…</button>
                    @endif
                </div>
            </div>

            <div class="col-res" data-var="--w2" data-sign="1" style="cursor:col-resize; display:flex; justify-content:center" title="Arrastra para cambiar el ancho (doble clic: restablecer)"><div style="width:3px; height:100%; background:#e5e7eb; border-radius:2px"></div></div>
            {{-- ============ Centro: el correo ============ --}}
            <div class="bg-white border rounded shadow-sm" style="min-width:0; display:flex; flex-direction:column; height:calc(100vh - 11rem); min-height:22rem">
                @if ($m)
                    <div class="p-3 border-b">
                        <div class="text-base font-semibold text-gray-800">{{ $m['asunto'] ?: '(sin asunto)' }}</div>
                        <div class="mt-1 text-sm text-gray-700"><b>{{ $m['nombre'] ?: $m['de'] }}</b> <span class="text-gray-500">&lt;{{ $m['de'] }}&gt;</span>
                            <span class="float-right text-xs text-gray-400">{{ \Carbon\Carbon::parse($m['fecha'])->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</span></div>
                        <div class="relative flex flex-wrap gap-1 mt-1">
                            <button type="button" wire:click="abrirMover" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">📁 Mover a…</button>
                            @if ($carpeta !== ($carpetas['eliminados'] ?? null))
                                <button type="button" wire:click="eliminar" class="px-2 py-0.5 text-xs text-red-700 border rounded hover:bg-red-50" title="Lo manda a Elementos eliminados (se puede recuperar de allí)">🗑 Eliminar</button>
                            @endif
                            @if ($mostrarMover)
                                <div class="absolute z-10 bg-white border rounded shadow-lg" style="top:1.6rem; left:0; width:20rem">
                                    <input type="text" wire:model.live.debounce.300ms="filtroCarpeta" placeholder="Buscar carpeta…" class="w-full text-sm border-0 border-b" autofocus>
                                    <div style="max-height:18rem; overflow-y:auto">
                                        @forelse ($destinos as $d)
                                            <button type="button" wire:key="d-{{ md5($d['id']) }}" wire:click="mover('{{ $d['id'] }}')" class="block w-full px-3 py-1 text-xs text-left truncate hover:bg-indigo-50">{{ $d['ruta'] }}</button>
                                        @empty
                                            <div class="p-2 text-xs text-gray-400">Sin resultados.</div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                            <button type="button" wire:click="$toggle('verImagenes')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50"
                                    title="Las imágenes remotas pueden avisar al remitente de que has abierto el correo">{{ $verImagenes ? '🚫 Ocultar imágenes' : '🖼 Mostrar imágenes' }}</button>
                        </div>
                        <div class="text-xs text-gray-500">Para: {{ $m['para'] ?: '—' }}@if ($m['cc']) · CC: {{ $m['cc'] }}@endif</div>
                        @if ($adjuntos)
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach ($adjuntos as $a)
                                    <span class="px-2 py-0.5 text-xs bg-gray-100 border rounded" title="{{ $a['tipo'] }}">📎 {{ $a['nombre'] }} <span class="text-gray-400">{{ $a['tamano'] >= 1048576 ? round($a['tamano'] / 1048576, 1).' MB' : max(1, round($a['tamano'] / 1024)).' KB' }}</span></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <iframe wire:key="msg-{{ md5($sel) }}" sandbox="" referrerpolicy="no-referrer" srcdoc="{{ $seguro }}" style="flex:1; width:100%; border:0"></iframe>
                @else
                    <div class="p-6 text-sm text-gray-400">Elige un correo de la lista.</div>
                @endif
            </div>

            <div class="col-res" data-var="--w4" data-sign="-1" style="cursor:col-resize; display:flex; justify-content:center" title="Arrastra para cambiar el ancho (doble clic: restablecer)"><div style="width:3px; height:100%; background:#e5e7eb; border-radius:2px"></div></div>
            {{-- ============ Derecha: qué hacer con él ============ --}}
            <div class="bg-white border rounded shadow-sm" style="min-width:0; overflow-y:auto; height:calc(100vh - 11rem); min-height:22rem">
                <div class="p-3">
                    <div class="mb-2 text-sm font-semibold text-gray-700">🤖 Qué hacer con este correo</div>
                    @if (! $m)
                        <div class="text-sm text-gray-400">Primero elige un correo.</div>
                    @elseif (! $doc || $doc->estado === 'error')
                        @if ($doc && $doc->estado === 'error')
                            <div class="p-2 mb-2 text-xs text-red-800 bg-red-100 border border-red-300 rounded">No se pudo anonimizar: {{ $doc->error ?: 'error desconocido' }}</div>
                        @endif
                        <label class="block text-xs text-gray-600">¿Qué quieres que haga Claude con este correo?</label>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <button type="button" wire:click="$set('instruccion', 'Resume este correo en pocas líneas y dime qué me piden y si hay algún plazo.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">Resumir y plazos</button>
                            <button type="button" wire:click="$set('instruccion', 'Redacta una respuesta a este correo, breve y profesional, en español.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">Redactar respuesta</button>
                            <button type="button" wire:click="$set('instruccion', 'Dime qué tengo que hacer a partir de este correo, paso a paso.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">¿Qué tengo que hacer?</button>
                        </div>
                        <div class="relative">
                            <textarea id="instr-nuevo" wire:model="instruccion" rows="4" class="w-full mt-1 text-sm border-gray-300 rounded" style="padding-right:2.2rem" placeholder="Escribe o dicta aquí lo que quieres hacer con este correo…"></textarea>
                            <button type="button" class="btn-dictar" data-dictar="instr-nuevo" title="Dictar con el micrófono (clic para empezar y otra vez para parar)" style="display:none; position:absolute; right:.4rem; top:.6rem; font-size:1.1rem; line-height:1">🎤</button>
                        </div>
                        <p class="mt-1 mb-2 text-xs text-gray-500">Anonimizar = los datos personales pasan a ser etiquetas (<b>Persona1, Empresa1, Mail1…</b>) en el correo, los adjuntos y tu texto.</p>
                        <label class="block text-xs text-gray-600">Perfil
                            <select wire:model="perfil" class="w-full mt-0.5 text-sm border-gray-300 rounded">
                                <option value="estricto">Estricto (extractos, personas y empresas)</option>
                                <option value="contable">Contable (deja los CIF de empresa)</option>
                                <option value="mercantil">Sociedad (CIF, nombre y domicilio visibles)</option>
                            </select>
                        </label>
                        <details class="mt-2 text-xs text-gray-600">
                            <summary class="cursor-pointer">Ocultar además estos nombres (opcional)</summary>
                            <textarea wire:model="nombres" rows="2" class="w-full mt-1 text-xs border-gray-300 rounded" placeholder="Personas, una por línea"></textarea>
                            <textarea wire:model="empresas" rows="2" class="w-full mt-1 text-xs border-gray-300 rounded" placeholder="Empresas, una por línea"></textarea>
                        </details>
                        @error('instruccion') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                        <button type="button" wire:click="preparar(true)" wire:loading.attr="disabled" wire:target="preparar" class="w-full px-3 py-2 mt-3 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">🔒 Anonimizar y enviar a Claude</button>
                        <p class="mt-1 text-xs text-gray-500">Se anonimiza en segundo plano y se envía solo, sin esperar a que lo revises. Ojo: en <b>tu texto</b> solo se ocultan los datos que se detectan igual que en el correo; un nombre que escribas y no esté en el correo puede ir a la vista (puedes ponerlo en «Ocultar además estos nombres»).</p>
                        <button type="button" wire:click="preparar(false)" wire:loading.attr="disabled" wire:target="preparar" class="block mx-auto mt-2 text-xs text-gray-500 underline hover:text-indigo-700">Solo anonimizar para revisarlo antes de enviar</button>
                        <span wire:loading wire:target="preparar" class="text-xs text-amber-700">⏳ Trayendo el correo…</span>
                        <div class="pt-3 mt-3 text-center border-t">
                            <button type="button" wire:click="sinAnonimizar" wire:loading.attr="disabled" wire:target="sinAnonimizar"
                                    wire:confirm="¿Estás seguro? Mira que el correo y sus adjuntos (PDF, imágenes y texto) saldrán EN CLARO hacia Anthropic, con todos los datos personales que contengan. Es decisión tuya y se hace."
                                    class="text-xs text-gray-500 underline hover:text-red-700">Enviar a Claude SIN anonimizar{{ trim($instruccion) !== '' ? ' (y enviar ya)' : '' }}…</button>
                            <span wire:loading wire:target="sinAnonimizar" class="text-xs text-amber-700">⏳</span>
                        </div>
                    @elseif ($doc->estado === 'procesando')
                        <div class="p-2 text-sm text-amber-800 bg-yellow-100 border border-yellow-300 rounded">⏳ Anonimizando…
                            @if (! empty($progreso['de']) && $progreso['de'] > 1) adjunto {{ $progreso['doc'] ?? 1 }} de {{ $progreso['de'] }}@endif
                            @if (! empty($progreso['total'])) · página {{ $progreso['pagina'] ?? 1 }} de {{ $progreso['total'] }}@endif
                            @if ($auto)<div class="mt-1 text-xs">Se enviará a Claude solo al terminar; puedes cerrar la pantalla.</div>@endif
                        </div>
                    @else
                        <div class="mb-2 text-xs text-gray-500">
                            @if ($claro)
                                <span class="px-1.5 py-0.5 font-semibold text-red-800 bg-red-100 border border-red-300 rounded">⚠ SIN ANONIMIZAR: Claude recibe el correo tal cual</span>
                            @else
                                Estado: <b>{{ $doc->estado === 'borrador' ? 'por revisar' : 'listo' }}</b> ·
                                @can('confidencialidad.ver')
                                    <a href="{{ route('confidencialidad', ['doc' => $doc->id]) }}" class="text-indigo-700 underline" target="_blank">abrir en Confidencialidad</a>
                                    @if ($doc->estado === 'borrador') (para ver el original al lado y ocultar más)@endif
                                @endcan
                            @endif
                        </div>
                        <details {{ $doc->estado === 'borrador' ? 'open' : '' }} class="mb-2">
                            <summary class="text-sm text-gray-700 cursor-pointer">{{ $claro ? 'Lo que recibe Claude (en claro)' : 'Lo que verá Claude (anonimizado)' }}</summary>
                            @if ($texto)
                                <pre class="p-2 mt-1 overflow-auto text-xs bg-gray-50 border rounded" style="max-height:30vh; white-space:pre-wrap">{{ $texto }}</pre>
                            @endif
                            @foreach ($imagenes as $n)
                                @if ($claro)
                                    <div class="text-xs text-gray-600">📎 {{ basename($doc->archivos[$n]) }}</div>
                                @else
                                <a href="{{ route('confidencialidad.archivo', ['id' => $doc->id, 'n' => $n]) }}" target="_blank" class="block text-xs text-indigo-700 underline">Adjunto escaneado {{ $n + 1 }} (anonimizado)</a>
                                @endif
                            @endforeach
                        </details>

                        @if ($doc->estado === 'borrador')
                            <p class="text-xs text-gray-500">Revisa que no quede nada sensible. Si falta algo, ábrelo en Confidencialidad para taparlo; si está bien, dalo por bueno.</p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button type="button" wire:click="darPorBueno" wire:confirm="¿Dar por bueno? Se borra el original del servidor y ya no podrás afinar qué ocultar."
                                        class="px-3 py-1 text-sm text-white bg-green-600 rounded hover:bg-green-700">✔ Dar por bueno (borra el original)</button>
                                <button type="button" wire:click="descartar" wire:confirm="¿Descartar y empezar de nuevo?" class="px-3 py-1 text-sm border rounded hover:bg-gray-50">Descartar</button>
                            </div>
                        @else
                            <div class="mb-1 text-sm font-semibold text-gray-700">💬 Conversación con Claude <span class="font-normal text-gray-400">{{ $claro ? '(recibe el correo sin anonimizar)' : '(solo ve lo anonimizado; aquí se ve con los datos reales)' }}</span></div>
                            @foreach ($conversacion as $r)
                                <div class="p-2 mt-1 text-sm rounded border {{ $r['quien'] === 'yo' ? 'bg-gray-50 border-gray-200 ml-6' : 'bg-indigo-50 border-indigo-200 mr-6' }}" style="white-space:pre-wrap"><div class="text-xs text-gray-400">{{ $r['quien'] === 'yo' ? 'Tú' : 'Claude' }} · {{ $r['fecha'] }}</div>{{ $r['texto'] }}</div>
                            @endforeach
                            @if ($esperaClaude)
                                <div class="mt-1 text-sm text-gray-400">⏳ Claude está en ello (puede tardar unos minutos; esto se actualiza solo).</div>
                            @endif
                            @if (! $doc->tarea_id)
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <button type="button" wire:click="$set('instruccion', 'Resume este correo en pocas líneas y dime qué me piden y si hay algún plazo.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">Resumir y plazos</button>
                                    <button type="button" wire:click="$set('instruccion', 'Redacta una respuesta a este correo, breve y profesional, en español.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">Redactar respuesta</button>
                                    <button type="button" wire:click="$set('instruccion', 'Dime qué tengo que hacer a partir de este correo, paso a paso.')" class="px-2 py-0.5 text-xs border rounded hover:bg-gray-50">¿Qué tengo que hacer?</button>
                                </div>
                            @endif
                            <div class="relative">
                            <textarea id="instr-chat" wire:model="instruccion" rows="4" class="w-full mt-2 text-sm border-gray-300 rounded" style="padding-right:2.2rem"
                                      placeholder="{{ $doc->tarea_id ? 'Escribe tu siguiente mensaje…' : '¿Qué quieres que haga Claude con este correo? Se anonimiza igual que el correo (mismas etiquetas).' }}"></textarea>
                            <button type="button" class="btn-dictar" data-dictar="instr-chat" title="Dictar con el micrófono (clic para empezar y otra vez para parar)" style="display:none; position:absolute; right:.4rem; top:.8rem; font-size:1.1rem; line-height:1">🎤</button>
                        </div>
                            @error('instruccion') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            <div class="flex items-center gap-3 mt-1">
                                <button type="button" wire:click="pedir" wire:loading.attr="disabled" wire:target="pedir" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Enviar</button>
                                <span wire:loading wire:target="pedir" class="text-xs text-amber-700">⏳</span>
                                <button type="button" wire:click="descartar" wire:confirm="¿Borrar el documento anonimizado y su mapa? La conversación se queda con etiquetas, sin poder recuperar los datos reales." class="ml-auto text-xs text-gray-500 underline">Borrar</button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

<script>
    (function () {
        const rejilla = document.getElementById('correo-grid');
        if (!rejilla) return;
        const guardarAnchos = () => { try { const o = {}; ['--w1', '--w2', '--w4'].forEach(v => { const x = rejilla.style.getPropertyValue(v); if (x) o[v] = x; }); localStorage.setItem('correoCols2', JSON.stringify(o)); } catch (e) {} };
        try { const g = JSON.parse(localStorage.getItem('correoCols2') || '{}'); Object.keys(g).forEach(v => rejilla.style.setProperty(v, g[v])); } catch (e) {}
        if (window.__correoPantalla) return;          // los escuchadores se ponen una sola vez; Livewire repinta la pantalla pero no los quita
        window.__correoPantalla = true;
        const grid = () => document.getElementById('correo-grid');
        document.addEventListener('mousedown', (ev) => {
            const t = ev.target.closest && ev.target.closest('#correo-grid .col-res'); if (!t) return;
            ev.preventDefault();
            const g = grid(), v = t.dataset.var, signo = parseInt(t.dataset.sign, 10), x0 = ev.clientX;
            const col = signo > 0 ? t.previousElementSibling : t.nextElementSibling;
            const w0 = col.getBoundingClientRect().width;
            const marcos = g.querySelectorAll('iframe'); marcos.forEach(f => f.style.pointerEvents = 'none'); document.body.style.userSelect = 'none';
            const mover = (e) => g.style.setProperty(v, Math.max(120, Math.min(900, w0 + signo * (e.clientX - x0))) + 'px');
            const soltar = () => { window.removeEventListener('mousemove', mover); window.removeEventListener('mouseup', soltar); marcos.forEach(f => f.style.pointerEvents = ''); document.body.style.userSelect = ''; guardarAnchos(); };
            window.addEventListener('mousemove', mover); window.addEventListener('mouseup', soltar);
        });
        document.addEventListener('dblclick', (ev) => { const t = ev.target.closest && ev.target.closest('#correo-grid .col-res'); if (!t) return; grid().style.removeProperty(t.dataset.var); guardarAnchos(); });
        document.addEventListener('dragstart', (ev) => { const f = ev.target.closest && ev.target.closest('#correo-grid [data-correo]'); if (f) { ev.dataTransfer.setData('text/plain', f.dataset.correo); ev.dataTransfer.effectAllowed = 'move'; } });
        document.addEventListener('dragover', (ev) => { const c = ev.target.closest && ev.target.closest('#correo-grid [data-carpeta]'); if (c) { ev.preventDefault(); c.classList.add('bg-green-100'); } });
        document.addEventListener('dragleave', (ev) => { const c = ev.target.closest && ev.target.closest('#correo-grid [data-carpeta]'); if (c) c.classList.remove('bg-green-100'); });
        document.addEventListener('drop', (ev) => {
            const c = ev.target.closest && ev.target.closest('#correo-grid [data-carpeta]'); if (!c) return;
            ev.preventDefault(); c.classList.remove('bg-green-100');
            const id = ev.dataTransfer.getData('text/plain'); if (!id) return;
            const raiz = c.closest('[wire\\:id]'); if (raiz && window.Livewire) window.Livewire.find(raiz.getAttribute('wire:id')).moverId(c.dataset.carpeta, id);
        });

        // Dictado por voz (reconocimiento del navegador; Chrome/Edge): un botón 🎤 junto a cada cuadro de texto
        const Reco = window.SpeechRecognition || window.webkitSpeechRecognition;
        let reco = null, botonActivo = null;
        const mostrarBotones = () => document.querySelectorAll('.btn-dictar').forEach(b => { b.style.display = Reco ? '' : 'none'; });
        const parar = () => { if (reco) { try { reco.stop(); } catch (e) {} } };
        mostrarBotones();
        new MutationObserver(mostrarBotones).observe(document.body, { childList: true, subtree: true });
        document.addEventListener('click', (ev) => {
            const b = ev.target.closest && ev.target.closest('.btn-dictar'); if (!b || !Reco) return;
            ev.preventDefault();
            if (botonActivo === b) { parar(); return; }
            parar();
            const caja = document.getElementById(b.dataset.dictar); if (!caja) return;
            reco = new Reco(); reco.lang = 'es-ES'; reco.continuous = true; reco.interimResults = false;
            botonActivo = b; b.textContent = '⏹'; b.style.color = '#dc2626'; b.title = 'Escuchando… clic para parar';
            reco.onresult = (e) => {
                let t = ''; for (let i = e.resultIndex; i < e.results.length; i++) { if (e.results[i].isFinal) t += e.results[i][0].transcript; }
                if (!t) return;
                caja.value = (caja.value && !/\s$/.test(caja.value) ? caja.value + ' ' : caja.value) + t.trim();
                caja.dispatchEvent(new Event('input', { bubbles: true }));
            };
            const fin = () => { if (botonActivo === b) { b.textContent = '🎤'; b.style.color = ''; b.title = 'Dictar con el micrófono (clic para empezar y otra vez para parar)'; botonActivo = null; } };
            reco.onend = fin; reco.onerror = fin;
            try { reco.start(); } catch (e) { fin(); }
        });
    })();
</script>
</div>
