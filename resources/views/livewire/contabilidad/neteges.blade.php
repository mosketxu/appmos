<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button type="button" class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                        x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)">&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.neteges'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.neteges'])

    <div class="p-4 space-y-6">

    <h1 class="text-2xl font-semibold text-gray-900">Neteges</h1>

    @php
        $filasBase = [
            ['clave' => 'plan', 'icono' => '📘', 'titulo' => 'Plan de cuentas', 'boton' => 'Subir plan', 'accept' => '.xlsx,.xls', 'datos' => $estadoBase['plan'] ?? null],
            ['clave' => 'mayor', 'icono' => '🏦', 'titulo' => 'Mayor', 'boton' => 'Subir mayor', 'accept' => '.xlsx,.xls', 'datos' => $estadoBase['mayor'] ?? null],
            ['clave' => 'ventas', 'icono' => '🧾', 'titulo' => 'Ficheros Ventas', 'boton' => 'Subir ventas', 'accept' => '.xlsx,.xls,.csv', 'datos' => null],
        ];
        $otrasCuentas = $estadoBase['otras_cuentas'] ?? [];
    @endphp
    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="mb-1 text-sm font-semibold text-gray-700">Ficheros base</h2>
            <p class="text-xs text-gray-500">
                El plan de cuentas y el mayor, exportados de SAGE, y el fichero de Ventas. Cada uno va en su fila
                (arrástralo encima o pulsa ⬆). El plan y el mayor se acumulan en la base de Neteges y lo repetido no
                se duplica; el mayor puede traer todas las cuentas: solo se guardan las de banco (las 572… y las que
                marques abajo). <b>Los extractos del banco no van aquí</b>, van abajo en «Extracto a procesar».
            </p>
        </div>

        <div class="divide-y">
            @foreach ($filasBase as $fb)
                <div wire:key="fila-base-{{ $fb['clave'] }}"
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
                    <input type="file" multiple accept="{{ $fb['accept'] }}" class="hidden" x-ref="input"
                           x-on:change="subir($event.target.files); $event.target.value = ''">
                    <div class="w-64 text-sm font-medium text-gray-800">{{ $fb['icono'] }} {{ $fb['titulo'] }}</div>
                    <div class="flex-1 min-w-[14rem] text-xs text-gray-600">
                        @if ($fb['clave'] === 'plan' && $fb['datos'])
                            <b>{{ $fb['datos']['cuentas'] }}</b> cuentas
                        @elseif ($fb['clave'] === 'mayor' && $cuentas)
                            @foreach ($cuentas as $codigo)
                                @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                                <div>
                                    <b>{{ $codigo }}</b>:
                                    @if ($dc)
                                        {{ $dc['apuntes'] }} apuntes
                                        @if ($dc['desde'] !== '') del {{ $dc['desde'] }} al {{ $dc['hasta'] }} @endif
                                    @endif
                                </div>
                            @endforeach
                        @elseif ($fb['clave'] === 'ventas' && $estadoVentas)
                            <b>{{ $estadoVentas['lineas'] }}</b> líneas
                            @if ($estadoVentas['facturas'] !== null) · <b>{{ $estadoVentas['facturas'] }}</b> facturas @endif
                            @if ($estadoVentas['desde'] !== '') del {{ $estadoVentas['desde'] }} al {{ $estadoVentas['hasta'] }} @endif
                            · {{ count($estadoVentas['ficheros']) }} {{ count($estadoVentas['ficheros']) === 1 ? 'fichero' : 'ficheros' }}
                            @if ($estadoVentas['cambios'])
                                <span class="font-semibold text-red-700" title="Facturas que ya estaban y han llegado distintas: pestaña Cambios">· ⚠️ {{ $estadoVentas['cambios'] }} facturas llegadas distintas</span>
                            @endif
                            <button type="button" wire:click="descargar('Base/Ventas Neteges.xlsx')" class="ml-1 text-blue-700 underline hover:text-blue-900">⬇ Ventas acumuladas</button>
                            <button type="button" wire:click="vaciarVentas"
                                    wire:confirm="¿Vaciar las ventas acumuladas para volver a subirlas? (El acumulado actual se guarda en Base/Recibidos.)"
                                    class="ml-1 text-red-600 underline hover:text-red-800">🗑 Vaciar</button>
                            <details class="text-gray-400">
                                <summary class="cursor-pointer">Ficheros incluidos</summary>
                                @foreach ($estadoVentas['ficheros'] as $vf)
                                    <div>{{ $vf }}</div>
                                @endforeach
                            </details>
                        @elseif ($fb['clave'] === 'ventas')
                            <span class="text-gray-400">(todavía nada) — puedes subir varios a la vez o en varias veces: se juntan sin duplicados</span>
                        @elseif (! $fb['datos'])
                            <span class="text-gray-400">(todavía nada)</span>
                        @endif
                        @if (! empty($fb['datos']['recibido']))
                            <span class="text-gray-400">último: {{ $fb['datos']['recibido']['fichero'] }}
                                ({{ \Illuminate\Support\Carbon::parse($fb['datos']['recibido']['fecha'])->format('d/m/Y H:i') }})</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span x-show="subiendo" x-cloak class="text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                        <x-button.secondary x-on:click="$refs.input.click()">⬆ {{ $fb['boton'] }}</x-button.secondary>
                    </div>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center px-4 py-2 text-xs text-gray-600 gap-x-2 gap-y-1">
                <span class="w-64 text-sm font-medium text-gray-800">＋ Otras cuentas de banco</span>
                <span class="text-gray-400">Además de las 572…, que siempre lo son:</span>
                @forelse ($otrasCuentas as $oc)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 border rounded-full">
                        <b>{{ $oc }}</b>
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

        <div class="px-4 py-2 border-t bg-gray-50">
            <div wire:loading.flex wire:target="procesarSubidas, anadirOtraCuenta, quitarOtraCuenta" class="items-center gap-2 mb-1 text-xs font-semibold text-amber-800">⏳ Actualizando la base…</div>
            @error('subidas')
                <p class="mb-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                @if ($hayBase)
                    <button type="button" wire:click="descargar('Base/Base Neteges.xlsx')" class="text-blue-700 underline hover:text-blue-900">⬇ Excel con todo lo acumulado</button>
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

    @if ($salida !== '')
        <div class="p-3 bg-gray-900 rounded-lg shadow">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-gray-300">Salida</span>
                <button type="button" wire:click="limpiarSalida" class="text-xs text-gray-400 hover:text-white">Limpiar</button>
            </div>
            <pre class="overflow-auto text-xs text-gray-100 whitespace-pre-wrap" style="max-height:20rem">{{ $salida }}</pre>
        </div>
    @endif

    <div class="overflow-hidden bg-white border rounded-lg shadow"
         x-data="{
             encima: false, subiendo: false, progreso: 0,
             subir(files) {
                 if (! files || ! files.length) return;
                 this.subiendo = true; this.progreso = 0;
                 $wire.uploadMultiple('extractos', files,
                     () => { this.subiendo = false; $wire.procesarExtractos(); },
                     () => { this.subiendo = false; },
                     (e) => { this.progreso = e.detail.progress; });
             },
         }">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="mb-1 text-sm font-semibold text-gray-700">Extractos del banco</h2>
            <p class="mb-3 text-xs text-gray-500">
                Suelta aquí todos los que quieras a la vez (Excel, XML o TXT). Se guardan en {{ $carpeta }}\Input y se
                reconoce la cuenta de cada uno: por el IBAN de dentro del fichero o, si no, por el nombre del banco en el
                nombre del fichero. Si no se reconoce, elígela en su fila. De momento solo se guardan: el proceso, después.
            </p>
            @if (empty($cuentas))
                <p class="text-sm text-amber-700">Primero sube arriba el mayor.</p>
            @else
                <label x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false"
                       x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                       :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
                       class="flex items-center gap-2 px-3 py-3 text-sm border-2 border-dashed rounded-md cursor-pointer">
                    <input type="file" multiple accept=".xlsx,.xls,.xml,.txt,.n43,.csv" class="hidden"
                           x-on:change="subir($event.target.files); $event.target.value = ''">
                    <span>📄</span>
                    <span class="text-gray-700">Arrastra aquí los extractos (varios a la vez) o haz clic para elegirlos</span>
                    <span x-show="subiendo" x-cloak class="ml-auto text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                </label>
                <div wire:loading.flex wire:target="procesarExtractos" class="items-center gap-2 mt-1 text-xs font-semibold text-amber-800">⏳ Reconociendo las cuentas…</div>
                @error('extractos')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endif
        </div>

        @if ($extractosInput)
            <table class="min-w-full text-xs">
                <thead class="text-left text-gray-500 bg-gray-50">
                    <tr><th class="px-4 py-1">Extracto (en Input)</th><th class="px-2 py-1">Tipo</th><th class="px-2 py-1">Cuenta</th><th class="px-2 py-1">Cómo</th>
                        <th class="px-2 py-1 text-right">
                            <button type="button" wire:click="borrarExtracto('')"
                                    wire:confirm="¿Quitar TODOS los extractos de Input? (Quedan en Input/Borrados.)"
                                    class="font-normal text-red-600 underline hover:text-red-800">🗑 Borrar todos</button>
                        </th></tr>
                </thead>
                <tbody>
                    @foreach ($extractosInput as $ex)
                        <tr class="border-t" wire:key="ex-{{ md5($ex['fichero']) }}">
                            <td class="px-4 py-1">
                                <button type="button" wire:click="descargar(@js('Input/'.$ex['fichero']))" class="underline hover:text-gray-900">{{ $ex['fichero'] }}</button>
                            </td>
                            <td class="px-2 py-1">{{ $ex['tipo'] }}</td>
                            <td class="px-2 py-1">
                                <select x-on:change="$wire.asignarCuenta(@js($ex['fichero']), $event.target.value)"
                                        class="py-0.5 text-xs rounded-md shadow-sm {{ $ex['cuenta'] === '' ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                    <option value="">— elige —</option>
                                    @foreach ($cuentas as $codigo)
                                        <option value="{{ $codigo }}" @selected($ex['cuenta'] === $codigo)>{{ $codigo }}{{ ($nombresCuentas[$codigo] ?? '') !== '' ? ' · '.$nombresCuentas[$codigo] : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-2 py-1 {{ $ex['cuenta'] === '' ? 'text-red-700' : 'text-gray-500' }}">{{ $ex['como'] }}</td>
                            <td class="px-2 py-1 text-right">
                                <button type="button" wire:click="borrarExtracto(@js($ex['fichero']))"
                                        wire:confirm="¿Quitar {{ $ex['fichero'] }} de Input?"
                                        class="text-gray-400 hover:text-red-600" title="Borrar">&times;</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    </div>
</div>
