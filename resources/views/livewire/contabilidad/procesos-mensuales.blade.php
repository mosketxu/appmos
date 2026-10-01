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
                <code>{mes}</code> y <code>{año}</code>, al enviar.
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
                <button type="button" wire:click="rellenarVacias" class="px-3 py-1 text-sm text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50"
                        title="Las marcadas que aún no tienen texto, con la plantilla de su idioma">Rellenar con la plantilla las marcadas sin texto</button>
            </div>

            <div class="space-y-2">
                @forelse ($empresas as $e)
                    <div wire:key="pet-{{ $e->id }}" class="p-3 bg-white border rounded-lg shadow-sm {{ ($checks[$e->id] ?? false) ? 'border-indigo-300' : 'border-gray-200' }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="inline-flex items-center gap-2 font-semibold text-gray-900">
                                <input type="checkbox" wire:model.live="checks.{{ $e->id }}" title="Enviarle el correo" class="text-indigo-600 border-gray-300 rounded">
                                {{ $e->entidad }}
                            </label>
                            @if ($e->alias && $e->alias !== $e->entidad) <span class="text-xs text-gray-400">({{ $e->alias }})</span> @endif
                            <span class="text-xs text-gray-500">✉ {{ $e->emailadm ?: ($e->emailgral ?: 'sin correo') }}</span>
                            @if (isset($enviados[$e->id]))
                                <span class="px-2 py-0.5 text-xs text-green-800 bg-green-100 rounded-full">✅ enviado {{ \Carbon\Carbon::parse($enviados[$e->id])->format('d/m/Y H:i') }}</span>
                            @endif
                            @if ($checks[$e->id] ?? false)
                                <label class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded {{ ($ahora[$e->id] ?? false) ? 'bg-amber-200 text-amber-900' : 'text-gray-500' }}">
                                    <input type="checkbox" wire:model.live="ahora.{{ $e->id }}" class="border-gray-300 rounded text-amber-600"> 🚀 Enviar ahora
                                </label>
                            @endif
                            <div class="flex items-center gap-2 ml-auto">
                                <select wire:model.live="idiomas.{{ $e->id }}" title="Idioma (se guarda en la entidad)" class="py-0.5 text-xs border-gray-300 rounded-md">
                                    @foreach ($idiomasDisponibles as $i => $nombre) <option value="{{ $i }}">{{ $i }}</option> @endforeach
                                </select>
                                <button type="button" wire:click="aplicarPlantilla({{ $e->id }})" class="px-2 py-0.5 text-xs text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">Usar plantilla</button>
                                <button type="button" wire:click="guardar({{ $e->id }})" class="px-2 py-0.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Guardar</button>
                            </div>
                        </div>
                        @if ($checks[$e->id] ?? false)
                            <textarea wire:model="textos.{{ $e->id }}" rows="6" class="w-full mt-2 text-xs border-gray-300 rounded-md" placeholder="Texto a enviar (pulsa «Usar plantilla» para partir de la de su idioma)"></textarea>
                        @endif
                    </div>
                @empty
                    <div class="p-6 text-sm italic text-center text-gray-400 bg-white border border-gray-200 border-dashed rounded-lg">
                        @if ($total)
                            Ninguna empresa que mostrar (marca «Ver también las no marcadas» o cambia la búsqueda).
                        @else
                            Sin empresas: el Admin las asigna en el panel de control (Responsable Suma o marcadas a mano).
                        @endif
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
