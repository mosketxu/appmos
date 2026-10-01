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

            <div class="flex flex-wrap items-center gap-3 p-3 border border-yellow-200 rounded-lg bg-yellow-50">
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
            <div style="display:grid; grid-template-columns:minmax(0, 11fr) minmax(0, 9fr); gap:1rem; align-items:start">
                <div class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="overflow-y-auto" style="max-height:72vh">
                        {{-- Cada control se guarda al momento en la entidad; clic en la fila = ver su correo a la derecha --}}
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 text-xs text-gray-600 bg-gray-100">
                                <tr>
                                    <th class="px-2 py-2 text-center" title="Enviarle el correo de petición">Mail</th>
                                    <th class="px-2 py-2 text-center" title="🚀 Enviar ahora ({{ $periodo }}): solo se envía a estas">Enviar</th>
                                    <th class="px-2 py-2 text-left">Empresa ({{ $empresas->count() }})</th>
                                    @if ($puedeEditar) <th class="px-2 py-2 text-left">Responsable</th> @endif
                                    <th class="px-2 py-2 text-center">Activa</th>
                                    <th class="px-2 py-2 text-center" title="Clic: pasa al siguiente">Idioma</th>
                                    <th class="px-2 py-2 text-center" title="Ciclo de impuestos. Clic: pasa al siguiente">Ciclo</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($empresas as $e)
                                    @php $para = $Pm::destinatarios($e->emailadm); $cicloId = $ciclosEnt[$e->id] ?? null; $cicloOk = in_array((int) $cicloId, [1, 3], true); @endphp
                                    <tr wire:key="pet-{{ $e->id }}" wire:click="seleccionar({{ $e->id }})"
                                        class="cursor-pointer {{ $seleccionada === $e->id ? 'bg-indigo-100' : 'hover:bg-gray-50' }} {{ ($checks[$e->id] ?? false) ? '' : 'text-gray-400' }}">
                                        <td class="px-2 py-1 text-center" x-on:click.stop>
                                            <input type="checkbox" wire:model.live="checks.{{ $e->id }}" title="Enviarle el correo" class="text-indigo-600 border-gray-300 rounded">
                                        </td>
                                        <td class="px-2 py-1 text-center" x-on:click.stop>
                                            <input type="checkbox" wire:model.live="ahora.{{ $e->id }}" title="🚀 Enviar ahora ({{ $periodo }})"
                                                   @disabled(! ($checks[$e->id] ?? false)) class="border-gray-300 rounded text-yellow-600">
                                        </td>
                                        <td class="px-2 py-1">
                                            <span class="{{ $seleccionada === $e->id ? 'font-semibold text-gray-900' : '' }} {{ ($activas[$e->id] ?? false) ? '' : 'line-through' }}" title="{{ $e->entidad }}">{{ $e->entidad }}</span>
                                        </td>
                                        @if ($puedeEditar)
                                            <td class="px-2 py-1" x-on:click.stop>
                                                <select wire:model.live="sumaIds.{{ $e->id }}" title="Responsable Suma"
                                                        class="py-0.5 pl-2 pr-7 text-xs border-gray-300 rounded-md {{ ($sumaIds[$e->id] ?? '') === '' ? 'text-purple-700' : '' }}">
                                                    <option value="">— sin resp. —</option>
                                                    @foreach ($sumas as $s) <option value="{{ $s->id }}">{{ $s->nombre }}</option> @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td class="px-2 py-1 text-center" x-on:click.stop>
                                            <input type="checkbox" wire:model.live="activas.{{ $e->id }}" title="Estado de la entidad" class="text-green-600 border-gray-300 rounded">
                                        </td>
                                        <td class="px-2 py-1 text-center" x-on:click.stop>
                                            <button type="button" wire:click="siguienteIdioma({{ $e->id }})" title="Clic: pasa al siguiente idioma"
                                                    class="w-20 px-2 py-0.5 text-xs border border-gray-300 rounded-md hover:bg-gray-100">{{ $idiomasDisponibles[$idiomas[$e->id] ?? 'ES'] }}</button>
                                        </td>
                                        <td class="px-2 py-1 text-center" x-on:click.stop>
                                            <button type="button" wire:click="siguienteCiclo({{ $e->id }})" title="Clic: pasa al siguiente ciclo"
                                                    class="w-24 px-2 py-0.5 text-xs border rounded-md {{ $cicloOk ? 'border-gray-300 hover:bg-gray-100' : 'text-yellow-800 border-yellow-400 bg-yellow-50' }}">{{ $nombresCiclo[(int) $cicloId] ?? 'Sin definir' }}</button>
                                        </td>
                                        <td class="px-2 py-1 whitespace-nowrap">
                                            @if (isset($enviados[$e->id])) <span title="Enviado {{ \Carbon\Carbon::parse($enviados[$e->id])->format('d/m/Y H:i') }}">✅</span> @endif
                                            @if (! $para) <span class="text-xs text-red-600" title="Sin Email Adm">✉⚠</span> @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="p-6 text-sm italic text-center text-gray-400">
                                        @if ($total)
                                            Ninguna empresa que mostrar (marca «Ver también las no marcadas» o cambia la búsqueda).
                                        @else
                                            Sin empresas: el Admin las asigna en el panel de control (Responsable Suma o marcadas a mano).
                                        @endif
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm" style="position:sticky; top:1rem">
                    @if ($sel)
                        @php
                            $para = $Pm::destinatarios($sel->emailadm);
                            $pt = $Pm::textoPeriodo($periodo, $ciclosEnt[$sel->id] ?? null, $idiomas[$sel->id] ?? 'ES', $cicloOk);
                        @endphp
                        <div wire:key="correo-{{ $sel->id }}" class="p-4 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900">{{ $sel->entidad }}</h2>
                                @if (isset($enviados[$sel->id]))
                                    <span class="px-2 py-0.5 text-xs text-green-800 bg-green-100 rounded-full">✅ enviado {{ \Carbon\Carbon::parse($enviados[$sel->id])->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="px-2 py-0.5 text-gray-700 bg-gray-100 rounded-full">{{ $idiomasDisponibles[$idiomas[$sel->id] ?? 'ES'] }}</span>
                                <span class="px-2 py-0.5 rounded-full {{ $cicloOk ? 'text-indigo-800 bg-indigo-50' : 'text-yellow-800 bg-yellow-100' }}"
                                      title="{{ $cicloOk ? 'Ciclo de impuestos de la entidad' : 'Ciclo de impuestos sin definir (o anual/puntual) en la entidad: se pone el mes' }}">🗓 {{ $pt }}{{ $cicloOk ? '' : ' ⚠' }}</span>
                            </div>
                            {{-- Para = Email Adm de la entidad: se guarda en la entidad al salir del campo --}}
                            @php $malos = array_filter($para, fn ($c) => ! filter_var($c, FILTER_VALIDATE_EMAIL)); @endphp
                            <div>
                                <label class="flex items-center gap-2 text-xs">
                                    <span class="text-gray-500">Para:</span>
                                    <input type="text" wire:model.blur="paras.{{ $sel->id }}" maxlength="500" placeholder="⚠ sin Email Adm: escríbelo aquí (varios separados por ;)"
                                           title="Email Adm de la entidad (se guarda en Entidades al salir del campo)"
                                           class="flex-1 py-1 text-xs rounded-md {{ ! $para || $malos ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                </label>
                                @if ($malos) <p class="mt-1 text-xs text-red-600" style="margin-left:2.6rem">No parece un correo válido: {{ implode(', ', $malos) }}</p> @endif
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
                                    <label class="inline-flex items-center gap-1 px-2 py-1 ml-auto text-sm font-semibold rounded {{ ($ahora[$sel->id] ?? false) ? 'bg-yellow-200 text-yellow-900' : 'text-gray-500' }}">
                                        <input type="checkbox" wire:model.live="ahora.{{ $sel->id }}" class="border-gray-300 rounded text-yellow-600"> 🚀 Enviar ahora
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
