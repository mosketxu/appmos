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
            ['clave' => 'plan', 'titulo' => '📘 Plan de cuentas', 'accept' => '.xlsx,.xls', 'datos' => $estadoBase['plan'] ?? null],
            ['clave' => 'mayor', 'titulo' => '🏦 Mayor', 'accept' => '.xlsx,.xls', 'datos' => $estadoBase['mayor'] ?? null],
            ['clave' => 'ventas', 'titulo' => '🧾 Ventas', 'accept' => '.xlsx,.xls,.csv', 'datos' => null],
            ['clave' => 'listado', 'titulo' => '👥 Clientes / proveedores', 'accept' => '.xlsx,.xls,.csv', 'datos' => null],
        ];
        $otrasCuentas = $estadoBase['otras_cuentas'] ?? [];
        $ev = $estadoVentas;
    @endphp
    <details class="bg-white border rounded-lg shadow" x-data="{ abierto: false }" x-on:toggle="abierto = $el.open">
        <summary class="flex flex-wrap items-center px-4 py-2 text-xs text-gray-600 cursor-pointer gap-x-4 gap-y-1">
            <span class="text-sm font-semibold text-gray-700">Ficheros base</span>
            <span>📘 {{ ($estadoBase['plan']['cuentas'] ?? null) ? $estadoBase['plan']['cuentas'].' cuentas' : 'sin plan' }}</span>
            <span>🏦
                @forelse ($cuentas as $codigo)
                    @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                    <b>{{ $codigo }}</b>@if ($dc && $dc['hasta'] !== '') hasta {{ $dc['hasta'] }}@endif{{ $loop->last ? '' : ' ·' }}
                @empty
                    sin mayor
                @endforelse
            </span>
            <span>🧾 @if (! empty($ev['facturas'])) {{ $ev['facturas'] }} facturas {{ $ev['desde'] }}–{{ $ev['hasta'] }}
                    @if ($ev['sin_cuenta']) · <b class="text-red-700">{{ $ev['sin_cuenta'] }} clientes sin cuenta SAGE</b> @endif
                    @if ($ev['cambios']) · <b class="text-red-700">⚠️ {{ $ev['cambios'] }} distintas</b> @endif
                 @else sin ventas @endif</span>
            <span>👥 {{ ($ev['listados'] ?? []) ? implode(' · ', array_map(fn ($t, $f) => "{$t} ({$f})", array_keys($ev['listados']), $ev['listados'])) : 'sin listados' }}</span>
            <span class="ml-auto text-indigo-700" x-text="abierto ? '▲ cerrar' : '▼ subir / ver'"></span>
        </summary>

        <div class="text-xs border-t divide-y">
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
                     class="flex flex-wrap items-center px-4 py-1 gap-x-3 gap-y-1">
                    <input type="file" multiple accept="{{ $fb['accept'] }}" class="hidden" x-ref="input"
                           x-on:change="subir($event.target.files); $event.target.value = ''">
                    <div class="w-44 font-medium text-gray-800">{{ $fb['titulo'] }}</div>
                    <div class="flex-1 text-gray-600 min-w-[14rem]">
                        @if ($fb['clave'] === 'plan')
                            @if ($fb['datos']) <b>{{ $fb['datos']['cuentas'] }}</b> cuentas @else <span class="text-gray-400">(todavía nada)</span> @endif
                        @elseif ($fb['clave'] === 'mayor')
                            @forelse ($cuentas as $codigo)
                                @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                                <b>{{ $codigo }}</b>@if ($dc): {{ $dc['apuntes'] }} apuntes @if ($dc['desde'] !== '') del {{ $dc['desde'] }} al {{ $dc['hasta'] }} @endif @endif{{ $loop->last ? '' : ' · ' }}
                            @empty
                                <span class="text-gray-400">(todavía nada) — puede traer todas las cuentas: se guardan las de banco</span>
                            @endforelse
                        @elseif ($fb['clave'] === 'ventas')
                            @if (! empty($ev['facturas']))
                                <b>{{ $ev['facturas'] }}</b> facturas ({{ $ev['lineas'] }} líneas) del {{ $ev['desde'] }} al {{ $ev['hasta'] }}
                                · <b>{{ $ev['clientes'] }}</b> clientes
                                @if ($ev['sin_cuenta']) <span class="font-semibold text-red-700" title="Pestaña Clientes del Excel: columna «Cuenta a mano»">({{ $ev['sin_cuenta'] }} sin cuenta SAGE)</span> @endif
                                @if ($ev['cambios']) <span class="font-semibold text-red-700" title="Facturas que ya estaban y han llegado distintas: pestaña Cambios">· ⚠️ {{ $ev['cambios'] }} llegadas distintas</span> @endif
                                <button type="button" wire:click="descargar('Base/Ventas Neteges.xlsx')" class="ml-1 text-blue-700 underline hover:text-blue-900">⬇ Excel</button>
                                <button type="button" wire:click="vaciarVentas"
                                        wire:confirm="¿Vaciar las ventas acumuladas para volver a subirlas? (El acumulado actual se guarda en Base/Recibidos; se pierde lo puesto a mano en «Cuenta a mano».)"
                                        class="ml-1 text-red-600 underline hover:text-red-800">🗑 Vaciar</button>
                                <span class="text-gray-400" title="{{ implode("\n", $ev['ficheros']) }}">· {{ count($ev['ficheros']) }} {{ count($ev['ficheros']) === 1 ? 'fichero' : 'ficheros' }}</span>
                            @else
                                <span class="text-gray-400">(todavía nada) — varios a la vez o en varias veces; una fila por factura o por línea</span>
                            @endif
                        @elseif ($fb['clave'] === 'listado')
                            @forelse ($ev['listados'] ?? [] as $tipo => $fecha)
                                listado de <b>{{ $tipo }}</b> ({{ $fecha }}){{ $loop->last ? '' : ' · ' }}
                            @empty
                                <span class="text-gray-400">(todavía nada) — listado de clientes o de proveedores de SAGE: se reconoce cuál es</span>
                            @endforelse
                        @endif
                    </div>
                    <span x-show="subiendo" x-cloak class="text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                    <button type="button" x-on:click="$refs.input.click()" class="px-2 py-0.5 text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">⬆ Subir</button>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center px-4 py-1 text-gray-600 gap-x-2 gap-y-1">
                <span class="font-medium text-gray-800 w-44">＋ Otras cuentas de banco</span>
                <span class="text-gray-400">además de las 572…:</span>
                @forelse ($otrasCuentas as $oc)
                    <span class="inline-flex items-center gap-1 px-2 bg-gray-100 border rounded-full">
                        <b>{{ $oc }}</b>
                        <button type="button" wire:click="quitarOtraCuenta(@js($oc))"
                                wire:confirm="¿Quitar la {{ $oc }} de las cuentas de banco? Se borra su pestaña de la base (se recupera volviendo a añadirla)."
                                class="text-gray-400 hover:text-red-600" title="Quitar">&times;</button>
                    </span>
                @empty
                    <span class="text-gray-400">ninguna</span>
                @endforelse
                <input type="text" wire:model="otraCuenta" wire:keydown.enter="anadirOtraCuenta" placeholder="p.ej. 551002"
                       class="w-28 py-0 text-xs border-gray-300 rounded-md shadow-sm">
                <button type="button" wire:click="anadirOtraCuenta" class="px-2 py-0.5 text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Añadir</button>
                @error('otraCuenta')
                    <span class="text-red-600">{{ $message }}</span>
                @enderror
            </div>
            <div class="flex flex-wrap items-center px-4 py-1 gap-x-4 gap-y-1 bg-gray-50">
                <div wire:loading.flex wire:target="procesarSubidas, anadirOtraCuenta, quitarOtraCuenta, vaciarVentas" class="items-center gap-2 font-semibold text-amber-800">⏳ Actualizando…</div>
                @error('subidas')
                    <span class="text-red-600">{{ $message }}</span>
                @enderror
                @if ($hayBase)
                    <button type="button" wire:click="descargar('Base/Base Neteges.xlsx')" class="text-blue-700 underline hover:text-blue-900">⬇ Base (plan + mayor de bancos)</button>
                @endif
                @if ($recibidos)
                    <span class="text-gray-400 cursor-help" title="{{ implode("\n", $recibidos) }}">Últimos ficheros subidos ({{ count($recibidos) }})</span>
                @endif
            </div>
        </div>
    </details>

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

    @include('livewire.contabilidad._salida')
</div>
