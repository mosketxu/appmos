{{-- Una fila de «subir fichero» de Neteges: nombre · ⬆ Subir · ⓘ con el detalle (2-oct-2026).
     Recibe $fb = ['clave', 'titulo', 'accept']; el resto sale del componente. Se puede arrastrar el fichero encima. --}}
@php
    $c = $fb['clave'];
    $info = $ev['listados_info'] ?? [];
    $en = $estadoNeteges ?? [];
    $btn = 'px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
@endphp
<div wire:key="fila-{{ $c }}"
     x-data="{
         encima: false, subiendo: false, progreso: 0,
         subir(files) {
             if (! files || ! files.length) return;
             this.subiendo = true; this.progreso = 0;
             $wire.uploadMultiple('subidas', files,
                 () => { this.subiendo = false; $wire.procesarSubidas(@js($c)); },
                 () => { this.subiendo = false; },
                 (e) => { this.progreso = e.detail.progress; });
         },
     }"
     x-on:dragover.prevent="encima = true"
     x-on:dragleave.prevent="encima = false"
     x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
     :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''"
     style="display:flex; align-items:center; gap:.5rem; padding:.2rem .75rem">
    <input type="file" multiple accept="{{ $fb['accept'] }}" class="hidden" x-ref="input"
           x-on:change="subir($event.target.files); $event.target.value = ''">
    <span class="text-sm text-gray-800" style="width:10.5rem; flex:none">{{ $fb['titulo'] }}</span>
    <button type="button" x-on:click="$refs.input.click()" class="{{ $btn }}" title="Subir (también se puede arrastrar encima)">⬆ Subir</button>

    <x-neteges-info>
        @switch($c)
            @case('plan')
                <b>Plan de cuentas de SAGE</b> (con el CIF de cada cuenta).<br>
                @if ($estadoBase['plan'] ?? null)
                    {{ $estadoBase['plan']['cuentas'] }} cuentas
                    @if (! empty($estadoBase['plan']['recibido'])) · {{ $estadoBase['plan']['recibido']['fichero'] }} ({{ \Illuminate\Support\Carbon::parse($estadoBase['plan']['recibido']['fecha'])->format('d/m/Y H:i') }}) @endif
                @else sin subir @endif
                <br>Al subir uno nuevo se rehacen las cuentas SAGE de los clientes de las ventas.
                @break
            @case('mayor')
                <b>Mayor de SAGE</b>: puede traer todas las cuentas; se guardan solo las de banco y <b>sustituye lo que había de su periodo</b> (se puede volver a subir tras borrar, importar o renumerar asientos).<br>
                @if ($mayorDesde)
                    Primera fecha: <b>{{ $mayorDesde }}</b> · última: <b>{{ $mayorHasta }}</b> (de las cuentas de banco)
                    @foreach ($cuentas as $codigo)
                        @php $dc = $estadoBase['cuentas'][$codigo] ?? null; @endphp
                        @if ($dc)<br>{{ $codigo }}: {{ $dc['apuntes'] }} apuntes del {{ $dc['desde'] }} al {{ $dc['hasta'] }}@endif
                    @endforeach
                    @if (! empty($estadoBase['mayor']['recibido']))
                        <br>Último subido: {{ $estadoBase['mayor']['recibido']['fichero'] }} ({{ \Illuminate\Support\Carbon::parse($estadoBase['mayor']['recibido']['fecha'])->format('d/m/Y H:i') }})
                    @endif
                @else
                    sin subir
                @endif
                @if ($hayBase)<br><button type="button" wire:click="descargar('Base/Base Neteges.xlsx')" class="text-blue-700 underline">⬇ Excel con lo guardado (Base)</button>@endif
                @break
            @case('clientessage')
                <b>Clientes SAGE</b>: el listado de clientes tal como sale de SAGE (vale el último).<br>{{ $info['clientes'] ?? 'sin subir' }}
                @break
            @case('proveedoressage')
                <b>Proveedores SAGE</b>: el listado de proveedores tal como sale de SAGE (vale el último).<br>{{ $info['proveedores'] ?? 'sin subir' }}
                @break
            @case('misclientes')
                <b>Mis clientes</b>: el tuyo; vale el último. Manda sobre Clientes SAGE; por encima solo «Cuenta a mano» del Excel de Ventas y la cuenta que trae el fichero de ventas.<br>
                {{ $info['mis clientes'] ?? 'sin subir' }}
                @if ($ev['avisos_mis_clientes'] ?? 0)<br><b class="text-red-700">⚠️ {{ $ev['avisos_mis_clientes'] }} cuentas distintas de las del fichero de ventas</b> (Excel de Ventas, pestaña Clientes, columna «Cómo»).@endif
                @break
            @case('netcobros')
                <b>Listado de cobros</b> del programa de Neteges (cobrosMM-AA.xlsx): una fila por factura cobrada, con fecha, banco, administrador y nº de remesa.<br>
                @if ($en['cobros'] ?? null)
                    {{ $en['cobros']['facturas'] }} facturas cobradas del {{ $en['cobros']['desde'] }} al {{ $en['cobros']['hasta'] }} ({{ $en['cobros']['ficheros'] }} {{ $en['cobros']['ficheros'] === 1 ? 'fichero' : 'ficheros' }})
                @else sin subir @endif
                @break
            @case('netbbva')
                <b>Control de bancos BBVA</b> de Neteges (BBBVA26NET.xlsx): su extracto con los números de factura de cada cobro.<br>
                @if ($en['control']['BBVA'] ?? null) hasta el {{ $en['control']['BBVA']['hasta'] }} · {{ $en['control']['BBVA']['con_facturas'] }} cobros con facturas @else sin subir @endif
                @break
            @case('netsabadell')
                <b>Control de bancos Sabadell</b> de Neteges (SABADELL26NET.xlsx): su extracto con los números de factura de cada cobro.<br>
                @if ($en['control']['Sabadell'] ?? null) hasta el {{ $en['control']['Sabadell']['hasta'] }} · {{ $en['control']['Sabadell']['con_facturas'] }} cobros con facturas @else sin subir @endif
                @break
            @case('remesas')
                <b>Remesas</b> del programa de Neteges (REMESAS dd-mm.xlsx): al conciliar, cada abono de remesa se reparte por cliente y las devoluciones vuelven a su cliente.<br>
                @if ($en['remesas'] ?? null)
                    {{ $en['remesas']['remesas'] }} remesas, {{ $en['remesas']['facturas'] }} facturas ({{ $en['remesas']['devueltas'] }} devueltas) del {{ $en['remesas']['desde'] }} al {{ $en['remesas']['hasta'] }}
                @else sin subir @endif
                @if ($remesas)<br><span style="white-space:pre-line">{{ implode("\n", $remesas) }}</span>@endif
                @break
            @case('ventas')
                <b>Ventas</b> (facturas emitidas): varios ficheros a la vez o en varias veces; una fila por factura o por línea. Se juntan sin duplicados.<br>
                @if (! empty($ev['facturas']))
                    {{ $ev['facturas'] }} facturas ({{ $ev['lineas'] }} líneas) del {{ $ev['desde'] }} al {{ $ev['hasta'] }} · {{ $ev['clientes'] }} clientes
                    @if ($ev['sin_cuenta'])<br><b class="text-red-700">{{ $ev['sin_cuenta'] }} clientes sin cuenta SAGE</b> (Excel de Ventas, pestaña Clientes, columna «Cuenta a mano»)@endif
                    @if ($ev['cambios'])<br><b class="text-red-700">⚠️ {{ $ev['cambios'] }} facturas han llegado distintas de las que ya estaban</b> (pestaña Cambios)@endif
                    <br>Ficheros: <span style="white-space:pre-line">{{ implode("\n", $ev['ficheros']) }}</span>
                @else sin subir @endif
                @break
        @endswitch
    </x-neteges-info>

    {{-- Avisos que sí merecen verse sin abrir la ⓘ --}}
    @if ($c === 'misclientes' && ($ev['avisos_mis_clientes'] ?? 0))
        <span class="text-red-600" title="{{ $ev['avisos_mis_clientes'] }} cuentas distintas de las del fichero de ventas">⚠️</span>
    @endif
    @if ($c === 'ventas')
        @if (! empty($ev['facturas']))
            <button type="button" wire:click="descargar('Base/Ventas Neteges.xlsx')" class="text-xs text-blue-700 underline hover:text-blue-900" title="Excel con todas las ventas acumuladas">⬇ Excel</button>
            <button type="button" wire:click="vaciarVentas"
                    wire:confirm="¿Vaciar las ventas acumuladas para volver a subirlas? (El acumulado actual se guarda en Base/Recibidos; se pierde lo puesto a mano en «Cuenta a mano».)"
                    class="text-xs text-red-600 underline hover:text-red-800">🗑 Vaciar</button>
        @endif
        @if (($ev['sin_cuenta'] ?? 0) || ($ev['cambios'] ?? 0))
            <span class="text-red-600" title="{{ $ev['sin_cuenta'] ?? 0 }} clientes sin cuenta SAGE · {{ $ev['cambios'] ?? 0 }} facturas llegadas distintas">⚠️</span>
        @endif
    @endif
    <span x-show="subiendo" x-cloak class="text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
</div>
