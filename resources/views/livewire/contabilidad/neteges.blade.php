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
        $otrasCuentas = $estadoBase['otras_cuentas'] ?? [];
        $xls = '.xlsx,.xls,.csv';
        $btn = 'px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
        // Primera y última fecha del mayor (de las cuentas de banco que se guardan)
        $fechasMayor = collect($estadoBase['cuentas'] ?? [])
            ->flatMap(fn ($c) => array_filter([$c['desde'] ?? '', $c['hasta'] ?? '']))
            ->map(fn ($d) => \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $d))
            ->sort()->values();
        $mayorDesde = $fechasMayor->first()?->format('d/m/Y');
        $mayorHasta = $fechasMayor->last()?->format('d/m/Y');
        $ep = $estadoPlugin;
    @endphp

    <div wire:loading.flex wire:target="procesarSubidas, anadirOtraCuenta, quitarOtraCuenta, vaciarVentas, buscarEnCorreo" class="items-center gap-2 text-xs font-semibold text-amber-800">⏳ Actualizando…</div>
    @error('subidas')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror

    {{-- Dos mitades: datos a la izquierda, procesos a la derecha (se apilan en pantallas estrechas) --}}
    <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-start">

        {{-- ============ Datos informativos ============ --}}
        <div style="flex:1 1 28rem; min-width:20rem">
            <details open class="bg-white border rounded-lg shadow" wire:key="datos">
                <summary class="px-4 py-2 text-sm font-semibold text-gray-700 cursor-pointer">Datos informativos</summary>
                <div class="pb-2 border-t">
                    <div class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-gray-500 uppercase">Comunes</div>
                    @foreach ([
                        ['clave' => 'plan', 'titulo' => '📘 Plan de cuentas', 'accept' => '.xlsx,.xls'],
                        ['clave' => 'clientessage', 'titulo' => '👥 Clientes SAGE', 'accept' => $xls],
                        ['clave' => 'proveedoressage', 'titulo' => '🏭 Proveedores SAGE', 'accept' => $xls],
                        ['clave' => 'misclientes', 'titulo' => '✍️ Mis clientes', 'accept' => $xls],
                        ['clave' => 'mayor', 'titulo' => '📒 Mayor', 'accept' => '.xlsx,.xls'],
                    ] as $fb)
                        @include('livewire.contabilidad.neteges._fila')
                    @endforeach

                    <div class="flex items-center px-3 pt-3 pb-1 gap-x-2">
                        <span class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Ficheros de Neteges</span>
                        <button type="button" wire:click="buscarEnCorreo" wire:loading.attr="disabled" wire:target="buscarEnCorreo"
                                class="text-xs text-blue-700 underline hover:text-blue-900" title="Busca en tu Outlook los Excel que manda Neteges y los guarda">
                            <span wire:loading.remove wire:target="buscarEnCorreo">📧 Buscar en el correo</span><span wire:loading wire:target="buscarEnCorreo">⏳ Buscando…</span>
                        </button>
                    </div>
                    @foreach ([
                        ['clave' => 'netcobros', 'titulo' => '💶 cobrosMM-AA', 'accept' => '.xlsx,.xls'],
                        ['clave' => 'remesas', 'titulo' => '📑 REMESAS dd-mm', 'accept' => '.xlsx,.xls,.csv,.txt,.xml,.pdf,.q19,.n19'],
                    ] as $fb)
                        @include('livewire.contabilidad.neteges._fila')
                    @endforeach

                    <div class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-gray-500 uppercase">Bancos</div>
                    @foreach ([
                        ['clave' => 'netbbva', 'titulo' => '🏦 BBBVA26NET', 'accept' => '.xlsx,.xls'],
                        ['clave' => 'netsabadell', 'titulo' => '🏦 SABADELL26NET', 'accept' => '.xlsx,.xls'],
                    ] as $fb)
                        @include('livewire.contabilidad.neteges._fila')
                    @endforeach
                    <div class="flex flex-wrap items-center px-3 py-1 gap-x-2 gap-y-1">
                        <span class="text-sm text-gray-800" style="width:10.5rem; flex:none">＋ Otras cuentas</span>
                        @forelse ($otrasCuentas as $oc)
                            <span class="inline-flex items-center gap-1 px-2 text-xs bg-gray-100 border rounded-full">
                                <b>{{ $oc }}</b>
                                <button type="button" wire:click="quitarOtraCuenta(@js($oc))"
                                        wire:confirm="¿Quitar la {{ $oc }} de las cuentas de banco? Se borra su pestaña de la base (se recupera volviendo a añadirla)."
                                        class="text-gray-400 hover:text-red-600" title="Quitar">&times;</button>
                            </span>
                        @empty
                            <span class="text-xs text-gray-400">ninguna</span>
                        @endforelse
                        <input type="text" wire:model="otraCuenta" wire:keydown.enter="anadirOtraCuenta" placeholder="p.ej. 551002"
                               class="py-0 text-xs border-gray-300 rounded-md shadow-sm" style="width:6.5rem">
                        <button type="button" wire:click="anadirOtraCuenta" class="{{ $btn }}">Añadir</button>
                        <x-neteges-info>
                            <b>Otras cuentas de banco</b>: además de las 572…, que siempre lo son, cuentas que se tratan como de banco al leer el mayor
                            (p.ej. la 551002 de Pevima). Al añadir una se guarda su pestaña en la base; al quitarla se borra (se recupera volviendo a añadirla).
                        </x-neteges-info>
                        @error('otraCuenta')
                            <span class="text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    @if ($recibidos)
                        <div class="px-3 pt-2 text-xs text-gray-500">
                            Historial de subidas
                            <x-neteges-info>
                                <b>Últimos ficheros subidos</b> (los más recientes primero; los originales están en Base/Recibidos):
                                <span style="white-space:pre-line; display:block; margin-top:.25rem">{{ implode("\n", $recibidos) }}</span>
                            </x-neteges-info>
                        </div>
                    @endif
                </div>
            </details>
        </div>

        {{-- ============ Procesos ============ --}}
        <div style="flex:1 1 28rem; min-width:20rem; display:flex; flex-direction:column; gap:1rem">

            <details open class="bg-white border rounded-lg shadow" wire:key="proc-conciliar">
                <summary class="px-4 py-2 text-sm font-semibold text-gray-700 cursor-pointer">⚖️ Conciliar bancos y remesas</summary>
                <div class="border-t">
                    @include('livewire.contabilidad.neteges._extractos')
                    <div class="flex flex-wrap items-center px-3 py-2 border-t gap-x-3 gap-y-1">
                        <button type="button" wire:click="conciliar" wire:loading.attr="disabled" wire:target="conciliar"
                                class="px-3 py-1 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            <span wire:loading.remove wire:target="conciliar">Conciliar</span><span wire:loading wire:target="conciliar">⏳ Conciliando…</span>
                        </button>
                        <x-neteges-info>
                            <b>Conciliar</b>: cada ingreso del extracto → la(s) factura(s) de Ventas que paga y la 430 de su cliente; las remesas se
                            reparten por cliente (ficheros REMESAS) y las devoluciones vuelven a su cliente. Deja tres ficheros:<br>
                            · <b>Resultado</b>: cada cobro, cómo se ha sacado y si coincide con tu mayor.<br>
                            · <b>1. Asientos a borrar</b>: los asientos de SAGE a borrar, por bloques (pestaña Bloques).<br>
                            · <b>2. Sustitución para SAGE</b>: para importar con la guía de Bancos después de borrar; cada cobro contra la 430 de su cliente.<br>
                            Lo que no se sabe de quién es se queda en la 430000000.
                        </x-neteges-info>
                        @if ($hayConciliacion)
                            <button type="button" wire:click="descargar('Output/Conciliacion cobros Neteges.xlsx')" class="text-xs text-blue-700 underline hover:text-blue-900"
                                    title="Hecho el {{ $hayConciliacion }}">⬇ Resultado</button>
                        @endif
                        @if ($haySustitucion)
                            <button type="button" wire:click="descargar('Output/Asientos a borrar Neteges.xlsx')" class="text-xs text-blue-700 underline hover:text-blue-900"
                                    title="Asientos de SAGE a borrar, por bloques (pestaña Bloques)">⬇ 1. Asientos a borrar</button>
                            <button type="button" wire:click="descargar('Output/Sustitucion cobros Neteges.xlsx')" class="text-xs text-blue-700 underline hover:text-blue-900"
                                    title="Para importar en SAGE (formato Bancos) después de borrar · hecho el {{ $haySustitucion }}">⬇ 2. Sustitución para SAGE</button>
                        @endif
                    </div>
                </div>
            </details>

            <details open class="bg-white border rounded-lg shadow" wire:key="proc-plugin">
                <summary class="px-4 py-2 text-sm font-semibold text-gray-700 cursor-pointer">📤 Plugin de ventas para SAGE</summary>
                <div class="py-1 border-t">
                    @php $fb = ['clave' => 'ventas', 'titulo' => '🧾 Ficheros de ventas', 'accept' => $xls]; @endphp
                    @include('livewire.contabilidad.neteges._fila')
                    <div class="flex flex-wrap items-center px-3 py-2 border-t gap-x-2 gap-y-1">
                        <span class="text-xs text-gray-600">
                            @if (($ep['pendientes'] ?? 0) > 0)
                                <b>{{ $ep['pendientes'] }}</b> facturas pendientes
                                <x-neteges-info>
                                    Facturas de Ventas que no están en SAGE (último mayor) ni en un plugin ya hecho:
                                    {{ implode(' · ', array_map(fn ($m, $n) => \Illuminate\Support\Carbon::parse($m.'-01')->translatedFormat('M Y').": {$n}", array_keys($ep['por_mes'] ?? []), $ep['por_mes'] ?? [])) }}.
                                </x-neteges-info>
                            @else
                                <span class="text-gray-500">Ninguna pendiente</span>
                                <x-neteges-info>Todas las facturas de Ventas están ya en SAGE o en un plugin ya hecho.</x-neteges-info>
                            @endif
                        </span>
                        <span class="text-xs text-gray-500">desde</span>
                        <input type="date" wire:model="pluginDesde" class="py-0 text-xs border-gray-300 rounded-md shadow-sm">
                        <span class="text-xs text-gray-500">hasta</span>
                        <input type="date" wire:model="pluginHasta" class="py-0 text-xs border-gray-300 rounded-md shadow-sm">
                        <button type="button" wire:click="prepararPlugin" wire:loading.attr="disabled" wire:target="prepararPlugin"
                                class="px-3 py-1 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            <span wire:loading.remove wire:target="prepararPlugin">Preparar plugin</span><span wire:loading wire:target="prepararPlugin">⏳ Preparando…</span>
                        </button>
                        <x-neteges-info>
                            <b>Plugin de emitidas</b>: el Excel con la plantilla del plugin de SAGE (pestaña Emitidas) para las facturas pendientes del periodo
                            (sin fechas: todas). Una fila por factura; si una factura tiene varias contrapartidas 705, va con la de mayor base y el reparto
                            queda en un Excel aparte («repartir 705»). Las facturas que entran quedan marcadas para no repetirlas.
                        </x-neteges-info>
                    </div>
                    @foreach ($plugins as $pf)
                        <div class="flex items-center px-3 py-0.5 gap-x-3 text-xs text-gray-600">
                            <button type="button" wire:click="descargar(@js('Output/'.$pf))" class="text-blue-700 underline hover:text-blue-900">⬇ {{ $pf }}</button>
                            <button type="button" wire:click="desmarcarPlugin(@js($pf))"
                                    wire:confirm="¿Volver a dejar pendientes las facturas de {{ $pf }}? (Solo si NO se llegó a importar en SAGE.)"
                                    class="text-gray-400 hover:text-red-600" title="Volver a dejarlas pendientes (solo si no se importó)">↺ no se importó</button>
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    </div>

    @include('livewire.contabilidad._salida')
</div>
