<div x-data="{ avisos: [] }"
     x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje }); setTimeout(() => avisos.shift(), 4000)">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" class="p-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg shadow-lg cursor-pointer" x-text="aviso.mensaje"></div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos-mensuales'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.procesos-mensuales'])

    <div class="p-4 space-y-4">
        <h1 class="text-2xl font-semibold text-gray-900">Procesos mensuales</h1>

        {{-- Un botón por proceso --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($procesos as $clave => $p)
                <button type="button" wire:click="$set('proceso', '{{ $clave }}')" title="{{ $p['descripcion'] }}"
                        class="px-3 py-1.5 text-sm font-medium border rounded-md {{ $proceso === $clave ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    {{ $p['icono'] }} {{ $p['titulo'] }}
                </button>
            @endforeach
        </div>

        @if ($proceso === 'petdocimpuestos')
            <p class="text-sm text-gray-600">
                Empresas que gestiona <b>{{ $usuario->name }}</b>: se le pide a {{ $marcadas }} de {{ $total }}. El check (enviar o no) y el idioma
                se guardan al momento en la entidad. El texto parte de la plantilla de su idioma y se puede personalizar. <code>{empresa}</code> se cambia al aplicar la plantilla;
                <code>{periodo}</code>, al enviar, por el mes o el trimestre según el ciclo de impuestos de la entidad.
            </p>

            {{-- Plantillas ES / EN, comunes a todos --}}
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <button type="button" wire:click="$toggle('verPlantillas')" class="flex items-center justify-between w-full px-4 py-2 text-sm font-semibold text-left text-gray-800">
                    <span>📝 Plantillas (español / inglés)</span><span>{{ $verPlantillas ? '▲' : '▼' }}</span>
                </button>
                @if ($verPlantillas)
                    <div class="grid gap-4 px-4 pb-4 md:grid-cols-2">
                        @foreach ($idiomasDisponibles as $i => $nombre)
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">{{ $nombre }} ({{ $i }})</label>
                                <textarea wire:model="plantillas.{{ $i }}" rows="10" class="w-full text-xs border-gray-300 rounded-md"></textarea>
                            </div>
                        @endforeach
                        <div class="md:col-span-2">
                            <button type="button" wire:click="guardarPlantillas" class="px-3 py-1.5 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Guardar plantillas</button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 p-3 border border-amber-200 rounded-lg bg-amber-50">
                <label class="text-sm font-semibold text-gray-800">Periodo
                    <input type="month" wire:model.live="periodo" class="py-1 ml-1 text-sm border-gray-300 rounded-md">
                </label>
                <span class="text-sm text-gray-700">🚀 Enviar ahora: <b>{{ $nAhora }}</b> (solo se envía a estas, aunque tengan el check)</span>
                <button type="button" wire:click="marcarTodasAhora(true)" class="px-2 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">Marcar todas</button>
                <button type="button" wire:click="marcarTodasAhora(false)" class="px-2 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">Desmarcar todas</button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar empresa..." class="py-1 text-sm border-gray-300 rounded-md">
                <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                    <input type="checkbox" wire:model.live="verNoMarcadas" class="border-gray-300 rounded"> Ver también las no marcadas
                </label>
                <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                    <input type="checkbox" wire:model.live="verBajas" class="border-gray-300 rounded"> Ver también las de baja
                </label>
                @if ($puedeEditar)
                    <label class="inline-flex items-center gap-1 text-sm text-gray-700" title="Empresas sin Responsable Suma: elige el responsable en el panel derecho">
                        <input type="checkbox" wire:model.live="verSinResponsable" class="border-gray-300 rounded"> Ver también las sin responsable
                    </label>
                @endif
                <button type="button" wire:click="rellenarVacias" class="px-3 py-1 text-sm text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50"
                        title="Las marcadas que aún no tienen texto, con la plantilla de su idioma">Rellenar con la plantilla las marcadas sin texto</button>
            </div>

            @php $Pm = \App\Http\Livewire\Contabilidad\ProcesosMensuales::class; $sel = $empresas->firstWhere('id', $seleccionada); @endphp
            {{-- Izquierda la lista de empresas; derecha el correo de la seleccionada (ninguna = sin texto) --}}
            <div style="display:grid; grid-template-columns:minmax(260px, 2fr) 3fr; gap:1rem; align-items:start">
                <div class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-600 bg-gray-100 border-b">
                        Empresas ({{ $empresas->count() }}) · ☑ enviar mail · 🚀 enviar ahora
                    </div>
                    <div class="overflow-y-auto divide-y divide-gray-100" style="max-height:70vh">
                        @forelse ($empresas as $e)
                            @php $para = $Pm::destinatarios($e->emailadm); $Pm::textoPeriodo($periodo, $e->cicloimpuesto_id, 'ES', $cicloOk); @endphp
                            <div wire:key="pet-{{ $e->id }}" wire:click="seleccionar({{ $e->id }})"
                                 class="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer {{ $seleccionada === $e->id ? 'bg-indigo-100' : 'hover:bg-gray-50' }} {{ ($checks[$e->id] ?? false) ? '' : 'text-gray-400' }}">
                                <input type="checkbox" wire:model.live="checks.{{ $e->id }}" x-on:click.stop title="Enviarle el correo" class="text-indigo-600 border-gray-300 rounded">
                                <input type="checkbox" wire:model.live="ahora.{{ $e->id }}" x-on:click.stop title="🚀 Enviar ahora ({{ $periodo }})"
                                       @disabled(! ($checks[$e->id] ?? false)) class="border-gray-300 rounded text-amber-600">
                                <span class="flex-1 truncate {{ $seleccionada === $e->id ? 'font-semibold text-gray-900' : '' }} {{ ($activas[$e->id] ?? false) ? '' : 'line-through' }}" title="{{ $e->entidad }}">{{ $e->entidad }}</span>
                                @if (! ($activas[$e->id] ?? false)) <span class="text-xs text-red-500">baja</span> @endif
                                @if (($sumaIds[$e->id] ?? '') === '') <span class="text-xs text-purple-600" title="Sin Responsable Suma">sin resp.</span> @endif
                                <span class="text-xs text-gray-400">{{ $idiomas[$e->id] ?? 'ES' }}</span>
                                @if (isset($enviados[$e->id])) <span title="Enviado {{ \Carbon\Carbon::parse($enviados[$e->id])->format('d/m/Y H:i') }}">✅</span> @endif
                                @if (! $para) <span class="text-xs text-red-600" title="Sin Email Adm">✉⚠</span> @endif
                                @if (! $cicloOk) <span class="text-xs text-amber-600" title="Ciclo de impuestos sin definir">🗓⚠</span> @endif
                            </div>
                        @empty
                            <div class="p-6 text-sm italic text-center text-gray-400">
                                @if ($total)
                                    Ninguna empresa que mostrar (marca «Ver también las no marcadas» o cambia la búsqueda).
                                @else
                                    Sin empresas: el Admin las asigna en el panel de control (Responsable Suma o marcadas a mano).
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm" style="position:sticky; top:1rem">
                    @if ($sel)
                        @php
                            $para = $Pm::destinatarios($sel->emailadm);
                            $pt = $Pm::textoPeriodo($periodo, $sel->cicloimpuesto_id, $idiomas[$sel->id] ?? 'ES', $cicloOk);
                        @endphp
                        <div wire:key="correo-{{ $sel->id }}" class="p-4 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900">{{ $sel->entidad }}</h2>
                                @if (isset($enviados[$sel->id]))
                                    <span class="px-2 py-0.5 text-xs text-green-800 bg-green-100 rounded-full">✅ enviado {{ \Carbon\Carbon::parse($enviados[$sel->id])->format('d/m/Y H:i') }}</span>
                                @endif
                                <div class="flex items-center gap-3 ml-auto">
                                    @if ($puedeEditar)
                                        <select wire:model.live="sumaIds.{{ $sel->id }}" title="Responsable Suma (se guarda al momento en la entidad)"
                                                class="py-0.5 text-xs border-gray-300 rounded-md {{ ($sumaIds[$sel->id] ?? '') === '' ? 'text-purple-700' : '' }}">
                                            <option value="">— sin responsable —</option>
                                            @foreach ($sumas as $s) <option value="{{ $s->id }}">{{ $s->nombre }}</option> @endforeach
                                        </select>
                                    @endif
                                    <label class="inline-flex items-center gap-1 text-xs font-semibold {{ ($activas[$sel->id] ?? false) ? 'text-green-700' : 'text-red-600' }}"
                                           title="Estado de la entidad (se guarda al momento). Solo salen las activas.">
                                        <input type="checkbox" wire:model.live="activas.{{ $sel->id }}" class="text-green-600 border-gray-300 rounded"> Activa
                                    </label>
                                    <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Enviarle el correo (se guarda al momento)">
                                        <input type="checkbox" wire:model.live="checks.{{ $sel->id }}" class="text-indigo-600 border-gray-300 rounded"> Enviar mail
                                    </label>
                                    <select wire:model.live="idiomas.{{ $sel->id }}" title="Idioma (se guarda en la entidad)" class="py-0.5 text-xs border-gray-300 rounded-md">
                                        @foreach ($idiomasDisponibles as $i => $nombre) <option value="{{ $i }}">{{ $nombre }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="text-gray-500">Para:</span>
                                @forelse ($para as $correo)
                                    <span class="px-2 py-0.5 rounded-full {{ filter_var($correo, FILTER_VALIDATE_EMAIL) ? 'text-gray-700 bg-gray-100' : 'text-red-800 bg-red-100' }}"
                                          title="{{ filter_var($correo, FILTER_VALIDATE_EMAIL) ? 'Email Adm de la entidad' : 'No parece un correo válido' }}">{{ $correo }}</span>
                                @empty
                                    <span class="px-2 py-0.5 text-red-800 bg-red-100 rounded-full" title="Se pone en la ficha de la entidad, campo Email Adm">⚠ sin Email Adm</span>
                                @endforelse
                                <span class="px-2 py-0.5 rounded-full {{ $cicloOk ? 'text-indigo-800 bg-indigo-50' : 'text-amber-800 bg-amber-100' }}"
                                      title="{{ $cicloOk ? 'Ciclo de impuestos de la entidad' : 'Ciclo de impuestos sin definir (o anual/puntual) en la entidad: se pone el mes' }}">🗓 {{ $pt }}{{ $cicloOk ? '' : ' ⚠' }}</span>
                            </div>
                            @if ($checks[$sel->id] ?? false)
                                <label class="flex items-center gap-2 text-xs">
                                    <span class="text-gray-500">CC:</span>
                                    <input type="text" wire:model="ccs.{{ $sel->id }}" placeholder="(nadie en copia)" title="Varios separados por ; — se guarda con «Guardar»"
                                           class="flex-1 py-1 text-xs border-gray-300 rounded-md">
                                </label>
                                <textarea wire:model="textos.{{ $sel->id }}" rows="16" class="w-full text-sm border-gray-300 rounded-md" placeholder="Texto a enviar (pulsa «Usar plantilla» para partir de la de su idioma)"></textarea>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" wire:click="aplicarPlantilla({{ $sel->id }})" class="px-3 py-1 text-sm text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">Usar plantilla</button>
                                    <button type="button" wire:click="guardar({{ $sel->id }})" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Guardar</button>
                                    <label class="inline-flex items-center gap-1 px-2 py-1 ml-auto text-sm font-semibold rounded {{ ($ahora[$sel->id] ?? false) ? 'bg-amber-200 text-amber-900' : 'text-gray-500' }}">
                                        <input type="checkbox" wire:model.live="ahora.{{ $sel->id }}" class="border-gray-300 rounded text-amber-600"> 🚀 Enviar ahora
                                    </label>
                                </div>
                            @else
                                <p class="p-4 text-sm italic text-center text-gray-400 border border-dashed rounded-md">No se le envía el correo (check desmarcado).</p>
                            @endif
                        </div>
                    @else
                        <div class="p-10 text-sm italic text-center text-gray-400">Selecciona una empresa de la lista para ver su correo.</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
