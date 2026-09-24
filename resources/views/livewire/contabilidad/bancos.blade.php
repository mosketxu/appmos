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

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

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
        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="mb-1 text-sm font-semibold text-gray-700">Ficheros base del proceso</h2>
                <p class="mb-3 text-xs text-gray-500">
                    Uno por cada cuenta de banco (mayor de SAGE, nombre <code class="px-1 bg-gray-100 rounded">572…</code>),
                    si hace falta el mayor de otra cuenta que haga de banco (p.ej. <code class="px-1 bg-gray-100 rounded">551002</code>),
                    y el plan de cuentas. Sus filas nuevas se añaden a
                    <code class="px-1 bg-gray-100 rounded">Bancos\{{ $cliente }}\Base\Base {{ $cliente }}.xlsx</code>;
                    lo que ya estaba no se repite. La pestaña <b>Maestro</b> de ese fichero junta todos los
                    apuntes en una fila por concepto (limpio de "Transferencia a", "Fra", nº de tarjeta…) y
                    contrapartida: es contra lo que se comparará lo que se suba a SAGE.
                </p>

                <div
                    x-data="{
                        encima: false,
                        subiendo: false,
                        progreso: 0,
                        subir(files) {
                            if (! files || ! files.length) return;
                            this.subiendo = true;
                            this.progreso = 0;
                            $wire.uploadMultiple('subidas', files,
                                () => { this.subiendo = false; },
                                () => { this.subiendo = false; },
                                (e) => { this.progreso = e.detail.progress; });
                        },
                    }"
                    x-on:dragover.prevent="encima = true"
                    x-on:dragleave.prevent="encima = false"
                    x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                    x-on:click="$refs.input.click()"
                    :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
                    class="flex flex-col items-center justify-center gap-1 p-8 text-center border-2 border-dashed rounded-lg cursor-pointer"
                >
                    <input type="file" multiple accept=".xlsx,.xls" class="hidden" x-ref="input"
                           x-on:change="subir($event.target.files); $event.target.value = ''">
                    <div class="text-3xl">📂</div>
                    <div class="text-sm font-medium text-gray-700">Arrastra aquí los ficheros o haz clic para elegirlos</div>
                    <div class="text-xs text-gray-400">Excel (.xlsx / .xls), varios a la vez</div>
                    <div x-show="subiendo" x-cloak class="w-full max-w-xs mt-2">
                        <div class="h-1.5 bg-gray-200 rounded">
                            <div class="h-1.5 bg-indigo-500 rounded" :style="'width:' + progreso + '%'"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</div>
                    </div>
                    <div wire:loading wire:target="subidas" class="mt-2 text-xs text-yellow-600">⏳ Actualizando la base…</div>
                </div>

                @error('subidas')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror

            </div>

            <div class="p-4">
                @if ($hayBase)
                    <div class="mb-3 text-xs">
                        <button type="button" wire:click="descargar(@js('Base/Base '.$cliente.'.xlsx'))" class="text-blue-700 underline hover:text-blue-900">⬇ Descargar Base {{ $cliente }}.xlsx</button>
                        <span class="text-gray-400">(Maestro, Variables, cuentas y plan)</span>
                    </div>
                @endif
                <h3 class="mb-1 text-xs font-semibold text-gray-600">Últimos recibidos (Base\Recibidos)</h3>
                @forelse ($recibidos as $f)
                    <div class="text-xs text-gray-800">{{ $f }}</div>
                @empty
                    <div class="text-xs text-gray-400">(ninguno todavía)</div>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="mb-1 text-sm font-semibold text-gray-700">Extracto a procesar → bancos{{ $cuenta ?: '572xxx' }}.xlsx</h2>
                <p class="mb-3 text-xs text-gray-500">
                    Elige la cuenta del banco y sube su extracto. Se quitan los movimientos que ya están en el mayor de
                    esa cuenta (y los que aparezcan en los otros mayores cargados, que se listan en la Salida).
                    La contrapartida de cada movimiento se busca en la base:
                    Variables (a mano) → Maestro → Plan de cuentas → palabras distintivas. Si no hay una única cuenta posible se deja en blanco;
                    los conceptos sin ninguna coincidencia se añaden a la pestaña Variables de la base para rellenarlos.
                </p>

                @if (! $hayBase || empty($cuentas))
                    <p class="text-sm text-amber-700">Primero sube arriba los ficheros base (mayores 572… / 551… y plan de cuentas).</p>
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
                        <x-contabilidad.resultado-fichero :r="$r" />
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-hidden bg-white border rounded-lg shadow" x-data="{ abierto: true }">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-200 bg-gray-50">
                <button type="button" x-on:click="abierto = ! abierto" class="text-sm font-semibold text-gray-700">
                    <span x-text="abierto ? '▾' : '▸'"></span> Palabras a quitar / genéricas
                    <span class="font-normal text-gray-400">(comunes a todos los clientes · Doc_y_Config\Configuracion.xlsx)</span>
                </button>
                <span wire:loading wire:target="anadirConfig, borrarConfig, probarConcepto" class="text-xs text-yellow-600">⏳</span>
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
                                        <button type="button" class="text-gray-400 hover:text-red-600"
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
                                <x-button.secondary
                                    x-on:click="if (texto.trim()) { $wire.anadirConfig(clave, texto, comentario); texto = ''; comentario = ''; }">
                                    ＋
                                </x-button.secondary>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400">
                    Los cambios valen para la siguiente conciliación. Al cambiar "Textos a quitar" se rehace en el momento
                    el Maestro de todos los clientes con base (lo manual de Variables no se toca).
                </p>
            </div>
        </div>

        @if ($hayBase)
            <div class="overflow-hidden bg-white border rounded-lg shadow"
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
                    <span wire:loading wire:target="guardarMaestro, borrarMaestro" class="text-xs text-yellow-600">⏳ Guardando…</span>
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

        <div class="grid gap-4 md:grid-cols-2">
            <div class="p-4 bg-white border rounded-lg shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">Extractos pendientes en Input</h2>
                @forelse ($pendientes as $f)
                    <div class="text-sm text-gray-800">{{ $f }}</div>
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

    </div>{{-- /columna izquierda --}}

    <div class="w-full" style="flex:35 1 0;min-width:0">
        <div class="sticky top-4">
            <div class="flex justify-start mb-2">
                <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
            </div>
            <div class="p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}">
                <h2 class="mb-2 text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
                <pre class="overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" style="max-height:calc(100vh - 9rem)">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
            </div>
        </div>
    </div>

    </div>{{-- /flex 2 columnas --}}
    </div>
</div>
