<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
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
    {{-- Fila de arriba: primer bloque + Salida a su altura; lo de debajo, a todo el ancho. --}}
    <div class="fila-salida">
    <div class="space-y-6 col-principal" style="--g:65">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3">
        <span>Bancos — cliente:</span>
        <select wire:model.live="cliente" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @forelse ($clientes as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @empty
                <option value="">(no hay clientes)</option>
            @endforelse
        </select>
    </h1>

    @if ($cliente !== '')
        @php
            $filasBase = [['clave' => 'plan', 'icono' => '📘', 'titulo' => 'Plan de cuentas', 'datos' => $estadoBase['plan'] ?? null]];
            foreach ($cuentas as $codigo => $nombreCuenta) {
                $filasBase[] = ['clave' => (string) $codigo, 'icono' => '🏦', 'titulo' => $codigo.($nombreCuenta !== '' ? ' · '.$nombreCuenta : ''), 'datos' => $estadoBase['cuentas'][$codigo] ?? null];
            }
            $filasBase[] = ['clave' => 'mayor', 'icono' => '＋', 'titulo' => 'Mayor de otra cuenta', 'datos' => null];
        @endphp
        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="mb-1 text-sm font-semibold text-gray-700">Ficheros base (de SAGE)</h2>
                <p class="text-xs text-gray-500">
                    El plan de cuentas y el mayor de cada cuenta de banco, exportados de SAGE. Cada uno va en su fila
                    (arrástralo encima o pulsa ⬆): se acumulan en la base de {{ $cliente }} y lo repetido no se duplica.
                    <b>Los extractos del banco no van aquí</b>, van abajo en «Extracto a procesar».
                </p>
            </div>

            <div class="divide-y">
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
                            @if ($fb['clave'] === 'mayor')
                                <span class="text-gray-400">Una cuenta de banco nueva (572…) o otra que haga de banco (p.ej. 551002).</span>
                            @elseif (! $fb['datos'])
                                <span class="text-gray-400">(todavía nada)</span>
                            @else
                                @if ($fb['clave'] === 'plan')
                                    <b>{{ $fb['datos']['cuentas'] }}</b> cuentas
                                @else
                                    <b>{{ $fb['datos']['apuntes'] }}</b> apuntes
                                    @if ($fb['datos']['desde'] !== '') del {{ $fb['datos']['desde'] }} al {{ $fb['datos']['hasta'] }} @endif
                                    @if ($fb['datos']['provisionales'])
                                        <span class="text-amber-700" title="Movimientos que Appmos ya ha pasado a bancos{{ $fb['clave'] }}.xlsx y todavía no han vuelto en un mayor de SAGE">· {{ $fb['datos']['provisionales'] }} provisionales de Appmos</span>
                                    @endif
                                @endif
                                @if (! empty($fb['datos']['recibido']))
                                    <span class="text-gray-400">· último: {{ $fb['datos']['recibido']['fichero'] }}
                                        ({{ \Illuminate\Support\Carbon::parse($fb['datos']['recibido']['fecha'])->format('d/m/Y H:i') }})</span>
                                @endif
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span x-show="subiendo" x-cloak class="text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                            <x-button.secondary x-on:click="$refs.input.click()">⬆ {{ $fb['clave'] === 'plan' ? 'Subir plan' : 'Subir mayor' }}</x-button.secondary>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="px-4 py-2 border-t bg-gray-50">
                <div wire:loading.flex wire:target="procesarSubidas" class="items-center gap-2 mb-1 text-xs font-semibold text-amber-800">⏳ Actualizando la base…</div>
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
    </div>{{-- /col-principal --}}
    @include('livewire.contabilidad._salida', ['ancho' => 35, 'cargando' => false])
    </div>{{-- /fila-salida --}}

    @if ($cliente !== '')
        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="mb-1 text-sm font-semibold text-gray-700">Extracto a procesar → bancos{{ $cuenta ?: '572xxx' }}.xlsx</h2>
                <p class="mb-3 text-xs text-gray-500">
                    Elige la cuenta del banco y sube su extracto. Se quitan los movimientos que ya están en el mayor de
                    esa cuenta (y los que aparezcan en los otros mayores cargados, que se listan en la Salida).
                    La contrapartida de cada movimiento se busca en la base:
                    Variables (a mano) → Maestro → Plan de cuentas → palabras distintivas.
                    Los movimientos que salen en bancos&lt;cuenta&gt;.xlsx se guardan también en la base como apuntes de esa
                    cuenta (provisionales hasta que subas el mayor de SAGE), así no se repiten ni hace falta volver a bajar
                    el mayor. Si luego pones a mano en el Maestro la cuenta de un concepto sin contrapartida, se rellena
                    también en la base y en su línea del fichero de bancos. Si no hay una única cuenta posible se deja en blanco;
                    los conceptos sin ninguna coincidencia se añaden a la pestaña Variables de la base para rellenarlos.
                </p>

                @if (! $hayBase || empty($cuentas))
                    <p class="text-sm text-amber-700">Primero sube arriba el plan de cuentas y el mayor de cada cuenta de banco.</p>
                @else
                    <div class="flex flex-wrap items-start gap-4">
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Cuenta del banco</label>
                            <select wire:model.live="cuenta" class="text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="">— elige —</option>
                                @foreach ($cuentas as $codigo => $nombreCuenta)
                                    <option value="{{ $codigo }}">{{ $codigo }}{{ $nombreCuenta !== '' ? ' · '.$nombreCuenta : '' }}</option>
                                @endforeach
                            </select>
                            @error('cuenta')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex-1 min-w-[16rem]"
                             x-data="{ encima: false }"
                             x-on:dragover.prevent="encima = true"
                             x-on:dragleave.prevent="encima = false"
                             x-on:drop.prevent="encima = false; $event.dataTransfer.files.length && $wire.upload('extracto', $event.dataTransfer.files[0])">
                            <label class="block mb-1 text-xs font-medium text-gray-600">Extracto del banco</label>
                            <label :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
                                   class="flex items-center gap-2 px-3 py-2 text-sm border-2 border-dashed rounded-md cursor-pointer">
                                <input type="file" wire:model="extracto" accept=".xlsx,.xls" class="hidden">
                                <span>📄</span>
                                <span class="text-gray-700">
                                    @if ($extracto)
                                        {{ $extracto->getClientOriginalName() }}
                                    @else
                                        Arrastra aquí el extracto o haz clic para elegirlo
                                    @endif
                                </span>
                            </label>
                            <div wire:loading wire:target="extracto" class="mt-1 text-xs text-gray-400">Subiendo…</div>
                            @error('extracto')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-5">
                            <x-button.primary
                                wire:click="conciliar"
                                wire:loading.attr="disabled"
                                wire:target="conciliar, extracto"
                                :disabled="$cuenta === '' || ! $extracto"
                            >
                                <span wire:loading.remove wire:target="conciliar">▶ Generar bancos{{ $cuenta }}.xlsx</span>
                                <span wire:loading wire:target="conciliar">⏳ Procesando…</span>
                            </x-button.primary>
                        </div>
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
            <div id="maestro" class="overflow-hidden bg-white border rounded-lg shadow"
                 x-data="{
                     abierto: true, q: '', filtro: 'todos',
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
                    <span wire:loading.flex wire:target="guardarMaestro, borrarMaestro" class="inline-flex items-center gap-2 px-3 py-1 text-sm font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-full animate-pulse"><span class="text-lg">⏳</span> Guardando…</span>
                </div>

                <div x-show="abierto" class="p-4 space-y-3">
                    <p class="text-xs text-gray-500">
                        <b>SAGE</b> = sale de los mayores subidos (se rehace con cada subida).
                        <b>Manual</b> = añadido o corregido aquí; se guarda en la pestaña Variables de la base y
                        manda sobre SAGE. Cambiar la cuenta de una fila SAGE crea su fila manual.
                        Las filas manuales sin cuenta son conceptos de extractos que no se encontraron: ponles la cuenta.
                    </p>
                    @if ($avisoMaestro !== '')
                        <p class="text-xs text-red-600 whitespace-pre-wrap">{{ $avisoMaestro }}</p>
                    @endif

                    <datalist id="plan-cuentas-{{ $cliente }}">
                        @foreach ($planCuentas as $codigo => $nombreCuenta)
                            <option value="{{ $codigo }}">{{ $nombreCuenta }}</option>
                        @endforeach
                    </datalist>

                    <div class="flex flex-wrap items-end gap-2 p-3 border border-dashed rounded-md border-indigo-300 bg-indigo-50/40"
                         x-data="{ concepto: '', cuenta: '' }">
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
                        <x-button.secondary
                            x-on:click="if (concepto.trim()) { $wire.guardarMaestro(concepto, cuenta, ''); concepto = ''; cuenta = ''; }">
                            ＋ Añadir
                        </x-button.secondary>
                    </div>

                    <div class="overflow-auto border rounded-md" style="max-height:32rem">
                        <table class="min-w-full text-xs">
                            <thead class="sticky top-0 bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-2 py-1 text-left">Concepto</th>
                                    <th class="px-2 py-1 text-left">Cuenta</th>
                                    <th class="px-2 py-1 text-left">Nombre</th>
                                    <th class="px-2 py-1 text-right">Veces</th>
                                    <th class="px-2 py-1 text-left">Último / modif.</th>
                                    <th class="px-2 py-1 text-left">Origen</th>
                                    <th class="px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($maestro as $i => $f)
                                    <tr wire:key="maestro-{{ $cliente }}-{{ $f['origen'] }}-{{ $i }}-{{ md5($f['concepto'].$f['cuenta']) }}"
                                        x-data="{ edit: false, concepto: @js($f['concepto']), cuenta: @js($f['cuenta']) }"
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
                                                    x-on:click="edit = false; $wire.guardarMaestro(concepto, cuenta, @js($f['origen'] === 'Manual' ? $f['concepto'] : ''))"
                                                    class="text-green-700 hover:underline">Guardar</button>
                                            <button type="button" x-show="edit" x-cloak
                                                    x-on:click="edit = false; concepto = @js($f['concepto']); cuenta = @js($f['cuenta'])"
                                                    class="ml-1 text-gray-500 hover:underline">Cancelar</button>
                                            @if ($f['origen'] === 'Manual')
                                                <button type="button" x-show="! edit"
                                                        x-on:click="confirm('¿Borrar la fila manual ' + @js($f['concepto']) + '?') && $wire.borrarMaestro(@js($f['concepto']))"
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
                                     x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 6000)" x-show="ver" x-transition
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
                             x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 6000)" x-show="ver" x-transition
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
</div>
