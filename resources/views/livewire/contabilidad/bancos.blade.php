<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
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

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.bancos'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.bancos'])

    <div class="p-4 space-y-6">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Bancos — cliente:</span>
        @include('livewire.contabilidad._selector-cliente')
    </h1>
    @include('livewire.contabilidad._modal-cliente-nuevo')

    @if ($cliente !== '')
        @php
            $filasBase = [
                ['clave' => 'plan', 'icono' => '📘', 'titulo' => 'Plan de cuentas', 'datos' => $estadoBase['plan'] ?? null],
                ['clave' => 'mayor', 'icono' => '🏦', 'titulo' => 'Mayor', 'datos' => $estadoBase['mayor'] ?? null],
                ['clave' => 'proveedores', 'icono' => '🏭', 'titulo' => 'Proveedores', 'datos' => $estadoBase['proveedores'] ?? null],
            ];
            $otrasCuentas = $estadoBase['otras_cuentas'] ?? [];
        @endphp
        <div class="overflow-hidden bg-white border rounded-lg shadow"
             x-data="{ abierto: (() => { try { return localStorage.getItem('bancos-base-abierto') !== '0' } catch (e) { return true } })() }"
             x-init="$watch('abierto', v => { try { localStorage.setItem('bancos-base-abierto', v ? '1' : '0') } catch (e) {} })">
            <div class="p-4 bg-gray-50" :class="abierto ? 'border-b border-gray-200' : ''">
                <h2 class="mb-1 text-sm font-semibold text-gray-700">
                    <button type="button" x-on:click="abierto = ! abierto" class="font-semibold">
                        <span x-text="abierto ? '▾' : '▸'"></span> Ficheros base (de SAGE)
                    </button>
                </h2>
                <p class="text-xs text-gray-500" x-show="abierto">
                    El plan de cuentas y el mayor, exportados de SAGE. Cada uno va en su fila (arrástralo encima o pulsa ⬆):
                    se acumulan en la base de {{ $cliente }} y lo repetido no se duplica. El mayor puede traer todas las
                    cuentas: solo se guardan las de banco (las 572… y las que marques abajo).
                    El listado de proveedores sirve para cruzar las cuentas creadas en Appmos con las que ya existen en SAGE.
                    <b>Los extractos del banco no van aquí</b>, van abajo en «Extracto a procesar».
                </p>
            </div>

            <div class="divide-y" x-show="abierto">
                @foreach ($filasBase as $fb)
                    <div wire:key="fila-base-{{ $cliente }}-{{ $fb['clave'] }}"
                         x-data="{
                             encima: false, subiendo: false, progreso: 0,
                             subir(files) {
                                 if (! files || ! files.length) return;
                                 this.subiendo = true; this.progreso = 0;
                                 $wire.uploadMultiple('subidas', files,
                                     () => { this.subiendo = false; $wire.procesarSubidas(@js($fb['clave'])); },
                                     () => { this.subiendo = false; },
                                     (e) => { this.progreso = e.detail.progress; });
                             },
                         }"
                         x-on:dragover.prevent="encima = true"
                         x-on:dragleave.prevent="encima = false"
                         x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                         :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''"
                         class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2">
                        <input type="file" multiple accept=".xlsx,.xls" class="hidden" x-ref="input"
                               x-on:change="subir($event.target.files); $event.target.value = ''">
                        <div class="w-64 text-sm font-medium text-gray-800">{{ $fb['icono'] }} {{ $fb['titulo'] }}</div>
                        <div class="flex-1 min-w-[14rem] text-xs text-gray-600">
                            @if ($fb['clave'] === 'plan' && $fb['datos'])
                                <b>{{ $fb['datos']['cuentas'] }}</b> cuentas
                            @elseif ($fb['clave'] === 'proveedores' && ! empty($fb['datos']['cuentas']))
                                <b>{{ $fb['datos']['cuentas'] }}</b> proveedores
                            @elseif ($fb['clave'] === 'mayor' && $cuentas)
                                @foreach ($cuentas as $codigo => $nombreCuenta)
                                    @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                                    <div>
                                        <b>{{ $codigo }}</b>{{ $nombreCuenta !== '' ? ' · '.$nombreCuenta : '' }}:
                                        @if ($dc)
                                            {{ $dc['apuntes'] }} apuntes
                                            @if ($dc['desde'] !== '') del {{ $dc['desde'] }} al {{ $dc['hasta'] }} @endif
                                            @if ($dc['provisionales'])
                                                <span class="text-amber-700" title="Movimientos que Appmos ya ha pasado a bancos{{ $codigo }}.xlsx y todavía no han vuelto en un mayor de SAGE">· {{ $dc['provisionales'] }} provisionales de Appmos</span>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <span class="text-gray-400">(todavía nada)</span>
                            @endif
                            @if (! empty($fb['datos']['recibido']))
                                <span class="text-gray-400">último: {{ $fb['datos']['recibido']['fichero'] }}
                                    ({{ \Illuminate\Support\Carbon::parse($fb['datos']['recibido']['fecha'])->format('d/m/Y H:i') }})</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span x-show="subiendo" x-cloak class="text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                            <x-button.secondary x-on:click="$refs.input.click()">⬆ {{ ['plan' => 'Subir plan', 'proveedores' => 'Subir proveedores'][$fb['clave']] ?? 'Subir mayor' }}</x-button.secondary>
                        </div>
                    </div>
                @endforeach
                <div class="flex flex-wrap items-center px-4 py-2 text-xs text-gray-600 gap-x-2 gap-y-1">
                    <span class="w-64 text-sm font-medium text-gray-800">＋ Otras cuentas de banco</span>
                    <span class="text-gray-400">Además de las 572…, que siempre lo son:</span>
                    @forelse ($otrasCuentas as $oc)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 border rounded-full">
                            <b>{{ $oc }}</b>{{ ($planCuentas[$oc] ?? '') !== '' ? ' · '.$planCuentas[$oc] : '' }}
                            <button type="button" wire:click="quitarOtraCuenta(@js($oc))"
                                    wire:confirm="¿Quitar la {{ $oc }} de las cuentas de banco? Se borra su pestaña de la base (se recupera volviendo a añadirla)."
                                    class="text-gray-400 hover:text-red-600" title="Quitar">&times;</button>
                        </span>
                    @empty
                        <span class="text-gray-400">ninguna</span>
                    @endforelse
                    <input type="text" wire:model="otraCuenta" wire:keydown.enter="anadirOtraCuenta" placeholder="p.ej. 551002"
                           class="w-32 py-0.5 text-xs border-gray-300 rounded-md shadow-sm">
                    <x-button.secondary wire:click="anadirOtraCuenta" wire:loading.attr="disabled" wire:target="anadirOtraCuenta">Añadir</x-button.secondary>
                    @error('otraCuenta')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="px-4 py-2 border-t bg-gray-50" x-show="abierto">
                <div wire:loading.flex wire:target="procesarSubidas, anadirOtraCuenta, quitarOtraCuenta" class="items-center gap-2 mb-1 text-xs font-semibold text-amber-800">⏳ Actualizando la base…</div>
                @error('subidas')
                    <p class="mb-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    @if ($hayBase)
                        <button type="button" wire:click="descargar(@js('Base/Base '.$cliente.'.xlsx'))" class="text-blue-700 underline hover:text-blue-900">⬇ Excel con todo lo acumulado</button>
                        <span class="text-gray-400">(Base {{ $cliente }}.xlsx: Maestro, Variables, una pestaña por cuenta y el plan; solo para consultarlo)</span>
                        <a href="#maestro" class="text-blue-700 underline hover:text-blue-900">Ver el Maestro ↓</a>
                    @endif
                    @if ($recibidos)
                        <details class="w-full">
                            <summary class="text-gray-500 cursor-pointer">Historial de ficheros subidos ({{ count($recibidos) }} últimos)</summary>
                            @foreach ($recibidos as $f)
                                <div class="text-gray-700">{{ $f }}</div>
                            @endforeach
                        </details>
                    @endif
                </div>
            </div>
        </div>

        @if ($hayBase && $planCuentas)
            <div class="overflow-hidden bg-white border rounded-lg shadow" x-data="{ abierto: false, q: '' }">
                <div class="flex flex-wrap items-center gap-3 px-4 py-2 bg-gray-50">
                    <button type="button" x-on:click="abierto = ! abierto" class="text-sm font-semibold text-gray-700">
                        <span x-text="abierto ? '▾' : '▸'"></span> Plan de cuentas de {{ $cliente }}
                        <span class="font-normal text-gray-400">({{ count($planCuentas) }} cuentas)</span>
                    </button>
                    <input x-show="abierto" x-cloak type="search" x-model="q" placeholder="Buscar código o nombre…"
                           class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-72">
                </div>
                <div x-show="abierto" x-cloak class="overflow-auto border-t" style="max-height:24rem">
                    <table class="min-w-full text-xs">
                        <tbody>
                            @foreach ($planCuentas as $codigo => $nombreCuenta)
                                <tr class="border-t" x-show="q === '' || @js(mb_strtoupper($codigo.' '.$nombreCuenta)).includes(q.toUpperCase())">
                                    <td class="px-4 py-0.5 font-mono w-24">{{ $codigo }}</td>
                                    <td class="px-2 py-0.5">{{ $nombreCuenta }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @endif

    @if ($cliente !== '')
        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="mb-3 text-sm font-semibold text-gray-700">Extractos a procesar → bancos&lt;cuenta&gt;.xlsx
                    <x-neteges-info>
                    Sube uno o varios extractos: propongo la cuenta de cada uno (si no la encuentro, la eliges tú) y se procesan uno tras otro.
                    Se quitan los movimientos que ya están en el mayor de esa cuenta (y los que aparezcan en los otros mayores cargados, que se listan en la Salida).
                    La contrapartida de cada movimiento se busca en la base: Variables (a mano) → Maestro → Plan de cuentas → palabras distintivas.
                    Los movimientos que salen en bancos&lt;cuenta&gt;.xlsx se guardan también en la base como apuntes de esa cuenta (provisionales hasta que subas el mayor de SAGE),
                    así no se repiten ni hace falta volver a bajar el mayor. Si luego pones a mano en el Maestro la cuenta de un concepto sin contrapartida,
                    se rellena también en la base y en su línea del fichero de bancos. Si no hay una única cuenta posible se deja en blanco;
                    los conceptos sin ninguna coincidencia se añaden a la pestaña Variables de la base para rellenarlos.
                    </x-neteges-info>
                </h2>

                @if (! $hayBase || empty($cuentas))
                    <p class="text-sm text-amber-700">Primero sube arriba el plan de cuentas y el mayor.</p>
                @else
                    <div x-data="{
                             encima: false, subiendo: false,
                             subir(files) {
                                 if (! files || ! files.length) return;
                                 this.subiendo = true;
                                 $wire.uploadMultiple('nuevosExtractos', files,
                                     () => { this.subiendo = false; $wire.anadirExtractos(); },
                                     () => { this.subiendo = false; });
                             },
                         }"
                         x-on:dragover.prevent="encima = true"
                         x-on:dragleave.prevent="encima = false"
                         x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)">
                        <label :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
                               class="flex items-center gap-2 px-3 py-3 text-sm border-2 border-dashed rounded-md cursor-pointer">
                            <input type="file" multiple accept=".xlsx,.xls" class="hidden"
                                   x-on:change="subir($event.target.files); $event.target.value = ''">
                            <span>📄</span>
                            <span class="text-gray-700">Arrastra aquí los extractos del banco (uno o varios) o haz clic para elegirlos</span>
                            <span x-show="subiendo" class="text-xs text-gray-400">Subiendo…</span>
                        </label>
                        @error('extractos')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($extractos)
                            <table class="mt-3 text-sm">
                                <thead>
                                    <tr class="text-xs text-left text-gray-500">
                                        <th class="pr-3 font-medium">Extracto</th>
                                        <th class="pr-3 font-medium">Cuenta del banco</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($extractos as $i => $ex)
                                        <tr wire:key="extracto-{{ $i }}-{{ $ex->getClientOriginalName() }}" class="align-top">
                                            <td class="py-1 pr-3 text-gray-800">{{ $ex->getClientOriginalName() }}</td>
                                            <td class="py-1 pr-3">
                                                <select wire:model.live="cuentasExtracto.{{ $i }}"
                                                        class="text-sm border-gray-300 rounded-md shadow-sm {{ ($cuentasExtracto[$i] ?? '') === '' ? 'border-amber-400 bg-amber-50' : '' }}">
                                                    <option value="">— elige —</option>
                                                    @foreach ($cuentas as $codigo => $nombreCuenta)
                                                        <option value="{{ $codigo }}">{{ $codigo }}{{ $nombreCuenta !== '' ? ' · '.$nombreCuenta : '' }}</option>
                                                    @endforeach
                                                </select>
                                                @if (($cuentasExtracto[$i] ?? '') === '')
                                                    <div class="mt-0.5 text-xs text-amber-700">No he encontrado la cuenta: elígela tú.</div>
                                                @elseif (($origenCuenta[$i] ?? '') !== '')
                                                    <div class="mt-0.5 text-xs text-gray-500">Propuesta {{ $origenCuenta[$i] }}.</div>
                                                @endif
                                                @if (($avisoCuenta[$i] ?? '') !== '' && ($cuentasExtracto[$i] ?? '') !== '')
                                                    <div class="mt-0.5 text-xs font-medium text-amber-700">⚠️ {{ $avisoCuenta[$i] }}</div>
                                                @endif
                                            </td>
                                            <td class="py-1">
                                                <button type="button" wire:click="quitarExtracto({{ $i }})" class="text-xs text-red-600 hover:underline">quitar</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @if ($previos)
                            <div class="p-3 mt-3 text-sm border rounded-md bg-amber-50 border-amber-300">
                                <div class="font-semibold text-amber-900">⚠️ Ya hay ficheros de bancos de una pasada anterior:</div>
                                <div class="mt-1 text-amber-900">{{ implode(', ', $previos) }}</div>
                                <p class="mt-1 text-xs text-amber-800">
                                    Si los dejas, los movimientos de esas pasadas ya están en la Base como apuntes provisionales y no se repetirán
                                    (el resultado nuevo saldrá casi vacío o como <b>_2</b>). Si quieres empezar de cero, bórralos: se quitan también
                                    sus apuntes provisionales de la Base (antes se hace copia de seguridad).
                                </p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <x-button.primary wire:click="responderPrevios('borrar')">🗑 Borrarlos y procesar</x-button.primary>
                                    <x-button.secondary wire:click="responderPrevios('mantener')">Mantenerlos y procesar</x-button.secondary>
                                    <x-button.secondary wire:click="responderPrevios('cancelar')">Cancelar</x-button.secondary>
                                </div>
                            </div>
                        @endif
                            <div class="mt-3">
                                <x-button.primary wire:click="conciliar" wire:loading.attr="disabled" wire:target="conciliar">
                                    <span wire:loading.remove wire:target="conciliar">▶ Generar bancos ({{ count($extractos) }} {{ count($extractos) === 1 ? 'extracto' : 'extractos' }})</span>
                                    <span wire:loading wire:target="conciliar">⏳ Procesando…</span>
                                </x-button.primary>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if (! empty($resultados))
            <div class="p-4 border rounded-lg shadow bg-green-50 border-green-200">
                <h2 class="mb-1 text-sm font-semibold text-green-800">Resultado de la última ejecución</h2>
                @foreach ($resultados as $r)
                    <div class="flex flex-wrap items-center gap-x-3">
                        <button type="button" wire:click="descargar(@js($r['relativa']))" class="text-sm text-blue-700 underline hover:text-blue-900">⬇ Descargar {{ basename(str_replace('\\', '/', $r['ruta'])) }}</button>
                        @if (preg_match('/^[A-Z]:\\\\/', $r['ruta']))
                            {{-- Ruta de Windows para copiar: solo tiene sentido en un PC, no en la web --}}
                            <x-contabilidad.resultado-fichero :r="$r" />
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($hayBase)
            @php $ficherosBancos = array_values(array_filter($generados, fn ($g) => preg_match('/^bancos.+\.xlsx$/i', $g) && ! str_starts_with($g, 'bancos_junto_'))); @endphp
            <div class="overflow-hidden bg-white border rounded-lg shadow"
                 id="revision-bancos" x-on:bancos-revisar.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                 wire:key="revision-{{ $cliente }}-{{ $revisar }}-{{ $revisionN }}"
                 x-data="{
                     lineas: @js(array_map(fn ($l) => $l + ['concepto_maestro' => '', 'vale' => ''], $lineasRevisar)),
                     nombres: @js((object) $planCuentas),
                     nuevas: {},
                     filtro: 'todas', q: '',
                     get vacias() { return this.lineas.filter(l => ! l.contrapartida).length },
                     visible(l) {
                         if (this.filtro === 'vacias' && l.contrapartida && ! l.tocada) return false;
                         const t = this.q.toUpperCase();
                         return t === '' || (l.concepto + ' ' + l.contrapartida + ' ' + this.nombre(l.contrapartida)).toUpperCase().includes(t);
                     },
                     nombre(c) { return this.nombres[c] || this.nuevas[c] || '' },
                     cuentaCambiada(l) {
                         l.tocada = true;
                         l.contrapartida = (l.contrapartida || '').trim();
                         const c = l.contrapartida;
                         if (c === '' || this.nombre(c)) return;
                         if (! /^\d{6,}$/.test(c)) { alert('La cuenta tiene que ser un número de 6 cifras o más.'); l.contrapartida = ''; return; }
                         const n = prompt('La cuenta ' + c + ' no está en el plan de cuentas.\nSi es nueva (p.ej. un proveedor dado de alta en Facturas OCR y aún no en SAGE), escribe su nombre para crearla:', l.concepto);
                         if (n && n.trim()) { this.nuevas[c] = n.trim(); } else { l.contrapartida = ''; }
                     },
                 }">
                <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Revisar contrapartidas de</h2>
                    @foreach ($ficherosBancos as $g)
                        <button type="button" wire:click="$set('revisar', @js($g))"
                                class="px-2 py-1 text-xs border rounded-md {{ $g === $revisar ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400' }}">{{ $g }}</button>
                    @endforeach
                    <select wire:model.live="revisar" class="py-1 text-sm border-gray-300 rounded-md shadow-sm">
                        <option value="">— elige un fichero de bancos —</option>
                        @foreach ($ficherosBancos as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </select>
                    @if ($revisar !== '' && $lineasRevisar)
                        <select x-model="filtro" class="py-1 text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="vacias">Solo sin contrapartida</option>
                            <option value="todas">Todas las líneas</option>
                        </select>
                        <input type="search" x-model="q" placeholder="Buscar concepto o cuenta…" class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-60">
                        <span class="text-xs" :class="vacias ? 'text-amber-700' : 'text-green-700'"
                              x-text="vacias ? vacias + ' sin contrapartida de ' + lineas.length : 'Todas las ' + lineas.length + ' líneas tienen contrapartida'"></span>
                    @endif
                    <span class="px-2 py-0.5 text-xs rounded" style="background:#fef08a;color:#854d0e">amarillo = varias cuentas posibles en el histórico</span>
                    <x-neteges-info>Al generar un fichero de bancos se abre aquí solo. También puedes elegir uno ya generado.</x-neteges-info>
                </div>
                <div class="p-4 space-y-3">
                    @if ($avisoRevisar !== '')
                        <p class="text-xs text-red-600 whitespace-pre-wrap">{{ $avisoRevisar }}</p>
                    @endif
                    @if ($revisar === '')
                        <p class="text-sm text-amber-700">Elige arriba el fichero de bancos que quieres revisar.</p>
                    @elseif ($lineasRevisar)
                        <p class="text-xs text-gray-500">
                            Pon la cuenta de las líneas vacías (te sugiere las del plan; si no existe, te pide el nombre y la crea en el plan de la base).
                            Si quieres que sirva para más casos, pon en <b>Concepto para el Maestro</b> la parte fija (p.ej. GOOGLE CLOUD) y en
                            <b>Vale para</b> si es solo para cobros o solo para pagos. Al pulsar <b>Generar</b> se escribe
                            {{ $revisar }} con la estructura de siempre, se descarga y el programa aprende de lo que has puesto.
                        </p>
                        <div class="overflow-auto border rounded-md" style="max-height:34rem">
                            <table class="min-w-full text-xs">
                                <thead class="sticky top-0 z-10 bg-gray-100 text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1 text-right">Nº</th>
                                        <th class="px-2 py-1 text-left">Fecha</th>
                                        <th class="px-2 py-1 text-left">Concepto</th>
                                        <th class="px-2 py-1 text-right">Importe</th>
                                        <th class="px-2 py-1 text-left">Contrapartida</th>
                                        <th class="px-2 py-1 text-left">Nombre</th>
                                        <th class="px-2 py-1 text-left">Concepto para el Maestro</th>
                                        <th class="px-2 py-1 text-left">Vale para</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="l in lineas" :key="l.numero">
                                        <tr x-show="visible(l)" class="border-t" :class="l.contrapartida ? '' : 'bg-amber-50'"
                                            :style="(l.varias && l.varias.length && ! l.tocada) ? 'background:#fef08a' : ''">
                                            <td class="px-2 py-1 text-right text-gray-400" x-text="l.numero"></td>
                                            <td class="px-2 py-1 whitespace-nowrap" x-text="l.fecha"></td>
                                            <td class="px-2 py-1" x-text="l.concepto" :title="l.original"></td>
                                            <td class="px-2 py-1 text-right whitespace-nowrap" :class="l.importe < 0 ? 'text-red-700' : 'text-green-700'"
                                                x-text="l.importe.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                            <td class="px-2 py-1">
                                                <input type="text" x-model="l.contrapartida" x-on:change="cuentaCambiada(l)" list="plan-cuentas-{{ $cliente }}"
                                                       class="w-24 py-0.5 text-xs font-mono border-gray-300 rounded">
                                            </td>
                                            <td class="px-2 py-1">
                                                <span x-text="nombre(l.contrapartida)"></span>
                                                <span x-show="nuevas[l.contrapartida]" class="text-indigo-600">(nueva)</span>
                                                <div x-show="l.varias && l.varias.length" class="text-xs font-medium" style="color:#854d0e"
                                                     x-text="'⚠ varias en el histórico: ' + (l.varias || []).map(c => c + (nombre(c) ? ' ' + nombre(c) : '')).join(' · ')"></div>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input type="text" x-model="l.concepto_maestro" placeholder="(opcional)"
                                                       class="w-44 py-0.5 text-xs border-gray-300 rounded">
                                            </td>
                                            <td class="px-2 py-1">
                                                <select x-model="l.vale" class="py-0.5 text-xs border-gray-300 rounded">
                                                    <option value="">cobros y pagos</option>
                                                    <option value="+">solo cobros (+)</option>
                                                    <option value="-">solo pagos (−)</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-button.primary x-on:click="$wire.generarBancos(lineas.map(l => ({ numero: l.numero, contrapartida: l.contrapartida, concepto_maestro: l.concepto_maestro, vale: l.vale })), nuevas)"
                                              wire:loading.attr="disabled" wire:target="generarBancos">
                                <span wire:loading.remove wire:target="generarBancos">✔ Generar {{ $revisar }}</span>
                                <span wire:loading wire:target="generarBancos">⏳ Generando…</span>
                            </x-button.primary>
                            <span class="text-xs text-gray-500">Se puede generar aunque queden líneas vacías, y repetir las veces que haga falta.</span>
                        </div>
                    @endif
                </div>
            </div>

            @php $borrables = array_values(array_filter($generados, fn ($g) => preg_match('/^bancos.+\.xlsx$/i', $g))); @endphp
            @if ($borrables)
                <div class="p-4 bg-white border rounded-lg shadow">
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">Ficheros de bancos generados
                        <x-neteges-info>Para empezar de cero o descartar una pasada: «Borrar» elimina el fichero y quita de la Base los movimientos provisionales que dejó (antes se guarda una copia de la Base), así se pueden volver a procesar sus extractos. Si ya lo has subido a SAGE, no hace falta borrarlo.</x-neteges-info>
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mb-2">
                        @foreach ($borrables as $g)
                            <span class="inline-flex items-center gap-1 text-sm">
                                {{ $g }}
                                <button type="button" wire:click="descartarSalida(@js([$g]))"
                                        wire:confirm="¿Borrar {{ $g }} y sus apuntes provisionales de la Base?"
                                        class="text-xs text-red-600 hover:underline">🗑 borrar</button>
                            </span>
                        @endforeach
                    </div>
                    @if (count($borrables) > 1)
                        <x-button.secondary wire:click="descartarTodos"
                                            wire:confirm="¿Borrar TODOS los ficheros de bancos generados ({{ count($borrables) }}) y sus apuntes provisionales de la Base?">
                            🗑 Borrar todos y empezar de nuevo
                        </x-button.secondary>
                    @endif
                </div>
            @endif

            @if (count($ficherosBancos) >= 2)
                <div class="p-4 bg-white border rounded-lg shadow" x-data="{ marcados: [] }">
                    <h2 class="mb-1 text-sm font-semibold text-gray-700">Juntar ficheros de bancos para subirlos a SAGE de una vez</h2>
                    <p class="mb-2 text-xs text-gray-500">
                        Marca los que quieras juntar: sale un solo bancos_junto_&lt;fecha&gt;.xlsx con la misma estructura, las líneas de todos
                        (cada una con su cuenta de banco) y el Numero de 1 en adelante. Los ficheros de cada banco no se tocan: se pueden
                        seguir revisando y subiendo por separado.
                    </p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2">
                        @foreach ($ficherosBancos as $g)
                            <label class="inline-flex items-center gap-1 text-sm">
                                <input type="checkbox" value="{{ $g }}" x-model="marcados" class="border-gray-300 rounded"> {{ $g }}
                            </label>
                        @endforeach
                    </div>
                    <x-button.secondary x-on:click="$wire.juntarBancos(marcados)" x-bind:disabled="marcados.length < 2"
                                        wire:loading.attr="disabled" wire:target="juntarBancos">
                        <span wire:loading.remove wire:target="juntarBancos">⧉ Juntar y descargar</span>
                        <span wire:loading wire:target="juntarBancos">⏳ Juntando…</span>
                    </x-button.secondary>
                </div>
            @endif

            @if ($cuentasNuevas)
                <div class="overflow-hidden bg-white border rounded-lg shadow" x-data="{ abierto: true }">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b bg-gray-50">
                        <button type="button" x-on:click="abierto = ! abierto" class="text-sm font-semibold text-gray-700">
                            <span x-text="abierto ? '▾' : '▸'"></span> Cuentas creadas aquí o en Facturas OCR, aún no en SAGE
                            <span class="font-normal text-gray-400">({{ count($cuentasNuevas) }})</span>
                        </button>
                        <span wire:loading.flex wire:target="buscarDatosCuenta" class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-full animate-pulse">⏳ Buscando en internet… (unos segundos)</span>
                    </div>
                    <div x-show="abierto" class="p-4 space-y-2">
                        <p class="text-xs text-gray-500">
                            Se comparten con Facturas OCR (no se repiten números y sus facturas se reconocen por el nombre). 🔎 busca en
                            internet el CIF y el código postal (unos céntimos por búsqueda): es una propuesta; revisa la fuente y acéptala o corrígela.
                        </p>
                        <table class="min-w-full text-xs">
                            <thead class="text-gray-600 bg-gray-100">
                                <tr>
                                    <th class="px-2 py-1 text-left">Cuenta</th><th class="px-2 py-1 text-left">Nombre</th>
                                    <th class="px-2 py-1 text-left">CIF</th><th class="px-2 py-1 text-left">CP</th>
                                    <th class="px-2 py-1 text-left">Origen</th><th class="px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cuentasNuevas as $c)
                                    @php $prop = $propuestasCif[$c['cuenta']] ?? null; @endphp
                                    <tr class="border-t align-top" wire:key="cn-{{ $cliente }}-{{ $c['cuenta'] }}-{{ md5(json_encode($c).json_encode($prop)) }}"
                                        x-data="{ cif: @js($prop['cif'] ?? $c['cif']), cp: @js($prop['cp'] ?? ($c['cp'] ?? '')) }">
                                        <td class="px-2 py-1 font-mono">{{ $c['cuenta'] }}</td>
                                        <td class="px-2 py-1">{{ $c['nombre'] }}</td>
                                        <td class="px-2 py-1"><input type="text" x-model="cif" class="w-28 py-0.5 text-xs font-mono border-gray-300 rounded" placeholder="(sin CIF)"></td>
                                        <td class="px-2 py-1"><input type="text" x-model="cp" class="w-20 py-0.5 text-xs font-mono border-gray-300 rounded"></td>
                                        <td class="px-2 py-1 text-gray-500">{{ $c['origen'] }}</td>
                                        <td class="px-2 py-1 text-right whitespace-nowrap">
                                            <button type="button" wire:click="buscarDatosCuenta(@js($c['cuenta']))" class="text-blue-700 hover:underline">🔎 Buscar</button>
                                            <button type="button" x-show="cif !== @js($c['cif']) || cp !== @js($c['cp'] ?? '')"
                                                    x-on:click="$wire.guardarDatosCuenta(@js($c['cuenta']), cif, cp)"
                                                    class="ml-2 text-green-700 hover:underline">✔ Guardar</button>
                                        </td>
                                    </tr>
                                    @php $sg = $c['sage'] ?? []; @endphp
                                    @if (! empty($sg['estado']))
                                        <tr wire:key="cns-{{ $cliente }}-{{ $c['cuenta'] }}-{{ md5(json_encode($sg)) }}"
                                            class="{{ $sg['estado'] === 'misma' ? 'bg-green-50' : 'bg-red-50' }}">
                                            <td></td>
                                            <td colspan="5" class="px-2 py-1 text-xs {{ $sg['estado'] === 'misma' ? 'text-green-800' : 'text-red-700 font-medium' }}">
                                                @if ($sg['estado'] === 'misma')
                                                    ✔ Ya está en SAGE como {{ $sg['cuenta'] }} · {{ $sg['nombre'] }}{{ $sg['cp'] !== '' ? ' · CP '.$sg['cp'] : '' }}.
                                                    Sube el plan de cuentas de SAGE y desaparece de esta lista.
                                                @elseif ($sg['estado'] === 'otra')
                                                    ⚠️ SAGE ya tiene este proveedor con OTRO número: {{ $sg['cuenta'] }} · {{ $sg['nombre'] }}. Usa la de SAGE (la {{ $c['cuenta'] }} sobra) y avisa a Facturas OCR.
                                                @else
                                                    ⚠️ En SAGE el número {{ $c['cuenta'] }} es OTRO proveedor ({{ $sg['nombre'] }}). Hay que dar a este uno nuevo.
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                    @if ($prop)
                                        <tr class="bg-indigo-50/50" wire:key="cnp-{{ $cliente }}-{{ $c['cuenta'] }}-{{ md5(json_encode($prop)) }}">
                                            <td></td>
                                            <td colspan="5" class="px-2 py-1 text-xs">
                                                @if (! empty($prop['error']))
                                                    <span class="text-red-600">⚠️ {{ $prop['error'] }}</span>
                                                @else
                                                    Propuesta{{ ! empty($prop['de_cache']) ? ' (ya buscada antes)' : '' }}:
                                                    <b>{{ $prop['nombre_oficial'] ?: '—' }}</b>
                                                    · CIF <b class="font-mono">{{ $prop['cif'] ?: '—' }}</b>
                                                    · CP <b class="font-mono">{{ $prop['cp'] ?: '—' }}</b> {{ $prop['poblacion'] ?? '' }}
                                                    {{ ! empty($prop['provincia']) ? '('.$prop['provincia'].')' : '' }}
                                                    · confianza {{ $prop['confianza'] ?: '?' }}
                                                    @if (! empty($prop['fuente']))
                                                        · <a href="{{ $prop['fuente'] }}" target="_blank" rel="noopener" class="text-blue-700 underline">fuente</a>
                                                    @endif
                                                    @if (! empty($prop['nota']))
                                                        <div class="text-gray-500">{{ $prop['nota'] }}</div>
                                                    @endif
                                                    <div class="text-gray-500">Ya está puesta arriba en CIF y CP: pulsa ✔ Guardar si es correcta.</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div id="maestro" class="overflow-hidden bg-white border rounded-lg shadow"
                 x-data="{
                     abierto: true, q: '', filtro: 'todos',
                     codigos: @js(array_map('strval', array_keys($planCuentas))),
                     // '' si la cuenta existe o va vacía; el nombre para crearla si es nueva; null si se cancela
                     nombreNueva(cuenta, concepto) {
                         const c = (cuenta || '').trim();
                         if (c === '' || this.codigos.includes(c)) return '';
                         if (! /^\d{6,}$/.test(c)) { alert('La cuenta tiene que ser un número de 6 cifras o más.'); return null; }
                         const n = prompt('La cuenta ' + c + ' no está en el plan de cuentas.\nSi es nueva (p.ej. un proveedor dado de alta en Facturas OCR y aún no en SAGE), escribe su nombre para crearla:', concepto);
                         return n && n.trim() ? n.trim() : null;
                     },
                     ver(texto, origen, pendiente, dudoso) {
                         if (this.filtro === 'manual' && origen !== 'Manual') return false;
                         if (this.filtro === 'pendientes' && ! pendiente) return false;
                         if (this.filtro === 'dudosos' && ! dudoso) return false;
                         return this.q === '' || texto.includes(this.q.toUpperCase());
                     },
                 }">
                <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-200 bg-gray-50">
                    <button type="button" x-on:click="abierto = ! abierto" class="text-sm font-semibold text-gray-700">
                        <span x-text="abierto ? '▾' : '▸'"></span> Maestro de {{ $cliente }}
                        <span class="font-normal text-gray-400">({{ count($maestro) }} filas)</span>
                    </button>
                    <input type="search" x-model="q" placeholder="Buscar concepto, cuenta o nombre…"
                           class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-72">
                    <select x-model="filtro" class="py-1 text-sm border-gray-300 rounded-md shadow-sm">
                        <option value="todos">Todas</option>
                        <option value="manual">Solo manuales</option>
                        <option value="pendientes">Sin cuenta</option>
                        <option value="dudosos">Con varias cuentas</option>
                    </select>
                    <x-neteges-info>
                        <b>SAGE</b> = sale de los mayores subidos (se rehace con cada subida).
                        <b>Appmos</b> = sale de líneas ya procesadas aquí (bancos&lt;cuenta&gt;.xlsx) que aún no han vuelto en un mayor de SAGE;
                        no se borra porque es el propio movimiento: si su cuenta no vale, edítala (crea una fila manual que manda).
                        <b>Manual</b> = añadido o corregido aquí; se guarda en la pestaña Variables de la base y
                        manda sobre SAGE. Cambiar la cuenta de una fila SAGE crea su fila manual.
                        Las filas manuales sin cuenta son conceptos de extractos que no se encontraron: ponles la cuenta.
                        <b>Vale para</b>: si un mismo concepto tiene cuenta de proveedor y de cliente (p.ej. BELLA AURORA),
                        crea dos filas manuales, una "solo pagos (−)" con la de proveedor y otra "solo cobros (+)" con la de cliente.
                        Aunque no lo pongas, si salen varias cuentas y solo una es de proveedor (40/41) o de cliente (43/44),
                        un pago se queda con la de proveedor y un cobro con la de cliente.
                    </x-neteges-info>
                    <span wire:loading.flex wire:target="guardarMaestro, borrarMaestro" class="inline-flex items-center gap-2 px-3 py-1 text-sm font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-full animate-pulse"><span class="text-lg">⏳</span> Guardando…</span>
                </div>

                <div x-show="abierto" class="p-4 space-y-3">
                    @if ($avisoMaestro !== '')
                        <p class="text-xs text-red-600 whitespace-pre-wrap">{{ $avisoMaestro }}</p>
                    @endif

                    <datalist id="plan-cuentas-{{ $cliente }}">
                        @foreach ($planCuentas as $codigo => $nombreCuenta)
                            <option value="{{ $codigo }}">{{ $nombreCuenta }}</option>
                        @endforeach
                    </datalist>

                    <div class="flex flex-wrap items-end gap-2 p-3 border border-dashed rounded-md border-indigo-300 bg-indigo-50/40"
                         x-data="{ concepto: '', cuenta: '', signo: '' }">
                        <div class="flex-1 min-w-[14rem]">
                            <label class="block text-xs text-gray-600">Nuevo concepto</label>
                            <input type="text" x-model="concepto" placeholder="p.ej. FERRETERIA MENGUAL"
                                   class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Cuenta</label>
                            <input type="text" x-model="cuenta" list="plan-cuentas-{{ $cliente }}" placeholder="400002"
                                   class="w-32 py-1 text-sm border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600" title="Para un mismo concepto con cuenta de proveedor (pagos) y de cliente (cobros): una fila para cada signo">Vale para</label>
                            <select x-model="signo" class="py-1 text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="">cobros y pagos</option>
                                <option value="+">solo cobros (+)</option>
                                <option value="-">solo pagos (−)</option>
                            </select>
                        </div>
                        <x-button.secondary
                            x-on:click="const n = nombreNueva(cuenta, concepto); if (concepto.trim() && n !== null) { $wire.guardarMaestro(concepto, cuenta, '', signo, '', n); concepto = ''; cuenta = ''; signo = ''; }">
                            ＋ Añadir
                        </x-button.secondary>
                    </div>

                    <div class="overflow-auto border rounded-md" style="max-height:32rem">
                        <table class="min-w-full text-xs">
                            <thead class="sticky top-0 bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-2 py-1 text-left">Concepto</th>
                                    <th class="px-2 py-1 text-left">Cuenta</th>
                                    <th class="px-2 py-1 text-left">Vale para</th>
                                    <th class="px-2 py-1 text-left">Nombre</th>
                                    <th class="px-2 py-1 text-right">Veces</th>
                                    <th class="px-2 py-1 text-left">Último / modif.</th>
                                    <th class="px-2 py-1 text-left">Origen</th>
                                    <th class="px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($maestro as $i => $f)
                                    <tr wire:key="maestro-{{ $cliente }}-{{ $f['origen'] }}-{{ $i }}-{{ md5($f['concepto'].$f['cuenta'].$f['signo']) }}"
                                        x-data="{ edit: false, concepto: @js($f['concepto']), cuenta: @js($f['cuenta']), signo: @js($f['signo']) }"
                                        x-show="ver(@js(mb_strtoupper($f['concepto'].' '.$f['cuenta'].' '.$f['nombre'].' '.$f['ejemplo'])), @js($f['origen']), @js($f['cuenta'] === ''), @js($f['otras'] !== ''))"
                                        class="border-t {{ $f['tapada'] ? 'text-gray-400 line-through' : '' }} {{ $f['origen'] === 'Manual' ? ($f['cuenta'] === '' ? 'bg-amber-50' : 'bg-indigo-50/50') : '' }}"
                                        @if ($f['ejemplo'] !== '') title="Ejemplo: {{ $f['ejemplo'] }}" @endif>
                                        <td class="px-2 py-1">
                                            @if ($f['origen'] === 'Manual')
                                                <span x-show="! edit">{{ $f['concepto'] }}</span>
                                                <input x-show="edit" x-cloak type="text" x-model="concepto" class="w-full py-0.5 text-xs border-gray-300 rounded">
                                            @else
                                                {{ $f['concepto'] }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-1 font-mono">
                                            <span x-show="! edit">{{ $f['cuenta'] ?: '—' }}</span>
                                            <input x-show="edit" x-cloak type="text" x-model="cuenta" list="plan-cuentas-{{ $cliente }}" class="w-24 py-0.5 text-xs border-gray-300 rounded">
                                        </td>
                                        <td class="px-2 py-1 whitespace-nowrap">
                                            <span x-show="! edit">{{ ['+' => 'solo cobros (+)', '-' => 'solo pagos (−)'][$f['signo']] ?? '' }}</span>
                                            <span x-show="edit" x-cloak>
                                                <select x-model="signo" class="py-0.5 text-xs border-gray-300 rounded">
                                                    <option value="">cobros y pagos</option>
                                                    <option value="+">solo cobros (+)</option>
                                                    <option value="-">solo pagos (−)</option>
                                                </select>
                                            </span>
                                        </td>
                                        <td class="px-2 py-1">
                                            {{ $f['nombre'] }}
                                            @if ($f['otras'] !== '')
                                                <span class="text-amber-600">(también {{ $f['otras'] }})</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1 text-right">{{ $f['veces'] ?: '' }}</td>
                                        <td class="px-2 py-1">{{ $f['ultimo'] }}</td>
                                        <td class="px-2 py-1">{{ $f['origen'] }}{{ $f['tapada'] ? ' (sustituida)' : '' }}</td>
                                        <td class="px-2 py-1 whitespace-nowrap text-right">
                                            <button type="button" x-show="! edit" x-on:click="edit = true" class="text-blue-700 hover:underline">Editar</button>
                                            <button type="button" x-show="edit" x-cloak
                                                    x-on:click="const n = nombreNueva(cuenta, concepto); if (n !== null) { edit = false; $wire.guardarMaestro(concepto, cuenta, @js($f['origen'] === 'Manual' ? $f['concepto'] : ''), signo, @js($f['signo']), n) }"
                                                    class="text-green-700 hover:underline">Guardar</button>
                                            <button type="button" x-show="edit" x-cloak
                                                    x-on:click="edit = false; concepto = @js($f['concepto']); cuenta = @js($f['cuenta']); signo = @js($f['signo'])"
                                                    class="ml-1 text-gray-500 hover:underline">Cancelar</button>
                                            @if ($f['origen'] === 'Manual')
                                                <button type="button" x-show="! edit"
                                                        x-on:click="confirm('¿Borrar la fila manual ' + @js($f['concepto']) + '?') && $wire.borrarMaestro(@js($f['concepto']), @js($f['signo']))"
                                                        class="ml-1 text-red-600 hover:underline">Borrar</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="overflow-hidden bg-white border rounded-lg shadow" x-data="{ abierto: true }">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-200 bg-gray-50">
                <button type="button" x-on:click="abierto = ! abierto" class="text-sm font-semibold text-gray-700">
                    <span x-text="abierto ? '▾' : '▸'"></span> Palabras a quitar / genéricas / abreviaturas
                    <span class="font-normal text-gray-400">(comunes a todos los clientes · Doc_y_Config\Configuracion.xlsx)</span>
                </button>
                <span wire:loading.flex wire:target="anadirConfig, anadirAbreviatura, borrarConfig, probarConcepto" class="inline-flex items-center gap-2 px-3 py-1 text-sm font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-full animate-pulse"><span class="text-lg">⏳</span> Guardando… (y rehaciendo el Maestro si cambia "Textos a quitar")</span>
            </div>
            <div x-show="abierto" class="p-4 space-y-4">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[16rem]">
                        <label class="block text-xs text-gray-600">Probar un concepto (cópialo del extracto o del mayor)</label>
                        <input type="text" wire:model="pruebaTexto" wire:keydown.enter="probarConcepto"
                               placeholder="p.ej. TRANSFERENCIA A EMERALD SKY INVESTMENT S L"
                               class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                    </div>
                    <x-button.secondary wire:click="probarConcepto">Probar</x-button.secondary>
                </div>
                @if ($pruebaResultado)
                    <div class="p-2 text-xs border rounded bg-gray-50">
                        <div>Concepto limpio (con el que se compara con el Maestro): <b class="font-mono">{{ $pruebaResultado['limpio'] !== '' ? $pruebaResultado['limpio'] : '(vacío)' }}</b></div>
                        <div>Palabras que cuentan en la búsqueda por palabras:
                            <b class="font-mono">{{ $pruebaResultado['palabras'] ? implode(' · ', $pruebaResultado['palabras']) : '(ninguna)' }}</b>
                        </div>
                        @if (isset($pruebaResultado['sage']))
                            <div>Concepto que va a SAGE (máx. 40):
                                <b class="font-mono px-1 bg-white border rounded">{{ $pruebaResultado['sage'] }}</b>
                                <span class="text-gray-400">({{ mb_strlen($pruebaResultado['sage']) }} car.)</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach (['textos' => ['Textos a quitar', 'Se borran del concepto antes de comparar. Además siempre se quitan los números (fechas, nº de tarjeta, nº de factura).', 'p.ej. PAGO'],
                               'genericas' => ['Palabras genéricas', 'No sirven para reconocer a nadie en la búsqueda por palabras (solo cuentan palabras de 5 letras o más).', 'p.ej. INVESTMENT']] as $clave => [$titulo, $ayuda, $ejemplo])
                        {{-- clave va en x-data: dentro de <x-button ...> un @js no se compila y rompería el clic --}}
                        <div x-data="{ texto: '', comentario: '', clave: @js($clave) }">
                            <h3 class="text-xs font-semibold text-gray-700">{{ $titulo }} <span class="font-normal text-gray-400">({{ count($config[$clave] ?? []) }})</span></h3>
                            <p class="mb-2 text-xs text-gray-500">{{ $ayuda }}</p>
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach ($config[$clave] ?? [] as $item)
                                    <span wire:key="cfg-{{ $clave }}-{{ md5($item['texto']) }}"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 border rounded-full"
                                          @if ($item['comentario'] !== '') title="{{ $item['comentario'] }}" @endif>
                                        <span class="font-mono">{{ $item['texto'] }}</span>
                                        <button type="button" title="Quitar" class="px-1 -mr-1 text-base font-bold leading-none text-gray-400 rounded-full hover:text-white hover:bg-red-500"
                                                x-on:click="confirm('¿Quitar ' + @js($item['texto']) + ' de {{ $titulo }}?') && $wire.borrarConfig(@js($clave), @js($item['texto']))">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <input type="text" x-model="texto" placeholder="{{ $ejemplo }}"
                                       x-on:keydown.enter="if (texto.trim()) { $wire.anadirConfig(clave, texto, comentario); texto = ''; comentario = ''; }"
                                       class="w-40 py-1 text-xs border-gray-300 rounded-md shadow-sm">
                                <input type="text" x-model="comentario" placeholder="comentario (opcional)"
                                       class="flex-1 min-w-[8rem] py-1 text-xs border-gray-300 rounded-md shadow-sm">
                                <x-button.primary
                                    x-on:click="if (texto.trim()) { $wire.anadirConfig(clave, texto, comentario); texto = ''; comentario = ''; }">
                                    ＋ Añadir
                                </x-button.primary>
                            </div>
                            @if (($avisoConfig['clave'] ?? '') === $clave)
                                <div wire:key="aviso-cfg-{{ $clave }}-{{ $avisoConfig['n'] }}"
                                     x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 6000)" x-show="ver" x-transition x-on:click="ver = false" title="Clic para cerrar"
                                     class="mt-2 px-3 py-2 text-sm font-medium rounded-md border {{ $avisoConfig['ok'] ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' }}">
                                    {{ $avisoConfig['ok'] ? '✅' : '⚠️' }} {{ $avisoConfig['texto'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div x-data="{ texto: '', abreviatura: '', comentario: '' }" class="pt-3 border-t">
                    <h3 class="text-xs font-semibold text-gray-700">Abreviaturas para SAGE <span class="font-normal text-gray-400">({{ count($config['abreviaturas'] ?? []) }})</span></h3>
                    <p class="mb-2 text-xs text-gray-500">
                        SAGE solo guarda 40 caracteres de concepto. En la columna Concepto de bancos&lt;cuenta&gt;.xlsx se quita el nº de
                        tarjeta y las formas jurídicas (S.L., SA, SLU…), se aplican estas abreviaturas y, si no cabe, se recorta el
                        nombre del proveedor (nunca los nº de factura): ELECTRICIDAD ENI PLENITUDE IBERIA S.L. F26ES-01269326 24 →
                        ELECTRICIDAD ENI PLENI F26ES-01269326 24. No afecta a la búsqueda de contrapartidas.
                    </p>
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach ($config['abreviaturas'] ?? [] as $item)
                            <span wire:key="cfg-abrev-{{ md5($item['texto']) }}"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 border rounded-full"
                                  @if ($item['comentario'] !== '') title="{{ $item['comentario'] }}" @endif>
                                <span class="font-mono">{{ $item['texto'] }} → <b>{{ $item['abreviatura'] }}</b></span>
                                <button type="button" title="Quitar" class="px-1 -mr-1 text-base font-bold leading-none text-gray-400 rounded-full hover:text-white hover:bg-red-500"
                                        x-on:click="confirm('¿Quitar la abreviatura de ' + @js($item['texto']) + '?') && $wire.borrarConfig('abreviaturas', @js($item['texto']))">&times;</button>
                            </span>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <input type="text" x-model="texto" placeholder="texto, p.ej. ADEUDO RECIBO"
                               class="w-48 py-1 text-xs border-gray-300 rounded-md shadow-sm">
                        <input type="text" x-model="abreviatura" placeholder="abreviatura, p.ej. Adeudo Rbo"
                               x-on:keydown.enter="if (texto.trim() && abreviatura.trim()) { $wire.anadirAbreviatura(texto, abreviatura, comentario); texto = ''; abreviatura = ''; comentario = ''; }"
                               class="w-40 py-1 text-xs border-gray-300 rounded-md shadow-sm">
                        <input type="text" x-model="comentario" placeholder="comentario (opcional)"
                               class="flex-1 min-w-[8rem] py-1 text-xs border-gray-300 rounded-md shadow-sm">
                        <x-button.primary
                            x-on:click="if (texto.trim() && abreviatura.trim()) { $wire.anadirAbreviatura(texto, abreviatura, comentario); texto = ''; abreviatura = ''; comentario = ''; }">
                            ＋ Añadir
                        </x-button.primary>
                    </div>
                    @if (($avisoConfig['clave'] ?? '') === 'abreviaturas')
                        <div wire:key="aviso-cfg-abreviaturas-{{ $avisoConfig['n'] }}"
                             x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 6000)" x-show="ver" x-transition x-on:click="ver = false" title="Clic para cerrar"
                             class="mt-2 px-3 py-2 text-sm font-medium rounded-md border {{ $avisoConfig['ok'] ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' }}">
                            {{ $avisoConfig['ok'] ? '✅' : '⚠️' }} {{ $avisoConfig['texto'] }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-gray-400">
                    Los cambios valen para la siguiente conciliación. Al cambiar "Textos a quitar" se rehace en el momento
                    el Maestro de todos los clientes con base (lo manual de Variables no se toca).
                </p>
            </div>
        </div>

        <div class="overflow-hidden bg-white border rounded-lg shadow" x-data="{ abierto: false }">
            <button type="button" x-on:click="abierto = ! abierto" class="flex items-center w-full gap-2 p-4 text-left bg-gray-50">
                <span x-text="abierto ? '▾' : '▸'"></span>
                <h2 class="text-sm font-semibold text-gray-700">Formatos de extracto de {{ $cliente }} ({{ count($formatos) }})</h2>
                <span class="text-xs text-gray-500">cómo se lee cada banco: se guardan solos al detectarlos, o al asignar las columnas a mano</span>
            </button>
            <div x-show="abierto" x-cloak class="p-4">
                @if (empty($formatos))
                    <div class="text-sm text-gray-400">(ninguno todavía: se irán guardando al procesar extractos)</div>
                @else
                    <table class="w-full text-sm">
                        <thead><tr class="text-xs text-left text-gray-500 border-b">
                            <th class="py-1 pr-3">Nombre</th><th class="pr-3">Columnas</th><th class="pr-3">Fichero de ejemplo</th><th class="pr-3">Cómo</th><th class="pr-3">Último uso</th><th></th>
                        </tr></thead>
                        <tbody>
                        @foreach ($formatos as $fm)
                            @php $cab = $fm['muestra'][$fm['cabecera']] ?? []; @endphp
                            <tr class="align-top border-b" wire:key="fmt-{{ $fm['id'] }}">
                                <td class="py-1 pr-3 font-medium">{{ $fm['nombre'] }}</td>
                                <td class="pr-3 text-xs">
                                    @foreach ($fm['columnas'] as $i => $rol)
                                        @if ($rol !== '')
                                            <span class="inline-block px-1 mb-0.5 bg-gray-100 rounded" title="Columna {{ $i + 1 }}">{{ $cab[$i] ?? ('col. '.($i + 1)) }} → <b>{{ \App\Http\Livewire\Contabilidad\Bancos::ROLES[$rol] ?? $rol }}</b></span>
                                        @endif
                                    @endforeach
                                </td>
                                <td class="pr-3 text-xs text-gray-600">{{ $fm['fichero'] ?? '' }}</td>
                                <td class="pr-3 text-xs">{{ ($fm['origen'] ?? '') === 'manual' ? 'a mano' : 'detectado' }}</td>
                                <td class="pr-3 text-xs text-gray-600">{{ $fm['usado'] ?? '' }}</td>
                                <td class="text-xs whitespace-nowrap">
                                    <button type="button" wire:click="editarFormato(@js($fm['id']))" class="text-blue-700 underline hover:text-blue-900">✎ Corregir</button>
                                    <button type="button" wire:click="borrarFormato(@js($fm['id']))" wire:confirm="¿Borrar el formato «{{ $fm['nombre'] }}»? La próxima vez ese extracto se detectará solo o se preguntará." class="ml-2 text-red-700 underline hover:text-red-900">Borrar</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="p-4 bg-white border rounded-lg shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">Extractos pendientes en Input</h2>
                @forelse ($pendientes as $f)
                    <div class="flex items-center gap-2 text-sm text-gray-800">
                        <span>{{ $f }}</span>
                        <button type="button" wire:click="mapearPendiente(@js($f))" class="text-xs text-blue-700 underline hover:text-blue-900"
                                title="Decir qué es cada columna del extracto (fecha, concepto, importe…)">Asignar columnas</button>
                        <button type="button" wire:click="borrarPendiente(@js($f))" wire:confirm="¿Borrar {{ $f }} de la carpeta Input? Es un extracto que no se llegó a procesar; no toca la Base."
                                class="text-xs text-red-600 hover:underline" title="Quitar este extracto de Input">🗑 quitar</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">(ninguno)</div>
                @endforelse
            </div>
            <div class="p-4 bg-white border rounded-lg shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">Generados en Output</h2>
                @forelse ($generados as $f)
                    <div class="text-sm">
                        <button type="button" wire:click="descargar(@js('Output/'.$f))" class="text-blue-700 underline hover:text-blue-900">⬇ {{ $f }}</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">(ninguno)</div>
                @endforelse
            </div>
        </div>
    @endif


    @include('livewire.contabilidad._salida', ['cargando' => false])
    </div>
    {{-- Asignar las columnas de un extracto (no reconocido, o para corregir un formato) --}}
    @if ($mapeo)
        <div class="fixed inset-0 z-40 flex items-start justify-center p-4 overflow-auto bg-black/40" wire:key="mapeo-{{ $mapeo['id'] }}-{{ $mapeo['fichero'] }}"
             x-data x-on:keydown.escape.window="$wire.cerrarMapeo()">
            <div class="w-full max-w-6xl p-4 mt-6 bg-white rounded-lg shadow-xl">
                <div class="flex items-start gap-3 mb-2">
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">Columnas del extracto <span class="font-normal text-gray-600">{{ $mapeo['fichero'] }}</span></h2>
                        @if ($mapeo['motivo'] !== '')
                            <div class="text-sm text-amber-800">⚠️ {{ $mapeo['motivo'] }}</div>
                        @endif
                        <div class="text-sm text-gray-600">
                            1) Marca la <b>fila de la cabecera</b> (la de los títulos) · 2) di qué es cada columna: una <b>Fecha</b>,
                            el <b>Importe</b> (o Debe y Haber) y una o varias de <b>Concepto</b> (se juntan). Se guarda para {{ $cliente }}
                            y la próxima vez un extracto igual entra solo.
                        </div>
                    </div>
                    <button type="button" wire:click="cerrarMapeo" class="text-2xl leading-none text-gray-400 hover:text-gray-700">&times;</button>
                </div>

                <div class="flex flex-wrap items-end gap-3 mb-2">
                    <label class="text-xs text-gray-600">Nombre del formato<br>
                        <input type="text" wire:model="mapeo.nombre" class="text-sm border-gray-300 rounded-md shadow-sm" style="width:260px" placeholder="p.ej. CaixaBank catalán">
                    </label>
                </div>

                <div class="overflow-auto border rounded" style="max-height:60vh">
                    <table class="text-xs">
                        <thead class="sticky top-0 bg-gray-100">
                            <tr>
                                <th class="px-2 py-1 text-left">Cabecera</th>
                                @foreach ($mapeo['columnas'] as $i => $rol)
                                    <th class="px-1 py-1">
                                        <select wire:model.live="mapeo.columnas.{{ $i }}" class="py-0.5 text-xs border-gray-300 rounded {{ $rol !== '' ? 'bg-indigo-50 border-indigo-400 font-semibold' : '' }}">
                                            @foreach (\App\Http\Livewire\Contabilidad\Bancos::ROLES as $k => $t)
                                                <option value="{{ $k }}">{{ $t }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mapeo['filas'] as $r => $fila)
                                @php $cabIdx = (int) $mapeo['cabecera']; @endphp
                                <tr class="border-t {{ $r === $cabIdx ? 'bg-indigo-100 font-semibold' : ($r < $cabIdx ? 'text-gray-400' : '') }}">
                                    <td class="px-2 py-0.5 whitespace-nowrap">
                                        <label class="cursor-pointer"><input type="radio" wire:model.live="mapeo.cabecera" value="{{ $r }}"> {{ $r + 1 }}</label>
                                    </td>
                                    @foreach ($fila as $i => $v)
                                        <td class="px-2 py-0.5 whitespace-nowrap {{ ($mapeo['columnas'][$i] ?? '') !== '' && $r > $cabIdx ? 'bg-indigo-50' : '' }}"
                                            style="max-width:260px; overflow:hidden; text-overflow:ellipsis" title="{{ $v }}">{{ $v }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-1 text-xs text-gray-500">Se ven las {{ count($mapeo['filas']) }} primeras filas. Los movimientos se leen desde debajo de la cabecera hasta la primera fila vacía o sin fecha.</div>

                @if ($mapeo['error'] !== '')
                    <div class="p-2 mt-2 text-sm text-red-800 border border-red-300 rounded bg-red-50">{{ $mapeo['error'] }}</div>
                @endif

                <div class="flex items-center gap-2 mt-3">
                    <x-button.primary wire:click="guardarMapeo" wire:loading.attr="disabled" wire:target="guardarMapeo">
                        <span wire:loading.remove wire:target="guardarMapeo">
                            {{ $mapeo['ruta'] !== '' && str_contains($mapeo['ruta'], '/Input/') && $mapeo['cuenta'] !== '' ? '💾 Guardar y procesar el extracto' : '💾 Guardar formato' }}
                        </span>
                        <span wire:loading wire:target="guardarMapeo">⏳ Guardando…</span>
                    </x-button.primary>
                    <x-button.secondary wire:click="cerrarMapeo">Cancelar</x-button.secondary>
                </div>
            </div>
        </div>
    @endif
    <x-contabilidad.procesando target="conciliar, responderPrevios, descartarSalida, descartarTodos, generarBancos, juntarBancos, procesarSubidas, guardarMapeo, anadirExtractos"
                               nota="Puede tardar un minuto por extracto. No cierres la página." />
</div>
