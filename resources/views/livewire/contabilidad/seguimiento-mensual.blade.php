<div x-data="{ avisos: [] }">
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.seguimiento-mensual'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.seguimiento-mensual'])

    @php
        $colores = ['ok' => 'bg-green-500 border-green-600 text-white', 'na' => 'bg-gray-200 border-gray-300 text-gray-500', 'proc' => 'bg-yellow-300 border-yellow-500 text-yellow-900'];
        $simbolo = ['ok' => '✓', 'na' => '–', 'proc' => 'P'];
    @endphp

    <div class="p-3 space-y-2">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">Seguimiento mensual</h1>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="cambiarAnio(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
                <span class="px-2 font-semibold">{{ $anio }}</span>
                <button type="button" wire:click="cambiarAnio(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
            </div>
            @if ($puede)
                <button type="button" wire:click="$toggle('nuevo')" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">＋ Nuevo proceso</button>
            @endif
            <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar empresa…" class="py-1 text-sm border-gray-300 rounded-md">
        </div>
        <p class="text-xs text-gray-500">
            Un check por proceso y mes (clic: ✓ hecho → – no toca → vacío). En los procesos <b>por empresa</b> la celda resume cuántas van (hechas/total);
            con ▸ se despliegan las empresas que gestionas. 💻 = se ejecuta en <b>local</b> (desde un PC); 🌐💻 = se puede lanzar en la web o en local. Aquí se anota.
            Los meses siguen hacia la derecha.
        </p>

        @if ($nuevo)
            <div class="grid gap-2 p-3 bg-white border border-indigo-200 rounded-lg md:grid-cols-4">
                <input type="text" wire:model="nNombre" placeholder="Nombre del proceso" class="text-sm border-gray-300 rounded-md md:col-span-2">
                <select wire:model="nAmbito" class="text-sm border-gray-300 rounded-md">
                    <option value="general">General (un check por mes)</option>
                    <option value="cliente">Por empresa (un check por empresa y mes)</option>
                </select>
                <select wire:model="nEjecucion" class="text-sm border-gray-300 rounded-md">
                    <option value="web">Se hace en la web</option>
                    <option value="local">Se ejecuta en local (PC)</option>
                    <option value="ambos">Web o local</option>
                </select>
                <input type="text" wire:model="nDetalle" placeholder="Detalle (opcional)" class="text-sm border-gray-300 rounded-md md:col-span-3">
                <button type="button" wire:click="crear" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Añadir</button>
            </div>
        @endif

        <div class="overflow-x-auto bg-white border rounded-lg shadow">
            <table class="text-xs">
                <thead class="bg-gray-50">
                    <tr class="text-xs font-medium text-left text-gray-500">
                        <th class="px-2 py-1" style="min-width:230px;max-width:300px">Proceso</th>
                        @foreach ($this->meses as $k => $n)
                            <th class="px-0 py-1 text-center {{ $k === $mesActual ? 'bg-indigo-100 text-indigo-800' : '' }}" style="min-width:34px">{{ $n }}</th>
                        @endforeach
                        <th class="px-2 py-1"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach ($procesos as $p)
                    @php $lista = $p->ambito === 'cliente' ? $aplican[$p->id] : collect(); $abierto = in_array($p->id, $abiertos, true); @endphp
                    <tr wire:key="p-{{ $p->id }}" class="align-top hover:bg-gray-50">
                        <td class="px-2 py-1" x-data="{ info: false }">
                            <div class="flex items-start gap-x-1">
                                @if ($p->ambito === 'cliente')
                                    <button type="button" wire:click="alternar({{ $p->id }})" class="text-gray-500 shrink-0 hover:text-gray-800" title="Ver las empresas">{{ $abierto ? '▾' : '▸' }}</button>
                                @else
                                    <span class="shrink-0" style="width:12px"></span>
                                @endif
                                <button type="button" x-on:click="info = !info" class="text-indigo-500 shrink-0 hover:text-indigo-700" title="Detalle">ⓘ</button>
                                <span class="font-medium text-gray-900">{{ $p->nombre }}</span>
                                @if ($p->ejecucion !== 'web')
                                    <span title="{{ $p->ejecucion === 'ambos' ? 'Se puede lanzar en la web y en local' : 'Se ejecuta en local (desde un PC)' }}" class="shrink-0">{{ $p->ejecucion === 'ambos' ? '🌐💻' : '💻' }}</span>
                                @endif
                                @if ($p->enlace)
                                    @php $href = str_starts_with($p->enlace, 'http') ? $p->enlace
                                        : (\Illuminate\Support\Facades\Route::has($p->enlace) ? route($p->enlace) : null); @endphp
                                    @if ($href)
                                        <a href="{{ $href }}" target="_blank" rel="noopener" class="ml-1 text-xs text-indigo-600 underline shrink-0">abrir ↗</a>
                                    @endif
                                @endif
                            </div>
                            <div x-show="info" style="display:none;max-width:420px" x-on:click.outside="info = false" class="p-2 mt-1 text-xs text-gray-700 border border-indigo-200 rounded bg-indigo-50">
                                {{ $p->detalle ?: 'Sin detalle.' }}
                                @if ($p->ejecucion === 'local') <br><b>Se ejecuta en local:</b> abre el enlace desde AlexMiniPC o PortalExomen. @endif
                            </div>
                        </td>
                        @foreach ($this->meses as $k => $n)
                            <td class="px-1 py-1 text-center {{ $k === $mesActual ? 'bg-indigo-50' : '' }}">
                                @if ($p->ambito === 'cliente')
                                    @php
                                        $tot = $lista->count();
                                        $ok = $lista->filter(fn ($e) => ($estado[$p->id][$e->id][$k] ?? null) === 'ok')->count();
                                        $na = $lista->filter(fn ($e) => ($estado[$p->id][$e->id][$k] ?? null) === 'na')->count();
                                        $pr = $lista->filter(fn ($e) => ($estado[$p->id][$e->id][$k] ?? null) === 'proc')->count();
                                        $hechas = $ok + $na;
                                        $color = $tot && $hechas === $tot ? 'bg-green-500 border-green-600 text-white' : (($hechas + $pr) ? 'bg-yellow-300 border-yellow-500 text-yellow-900' : 'bg-white border-gray-300 text-gray-400');
                                    @endphp
                                    <button type="button" wire:click="alternar({{ $p->id }})" title="{{ $ok }} hechas · {{ $pr }} en curso · {{ $na }} no tocan · de {{ $tot }}"
                                        class="inline-flex items-center justify-center px-1 text-xs border rounded {{ $color }}" style="min-width:32px;height:18px">{{ $hechas }}/{{ $tot }}</button>
                                @else
                                    @php $m = $estado[$p->id][0][$k] ?? null; @endphp
                                    <button type="button" @if ($puede) wire:click="marcar({{ $p->id }}, '{{ $k }}')" @endif
                                        title="{{ ['ok' => 'Hecho', 'na' => 'No toca', 'proc' => 'En curso'][$m ?? ""] ?? 'Sin hacer' }}"
                                        class="inline-flex items-center justify-center w-5 h-5 text-xs border rounded {{ $colores[$m ?? ""] ?? 'bg-white border-gray-300' }}">{{ $simbolo[$m ?? ""] ?? '' }}</button>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-2 py-1 text-xs text-gray-400 whitespace-nowrap">
                            @if ($puede)
                                <button type="button" wire:click="mover({{ $p->id }}, -1)" class="hover:text-gray-800" title="Subir">▲</button>
                                <button type="button" wire:click="mover({{ $p->id }}, 1)" class="hover:text-gray-800" title="Bajar">▼</button>
                                <button type="button" wire:click="quitar({{ $p->id }})" wire:confirm="¿Quitar «{{ addslashes($p->nombre) }}» del seguimiento? (las marcas se conservan)" class="ml-1 hover:text-red-600" title="Quitar">✕</button>
                            @endif
                        </td>
                    </tr>
                    @if ($p->ambito === 'cliente' && $abierto)
                        @foreach ($lista->when($filtro !== '', fn ($c) => $c->filter(fn ($e) => stripos($e->entidad.' '.$e->alias, $filtro) !== false)) as $e)
                            <tr wire:key="p-{{ $p->id }}-e-{{ $e->id }}" class="bg-gray-50">
                                <td class="py-0.5 pr-2 text-xs text-gray-700" style="padding-left:2.5rem">{{ $e->entidad }}</td>
                                @foreach ($this->meses as $k => $n)
                                    @php $m = $estado[$p->id][$e->id][$k] ?? null; @endphp
                                    <td class="px-1 py-0.5 text-center {{ $k === $mesActual ? 'bg-indigo-50' : '' }}">
                                        <button type="button" @if ($puede) wire:click="marcar({{ $p->id }}, '{{ $k }}', {{ $e->id }})" @endif
                                            title="{{ ['ok' => $p->auto ? 'Recibido' : 'Hecho', 'na' => 'No toca', 'proc' => 'Solicitado'][$m ?? ""] ?? 'Sin hacer' }}"
                                            class="inline-flex items-center justify-center w-4 h-4 text-xs border rounded {{ $colores[$m ?? ""] ?? 'bg-white border-gray-300' }}">{{ $simbolo[$m ?? ""] ?? '' }}</button>
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        @endforeach
                        @if ($lista->isEmpty())
                            <tr><td colspan="{{ 2 + count($this->meses) }}" class="py-1 text-xs text-gray-500" style="padding-left:2.5rem">No hay empresas que gestiones para este proceso.</td></tr>
                        @endif
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($hayCliente)
            <p class="text-xs text-gray-500">Pet. Documentación Impuestos: <b>P</b> = solicitado (se pone al enviar el correo), <b>✓</b> = recibido. Es el mismo estado que en Proc.Mensuales.</p>
        @endif
    </div>
</div>
