<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    {{-- Avisos de fin de proceso: pedido explícito 2026-09-07 -- NO bloqueantes
         (nada de alert(), se puede seguir usando la pantalla mientras están
         puestos) y superpuestos (varios a la vez, apilados), cada uno con su
         propia (x) para cerrarlo a mano -- no desaparecen solos. --}}
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button
                    type="button"
                    class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                    x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"
                >&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4 space-y-6">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Procesos de Fashion IQ del mes:</span>
        <select wire:model.live="mes" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
        <select wire:model.live="anio" class="text-base font-normal border-gray-300 rounded-md shadow-sm" title="Año de trabajo">
            @foreach (\App\Support\Anio::lista() as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>
        <button type="button" wire:click="abrirHistorial" class="px-2 py-1 text-sm font-normal text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50" title="Lo que ha salido de cada proceso ejecutado, con filtro por fechas">📋 Resultados anteriores</button>
        {{-- 17-oct-2026: quitados los saltos «Rentas Variables · Cash in store · Pagos fin de mes»: los tres son filas del checklist --}}
    </h1>

    @include('livewire.contabilidad._pcs')

    @include('livewire._ficheros_base', ['eid' => (int) config('contabilidad.fiq_entidad'), 'clave' => 'fiq'])

    @if ($histAbierto)
        {{-- Historial de resultados de los procesos (25-oct-2026): filtro por fechas; «Ver» abre la salida de esa ejecución --}}
        <div class="fixed inset-0 z-40 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(17,24,39,.5)" wire:keydown.escape="$set('histAbierto', false)">
            <div class="w-full p-5 space-y-3 bg-white rounded-lg shadow-xl" style="max-width:60rem">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">📋 Resultados de los procesos</h2>
                    <x-button.secondary wire:click="$set('histAbierto', false)">Cerrar</x-button.secondary>
                </div>
                <div class="flex flex-wrap items-end gap-3 text-sm text-gray-700">
                    <label>Desde <input type="date" wire:model.live="histDesde" class="block text-sm border-gray-300 rounded-md"></label>
                    <label>Hasta <input type="date" wire:model.live="histHasta" class="block text-sm border-gray-300 rounded-md"></label>
                    <label>Proceso <input type="text" wire:model.live.debounce.400ms="histTexto" placeholder="p. ej. Adyen" class="block text-sm border-gray-300 rounded-md"></label>
                </div>
                @php $filasHist = $this->historialTareas(); @endphp
                <div class="overflow-auto border rounded" style="max-height:60vh">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 text-left text-gray-500 bg-gray-50"><tr><th class="px-2 py-1">Fecha</th><th class="px-2 py-1">Proceso</th><th class="px-2 py-1">PC</th><th class="px-2 py-1">Resultado</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($filasHist as $h)
                                <tr class="border-t">
                                    <td class="px-2 py-1 whitespace-nowrap">{{ $h['fecha'] }}</td>
                                    <td class="px-2 py-1">{{ $h['etiqueta'] }}</td>
                                    <td class="px-2 py-1 text-gray-500">{{ $h['pc'] }}</td>
                                    <td class="px-2 py-1 whitespace-nowrap">{{ $h['estado'] === 'ok' ? '✅ bien' : ($h['estado'] === 'cancelada' ? '🚫 cancelada' : '⚠️ con problema') }}</td>
                                    <td class="px-2 py-1 text-right"><button type="button" wire:click="verResultadoTarea({{ $h['id'] }})" class="text-indigo-600 hover:underline">Ver</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-2 py-3 text-gray-500">No hay ejecuciones en esas fechas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500">Salen las últimas 300 ejecuciones de Procesos FIQ; los procesos automáticos (Fiscal Report, UBS…) no están aquí.</p>
            </div>
        </div>
    @endif


    @if ($edenredAbierto && $edenredAnalisis)
        {{-- EDENRED: diferencias con el mes anterior y trabajadores nuevos, antes de generar los Recargas (27-oct-2026) --}}
        @php $ea = $edenredAnalisis; @endphp
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(17,24,39,.5)" wire:keydown.escape="cerrarEdenred">
            <div class="w-full p-5 space-y-4 bg-white rounded-lg shadow-xl" style="max-width:60rem; border-top:6px solid #2563eb">
                <h2 class="text-lg font-semibold text-gray-900">EDENRED {{ str_pad($ea['mes'], 2, '0', STR_PAD_LEFT) }}/{{ $ea['anio'] }}: antes de generar los Recargas</h2>
                <p class="text-xs text-gray-500">Imputación: {{ $ea['imputacion'] }}. Solo aparecen en los Recargas quienes tienen importe en la fila 95. Esto es interno: Edenred no ve las regularizaciones, solo el importe final.</p>

                @if (! empty($ea['comparacion']))
                    @php $cmp = $ea['comparacion']; @endphp
                    <p class="p-2 text-xs text-blue-900 bg-blue-50 border border-blue-200 rounded">
                        Se compara la <b>última versión del mes anterior</b>: {{ $cmp['ultima'] }} ({{ $cmp['version_ultima'] }})
                        con la <b>que se usó para preparar los Recargas</b>: {{ $cmp['usada'] }} ({{ $cmp['version_usada'] }}) · origen: {{ $cmp['fuente'] }}.
                    </p>
                @endif
                @foreach ($ea['avisos'] ?? [] as $av)
                    <p class="p-2 text-xs text-yellow-800 bg-yellow-50 border border-yellow-300 rounded">⚠️ {{ $av }}</p>
                @endforeach

                <div>
                    <h3 class="mb-1 text-sm font-semibold text-gray-800">Diferencias del mes anterior (última versión frente a la copia con la que se prepararon los Recargas)</h3>
                    @if (! empty($ea['diferencias']))
                        <table class="w-full text-xs border border-gray-200">
                            <thead class="bg-gray-50"><tr class="text-left">
                                <th class="p-1.5">Tienda</th><th class="p-1.5">Trabajador</th><th class="p-1.5 text-right">Última versión</th><th class="p-1.5 text-right">Copia de Carme</th>
                                <th class="p-1.5 text-right">Diferencia</th><th class="p-1.5">Este mes</th><th class="p-1.5">Nota (interna)</th></tr></thead>
                            <tbody>
                            @foreach ($ea['diferencias'] as $x)
                                @php $k = $x['key']; @endphp
                                <tr class="border-t border-gray-100 {{ empty($x['aplicable']) ? 'bg-yellow-50' : '' }}">
                                    <td class="p-1.5">{{ $x['tienda'] }}</td>
                                    <td class="p-1.5">{{ $x['nombre'] }}@if (empty($x['aplicable'])) <span class="text-yellow-700" title="No aparece en la fila 95 de este mes: el ajuste no se puede aplicar.">(no está este mes)</span>@endif</td>
                                    <td class="p-1.5 text-right">{{ number_format($x['ultima'], 2, ',', '.') }}</td>
                                    <td class="p-1.5 text-right">{{ number_format($x['carme'], 2, ',', '.') }}</td>
                                    <td class="p-1.5 font-semibold text-right {{ $x['dif'] < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($x['dif'], 2, ',', '.') }}</td>
                                    <td class="p-1.5 whitespace-nowrap">
                                        <select wire:model="edenredDec.{{ $k }}.accion" class="py-0.5 text-xs border-gray-300 rounded">
                                            <option value="aplicar">Aplicar {{ number_format($x['dif'], 2, ',', '.') }}</option>
                                            <option value="no">No aplicar</option>
                                            <option value="importe">Otro importe…</option>
                                        </select>
                                        @if (($edenredDec[$k]['accion'] ?? '') === 'importe')
                                            <input type="number" step="0.01" wire:model.lazy="edenredDec.{{ $k }}.importe" class="w-20 py-0.5 text-xs border-gray-300 rounded">
                                        @endif
                                    </td>
                                    <td class="p-1.5"><input type="text" wire:model.lazy="edenredDec.{{ $k }}.nota" class="w-44 py-0.5 text-xs border-gray-300 rounded" placeholder="motivo"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <p class="mt-1 text-xs text-gray-500">«Aplicar» suma o resta la diferencia al importe de este mes. Si no decides nada, se aplica.</p>
                    @else
                        <p class="text-xs text-green-700">✓ Sin diferencias con lo que se preparó el mes anterior.</p>
                    @endif
                </div>

                <div>
                    <h3 class="mb-1 text-sm font-semibold text-gray-800">Trabajadores nuevos (no están en ningún Recargas anterior)</h3>
                    @if (! empty($ea['nuevos']))
                        <ul class="ml-4 text-xs list-disc">
                            @foreach ($ea['nuevos'] as $n)
                                <li>{{ $n['nombre'] }} ({{ $n['tienda'] }}) · {{ number_format($n['importe'], 2, ',', '.') }} €</li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-xs text-gray-500">Al generar se buscan sus datos (DNI/NIE, correo y fecha de nacimiento) en el contrato, en el Outlook de Alex (Laboral). Los que no se encuentren quedan en amarillo y podrás pedírselos a Jordi.</p>
                    @else
                        <p class="text-xs text-green-700">✓ Ninguno.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-x-2">
                    <x-button.secondary wire:click="cerrarEdenred">Cancelar</x-button.secondary>
                    <x-button.primary wire:click="generarEdenred" wire:loading.attr="disabled">▶ Generar Recargas</x-button.primary>
                </div>
            </div>
        </div>
    @endif

    @if ($edenredUsuariosAbierto)
        {{-- EDENRED: usuarios por dar de alta en Edenred (datos del contrato) y petición de contratos a Jordi --}}
        @php $edu = $this->edenredUsuarios; @endphp
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(17,24,39,.5)" wire:keydown.escape="$set('edenredUsuariosAbierto', false)">
            <div class="w-full p-5 space-y-3 bg-white rounded-lg shadow-xl" style="max-width:60rem; border-top:6px solid #2563eb">
                <h2 class="text-lg font-semibold text-gray-900">👤 Usuarios de Edenred por dar de alta</h2>
                <p class="text-xs text-gray-500">Datos del contrato para crear el usuario en Edenred. Datos personales: no se guardan en git, solo en OneDrive/_ClaudeDesarrollo/Edenred. {{ ! empty($edu['cuando']) ? 'Última ejecución: '.$edu['cuando'].'.' : '' }}</p>
                @if (! empty($edu['por_alta']))
                    <table class="w-full text-xs border border-gray-200">
                        <thead class="bg-gray-50"><tr class="text-left"><th class="p-1.5">Tienda</th><th class="p-1.5">Nombre</th><th class="p-1.5">DNI / NIE / pasaporte</th><th class="p-1.5">Correo</th><th class="p-1.5">Nacimiento</th><th class="p-1.5"></th></tr></thead>
                        <tbody>
                        @foreach ($edu['por_alta'] as $u)
                            <tr class="border-t border-gray-100 {{ ($u['dni'] ?? 'PENDIENTE') === 'PENDIENTE' || ! empty($u['faltan']) ? 'bg-yellow-50' : '' }}">
                                <td class="p-1.5">{{ $u['tienda'] ?? '' }}</td>
                                <td class="p-1.5">{{ trim(($u['nombre'] ?? '').' '.($u['ap1'] ?? '').' '.($u['ap2'] ?? '')) }}
                                    @if (! empty($u['fuente']))<div class="text-gray-400" style="font-size:10px" title="Correo del que salen los datos: compruébalo antes de crear el usuario">de: {{ $u['fuente'] }}</div>@endif
                                    @if (! empty($u['faltan']))<div class="text-red-700" style="font-size:10px">Falta: {{ implode(', ', $u['faltan']) }}</div>@endif
                                </td>
                                <td class="p-1.5">{{ $u['dni'] ?? 'PENDIENTE' }}</td>
                                <td class="p-1.5">{{ $u['email'] ?? '—' }}</td>
                                <td class="p-1.5">{{ $u['nacimiento'] ?? '—' }}</td>
                                <td class="p-1.5 text-right"><button type="button" wire:click="altaEdenred('{{ $u['clave'] }}')" class="px-2 py-0.5 text-xs bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50" title="Ya está creado en Edenred">✓ Dado de alta</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-xs text-green-700">✓ No hay usuarios pendientes de alta.</p>
                @endif
                @if (! empty($edu['pendientes']))
                    <div class="p-2 text-xs border border-yellow-300 rounded bg-yellow-50">
                        <p class="font-semibold text-yellow-800">Sin contrato (faltan DNI/NIE, correo y fecha de nacimiento): {{ collect($edu['pendientes'])->map(fn ($p) => $p['nombre'].' ('.$p['tienda'].')')->implode(', ') }}</p>
                        <div class="flex gap-x-2 mt-1">
                            <button type="button" wire:click="correoJordi('borrador')" class="px-2 py-0.5 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50">✎ Borrador para Jordi</button>
                            @if ($edenredConfirmaJordi)
                                <button type="button" wire:click="correoJordi('enviar')" class="px-2 py-0.5 text-white bg-red-600 rounded shadow-sm hover:bg-red-700">⚠️ Confirmar envío REAL a Jordi (desde tu buzón)</button>
                                <button type="button" wire:click="$set('edenredConfirmaJordi', false)" class="px-2 py-0.5 text-gray-600 underline">cancelar</button>
                            @else
                                <button type="button" wire:click="$set('edenredConfirmaJordi', true)" class="px-2 py-0.5 text-white bg-blue-600 rounded shadow-sm hover:bg-blue-700">✉ Pedir contratos a Jordi…</button>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($edenredMsg !== '')<p class="text-xs text-gray-700">{{ $edenredMsg }}</p>@endif
                <div class="flex justify-end"><x-button.secondary wire:click="$set('edenredUsuariosAbierto', false)">Cerrar</x-button.secondary></div>
            </div>
        </div>
    @endif
    @if ($resultadoModal !== '')
        {{-- Resultado de «Batches Adyen» (25-oct-2026): lo que ha hecho el proceso, en una ventana --}}
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(17,24,39,.5)" wire:keydown.escape="cerrarResultadoModal">
            <div class="w-full p-5 space-y-3 bg-white rounded-lg shadow-xl" style="max-width:56rem; border-top:6px solid {{ $resultadoModalOk ? '#16a34a' : '#dc2626' }}">
                <h2 class="text-lg font-semibold text-gray-900">{{ $resultadoModalTitulo !== '' ? ($resultadoModalOk ? '✅ ' : '⚠️ ').$resultadoModalTitulo : ($resultadoModalOk ? '✅ Batches Adyen: terminado' : '⚠️ Batches Adyen: ha habido un problema') }}</h2>
                <pre class="p-3 overflow-auto text-xs whitespace-pre-wrap bg-gray-900 rounded text-green-400" style="max-height:65vh">{{ $resultadoModal }}</pre>
                <div class="flex justify-end">
                    <x-button.secondary wire:click="cerrarResultadoModal">Cerrar</x-button.secondary>
                </div>
            </div>
        </div>
    @endif

    @if ($staffAbierto && $staff)
        {{-- Anaplan · personal: propuesta nómina ↔ empleado de Anaplan (16-oct-2026). Amarillo = pareja nueva (no está en el histórico). --}}
        <div class="fixed inset-0 z-40 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(17,24,39,.5)" wire:keydown.escape="$set('staffAbierto', false)">
            <div class="w-full p-5 space-y-3 bg-white rounded-lg shadow-xl" style="max-width:64rem">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">Anaplan · personal {{ $staffMesAnaplan }}</h2>
                    <span class="text-xs text-gray-500">{{ $staffFichero }}</span>
                </div>
                <p class="text-sm text-gray-600">Bruto sin Bonus → <b>Salary Value MTD</b> · Bonus + Incentivo → <b>Bonus Value MTD</b>.
                    <span class="px-1 bg-red-50">Rojo</span> = sin nombre de Anaplan: créalo allí y escribe aquí el nombre tal cual está en Anaplan.
                    <span class="px-1 bg-yellow-100">Amarillo</span> = escrito a mano (se guarda al momento). Vacío = no se sube.</p>
                @foreach ($staffAvisos as $a)<p class="text-sm text-yellow-800">⚠️ {{ $a }}</p>@endforeach
                @foreach ($staff as $bi => $bloque)
                    <datalist id="staff-anaplan-{{ $bi }}">@foreach ($staffAnaplan[$bloque['tienda']] ?? [] as $n)<option value="{{ $n }}">@endforeach</datalist>
                    <div>
                        <div class="mt-2 mb-1 text-sm font-semibold text-gray-800">{{ $bloque['tienda'] }}</div>
                        <table class="w-full text-sm">
                            <tr class="text-xs text-left text-gray-500"><th class="pr-2">En la nómina</th><th class="pr-2 text-right">Bruto sin Bonus</th><th class="pr-2 text-right">Bonus + Incentivo</th><th class="pr-2">Empleado en Anaplan</th><th></th></tr>
                            @foreach ($bloque['filas'] as $fi => $f)
                                <tr wire:key="staff-{{ $bi }}-{{ $fi }}" class="{{ trim($f['anaplan']) === '' ? 'bg-red-50' : ($f['origen'] !== 'historico' ? 'bg-yellow-100' : '') }}">
                                    <td class="py-0.5 pr-2">{{ $f['excel'] }}</td>
                                    <td class="pr-2 text-right">{{ number_format($f['bruto'], 2, ',', '.') }}</td>
                                    <td class="pr-2 text-right">{{ number_format($f['bonus'], 2, ',', '.') }}</td>
                                    <td class="pr-2"><input type="text" list="staff-anaplan-{{ $bi }}" wire:model.blur="staff.{{ $bi }}.filas.{{ $fi }}.anaplan" class="w-full px-1 py-0.5 text-sm border-gray-300 rounded" placeholder="(no se sube)"></td>
                                    <td class="text-xs {{ trim($f['anaplan']) === '' ? 'text-red-700' : 'text-yellow-800' }}">{{ $f['origen'] === 'historico' ? '' : $f['nota'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endforeach
                @php
                    $nSubir = collect($staff)->sum(fn ($b) => collect($b['filas'])->filter(fn ($f) => trim($f['anaplan']) !== '')->count());
                    // 17-oct-2026: si queda alguno sin empleado de Anaplan, se avisa en la confirmación (Alex decide: avisar, no bloquear)
                    $sinPareja = collect($staff)->flatMap(fn ($b) => collect($b['filas'])->filter(fn ($f) => trim($f['anaplan']) === '')->map(fn ($f) => "· {$b['tienda']}: {$f['excel']}"))->values();
                    $confirmar = "¿Escribir en Anaplan ({$staffMesAnaplan}) el salario y el bonus de {$nSubir} empleados?";
                    if ($sinPareja->isNotEmpty()) {
                        $confirmar = "⚠️ ¿Estás seguro? {$sinPareja->count()} empleado(s) NO tienen pareja en Anaplan y NO se subirán:\n".$sinPareja->implode("\n")."\n\n".$confirmar;
                    }
                @endphp
                <div class="flex flex-wrap justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="$set('staffAbierto', false)" class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cerrar</button>
                    <button type="button" wire:click="guardarNombresStaff" wire:loading.attr="disabled" class="px-3 py-1.5 text-sm text-indigo-700 border border-indigo-300 rounded-md hover:bg-indigo-50" title="Los nombres se guardan al escribirlos (valen para los meses siguientes). Esto además vuelve a pasar «Laboral» para que el Excel de imputación de costes lleve los nombres de Anaplan (cierra antes el Excel si lo tienes abierto)">💾 Guardar y actualizar el Excel</button>
                    <button type="button" wire:click="subirStaff" wire:loading.attr="disabled"
                        wire:confirm="{{ $confirmar }}"
                        class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">⬆ Subir a Anaplan ({{ $nSubir }})</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Procesos del mes + checklist de cierre (pedido 2026-10-01): una fila por
         proceso de monthlyFIQ/checklist.json, ⓘ con el detalle, botón si se lanza
         desde aquí y un check por mes (se marca solo al terminar bien; a mano:
         clic = ✓ → «no toca» → vacío). Las marcas viven en OneDrive. --}}
    <div id="procesos-mes" class="bg-white border rounded-lg shadow"
         x-data="{ q: '', norm(t) { return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); },
                   ver(t) { return this.q.trim() === '' || this.norm(t).includes(this.norm(this.q).trim()); } }">
        <div class="flex flex-wrap items-center px-3 py-2 border-b border-gray-200 gap-x-4 gap-y-2 bg-gray-50">
            <span class="flex items-center gap-1">
                <input type="search" x-model="q" placeholder="🔍 Buscar proceso…" class="px-2 py-1 text-sm border-gray-300 rounded-md shadow-sm" style="width:14rem">
                <button type="button" x-show="q !== ''" x-on:click="q = ''" class="text-xs text-gray-500 hover:text-gray-800">✕ quitar filtro</button>
            </span>
        </div>
        @php
            $marcas = $this->checklistMarcas;
            $mesSel = sprintf('%d-%02d', $anio, $mes);
            $botones = $this->procesos;
            // Filas con «grupo» en checklist.json (17-oct-2026, Alex: «si son procesos diferentes, pon seguido varios botones»):
            // una sola fila con el nombre del grupo, un botón por proceso («boton») y un check pequeño por proceso en cada mes.
            $grupos = collect($this->checklist)->filter(fn ($p) => ! empty($p['grupo']))->groupBy('grupo')->map(fn ($g) => $g->values()->all())->all();
            $gruposPintados = [];
        @endphp
        <div class="overflow-x-auto">
        <table class="text-sm">
            <thead class="bg-gray-50">
                <tr class="text-xs font-medium text-left text-gray-500">
                    <th class="px-1 py-1"></th>
                    <th class="px-2 py-1">Proceso</th>
                    <th class="px-2 py-1">Acción</th>
                    @foreach ($this->checklistMeses as $k => $n)
                        <th class="px-1 py-1 text-center {{ $k === $mesSel ? 'bg-indigo-100 text-indigo-800' : '' }}" style="width:34px">{{ $n }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"
                x-data="{ arrastrando: null, sobre: null, debajo: false }"
                x-on:dragend.window="arrastrando = null; sobre = null">
                @forelse ($this->checklist as $p)
                    @php
                        $id = $p['id']; $auto = $p['auto'] ?? null;
                        $grupo = $p['grupo'] ?? null;
                        $miembros = $grupo ? $grupos[$grupo] : [$p];
                        $idsFila = array_column($miembros, 'id');
                    @endphp
                    @if ($grupo && in_array($grupo, $gruposPintados, true))
                        @continue
                    @endif
                    @php if ($grupo) { $gruposPintados[] = $grupo; } @endphp
                    <tr wire:key="chk-{{ $id }}" x-show="ver($el.textContent)" @if ($id === 'cash_in_store') id="cash-in-store" @endif class="align-top hover:bg-gray-50"
                        x-on:dragover.prevent="if (arrastrando && arrastrando !== '{{ $id }}') { sobre = '{{ $id }}'; debajo = $event.clientY > $el.getBoundingClientRect().top + $el.offsetHeight / 2 }"
                        x-on:drop.prevent="if (arrastrando && arrastrando !== '{{ $id }}') $wire.moverChecklist(arrastrando, '{{ $id }}', debajo); arrastrando = null; sobre = null"
                        x-bind:style="(sobre === '{{ $id }}' ? (debajo ? 'box-shadow: inset 0 -2px 0 #6366f1;' : 'box-shadow: inset 0 2px 0 #6366f1;') : '') + (arrastrando === '{{ $id }}' ? 'opacity:.4' : '')">
                        <td class="px-1 py-1 text-gray-400 cursor-move select-none hover:text-gray-700" title="Arrastra para cambiar el orden"
                            draggable="true"
                            x-on:dragstart="arrastrando = '{{ $id }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $id }}'); $event.dataTransfer.setDragImage($el.parentElement, 10, 10)">⠿</td>
                        <td class="px-2 py-1" x-data="{ info: false }" style="width:340px;max-width:340px">
                            <div class="flex items-start gap-x-1">
                                <button type="button" x-on:click="info = !info" class="text-indigo-500 shrink-0 hover:text-indigo-700" title="Detalle">ⓘ</button>
                                <span class="{{ $auto ? 'font-medium text-gray-900' : 'text-gray-700' }}">{{ $grupo ?? $p['nombre'] }}</span>
                            </div>
                            <div x-show="info" style="display:none;max-width:420px" x-on:click.outside="info = false" class="p-2 mt-1 text-xs text-gray-700 border border-indigo-200 rounded bg-indigo-50">
                                @if ($grupo)
                                    @foreach ($miembros as $mb)<p class="{{ $loop->first ? '' : 'mt-1' }}"><b>{{ $mb['boton'] ?? $mb['nombre'] }}</b> ({{ $mb['nombre'] }}): {{ $mb['detalle'] ?? '' }}</p>@endforeach
                                @else
                                    {{ $p['detalle'] ?? '' }}
                                @endif
                            </div>
                        </td>
                        <td class="px-2 py-1">
                            @if ($grupo === 'Alquiler')
                                <div class="flex flex-wrap items-center gap-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" x-on:click="$dispatch('abrir-panel', 'rentas-variables')"
                                        wire:click="ejecutarRvCalculos" wire:loading.attr="disabled" wire:target="ejecutarRvCalculos"
                                        wire:confirm="Provisión: Cálculos de las 4 tiendas en CalculosRentasVbles{{ $anio }}.xlsx, mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}. Escribe sobre los ficheros reales. ¿Seguro?"
                                        title="calculosRentasVariables.js: alquiler del mes (6210000 del SyS) y ventas fiscales de CTROL_ALL por tienda">
                                        <span wire:loading.remove wire:target="ejecutarRvCalculos">▶ Provisión</span><span wire:loading wire:target="ejecutarRvCalculos">⏳…</span>
                                    </button>
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" x-on:click="$dispatch('abrir-panel', 'rentas-variables')"
                                        wire:click="ejecutarRvDeclaracion" wire:loading.attr="disabled" wire:target="ejecutarRvDeclaracion"
                                        wire:confirm="Certificación: ventas netas del trimestre en el fichero del arrendador de {{ implode(' y ', $rvTiendas) ?: '(ninguna tienda marcada)' }}, mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}. Escribe sobre los ficheros reales. ¿Seguro?"
                                        title="rentasVariablesDeclaracion.js: Turnover de las tiendas marcadas en el panel (BCN, MAL)">
                                        <span wire:loading.remove wire:target="ejecutarRvDeclaracion">▶ Certificación</span><span wire:loading wire:target="ejecutarRvDeclaracion">⏳…</span>
                                    </button>
                                    <button type="button" x-data="{ ab: false }" x-on:panel-abierto.window="if ($event.detail.id === 'rentas-variables') ab = $event.detail.open" x-on:click="$dispatch('alternar-panel', 'rentas-variables')" x-text="ab ? '▴ Plegar' : 'Envío ↓'" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" title="Envío a los arrendadores (y correo de prueba), tiendas y resultados">Envío ↓</button>
                                </div>
                            @elseif ($grupo === 'ANAPLAN')
                                {{-- 27-oct-2026 (Alex): las 3 líneas de Anaplan en una sola fila: SyS por canal · subir a la web · personal.
                                     Cada paso conserva su propio check (los 3 cuadritos del control mensual, como Alquiler). --}}
                                @php
                                    $mmSel = str_pad($mes, 2, '0', STR_PAD_LEFT); $ent = $this->entradas['anaplan'] ?? null;
                                    $sinNom = $this->staffSinNombreMes;
                                    $confSubir = '¿Escribir en Anaplan el salario y el bonus del personal del mes '.$mmSel.'?';
                                    if ($sinNom) {
                                        $confSubir = '⚠️ ¿Estás seguro? '.count($sinNom)." empleado(s) NO tienen nombre de Anaplan y NO se subirán:\n"
                                            .collect($sinNom)->map(fn ($f) => "· {$f['tienda']}: {$f['excel']}")->implode("\n")."\n\n".$confSubir;
                                    }
                                    $btn = 'inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50 whitespace-nowrap';
                                    // verde = ya hecho este mes (la marca del check de ese paso)
                                    $verde = fn ($pid) => ($marcas[$mesSel][$pid]['estado'] ?? '') === 'ok' ? 'background:#f0fdf4;border-color:#22c55e;color:#166534' : '';
                                @endphp
                                <div class="flex flex-nowrap items-center text-xs text-gray-600 gap-x-1.5">
                                    <x-subir-fichero wire:model="sysFichero" accept=".xlsx" :fichero="$sysFichero"
                                        texto="Subir SyS {{ $mmSel }}"
                                        title="El balance de sumas y saldos de SAGE (con desglose por canal), con el nombre que tenga. Antes del proceso se comprueba que es de Fashion IQ y del mes {{ $mmSel }} y se deja como «SyS {{ $mmSel }}.xlsx» en la carpeta Anaplan (sustituye al que hubiera). Si no subes ninguno se usa el de los ficheros base. Puedes arrastrarlo aquí." />
                                    @if ($sysFichero)<span class="text-green-700">→ SyS {{ $mmSel }}.xlsx</span>@endif
                                    <button type="button" class="{{ $btn }}" style="{{ $verde('anaplan') }}" wire:click="ejecutar('anaplan')" wire:loading.attr="disabled" wire:target="ejecutar('anaplan')"
                                        wire:confirm="Esto escribe sobre los ficheros reales. ¿Seguro?"
                                        title="Anaplan (sysSplit.js → anaplanConsolida.js → anaplanDesviaciones.js): separa el SyS por canal (pestaña U y una por tienda), consolida el mes en SyS {{ $anio }}.xlsx y marca las cuentas que se salen de lo normal.">
                                        <span wire:loading.remove wire:target="ejecutar('anaplan')">▶ Split SyS Canal</span><span wire:loading wire:target="ejecutar('anaplan')">⏳…</span>
                                    </button>
                                    @foreach ($resultados['anaplan'] ?? [] as $r)
                                        <x-contabilidad.resultado-fichero :r="$r" compacto />
                                    @endforeach
                                    <span class="text-gray-300">│</span>
                                    <button type="button" class="{{ $btn }}" style="{{ $verde('anaplan_web') }}" wire:click="ejecutar('anaplan_web')" wire:loading.attr="disabled" wire:target="ejecutar('anaplan_web')"
                                        wire:confirm="Esto escribe sobre los ficheros reales. ¿Seguro?"
                                        title="Pega U_Anaplan y las tiendas en el modelo de Anaplan (LTB-Upload for LE / AC), lo comprueba fila a fila y revisa «All Check Reports». Abre Chrome solo; si pide login, entra en esa ventana y sigue solo. Mientras corre, no toques esa ventana.">
                                        <span wire:loading.remove wire:target="ejecutar('anaplan_web')">▶ Subir Web</span><span wire:loading wire:target="ejecutar('anaplan_web')">⏳…</span>
                                    </button>
                                    <button type="button" class="{{ $btn }}" wire:click="reiniciarAnaplan" wire:loading.attr="disabled" wire:target="reiniciarAnaplan"
                                        title="Reiniciar: para la subida a Anaplan (si se ha quedado colgada o has tocado la ventana de Chrome de Anaplan), cierra ese Chrome (solo el de Anaplan) y lo abre limpio. Luego entra si pide sesión y vuelve a pulsar «Subir Web»."
                                        wire:confirm="¿Parar la subida a Anaplan (si hay una en marcha) y reiniciar su ventana de Chrome?">
                                        <span wire:loading.remove wire:target="reiniciarAnaplan">↻</span><span wire:loading wire:target="reiniciarAnaplan">⏳</span>
                                    </button>
                                    @foreach ($resultados['anaplan_web'] ?? [] as $r)
                                        <x-contabilidad.resultado-fichero :r="$r" compacto />
                                    @endforeach
                                    <span class="text-gray-300">│</span>
                                    <button type="button" class="{{ $btn }}" style="{{ $verde('anaplan_staff') }}"
                                        wire:click="subirStaff" wire:loading.attr="disabled" wire:target="subirStaff" wire:confirm="{{ $confSubir }}"
                                        title="Se conecta a Anaplan (Chrome del PC de «Ejecutar en»), escribe Bruto sin Bonus en Salary Value MTD y Bonus + Incentivo en Bonus Value MTD, lo relee y marca el check">
                                        <span wire:loading.remove wire:target="subirStaff">▶ Split Empleados</span>
                                        <span wire:loading wire:target="subirStaff">⏳…</span>
                                    </button>
                                    <button type="button" class="{{ $btn }}" style="{{ $sinNom ? 'background:#dc2626;border-color:#dc2626;color:#fff' : '' }}"
                                        wire:click="abrirNombresStaff" wire:loading.attr="disabled" wire:target="abrirNombresStaff"
                                        title="Nombres de Anaplan: empleados de la nómina del mes (los deja «Laboral») con su nombre en Anaplan; escribe el de los que falten{{ $sinNom ? ' ('.count($sinNom).' faltan)' : '' }}">
                                        {{-- dos caras sonrientes --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="16" viewBox="0 0 26 16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"><circle cx="7" cy="8" r="6.3"/><circle cx="19" cy="8" r="6.3"/><path d="M4.6 10c1.3 1.7 3.5 1.7 4.8 0M16.6 10c1.3 1.7 3.5 1.7 4.8 0"/><circle cx="5.3" cy="6.3" r=".5" fill="currentColor"/><circle cx="8.7" cy="6.3" r=".5" fill="currentColor"/><circle cx="17.3" cy="6.3" r=".5" fill="currentColor"/><circle cx="20.7" cy="6.3" r=".5" fill="currentColor"/></svg>{{ $sinNom ? ' '.count($sinNom) : '' }}
                                    </button>
                                </div>
                                @if ($ent)
                                    @php $faltan = array_filter($ent, fn ($e) => ! $e['ok'] && empty($e['opcional'])); @endphp
                                    @if ($faltan)
                                        <div class="text-xs font-medium text-red-700 whitespace-nowrap" title="@foreach ($faltan as $e)✗ {{ $e['que'] }} ({{ $e['donde'] }}{{ ! empty($e['nota']) ? ' · '.$e['nota'] : '' }})&#10;@endforeach">✗ Falta{{ count($faltan) > 1 ? 'n' : '' }} {{ count($faltan) }} fichero{{ count($faltan) > 1 ? 's' : '' }}: {{ collect($faltan)->pluck('que')->implode(', ') }}</div>
                                    @endif
                                @endif
                                @include('livewire.contabilidad._staff-faltan')
                                @if (! empty($this->entradas['sin_canal']))
                                    @php $sc = $this->entradas['sin_canal']; @endphp
                                    <div style="max-width:24rem;margin-top:.3rem;padding:.4rem .6rem;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:.375rem;background:#fef2f2;font-size:.75rem;line-height:1.25;color:#7f1d1d">
                                        <div style="font-weight:700">🚨 {{ count($sc) }} cuenta(s) de gasto/ingreso SIN TIENDA en el SyS {{ $mmSel }} · {{ number_format(array_sum(array_column($sc, 'mes')), 2, ',', '.') }} €</div>
                                        @foreach ($sc as $h)
                                            <div>{{ $h['cuenta'] }} {{ \Illuminate\Support\Str::limit($h['descripcion'], 30) }}: <b>{{ number_format($h['mes'], 2, ',', '.') }} €</b></div>
                                        @endforeach
                                        <div style="margin-top:.2rem;color:#991b1b">👉 En Anaplan, LE y AC no cuadrarán por ese importe. Asigna tienda en SAGE, exporta de nuevo el SyS y vuelve a lanzar Anaplan.</div>
                                    </div>
                                @endif
                            @elseif ($id === 'ubs')
                                {{-- 27-oct-2026 (Alex): el UBS es automático (miniServ); aquí se ve cuándo fue lo último y se puede lanzar a mano con un extracto. --}}
                                @php $uu = $this->ubsUltimo; @endphp
                                <div class="flex flex-wrap items-center text-xs text-gray-600 gap-x-2 gap-y-1">
                                    <button type="button" class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50 whitespace-nowrap"
                                        wire:click="ejecutar('ubs')" wire:loading.attr="disabled" wire:target="ejecutar('ubs')"
                                        wire:confirm="Esto escribe en UBS BANCOS.xlsx y crea el plugin de bancos (y te manda el correo con el resumen). ¿Seguro?"
                                        title="Procesa el extracto elegido: lo guarda en Bancos UBS\MM, añade sus movimientos a UBS BANCOS.xlsx y crea el plugin de bancos (5720001) con lo que aún no se había contabilizado.">
                                        <span wire:loading.remove wire:target="ejecutar('ubs')">▶ Ejecutar</span>
                                        <span wire:loading wire:target="ejecutar('ubs')">⏳…</span>
                                    </button>
                                    <x-subir-fichero wire:model="ubsFichero" accept=".xlsx" :fichero="$ubsFichero" texto="Extracto UBS"
                                        title="El Excel «FIQ UBS dd.mm - dd.mm.xlsx» que manda Luca. Normalmente se procesa solo al llegar el correo; esto es para hacerlo a mano. Puedes arrastrarlo aquí." />
                                    @if ($uu)
                                        @php
                                            $uuCuando = \Illuminate\Support\Carbon::parse($uu['cuando'] ?? now())->format('d/m/Y H:i');
                                            $uuManual = ($uu['modo'] ?? '') === 'manual';
                                        @endphp
                                        <span class="whitespace-nowrap" title="Extracto «{{ $uu['archivo'] ?? '' }}»: {{ $uu['filas'] ?? 0 }} movimientos, {{ $uu['nuevas'] ?? 0 }} nuevos en UBS BANCOS.xlsx">
                                            Último: <b>{{ $uuCuando }}</b> ·
                                            <span class="px-1 rounded" style="{{ $uuManual ? 'background:#e0e7ff;color:#3730a3' : 'background:#f3f4f6;color:#374151' }}">{{ $uuManual ? 'manual' : 'automático' }}</span>
                                        </span>
                                        @if (! empty($uu['plugin']))
                                            <x-contabilidad.resultado-fichero :r="['ruta' => $uu['plugin'], 'tarea' => $uu['tarea'] ?? null, 'nombre' => $uu['plugin_nombre'] ?? '']" compacto />
                                        @else
                                            <span class="text-gray-400">(sin plugin: nada nuevo que contabilizar)</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">Aún sin registro de la última vez</span>
                                    @endif
                                </div>
                            @elseif ($auto === 'tabla' && isset($botones[$id]))
                                <div class="flex gap-x-2 {{ $id === 'laboral' ? 'items-center flex-nowrap whitespace-nowrap' : 'items-start' }}">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50 whitespace-nowrap"
                                        wire:click="ejecutar('{{ $id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="ejecutar('{{ $id }}')"
                                        wire:confirm="Esto escribe sobre los ficheros reales. ¿Seguro?"
                                    >
                                        <span wire:loading.remove wire:target="ejecutar('{{ $id }}')">▶ Ejecutar</span>
                                        <span wire:loading wire:target="ejecutar('{{ $id }}')">⏳…</span>
                                    </button>
                                    @if ($id === 'anaplan_web')
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50"
                                            title="Si la subida se ha quedado colgada o has tocado la ventana de Chrome de Anaplan: para la subida, cierra ese Chrome (solo el de Anaplan) y lo abre limpio. Luego entra si pide sesión y vuelve a pulsar ▶ Ejecutar."
                                            wire:click="reiniciarAnaplan" wire:loading.attr="disabled" wire:target="reiniciarAnaplan"
                                            wire:confirm="¿Parar la subida a Anaplan (si hay una en marcha) y reiniciar su ventana de Chrome?">
                                            <span wire:loading.remove wire:target="reiniciarAnaplan">🔄 Reiniciar</span>
                                            <span wire:loading wire:target="reiniciarAnaplan">⏳…</span>
                                        </button>
                                    @endif
                                    @if (in_array($id, ['anaplan', 'anaplan_web'], true) && ! empty($this->entradas['sin_canal']))
                                        {{-- 16-oct-2026 (Alex: «dato muy importante, aviso en pantalla fuera de la salida»): gastos/ingresos del SyS
                                             del mes sin tienda. Van a «Alta automática»: en Anaplan LE ≠ AC por ese importe. --}}
                                        @php $sc = $this->entradas['sin_canal']; @endphp
                                        <div style="max-width:24rem;padding:.4rem .6rem;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:.375rem;background:#fef2f2;font-size:.75rem;line-height:1.25;color:#7f1d1d">
                                            <div style="font-weight:700">🚨 {{ count($sc) }} cuenta(s) de gasto/ingreso SIN TIENDA en el SyS {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }} · {{ number_format(array_sum(array_column($sc, 'mes')), 2, ',', '.') }} €</div>
                                            @foreach ($sc as $h)
                                                <div>{{ $h['cuenta'] }} {{ \Illuminate\Support\Str::limit($h['descripcion'], 30) }}: <b>{{ number_format($h['mes'], 2, ',', '.') }} €</b></div>
                                            @endforeach
                                            <div style="margin-top:.2rem;color:#991b1b">👉 En Anaplan, LE y AC no cuadrarán por ese importe. Asigna tienda en SAGE, exporta de nuevo el SyS y vuelve a lanzar Anaplan.</div>
                                        </div>
                                    @endif
                                    @if ($id === 'laboral')
                                        {{-- 17-oct-2026: los nombres de Anaplan del personal salen de aquí (Alex: «este proceso va unido a Entrar nóminas») --}}
                                        @php $sinNomL = $this->staffSinNombreMes; @endphp
                                        @if (! empty($this->staffDatosMes['tiendas']))
                                            <button type="button" class="inline-flex items-center px-2 py-1 text-xs font-medium whitespace-nowrap border rounded shadow-sm disabled:opacity-50 {{ $sinNomL ? 'text-white bg-red-600 border-red-600 hover:bg-red-700' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50' }}"
                                                wire:click="abrirNombresStaff" wire:loading.attr="disabled" wire:target="abrirNombresStaff"
                                                title="{{ ! $sinNomL && ! empty($this->staffDatosMes['tiendas']) ? '✓ Los '.collect($this->staffDatosMes['tiendas'])->flatten(1)->count().' empleados del mes tienen nombre de Anaplan. ' : '' }}Nombres de Anaplan: empleados de la nómina del mes con su nombre en Anaplan; escribe el de los que falten. Luego «⬆ Subir a Anaplan» en ANAPLAN · personal (o en la propia ventana).">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="16" viewBox="0 0 26 16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"><circle cx="7" cy="8" r="6.3"/><circle cx="19" cy="8" r="6.3"/><path d="M4.6 10c1.3 1.7 3.5 1.7 4.8 0M16.6 10c1.3 1.7 3.5 1.7 4.8 0"/><circle cx="5.3" cy="6.3" r=".5" fill="currentColor"/><circle cx="8.7" cy="6.3" r=".5" fill="currentColor"/><circle cx="17.3" cy="6.3" r=".5" fill="currentColor"/><circle cx="20.7" cy="6.3" r=".5" fill="currentColor"/></svg>{{ $sinNomL ? ' '.count($sinNomL) : '' }}
                                            </button>
                                        @endif
                                        
                                    @endif
                                    @if ($id === 'adyen')
                                        <x-subir-fichero wire:model="adyenSettlement" accept=".xlsx" :fichero="$adyenSettlement" texto="Settlement"
                                            title="El settlement_report_aggregate_*.xlsx de Adyen. Si ya está en la carpeta Adyen del mes puedes ejecutar sin subirlo (te avisa antes)." />
                                    @endif
                                    @if ($id === 'adyen_batch')
                                        {{-- 25-oct-2026: los settlement detail de Adyen, tal como bajan (varios a la vez); el botón «Ejecutar» los procesa --}}
                                        <div class="flex items-center text-xs text-gray-600 gap-x-2 whitespace-nowrap">
                                            <x-subir-fichero wire:model="adyenBatchFicheros" multiple accept=".xlsx" texto="Settlement de Adyen"
                                                title="Los settlement_detail_report_batch_NNN.xlsx tal como bajan de Adyen (Reports › Settlement details), sin tocar. Se pueden elegir o arrastrar varios (uno o varios batches a la vez)." />
                                            @if (count(array_filter((array) $adyenBatchFicheros)))
                                                <span class="text-green-700">✓ {{ count(array_filter((array) $adyenBatchFicheros)) }} fichero(s) listos</span>
                                                <button type="button" wire:click="$set('adyenBatchFicheros', [])" class="text-gray-500 underline">quitar</button>
                                            @else
                                                <span class="text-gray-500">Falta subir ficheros</span>
                                            @endif
                                            <button type="button" wire:click="abrirHistorial('Adyen')" class="px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 whitespace-nowrap" title="Resultados anteriores: lo que salió de las ejecuciones previas de este proceso, con filtro por fechas">📋 Resultados</button>
                                        </div>
                                    @endif
                                    @if (in_array($id, ['monthly_sales', 'anaplan'], true))
                                        @php $mmSel = str_pad($mes, 2, '0', STR_PAD_LEFT); $ent = $this->entradas[$id] ?? null; @endphp
                                        {{-- 17-oct-2026 (Alex, para toda la web): el estado va a la derecha del botón si cabe; si no, debajo --}}
                                        <div class="flex flex-wrap items-center text-xs text-gray-600 gap-x-3 gap-y-1">
                                            <div class="flex flex-wrap items-center gap-x-2">
                                                <x-subir-fichero wire:model="sysFichero" accept=".xlsx" :fichero="$sysFichero"
                                                    texto="Sumas y saldos {{ $mmSel }}"
                                                    title="El balance de sumas y saldos de SAGE (con desglose por canal), con el nombre que tenga. Antes del proceso se comprueba que es de Fashion IQ y del mes {{ $mmSel }} y se deja como «SyS {{ $mmSel }}.xlsx» en la carpeta Anaplan (sustituye al que hubiera); si no lo parece, se para y te lo digo. Vale para Monthly sales y Anaplan: basta subirlo una vez. Puedes arrastrarlo aquí." />
                                                @if ($sysFichero)<span class="text-green-700">→ SyS {{ $mmSel }}.xlsx</span>@endif
                                            </div>
                                            @if ($ent)
                                                @php $faltan = array_filter($ent, fn ($e) => ! $e['ok'] && empty($e['opcional'])); $opc = array_filter($ent, fn ($e) => ! $e['ok'] && ! empty($e['opcional'])); @endphp
                                                @php $sc = $this->entradas['sys_central'] ?? null; @endphp
                                                <div class="leading-tight" title="{{ $this->entradas['origen'] ?? '' }}{{ $sc ? "\nSyS {$mmSel} de los ficheros base: «{$sc['nombre']}», {$sc['fecha']}".($sc['origen'] ? " · {$sc['origen']}" : '') : '' }}">
                                                    @if ($faltan)
                                                        <span class="font-medium text-red-700">Falta para el mes {{ $mmSel }}:</span>
                                                        @foreach ($faltan as $e)
                                                            <div class="text-red-700">✗ {{ $e['que'] }} <span class="text-gray-500">({{ $e['donde'] }}{{ ! empty($e['nota']) ? ' · '.$e['nota'] : '' }})</span></div>
                                                        @endforeach
                                                    @endif
                                                    @foreach ($opc as $e)
                                                        <div class="text-yellow-700">· {{ $e['que'] }} <span class="text-gray-500">({{ $e['donde'] }}{{ ! empty($e['nota']) ? ' · '.$e['nota'] : '' }})</span></div>
                                                    @endforeach
                                                    @if ($sc && $faltan)
                                                        <div class="{{ $sc['nuevo'] ? 'text-indigo-700' : 'text-gray-500' }}">SyS: «{{ $sc['nombre'] }}» {{ \Illuminate\Support\Str::before($sc['fecha'], ' ') }}{{ $sc['nuevo'] ? ' (nuevo: se deja en la carpeta al ejecutar)' : '' }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400">Sin datos de qué ficheros hay todavía (se piden solos al PC al abrir la pantalla: vuelve a mirar en un momento).</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($id === 'laboral')
                                        @php $mmL = str_pad($mes, 2, '0', STR_PAD_LEFT); @endphp
                                        <x-subir-fichero wire:model="laboralFichero" accept=".xlsx,.xls" :fichero="$laboralFichero" texto="Imputación de costes {{ $mmL }}"
                                            title="Si no eliges ninguno, se usa «imputacion costes {{ $mmL }}{{ $anio }}…» de versión más alta en la carpeta Laboral {{ $anio }} del mes." />
                                    @endif
                                    @if ($id === 'edenred')
                                        {{-- 27-oct-2026: Recargas de Edenred. Se sube la Imputación (última versión) y «Ejecutar» analiza; la decisión sobre cada diferencia se toma en una ventana. --}}
                                        @php $edu = $this->edenredUsuarios; $nAlta = count($edu['por_alta'] ?? []); @endphp
                                        <div class="flex flex-wrap items-center text-xs text-gray-600 gap-x-3 gap-y-1">
                                            <x-subir-fichero wire:model="edenredFichero" accept=".xlsx" :fichero="$edenredFichero" texto="Imputación de costes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}"
                                                title="La última versión de «IMPUTACION COSTES MMAAAA vN.0.xlsx» del mes (la que llega de Jordi). Se compara con la que se usó el mes anterior." />
                                            @if ($edenredFichero)
                                                <span class="text-green-700">✓ {{ $edenredFichero->getClientOriginalName() }}</span>
                                            @else
                                                <span class="text-gray-500">Falta subir la Imputación</span>
                                            @endif
                                            <button type="button" wire:click="$set('edenredUsuariosAbierto', true)" class="px-2 py-0.5 text-xs bg-white border rounded shadow-sm hover:bg-gray-50 {{ $nAlta ? 'border-red-400 text-red-700' : 'border-gray-300 text-gray-700' }}"
                                                title="Trabajadores nuevos con sus datos (DNI/NIE, correo, fecha de nacimiento) para crear el usuario en Edenred.">
                                                👤 Usuarios{{ $nAlta ? " ({$nAlta} por dar de alta)" : '' }}
                                            </button>
                                        </div>
                                    @endif
                                    @if ($id === 'open_items')
                                        @php $mc = $this->cashflowMayorCentral; @endphp
                                        <label class="flex items-center text-xs text-gray-600 gap-x-2 whitespace-nowrap" title="Opcional: el mayor de SAGE en columnas («FIQ MAyor.xlsx» de SageLauncher). Si no eliges ninguno, se usa el más reciente entre el de SageLauncher del PC y el último subido en cualquier pantalla. Resultado: OpenItems_FIQ_CL_<hoy>.xlsx, con el cuadre por cuenta en el resumen (amarillo = no cuadra).">
                                            <x-subir-fichero wire:model="openItemsMayor" accept=".xlsx" :fichero="$openItemsMayor" opcional texto="Mayor" />
                                            @if (! $openItemsMayor)
                                                <span class="text-gray-500" title="Usa el más reciente entre el de SageLauncher del PC{{ $mc ? ' y «'.$mc['nombre'].'» ('.$mc['fecha'].')' : '' }}">✓ Usa el más reciente <span class="text-indigo-500 cursor-help">ⓘ</span></span>
                                            @endif
                                        </label>
                                    @endif
                                    @if ($id === 'cashflow')
                                        @php $mc = $this->cashflowMayorCentral; @endphp
                                        @php $estCf = $marcas[$mesSel]['cashflow']['estado'] ?? null; @endphp
                                        <button type="button" wire:click="enviarCashflow" wire:loading.attr="disabled" wire:target="enviarCashflow,ejecutar('cashflow')"
                                            wire:confirm="¿Mandar YA a Plein el «Cashflow {{ $anio }} {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}.xlsx»? (revísalo antes)"
                                            class="inline-flex items-center px-2 py-0.5 text-xs font-medium border rounded shadow-sm disabled:opacity-50 {{ $estCf === 'ok' ? 'text-gray-500 bg-white border-gray-300' : 'text-white bg-indigo-600 border-indigo-600 hover:bg-indigo-700' }}">
                                            <span wire:loading.remove wire:target="enviarCashflow">{{ $estCf === 'ok' ? '✉ Reenviar' : '✉ Enviar a Plein' }}</span>
                                            <span wire:loading wire:target="enviarCashflow">⏳…</span>
                                        </button>
                                        <label class="flex items-center text-xs text-gray-600 gap-x-2 whitespace-nowrap" title="Opcional: el mayor de SAGE (listado en columnas, el mismo que usan Bancos, Facturas OCR...). Se guarda en los ficheros base de Fashion IQ para todos los procesos. Si no eliges ninguno, se usa el último subido en cualquier pantalla.">
                                            <x-subir-fichero wire:model="cashflowMayor" accept=".xlsx" :fichero="$cashflowMayor" opcional texto="Mayor" />
                                            @if ($cashflowMayor)
                                            @elseif ($mc)
                                                <span class="text-gray-500 whitespace-nowrap" title="Usa «{{ $mc['nombre'] }}», {{ $mc['fecha'] }}{{ $mc['origen'] ? ' · '.$mc['origen'] : '' }}">✓ {{ \Illuminate\Support\Str::limit($mc['nombre'], 24) }} · {{ $mc['fecha'] }} <span class="text-indigo-500 cursor-help">ⓘ</span></span>
                                            @else
                                                <span class="text-yellow-700">No hay mayor en los ficheros base: lo busca en Descargas del PC.</span>
                                            @endif
                                        </label>
                                    @endif
                                    @if (! empty($resultados[$id]))
                                        <div class="flex flex-col min-w-0 gap-y-1 pt-1">
                                            @foreach ($resultados[$id] as $r)
                                                <x-contabilidad.resultado-fichero :r="$r" />
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @if ($id === 'laboral')@include('livewire.contabilidad._staff-faltan')@endif
                            @elseif ($auto === 'cash-in-store')
                                <div class="flex flex-wrap items-center gap-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="buscarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore">
                                        <span wire:loading.remove wire:target="buscarCashInStore">🔎 Buscar</span>
                                        <span wire:loading wire:target="buscarCashInStore">⏳…</span>
                                    </button>
                                    @if ($this->cisFaltan)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="pedirCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            wire:confirm="¿Mandar YA el correo pidiendo el efectivo a {{ implode(', ', $this->cisFaltan) }}?">
                                            ✉ Pedir ({{ implode(', ', $this->cisFaltan) }})
                                        </button>
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="recordarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            title="Responder a la petición ya enviada: deja el borrador en Outlook para revisarlo y enviarlo">
                                            🔔 Recordatorio (borrador)
                                        </button>
                                    @endif
                                    @if ($cisFilas)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-white bg-indigo-600 border border-indigo-600 rounded shadow-sm hover:bg-indigo-700 disabled:opacity-50" wire:click="grabarCashInStore" wire:loading.attr="disabled" wire:target="buscarCashInStore,pedirCashInStore,recordarCashInStore,grabarCashInStore"
                                            wire:confirm="¿Escribir estos importes en Cash End Month de Ctrol Dinamico? (cierra antes el Excel si lo tienes abierto)">
                                            💾 Grabar
                                        </button>
                                    @endif
                                    @if ($cisFilas)
                                        <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" wire:click="$toggle('cisAbierto')">{{ $cisAbierto ? '▴ Plegar' : '▾ Ver importes' }}</button>
                                    @endif
                                    <span wire:loading wire:target="pedirCashInStore,recordarCashInStore,grabarCashInStore" class="text-xs text-gray-500">⏳…</span>
                                    @foreach ($resultados['cis'] ?? [] as $r)
                                        <x-contabilidad.resultado-fichero :r="$r" />
                                    @endforeach
                                </div>
                            @elseif ($auto === 'intrastat')
                                {{-- 14-oct-2026: se elige el Excel de Outbound, se procesa con el motor de Impuestos → Intrastat y se marca hecho --}}
                                <div class="flex flex-wrap items-start gap-x-2 gap-y-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50"
                                        wire:click="procesarIntrastat" wire:loading.attr="disabled" wire:target="procesarIntrastat,intrastatFichero"
                                        @if (! $intrastatFichero && ! $intrastatRes) disabled title="Elige antes el Excel" @endif>
                                        <span wire:loading.remove wire:target="procesarIntrastat">▶ Procesar</span>
                                        <span wire:loading wire:target="procesarIntrastat">⏳…</span>
                                    </button>
                                    <x-subir-fichero wire:model="intrastatFichero" accept=".xlsx,.xlsm" :fichero="$intrastatFichero"
                                        texto="Outbound PPI & PS {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}"
                                        title="Excel mensual «Outbound Fashion IQ PPI & PS - {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}.{{ $anio }}.xlsx». Sustituye al que hubiera procesado de este mes. Puedes arrastrarlo aquí." />
                                    @if ($intrastatRes)
                                        <div class="flex flex-col text-xs text-gray-600">
                                            <span>
                                                <button type="button" wire:click="descargarIntrastat" class="text-indigo-700 underline hover:text-indigo-900">⬇ {{ $intrastatRes['fichero'] ?? 'CSV' }}</button>
                                                · {{ $intrastatRes['partidas'] ?? 0 }} partidas · {{ $intrastatRes['generado'] ?? '' }}
                                            </span>
                                            @if (! empty($intrastatRes['avisos']))
                                                <a href="{{ route('impuestos.intrastat') }}" class="text-yellow-800 hover:underline" title="{{ implode("\n", array_slice($intrastatRes['avisos'], 0, 15)) }}">⚠️ {{ count($intrastatRes['avisos']) }} avisos · ver en Impuestos → Intrastat</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @elseif ($auto === 'anaplan-staff')
                                {{-- 16-oct-2026: salario y bonus de cada empleado (imputación de costes) a «Input Staff Data for AC» de Anaplan.
                                     17-oct: los datos los deja Laboral; «✎ Nombres» para los que faltan; «⬆ Subir» ya se conecta a Anaplan. --}}
                                @php
                                    $sinNom = $this->staffSinNombreMes;
                                    $confSubir = '¿Escribir en Anaplan el salario y el bonus del personal del mes '.str_pad($mes, 2, '0', STR_PAD_LEFT).'?';
                                    if ($sinNom) {
                                        $confSubir = '⚠️ ¿Estás seguro? '.count($sinNom)." empleado(s) NO tienen nombre de Anaplan y NO se subirán:\n"
                                            .collect($sinNom)->map(fn ($f) => "· {$f['tienda']}: {$f['excel']}")->implode("\n")."\n\n".$confSubir;
                                    }
                                @endphp
                                <div class="flex flex-wrap items-start gap-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-white bg-indigo-600 border border-indigo-600 rounded shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                                        wire:click="subirStaff" wire:loading.attr="disabled" wire:target="subirStaff" wire:confirm="{{ $confSubir }}"
                                        title="Se conecta a Anaplan (Chrome del PC de «Ejecutar en»), escribe Bruto sin Bonus en Salary Value MTD y Bonus + Incentivo en Bonus Value MTD, lo relee y marca el check">
                                        <span wire:loading.remove wire:target="subirStaff">⬆ Subir a Anaplan</span>
                                        <span wire:loading wire:target="subirStaff">⏳…</span>
                                    </button>
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50"
                                        wire:click="abrirNombresStaff" wire:loading.attr="disabled" wire:target="abrirNombresStaff"
                                        title="Empleados de la nómina del mes (los deja «Laboral») con su nombre en Anaplan; escribe el de los que falten">
                                        ✎ Nombres{{ $sinNom ? ' ('.count($sinNom).' faltan)' : '' }}
                                    </button>
                                    @include('livewire.contabilidad._staff-faltan')
                                </div>
                            @elseif ($auto === 'fiscal-dias')
                                {{-- 18-oct-2026: estado por días del Fiscal Report y del Cashier + lanzarlos a mano (modal) --}}
                                <button type="button" wire:click="abrirFiscalDias('{{ $mesSel }}')" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50">📅 Ver días</button>
                            @elseif ($auto === 'comunicar-impuestos')
                                <div class="flex flex-wrap items-center gap-1">
                                    <button type="button" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50" wire:click="$toggle('ciAbierto')">{{ $ciAbierto ? '▴ Plegar' : '✎ Preparar correo' }}</button>
                                    @if ($ciEnviado)<span class="text-xs text-green-700">✓ enviado el {{ $ciEnviado }}</span>
                                    @elseif ($ciBorrador)<span class="text-xs text-yellow-700">P borrador en Outlook el {{ $ciBorrador }}</span>@endif
                                </div>
                            @elseif (in_array($id, ['rv_envio', 'pagos_fin_mes'], true))
                                {{-- 17-oct-2026 (Alex): el panel de Rentas Variables / Pagos fin de mes se abre aquí debajo; ya no son desplegables aparte --}}
                                <button type="button" x-data="{ ab: false }" x-on:panel-abierto.window="if ($event.detail.id === '{{ $auto }}') ab = $event.detail.open" x-on:click="$dispatch('alternar-panel', '{{ $auto }}')" x-text="ab ? '▴ Plegar' : 'abrir ↓'" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50">abrir ↓</button>
                            @elseif ($auto && $auto !== 'pendiente')
                                <button type="button" x-on:click="$dispatch('abrir-panel', '{{ $auto }}')" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50">abrir ↓</button>
                            @else
                                {{-- manuales y los «pendiente de montar»: mientras tanto, se marcan a mano --}}
                                @php $hecho = ($marcas[$mesSel][$id]['estado'] ?? '') === 'ok'; @endphp
                                <button type="button" wire:click="marcarMesActual('{{ $id }}')" class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded shadow-sm hover:bg-gray-50 disabled:opacity-50" title="Marca (o desmarca) el check del mes {{ $this->checklistMeses[$mesSel] }}">
                                    {{ $hecho ? '↺ Desmarcar' : '✓ Marcar' }}
                                </button>
                                @if ($auto === 'pendiente')
                                    <span class="ml-1 text-xs text-gray-400">pendiente de montar</span>
                                @endif
                            @endif
                        </td>
                        @foreach ($this->checklistMeses as $k => $n)
                            @php $m = $marcas[$k][$id] ?? null; @endphp
                            @if ($grupo)
                                <td class="px-1 py-1 text-center {{ $k === $mesSel ? 'bg-indigo-50' : '' }}">
                                    <span class="inline-flex" style="gap:1px">
                                        @foreach ($miembros as $mb)
                                            @php $mm2 = $marcas[$k][$mb['id']] ?? null; @endphp
                                            <button type="button" wire:click="alternarChecklist('{{ $mb['id'] }}', '{{ $k }}')"
                                                title="{{ $mb['boton'] ?? $mb['nombre'] }}: {{ $mm2 ? ((['ok' => 'Hecho', 'na' => 'No toca', 'proc' => 'Procesado, sin enviar'][$mm2['estado']] ?? $mm2['estado']) . ($mm2['cuando'] ? ' · ' . $mm2['cuando'] : '') . ' · ' . $mm2['como']) : 'Sin hacer' }}"
                                                class="border rounded-sm {{ ['ok' => 'bg-green-500 border-green-600', 'na' => 'bg-gray-200 border-gray-300', 'proc' => 'bg-yellow-300 border-yellow-500'][$mm2['estado'] ?? ''] ?? 'bg-white border-gray-300' }}"
                                                style="width:10px;height:20px"></button>
                                        @endforeach
                                    </span>
                                </td>
                                @continue
                            @endif
                            <td class="px-1 py-1 text-center {{ $k === $mesSel ? 'bg-indigo-50' : '' }}">
                                <button type="button" wire:click="{{ $auto === 'fiscal-dias' ? "abrirFiscalDias('{$k}')" : "alternarChecklist('{$id}', '{$k}')" }}"
                                    title="{{ $m ? ((['ok' => 'Hecho', 'na' => 'No toca', 'proc' => 'Procesado, sin enviar'][$m['estado']] ?? $m['estado']) . ($m['cuando'] ? ' · ' . $m['cuando'] : '') . ' · ' . $m['como']) : 'Sin hacer' }}"
                                    class="inline-flex items-center justify-center w-5 h-5 text-xs border rounded {{ ['ok' => 'bg-green-500 border-green-600 text-white', 'na' => 'bg-gray-200 border-gray-300 text-gray-500', 'proc' => 'bg-yellow-300 border-yellow-500 text-yellow-900'][$m['estado'] ?? ''] ?? 'bg-white border-gray-300' }}">
                                    {{ ['ok' => '✓', 'na' => '–', 'proc' => 'P'][$m['estado'] ?? ''] ?? '' }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                    @if ($id === 'comunicar_impuestos' && $ciAbierto)
                        {{-- Correo «<Month> Taxes» a Plein (14-oct-2026) --}}
                        <tr wire:key="chk-ci-detalle" x-show="ver('comunicar impuestos taxes')">
                            <td colspan="{{ 3 + count($this->checklistMeses) }}" class="px-2 pb-3">
                                <div class="flex flex-wrap gap-6 p-2 border border-gray-200 rounded">
                                    <div style="flex:1 1 420px;min-width:0">
                                        <div class="mb-1 text-sm font-semibold text-gray-800">«{{ \Carbon\Carbon::create($anio, $mes, 1)->locale('en')->monthName }} Taxes» · importes en euros (vacío = no va en el correo)</div>
                                        <table class="text-sm text-gray-800">
                                            <tr class="text-xs text-left text-gray-500"><th class="pr-2">Modelo</th><th class="pr-2">En el correo</th><th class="pr-2">Importe</th><th>Día de cargo</th></tr>
                                            @foreach (\App\Http\Livewire\Contabilidad\Procesos::CI_FILAS as $k => [$modelo, $texto])
                                                <tr wire:key="ci-{{ $k }}">
                                                    <td class="py-0.5 pr-2 font-semibold">{{ $modelo }}</td>
                                                    <td class="py-0.5 pr-2 text-gray-600">{{ $texto }}</td>
                                                    <td class="py-0.5 pr-2">
                                                        <input type="text" wire:model.blur="ciImp.{{ $k }}" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:110px"
                                                            placeholder="{{ $k === 'm303' ? 'Buscar 303' : '' }}">
                                                    </td>
                                                    <td class="py-0.5"><input type="text" wire:model.blur="ciFecha.{{ $k }}" class="px-1 py-0 text-sm border-gray-300 rounded" style="width:95px" placeholder="dd/mm/aaaa"></td>
                                                </tr>
                                            @endforeach
                                        </table>
                                        <div class="flex flex-wrap items-center mt-2 gap-x-2 gap-y-1">
                                            <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                                            <input type="text" wire:model="ciTo" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                                        </div>
                                        <div class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1">
                                            <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                                            <input type="text" wire:model="ciCc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                                        </div>
                                        <div class="flex flex-wrap items-center mt-2 gap-x-2 gap-y-2">
                                            @php $ciT = 'buscar303,comunicarImpuestos,armarComunicarImpuestos'; @endphp
                                            <x-button.secondary wire:click="buscar303" wire:loading.attr="disabled" wire:target="{{ $ciT }}">🔎 Buscar 303</x-button.secondary>
                                            <x-button.secondary wire:click="comunicarImpuestos('vista')" wire:loading.attr="disabled" wire:target="{{ $ciT }}">Vista previa</x-button.secondary>
                                            <input type="email" wire:model="ciEmailPrueba" class="text-sm border-gray-300 rounded-md shadow-sm" style="min-width:200px">
                                            <x-button.secondary wire:click="comunicarImpuestos('prueba')" wire:loading.attr="disabled" wire:target="{{ $ciT }}">Enviar a prueba</x-button.secondary>
                                            <x-button.secondary wire:click="comunicarImpuestos('borrador')" wire:loading.attr="disabled" wire:target="{{ $ciT }}" title="Lo deja en Borradores de Outlook para revisarlo y enviarlo desde allí (queda P)">✉ Borrador en Outlook</x-button.secondary>
                                            @if (! $ciArmado)
                                                <x-button.primary wire:click="armarComunicarImpuestos" wire:loading.attr="disabled" wire:target="{{ $ciT }}">Enviar a Plein…</x-button.primary>
                                            @else
                                                <button type="button" wire:click="comunicarImpuestos('real')" wire:loading.attr="disabled" wire:target="{{ $ciT }}"
                                                    class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-red-600 border border-red-700 rounded shadow-sm hover:bg-red-700">⚠️ Confirmar envío REAL a Plein</button>
                                                <button type="button" wire:click="armarComunicarImpuestos" class="text-xs text-gray-600 underline">cancelar</button>
                                            @endif
                                            <span wire:loading wire:target="{{ $ciT }}" class="text-sm text-gray-500">⏳ …</span>
                                        </div>
                                        @if (! empty($resultados['ci']))
                                            <div class="flex flex-col mt-2 gap-y-1">
                                                @foreach ($resultados['ci'] as $r)
                                                    <x-contabilidad.resultado-fichero :r="$r" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div style="flex:1 1 320px;min-width:0">
                                        <div class="mb-1 text-xs font-semibold text-gray-600">Así sale la tabla del correo</div>
                                        <table class="text-sm text-gray-800 border-collapse">
                                            <tr><td></td><td></td><td class="px-2 border border-gray-500"><b>BBVA DD</b></td></tr>
                                            <tr>
                                                <td class="px-2 border border-gray-500"><b>{{ \Carbon\Carbon::create($anio, $mes, 1)->locale('en')->monthName }} {{ $anio }}</b></td>
                                                <td class="px-2 border border-gray-500"><b>Amount</b></td>
                                                <td class="px-2 border border-gray-500"><b>Payment day</b></td>
                                            </tr>
                                            @forelse ($this->ciLineas as [$t, $v, $d])
                                                <tr><td class="px-2 border border-gray-500">{{ $t }}</td><td class="px-2 text-right border border-gray-500">{{ $v }}</td><td class="px-2 text-right border border-gray-500">{{ $d }}</td></tr>
                                            @empty
                                                <tr><td colspan="3" class="px-2 text-xs text-gray-500 border border-gray-500">Sin importes todavía.</td></tr>
                                            @endforelse
                                        </table>
                                        <p class="mt-1 text-xs text-gray-500">Si el M303 está vacío al preparar el correo, el script lo busca solo. Al enviar o dejar el borrador, los destinatarios quedan como base del mes siguiente.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if (in_array('rv_envio', $idsFila, true))
                        {{-- Rentas Variables (Cálculos + Turnover y envío a los arrendadores de BCN y MAL): antes era un desplegable
                             aparte debajo de la tabla; desde el 17-oct-2026 se abre aquí. «abrir ↓» de las otras filas de RV y el enlace de arriba también. --}}
                        <tr wire:key="chk-rv-detalle" id="rentas-variables" x-data="{ open: false }" x-show="open && ver('rentas variables enviar ventas arrendatarios turnover')" style="display:none"
                            x-effect="$dispatch('panel-abierto', { id: 'rentas-variables', open })"
                            x-on:alternar-panel.window="if ($event.detail === 'rentas-variables') open = !open"
                            x-on:abrir-panel.window="if ($event.detail === 'rentas-variables') { open = true; $nextTick(() => $el.scrollIntoView({behavior: 'smooth', block: 'start'})) }">
                            <td colspan="{{ 3 + count($this->checklistMeses) }}" class="px-2 pb-3">
                                <div class="border border-gray-200 rounded">
    <div class="p-3">
        <div class="flex flex-wrap gap-8">
            {{-- IZQUIERDA 35%: Cálculos + Turnover (usa el "Mes" del título) --}}
            <div style="flex:0 0 35%;min-width:280px">
                <h2 class="text-lg font-semibold text-gray-900">Rentas Variables</h2>
                <p class="mt-1 mb-3 text-xs text-gray-500">Rellena CalculosRentasVbles{{ $anio }}.xlsx del mes y el fichero del turnover de BCN y MAL.</p>
                <div class="mt-1 space-y-1">
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="BCN" class="mr-2 border-gray-300 rounded"> Barcelona
                    </label>
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="rvTiendas" value="MAL" class="mr-2 border-gray-300 rounded"> Málaga
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> La Roca <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                    <label class="flex items-center text-sm text-gray-400" title="Entra en Cálculos, pero no tiene arrendador externo">
                        <input type="checkbox" checked disabled class="mr-2 border-gray-300 rounded"> Las Rozas <span class="ml-1 text-xs">(solo Cálculos)</span>
                    </label>
                </div>

                <div class="mt-3">
                    <x-button.secondary
                        wire:click="ejecutarRvCalculosYDeclaracion"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvCalculosYDeclaracion"
                        wire:confirm="Cálculos (4 tiendas) + Turnover de las tiendas marcadas, para el mes seleccionado. Escribe sobre los ficheros reales. ¿Seguro?"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvCalculosYDeclaracion">Cálculos + Turnover</span>
                        <span wire:loading wire:target="ejecutarRvCalculosYDeclaracion">⏳ Ejecutando…</span>
                    </x-button.secondary>
                </div>

                @if (! empty($resultados['rv']))
                    <div class="flex flex-col mt-2 gap-y-1">
                        @foreach ($resultados['rv'] as $r)
                            <x-contabilidad.resultado-fichero :r="$r" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DERECHA: Envío del correo -- ocupa el resto que quede libre --}}
            <div style="flex:1 1 340px;min-width:340px">
                <h3 class="text-sm font-semibold text-gray-700">Envío del correo</h3>

                {{-- tarjetas de producción --}}
                <div class="flex flex-col mt-2 gap-y-1">
                    @foreach ($this->rvTiendasArrendador as $k => $label)
                        {{-- To, input, CC, input y el botón en la MISMA fila;
                             "Corrección" debajo (w-full fuerza el salto de línea). --}}
                        <div wire:key="rv-envio-{{ $k }}" class="flex flex-wrap items-center p-2 border border-gray-200 rounded gap-x-2 gap-y-1">
                            <div class="mr-4 text-sm font-semibold text-gray-800 shrink-0 w-14">{{ $label }}</div>
                            <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.to" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                            <input type="text" wire:model="rvEnvio.{{ $k }}.cc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                            <x-button.secondary
                                class="shrink-0"
                                style="padding-top:.25rem;padding-bottom:.25rem"
                                wire:click="ejecutarRvEnvio('{{ $k }}')"
                                wire:loading.attr="disabled"
                                wire:target="ejecutarRvEnvio('{{ $k }}')"
                                wire:confirm="¿Mandar el correo de {{ $label }} a sus destinatarios REALES?"
                            >
                                <span wire:loading.remove wire:target="ejecutarRvEnvio('{{ $k }}')">Enviar</span>
                                <span wire:loading wire:target="ejecutarRvEnvio('{{ $k }}')">⏳ Enviando…</span>
                            </x-button.secondary>
                            <label class="flex items-center w-full text-xs text-gray-700">
                                <input type="checkbox" wire:model="rvEnvio.{{ $k }}.correccion" class="mr-1 border-gray-300 rounded"> Corrección
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- correo de prueba: debajo de las dos tiendas, sin línea ni fondo --}}
                <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
                    <label class="text-xs font-medium text-gray-600 shrink-0">Correo de prueba</label>
                    <input type="email" wire:model="rvEmailPrueba" placeholder="tucorreo@ejemplo.com" class="flex-1 text-sm border-gray-300 rounded-md shadow-sm" style="min-width:220px">
                    <x-button.secondary
                        wire:click="ejecutarRvEnvioPrueba"
                        wire:loading.attr="disabled"
                        wire:target="ejecutarRvEnvioPrueba"
                        wire:confirm="¿Mandar los ficheros de Barcelona y Málaga al correo de prueba?"
                    >
                        <span wire:loading.remove wire:target="ejecutarRvEnvioPrueba">Enviar a correo de prueba</span>
                        <span wire:loading wire:target="ejecutarRvEnvioPrueba">⏳ Enviando…</span>
                    </x-button.secondary>
                </div>
            </div>
        </div>
    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if ($id === 'pagos_fin_mes')
                        {{-- Pagos fin de mes: antes un desplegable aparte debajo de la tabla; desde el 17-oct-2026 se abre aquí. --}}
                        <tr wire:key="chk-pf-detalle" id="pagos-fin-mes" x-data="{ open: false }" x-show="open && ver('pagos fin de mes correo a plein')" style="display:none"
                            x-effect="$dispatch('panel-abierto', { id: 'pagos-fin-mes', open })"
                            x-on:alternar-panel.window="if ($event.detail === 'pagos-fin-mes') open = !open"
                            x-on:abrir-panel.window="if ($event.detail === 'pagos-fin-mes') { open = true; $nextTick(() => $el.scrollIntoView({behavior: 'smooth', block: 'start'})) }">
                            <td colspan="{{ 3 + count($this->checklistMeses) }}" class="px-2 pb-3">
                                <div class="border border-gray-200 rounded">
    {{-- Correo mensual a Plein con los cargos de fin/principio de mes en BBVA
         (pedido 2026-09-25). Solo cambian estos datos; el resto sale de
         monthlyFIQ/pagosFinMes.json. --}}
    <div class="p-3">
        <h2 class="flex flex-wrap items-center text-lg font-semibold text-gray-900 gap-x-3">
            <span>Pagos fin de mes · correo a Plein</span>
            {{-- mismo mes que el del título (pedido 2026-10-01) --}}
            <span class="text-base font-normal text-gray-600">{{ \Carbon\Carbon::create($anio, $pfMes, 1)->locale('en')->monthName }}</span>
            @if ($pfEnviado)
                <span class="text-xs font-normal text-green-700">✓ enviado a Plein el {{ $pfEnviado }}</span>
            @endif
        </h2>
        <div class="flex flex-wrap gap-6">
        {{-- IZQUIERDA: datos, texto, destinatarios y botones --}}
        <div style="flex:1 1 380px;min-width:0">
        <p class="mt-1 mb-3 text-xs text-gray-500">"End and begining of month payments &lt;mes&gt;." Importes en K (vale 85,9 o 85.912,39). "Buscar importes" rellena VAT TAX (PDF del 303 del mes anterior en _Impuestos), Social Security (correo de Jordi en Outlook, carpeta Laboral) y Payrolls (remesa RM*.xml); todo se puede cambiar a mano. Si se envía con VAT o SS vacíos, se buscan solos. Día de cargo vacío = último día hábil del mes.</p>
        <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
            @foreach (['pfSaldo' => 'Saldo BBVA hoy', 'pfIva' => 'VAT TAX', 'pfSs' => 'Social Security', 'pfNominas' => 'Payrolls', 'pfCargo' => 'Día de cargo'] as $campo => $label)
                <label class="flex flex-col text-xs font-medium text-gray-600">
                    {{ $label }}
                    <input type="text" wire:model.blur="{{ $campo }}" class="mt-1 text-sm border-gray-300 rounded shadow-sm" style="width:{{ $campo === 'pfCargo' ? '150px' : '100px' }}"
                        placeholder="{{ $campo === 'pfNominas' ? 'remesa' : ($campo === 'pfCargo' ? 'último hábil' : '') }}">
                </label>
            @endforeach
        </div>
        <div class="flex flex-col mt-3 gap-y-2">
            <label class="flex flex-col text-xs font-medium text-gray-600">
                Texto antes de la tabla (línea en blanco = párrafo nuevo)
                <textarea wire:model="pfTexto" rows="4" class="mt-1 text-sm border-gray-300 rounded shadow-sm"></textarea>
            </label>
            <div class="flex flex-wrap items-center gap-x-2">
                <label class="flex items-center text-xs font-medium text-gray-600 shrink-0">
                    <input type="checkbox" wire:model="pfIncluirDestacado" class="mr-1 border-gray-300 rounded"> Frase en negrita
                </label>
                <input type="text" wire:model="pfDestacado" class="flex-1 min-w-0 text-sm font-semibold border-gray-300 rounded shadow-sm">
            </div>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <label class="text-xs font-medium text-gray-600 shrink-0">To</label>
                <input type="text" wire:model="pfTo" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
                <label class="text-xs font-medium text-gray-600 shrink-0">CC</label>
                <input type="text" wire:model="pfCc" class="flex-1 min-w-0 text-sm border-gray-300 rounded shadow-sm">
            </div>
            <p class="text-xs text-gray-500">Al "Enviar a Plein", el texto, la frase y los destinatarios quedan guardados como base del mes siguiente.</p>
        </div>
        <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
            <x-button.secondary wire:click="buscarImportesPagosFinMes" wire:loading.attr="disabled" wire:target="buscarImportesPagosFinMes,ejecutarPagosFinMes">
                <span wire:loading.remove wire:target="buscarImportesPagosFinMes">🔎 Buscar importes</span>
                <span wire:loading wire:target="buscarImportesPagosFinMes">⏳ Buscando…</span>
            </x-button.secondary>
            <x-button.secondary wire:click="ejecutarPagosFinMes('vista')" wire:loading.attr="disabled" wire:target="ejecutarPagosFinMes">
                Vista previa
            </x-button.secondary>
            <label class="text-xs font-medium text-gray-600 shrink-0">Correo de prueba</label>
            <input type="email" wire:model="pfEmailPrueba" class="text-sm border-gray-300 rounded-md shadow-sm" style="min-width:220px">
            <x-button.secondary wire:click="ejecutarPagosFinMes('prueba')" wire:loading.attr="disabled" wire:target="ejecutarPagosFinMes">
                Enviar a prueba
            </x-button.secondary>
            <x-button.primary
                wire:click="ejecutarPagosFinMes('real')"
                wire:loading.attr="disabled"
                wire:target="ejecutarPagosFinMes"
                wire:confirm="¿Mandar el correo de pagos a los destinatarios REALES (To/CC de arriba)?"
            >
                Enviar a Plein
            </x-button.primary>
            <span wire:loading wire:target="ejecutarPagosFinMes" class="text-sm text-gray-500">⏳ …</span>
        </div>
        @if (! empty($resultados['pf']))
            <div class="flex flex-col mt-2 gap-y-1">
                @foreach ($resultados['pf'] as $r)
                    <x-contabilidad.resultado-fichero :r="$r" />
                @endforeach
            </div>
        @endif
        </div>

        {{-- DERECHA: las líneas de importes tal como saldrán en el correo --}}
        <div style="flex:1 1 380px;min-width:0">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Importes del correo (K)</h3>
            @php $pl = $this->pfLineas; @endphp
            <table class="text-sm text-gray-800">
                @foreach ($pl['cabecera'] as [$t, $v, $u])
                    <tr>
                        <td class="py-0.5 pr-3 {{ $loop->first ? '' : 'font-semibold' }}">{{ $t }}</td>
                        <td class="py-0.5 px-2 text-right font-semibold">{{ $v }}</td>
                        <td class="py-0.5 px-2">{{ $u }}</td>
                    </tr>
                @endforeach
            </table>
            <table class="w-full mt-3 text-sm text-gray-800 border border-collapse border-gray-400">
                @foreach ($pl['filas'] as $i => [$a, $b, $c, $d])
                    <tr>
                        <td class="px-2 py-0.5 border border-gray-400 whitespace-nowrap">{{ $a }}</td>
                        <td class="px-2 py-0.5 border border-gray-400 whitespace-nowrap">{{ $b }}</td>
                        <td class="px-2 py-0.5 text-right border border-gray-400">{{ $c }}</td>
                        <td class="px-2 py-0.5 border border-gray-400" style="width:55%">
                            <div class="flex items-center gap-x-1">
                                <span class="text-gray-500">K</span>
                                <input type="text" wire:model.blur="pfObs.{{ $i }}"
                                    class="flex-1 min-w-0 px-1 py-0 text-sm border-gray-200 rounded focus:ring-0">
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="px-2 py-0.5 border border-gray-400">Total</td>
                    <td class="px-2 py-0.5 border border-gray-400"></td>
                    <td class="px-2 py-0.5 text-right border border-gray-400">{{ $pl['total'] }}</td>
                    <td class="px-2 py-0.5 border border-gray-400">K</td>
                </tr>
            </table>
            <p class="mt-1 text-xs text-gray-500">"—" = vacío (VAT y SS se buscan solos al enviar). Se actualiza al salir de cada campo. La columna tras la K es una observación editable por fila (VAT y SS vienen rellenas con el día de cargo; si las borras o cambias, se respeta). Las filas fijas están en monthlyFIQ/pagosFinMes.json.</p>
        </div>
        </div>

        {{-- Recordatorio "Kindly reminder and update" sobre el correo ya enviado
             ese mes (pedido 2026-09-28). No guarda nada: el mes que viene sale
             con los importes de siempre. --}}
        <div class="pt-3 mt-4 border-t">
            <h3 class="flex flex-wrap items-center text-sm font-semibold text-gray-700 gap-x-3">
                <span>Recordatorio · "Kindly reminder and update"</span>
                <x-button.secondary wire:click="leerEnviadoPagosFinMes" wire:loading.attr="disabled" wire:target="leerEnviadoPagosFinMes,ejecutarRecordatorioPagosFinMes">
                    <span wire:loading.remove wire:target="leerEnviadoPagosFinMes">📥 Cargar correo enviado</span>
                    <span wire:loading wire:target="leerEnviadoPagosFinMes">⏳ Leyendo Outlook…</span>
                </x-button.secondary>
                @if ($pfRecOriginal)
                    <span class="text-xs font-normal text-gray-500">Responde a: {{ $pfRecOriginal }}</span>
                @endif
            </h3>
            <p class="mt-1 text-xs text-gray-500">Lee de Enviados de Outlook el correo de pagos del mes elegido arriba. Marca qué está pagado, retoca importes y pon el saldo de hoy. "Preparar en Outlook" deja un "Responder a todos" en Borradores (con el original citado debajo) para revisarlo y enviarlo desde allí. Los cambios de importes no se guardan.</p>
            @if ($pfRecFilas)
                @php $rt = $this->pfRecTotales; @endphp
                <div class="flex flex-wrap gap-6 mt-2">
                    <div style="flex:1 1 380px;min-width:0">
                        <label class="flex flex-col text-xs font-medium text-gray-600">
                            Texto antes de la tabla (línea en blanco = párrafo nuevo)
                            <textarea wire:model="pfRecTexto" rows="5" class="mt-1 text-sm border-gray-300 rounded shadow-sm"></textarea>
                        </label>
                        <div class="flex flex-wrap items-end mt-2 gap-x-4 gap-y-2">
                            <label class="flex flex-col text-xs font-medium text-gray-600">
                                Saldo BBVA hoy
                                <input type="text" wire:model.blur="pfRecSaldo" class="mt-1 text-sm border-gray-300 rounded shadow-sm" style="width:100px">
                            </label>
                            <table class="text-sm text-gray-800">
                                <tr><td class="pr-3">Pending payments</td><td class="px-2 font-semibold text-right">{{ $rt['pendiente'] }}</td><td>K</td></tr>
                                <tr><td class="pr-3">Upload for pending taxes + payrolls</td><td class="px-2 font-semibold text-right">{{ $rt['subirImp'] }}</td><td>K</td></tr>
                                <tr><td class="pr-3">Total amount to upload</td><td class="px-2 font-semibold text-right">{{ $rt['subirTotal'] }}</td><td>K</td></tr>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center mt-3 gap-x-2 gap-y-2">
                            <x-button.secondary wire:click="ejecutarRecordatorioPagosFinMes('vista')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Vista previa
                            </x-button.secondary>
                            <x-button.secondary wire:click="ejecutarRecordatorioPagosFinMes('prueba')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Enviar a prueba
                            </x-button.secondary>
                            <x-button.primary wire:click="ejecutarRecordatorioPagosFinMes('real')" wire:loading.attr="disabled" wire:target="ejecutarRecordatorioPagosFinMes">
                                Preparar en Outlook
                            </x-button.primary>
                            <span wire:loading wire:target="ejecutarRecordatorioPagosFinMes" class="text-sm text-gray-500">⏳ …</span>
                        </div>
                        @if (! empty($resultados['pfRec']))
                            <div class="flex flex-col mt-2 gap-y-1">
                                @foreach ($resultados['pfRec'] as $r)
                                    <x-contabilidad.resultado-fichero :r="$r" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div style="flex:1 1 380px;min-width:0">
                        <table class="w-full text-sm text-gray-800 border border-collapse border-gray-400">
                            @foreach ($pfRecFilas as $i => $f)
                                <tr wire:key="pfrec-{{ $i }}" class="{{ $f[4] === 'paid' ? 'bg-green-50' : '' }}">
                                    <td class="px-2 py-0.5 border border-gray-400">{{ $f[0] }}</td>
                                    <td class="px-2 py-0.5 border border-gray-400">{{ $f[1] }}</td>
                                    <td class="px-1 py-0.5 border border-gray-400">
                                        <input type="text" wire:model.blur="pfRecFilas.{{ $i }}.2" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:70px">
                                    </td>
                                    <td class="px-1 py-0.5 border border-gray-400">
                                        <div class="flex items-center gap-x-1">
                                            <span class="text-gray-500">K</span>
                                            <input type="text" wire:model.blur="pfRecFilas.{{ $i }}.3" class="flex-1 min-w-0 px-1 py-0 text-sm border-gray-200 rounded focus:ring-0" style="min-width:150px">
                                        </div>
                                    </td>
                                    <td class="px-1 py-0.5 border border-gray-400">
                                        <select wire:model.live="pfRecFilas.{{ $i }}.4" class="py-0 pl-1 pr-6 text-sm border-gray-300 rounded {{ $f[4] === 'paid' ? 'text-green-700' : 'text-red-700' }}">
                                            <option value="pending">Pending</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if ($id === 'cash_in_store' && $cisFilas && $cisAbierto)
                        <tr wire:key="chk-cis-detalle" x-show="ver('cash in store')">
                            <td colspan="{{ 3 + count($this->checklistMeses) }}" class="px-2 pb-2">
                                <table class="text-sm text-gray-800 border border-collapse border-gray-300">
                                    <tr class="text-xs text-left text-gray-600 bg-gray-50">
                                        <th class="px-2 py-0.5 border">Tienda</th>
                                        <th class="px-2 py-0.5 border">Cash (Prosegur)</th>
                                        <th class="px-2 py-0.5 border">Petty Cash</th>
                                        <th class="px-2 py-0.5 border">Correo ({{ \Carbon\Carbon::create($anio, $mes, 1)->locale('es')->monthName }})</th>
                                    </tr>
                                    @foreach ($cisFilas as $t => $r)
                                        <tr wire:key="cis-{{ $t }}" class="align-top {{ $r['encontrado'] ? '' : 'bg-red-50' }}">
                                            <td class="px-2 py-0.5 font-semibold border">{{ $t }}</td>
                                            <td class="px-2 py-0.5 border">
                                                <input type="text" wire:model.blur="cisFilas.{{ $t }}.cash" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:100px">
                                            </td>
                                            <td class="px-2 py-0.5 border">
                                                <input type="text" wire:model.blur="cisFilas.{{ $t }}.petty" class="px-1 py-0 text-sm text-right border-gray-300 rounded" style="width:90px">
                                            </td>
                                            <td class="px-2 py-0.5 text-xs border">
                                                @if ($r['encontrado'])
                                                    <div class="font-medium">«{{ $r['asunto'] }}» · {{ $r['recibido'] }}</div>
                                                    <pre class="text-xs text-gray-600 whitespace-pre-wrap" style="max-height:6rem;overflow:auto">{{ $r['texto'] }}</pre>
                                                @else
                                                    <span class="text-red-700">Sin dato: no ha mandado el efectivo → «Pedir»</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="17" class="px-3 py-2 text-sm text-gray-500">No encuentro monthlyFIQ/checklist.json en este equipo.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>




    @include('livewire.contabilidad._salida')
    </div>
    @include('livewire.contabilidad._fiscal-dias')
</div>
