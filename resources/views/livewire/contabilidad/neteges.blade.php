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

    <div class="p-4 space-y-4">

    <h1 class="text-2xl font-semibold text-gray-900">Neteges</h1>

    @php
        $ev = $estadoVentas;
        $lis = $ev['listados'] ?? [];
        $otrasCuentas = $estadoBase['otras_cuentas'] ?? [];
        $xls = '.xlsx,.xls,.csv';
        $bloques = [
            'comunes' => ['titulo' => '📚 Ficheros comunes', 'filas' => [
                ['clave' => 'plan', 'titulo' => '📘 Plan de cuentas', 'accept' => '.xlsx,.xls'],
                ['clave' => 'clientessage', 'titulo' => '👥 Clientes SAGE', 'accept' => $xls],
                ['clave' => 'proveedoressage', 'titulo' => '🏭 Proveedores SAGE', 'accept' => $xls],
                ['clave' => 'misclientes', 'titulo' => '✍️ Mis clientes', 'accept' => $xls],
            ]],
            'bancos' => ['titulo' => '🏦 Bancos', 'filas' => [
                ['clave' => 'mayor', 'titulo' => '📒 Mayor', 'accept' => '.xlsx,.xls'],
                ['clave' => 'netcobros', 'titulo' => '📥 Ficheros de Neteges', 'accept' => '.xlsx,.xls'],
            ]],
            'ventas' => ['titulo' => '🧾 Ventas', 'filas' => [
                ['clave' => 'ventas', 'titulo' => '🧾 Ficheros de ventas', 'accept' => $xls],
            ]],
            'remesas' => ['titulo' => '📑 Remesas', 'filas' => [
                ['clave' => 'remesas', 'titulo' => '📑 Ficheros de remesas', 'accept' => '.xlsx,.xls,.csv,.txt,.xml,.pdf,.q19,.n19'],
            ]],
        ];
        $resumen = [
            'comunes' => (($estadoBase['plan']['cuentas'] ?? null) ? $estadoBase['plan']['cuentas'].' cuentas' : 'sin plan')
                .' · clientes SAGE '.(($lis['clientes'] ?? null) ?: 'no').' · proveedores SAGE '.(($lis['proveedores'] ?? null) ?: 'no')
                .' · mis clientes '.(($lis['mis clientes'] ?? null) ?: 'no'),
            'bancos' => ($cuentas ? implode(' · ', array_map(fn ($c) => $c.((($estadoBase['cuentas'][$c]['hasta'] ?? '') !== '') ? ' hasta '.$estadoBase['cuentas'][$c]['hasta'] : ''), $cuentas)) : 'sin mayor'),
            'ventas' => ! empty($ev['facturas'])
                ? "{$ev['facturas']} facturas {$ev['desde']}–{$ev['hasta']} · {$ev['clientes']} clientes".($ev['sin_cuenta'] ? " · {$ev['sin_cuenta']} sin cuenta SAGE" : ' · todos con cuenta SAGE')
                : 'sin ventas',
            'remesas' => $remesas ? count($remesas).' '.(count($remesas) === 1 ? 'fichero' : 'ficheros').' (se procesarán más adelante)' : 'segundo proceso, pendiente',
        ];
        $boton = 'px-2 py-0.5 text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
    @endphp

    <div wire:loading.flex wire:target="procesarSubidas, anadirOtraCuenta, quitarOtraCuenta, vaciarVentas" class="items-center gap-2 text-xs font-semibold text-amber-800">⏳ Actualizando…</div>
    @error('subidas')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror

    @foreach ($bloques as $bk => $bloque)
        <details open class="bg-white border rounded-lg shadow" wire:key="bloque-{{ $bk }}" x-data="{ abierto: true }" x-on:toggle="abierto = $el.open">
            <summary class="flex flex-wrap items-center px-4 py-2 text-xs text-gray-600 cursor-pointer gap-x-4 gap-y-1">
                <span class="text-sm font-semibold text-gray-700">{{ $bloque['titulo'] }}</span>
                <span class="{{ $bk === 'ventas' && ($ev['sin_cuenta'] ?? 0) ? 'text-red-700' : '' }}">{{ $resumen[$bk] }}</span>
                <span class="ml-auto text-indigo-700" x-text="abierto ? '▲ plegar' : '▼ desplegar'"></span>
            </summary>
            <div class="text-xs border-t divide-y">
                @foreach ($bloque['filas'] as $fb)
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
                        <div class="font-medium text-gray-800 w-44">{{ $fb['titulo'] }}</div>
                        <div class="flex-1 text-gray-600 min-w-[14rem]">
                            @switch($fb['clave'])
                                @case('plan')
                                    @if ($estadoBase['plan'] ?? null) <b>{{ $estadoBase['plan']['cuentas'] }}</b> cuentas @else <span class="text-gray-400">(todavía nada)</span> @endif
                                    @break
                                @case('mayor')
                                    @forelse ($cuentas as $codigo)
                                        @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                                        <b>{{ $codigo }}</b>@if ($dc): {{ $dc['apuntes'] }} apuntes @if ($dc['desde'] !== '') del {{ $dc['desde'] }} al {{ $dc['hasta'] }} @endif @endif{{ $loop->last ? '' : ' · ' }}
                                    @empty
                                        <span class="text-gray-400">(todavía nada) — puede traer todas las cuentas: se guardan las de banco</span>
                                    @endforelse
                                    @if ($hayBase)
                                        <button type="button" wire:click="descargar('Base/Base Neteges.xlsx')" class="ml-1 text-blue-700 underline hover:text-blue-900">⬇ Base</button>
                                    @endif
                                    @break
                                @case('netcobros')
                                    @php $en = $estadoNeteges; @endphp
                                    @if ($en['cobros'] ?? null)
                                        cobros: <b>{{ $en['cobros']['facturas'] }}</b> facturas cobradas del {{ $en['cobros']['desde'] }} al {{ $en['cobros']['hasta'] }}
                                    @else
                                        <span class="text-gray-400">sin listado de cobros</span>
                                    @endif
                                    @foreach ($en['control'] ?? [] as $banco => $d)
                                        · control {{ $banco }} hasta el <b>{{ $d['hasta'] }}</b>
                                    @endforeach
                                    <span class="text-gray-400">— los Excel de su programa (cobrosMM-AA, BBBVA26NET, SABADELL26NET): dicen qué facturas paga cada cobro y cada remesa</span>
                                    <button type="button" wire:click="buscarEnCorreo" wire:loading.attr="disabled" wire:target="buscarEnCorreo"
                                            class="ml-1 text-blue-700 underline hover:text-blue-900">
                                        <span wire:loading.remove wire:target="buscarEnCorreo">📧 Buscar en el correo</span><span wire:loading wire:target="buscarEnCorreo">⏳ Buscando en Outlook…</span>
                                    </button>
                                    @break
                                @case('proveedoressage')
                                    @if ($ev['listados_info']['proveedores'] ?? null) <b>{{ $ev['listados_info']['proveedores'] }}</b> @else <span class="text-gray-400">(todavía nada)</span> @endif
                                    <span class="text-gray-400">— el listado de proveedores tal como sale de SAGE (vale el último)</span>
                                    @break
                                @case('ventas')
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
                                    @break
                                @case('clientessage')
                                    @if ($ev['listados_info']['clientes'] ?? null) <b>{{ $ev['listados_info']['clientes'] }}</b> @else <span class="text-gray-400">(todavía nada)</span> @endif
                                    <span class="text-gray-400">— el listado de clientes tal como sale de SAGE (vale el último)</span>
                                    @break
                                @case('misclientes')
                                    @if ($ev['listados_info']['mis clientes'] ?? null) <b>{{ $ev['listados_info']['mis clientes'] }}</b>
                                        @if ($ev['avisos_mis_clientes'] ?? 0) <span class="font-semibold text-red-700" title="Pestaña Clientes del Excel de Ventas: «⚠️ Mis clientes dice…»">· ⚠️ {{ $ev['avisos_mis_clientes'] }} cuentas distintas de las del fichero de ventas</span> @endif
                                    @else <span class="text-gray-400">(todavía nada)</span> @endif
                                    <span class="text-gray-400">— el tuyo: vale el último; manda sobre Clientes SAGE (por encima solo «Cuenta a mano» y la cuenta que trae el fichero de ventas)</span>
                                    @break
                                @case('remesas')
                                    @if ($remesas)
                                        <span title="{{ implode("\n", $remesas) }}"><b>{{ count($remesas) }}</b> {{ count($remesas) === 1 ? 'fichero' : 'ficheros' }}</span>
                                    @else
                                        <span class="text-gray-400">(todavía nada)</span>
                                    @endif
                                    <span class="text-gray-400">— de momento solo se guardan (Base/Remesas): el proceso, más adelante</span>
                                    @break
                            @endswitch
                        </div>
                        <span x-show="subiendo" x-cloak class="text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                        <button type="button" x-on:click="$refs.input.click()" class="{{ $boton }}">⬆ Subir</button>
                    </div>
                @endforeach

                @if ($bk === 'ventas')
                    @php $ep = $estadoPlugin; @endphp
                    <div class="flex flex-wrap items-center px-4 py-2 border-t-2 border-gray-200 gap-x-3 gap-y-1">
                        <span class="text-sm font-semibold text-gray-700">📤 Plugin de emitidas para SAGE</span>
                        <span class="text-gray-600">
                            @if (($ep['pendientes'] ?? 0) > 0)
                                <b>{{ $ep['pendientes'] }}</b> facturas pendientes de subir
                                <span class="text-gray-400">({{ implode(' · ', array_map(fn ($m, $n) => \Illuminate\Support\Carbon::parse($m.'-01')->translatedFormat('M Y').": {$n}", array_keys($ep['por_mes'] ?? []), $ep['por_mes'] ?? [])) }})</span>
                            @else
                                <span class="text-gray-400">ninguna pendiente: todas están en SAGE o en un plugin ya hecho</span>
                            @endif
                        </span>
                        <span class="text-gray-500">desde</span>
                        <input type="date" wire:model="pluginDesde" class="py-0 text-xs border-gray-300 rounded-md shadow-sm">
                        <span class="text-gray-500">hasta</span>
                        <input type="date" wire:model="pluginHasta" class="py-0 text-xs border-gray-300 rounded-md shadow-sm">
                        <button type="button" wire:click="prepararPlugin" wire:loading.attr="disabled" wire:target="prepararPlugin"
                                class="px-3 py-1 font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            <span wire:loading.remove wire:target="prepararPlugin">Preparar plugin</span><span wire:loading wire:target="prepararPlugin">⏳ Preparando…</span>
                        </button>
                        <span class="text-gray-400">(sin fechas: todas las pendientes)</span>
                    </div>
                    @foreach ($plugins as $pf)
                        <div class="flex items-center px-4 py-1 gap-x-3 text-gray-600">
                            <button type="button" wire:click="descargar(@js('Output/'.$pf))" class="text-blue-700 underline hover:text-blue-900">⬇ {{ $pf }}</button>
                            <button type="button" wire:click="desmarcarPlugin(@js($pf))"
                                    wire:confirm="¿Volver a dejar pendientes las facturas de {{ $pf }}? (Solo si NO se llegó a importar en SAGE.)"
                                    class="text-gray-400 hover:text-red-600" title="Volver a dejarlas pendientes">↺ no se importó</button>
                        </div>
                    @endforeach
                @endif
                @if ($bk === 'bancos')
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
                               class="py-0 text-xs border-gray-300 rounded-md shadow-sm w-28">
                        <button type="button" wire:click="anadirOtraCuenta" class="{{ $boton }}">Añadir</button>
                        @error('otraCuenta')
                            <span class="text-red-600">{{ $message }}</span>
                        @enderror
                    </div>
                    @include('livewire.contabilidad.neteges._extractos')
                    <div class="flex flex-wrap items-center px-4 py-2 border-t-2 border-gray-200 gap-x-3 gap-y-1">
                        <span class="text-sm font-semibold text-gray-700">⚖️ Conciliar cobros</span>
                        <span class="text-gray-500">cada ingreso → su(s) factura(s) de Ventas y su cuenta 430 (las remesas, aparte)</span>
                        <button type="button" wire:click="conciliar" wire:loading.attr="disabled" wire:target="conciliar"
                                class="px-3 py-1 font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            <span wire:loading.remove wire:target="conciliar">Conciliar</span><span wire:loading wire:target="conciliar">⏳ Conciliando…</span>
                        </button>
                        @if ($hayConciliacion)
                            <button type="button" wire:click="descargar('Output/Conciliacion cobros Neteges.xlsx')" class="text-blue-700 underline hover:text-blue-900">⬇ Resultado ({{ $hayConciliacion }})</button>
                        @endif
                    </div>
                    @if ($recibidos)
                        <div class="px-4 py-1 text-gray-400 bg-gray-50">
                            <span class="cursor-help" title="{{ implode("\n", $recibidos) }}">Últimos ficheros subidos ({{ count($recibidos) }})</span>
                        </div>
                    @endif
                @endif
            </div>
        </details>
    @endforeach

    </div>

    @include('livewire.contabilidad._salida')
</div>
