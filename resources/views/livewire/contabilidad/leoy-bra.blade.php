<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.leoybra'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.leoybra'])

    <div class="p-4 space-y-4">
    <h1 class="text-2xl font-semibold text-gray-900">LeoyBra <span class="text-sm font-normal text-gray-500">Grupo Leoybra, S.L. · B06875751</span></h1>

    @php
        $btn = 'px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
        $base = $estado['base'] ?? [];
        $per = $estado['periodos'][$periodo] ?? [];
        $res = $resultado;
        $eur = fn ($x) => number_format((float) $x, 2, ',', '.').' €';
        $color = ['info' => '#1e3a8a', 'base' => '#92400e', 'proveedores' => '#9a3412', 'bancos' => '#7f1d1d', 'emitidas' => '#7f1d1d', 'recibidas' => '#7f1d1d', 'datos' => '#7f1d1d', 'pdf' => '#92400e'];
    @endphp

    <div wire:loading.flex wire:target="generar, procesarSubidas, borrarPdfs"
         style="position:fixed; top:1rem; left:50%; transform:translateX(-50%); z-index:70; align-items:center; gap:.75rem; padding:.75rem 1.5rem; background:#f59e0b; color:#fff; font-weight:700; font-size:1.05rem; border-radius:.5rem; box-shadow:0 6px 20px rgba(0,0,0,.3)">
        <span class="animate-pulse" style="font-size:1.6rem">⏳</span><span>Trabajando… no cierres la página.</span>
    </div>
    @if ($error)
        <div style="padding:.5rem .75rem; background:#fee2e2; border:1px solid #dc2626; border-radius:.4rem; color:#7f1d1d; font-size:.85rem">⚠️ {{ $error }}</div>
    @endif
    @error('subidas') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-start">

        {{-- ============ Ficheros base ============ --}}
        <div style="flex:1 1 24rem; min-width:20rem">
            <div class="bg-white border rounded-lg shadow">
                <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">Ficheros base <span class="font-normal text-gray-500">(de SAGE; se suben una vez y se actualizan cuando cambien)</span></div>
                @foreach ([['mayor', '📒 Mayor', 'Mayor de SAGE del cliente. Se pueden subir varios (se suman). Con él se sabe la cuenta de gasto de cada proveedor, la de ventas y la compensación de IVA.'],
                           ['plan', '📘 Plan de cuentas', 'Plan de cuentas de SAGE del cliente.'],
                           ['proveedores', '🏭 Proveedores', 'Listado de proveedores de SAGE (cuenta 410, CIF, contrapartida).'],
                           ['clientes', '👥 Clientes', 'Listado de clientes de SAGE (cuenta 430 y CIF). Sin él, las ventas van a clientes varios (430000).']] as [$clave, $titulo, $ayuda])
                    <div wire:key="fila-{{ $clave }}"
                         x-data="{ encima: false, subiendo: false, procesando: false,
                             subir(files) { if (! files || ! files.length) return; this.subiendo = true;
                                 $wire.uploadMultiple('subidas', files, () => { this.subiendo = false; this.procesando = true; $wire.procesarSubidas(@js($clave)).finally(() => this.procesando = false); }, () => { this.subiendo = false; }); } }"
                         x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                         :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''"
                         style="display:flex; align-items:center; gap:.5rem; padding:.3rem .75rem" title="{{ $ayuda }}">
                        <input type="file" multiple accept=".xlsx,.xls" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
                        <span class="text-sm text-gray-800" style="width:9rem; flex:none">{{ $titulo }}</span>
                        <button type="button" x-on:click="$refs.input.click()" class="{{ $btn }}">⬆ Subir</button>
                        <span class="text-xs" style="color:{{ isset($base[$clave]) ? '#047857' : '#b91c1c' }}">
                            @if (isset($base[$clave]))
                                ✔ {{ \Illuminate\Support\Str::limit(preg_replace('/^[a-z]+_\d{8}_\d{6}_/', '', $base[$clave]['nombre']), 34) }} · {{ $base[$clave]['fecha'] }}
                            @else sin subir @endif
                        </span>
                        <span x-show="subiendo || procesando" x-cloak class="text-xs text-gray-500">⏳</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ============ Trimestre ============ --}}
        <div style="flex:2 1 34rem; min-width:22rem">
            <div class="bg-white border rounded-lg shadow">
                <div class="px-4 py-2 border-b" style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap">
                    <span class="text-sm font-semibold text-gray-700">Trimestre</span>
                    <select wire:model.live="periodo" class="text-sm border border-gray-300 rounded" style="padding:.15rem 1.6rem .15rem .5rem">
                        @php
                            $periodos = collect(array_keys($estado['periodos'] ?? []))->push($periodo)->unique();
                            foreach ([date('Y') - 1, (int) date('Y')] as $a) { foreach ([1, 2, 3, 4] as $t) { $periodos->push("{$a}-{$t}T"); } }
                        @endphp
                        @foreach ($periodos->unique()->sort()->values() as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-500">Nº inicial de asiento (bancos):</span>
                    <input type="text" wire:model.blur="numeroInicial" placeholder="{{ (int) substr($periodo, 5, 1) * 100 }}" class="text-xs border border-gray-300 rounded" style="width:4rem; padding:.15rem .3rem">
                </div>

                <div style="padding:.5rem .75rem; display:flex; flex-direction:column; gap:.5rem">
                    <div wire:key="fila-datos"
                         x-data="{ encima: false, subiendo: false,
                             subir(files) { if (! files || ! files.length) return; this.subiendo = true;
                                 $wire.uploadMultiple('subidas', files, () => { $wire.procesarSubidas('datos').finally(() => this.subiendo = false); }, () => { this.subiendo = false; }); } }"
                         x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                         :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap">
                        <input type="file" accept=".xlsx,.xlsm" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
                        <span class="text-sm text-gray-800" style="width:11rem">📊 Datos del trimestre</span>
                        <button type="button" x-on:click="$refs.input.click()" class="{{ $btn }}">⬆ Subir</button>
                        <span class="text-xs text-gray-500">el Excel del cliente (Emitidas · Recibidas · Banco · Tarjeta)</span>
                        @foreach (($per['datos'] ?? []) as $f)
                            @if (str_ends_with($f, '.xlsx')) <span class="text-xs" style="color:#047857">✔ {{ \Illuminate\Support\Str::limit(preg_replace('/^\d{8}_\d{6}_/', '', $f), 40) }}</span> @endif
                        @endforeach
                    </div>
                    <div wire:key="fila-pdfs"
                         x-data="{ encima: false, subiendo: false,
                             subir(files) { if (! files || ! files.length) return; this.subiendo = true;
                                 $wire.uploadMultiple('subidas', files, () => { $wire.procesarSubidas('pdfs').finally(() => this.subiendo = false); }, () => { this.subiendo = false; }); } }"
                         x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                         :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap">
                        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
                        <span class="text-sm text-gray-800" style="width:11rem">🧾 Facturas (opcional)</span>
                        <button type="button" x-on:click="$refs.input.click()" class="{{ $btn }}">⬆ Subir</button>
                        <span class="text-xs text-gray-500">los PDF de emitidas y recibidas, para contrastar fechas e importes</span>
                        @php $nPdf = count(glob(rtrim(config('contabilidad.leoybra_dir'), '/').'/Datos/'.$periodo.'/PDF/*') ?: []); @endphp
                        @if ($nPdf)
                            <span class="text-xs" style="color:#047857">✔ {{ $nPdf }} subidas</span>
                            <button type="button" wire:click="borrarPdfs" wire:confirm="¿Quitar los {{ $nPdf }} PDF de este trimestre?" class="{{ $btn }}">🗑</button>
                        @endif
                    </div>
                    <div>
                        <button type="button" wire:click="generar" wire:loading.attr="disabled" wire:target="generar"
                                style="padding:.4rem 1.2rem; font-weight:700; background:#059669; color:#fff; border-radius:.4rem">▶ Generar {{ $periodo }}</button>
                        <span class="text-xs text-gray-500" style="margin-left:.5rem">PluginFacturas · PluginBancos · IVA · Informe</span>
                    </div>
                </div>
            </div>

            {{-- ============ Resultado ============ --}}
            @if ($res)
                <div class="bg-white border rounded-lg shadow" style="margin-top:1rem">
                    <div class="px-4 py-2 border-b text-sm font-semibold text-gray-700">Resultado {{ $res['periodo'] }} <span class="font-normal text-gray-500">generado el {{ $res['generado'] }} · {{ $res['datos'] }}</span></div>
                    <div style="padding:.6rem .75rem; display:flex; flex-wrap:wrap; gap:1.5rem; align-items:flex-start">
                        <div class="text-sm">
                            <div>Emitidas: <b>{{ $res['emitidas'] }}</b> · Recibidas con IVA: <b>{{ $res['recibidas'] }}</b> · Solo gasto: <b>{{ $res['sin_iva'] }}</b></div>
                            <div>Movimientos de banco y tarjeta: <b>{{ $res['movimientos'] }}</b></div>
                            <table class="text-sm" style="margin-top:.4rem">
                                <tr><td style="padding-right:1rem">IVA repercutido</td><td style="text-align:right">{{ $eur($res['iva']['repercutido']) }}</td></tr>
                                <tr><td>IVA soportado</td><td style="text-align:right">{{ $eur($res['iva']['soportado']) }}</td></tr>
                                <tr><td>Resultado del trimestre</td><td style="text-align:right">{{ $eur($res['iva']['resultado_trimestre']) }}</td></tr>
                                <tr><td>Compensación anterior</td><td style="text-align:right">{{ $eur($res['iva']['compensacion_anterior']) }}</td></tr>
                                <tr style="font-weight:700"><td>{{ $res['iva']['a_ingresar'] > 0 ? 'A ingresar' : 'A compensar' }}</td><td style="text-align:right">{{ $eur($res['iva']['a_ingresar'] > 0 ? $res['iva']['a_ingresar'] : $res['iva']['a_compensar_final']) }}</td></tr>
                            </table>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:.3rem">
                            @foreach ($res['archivos'] as $a)
                                <button type="button" wire:click="descargar(@js($a))" class="{{ $btn }}" style="text-align:left">⬇ {{ basename($a) }}</button>
                            @endforeach
                        </div>
                    </div>
                    @if (! empty($res['avisos']))
                        <div style="padding:.4rem .75rem .7rem; border-top:1px solid #e5e7eb">
                            <div class="text-sm font-semibold text-gray-700">Qué mirar ({{ count($res['avisos']) }})</div>
                            <ul style="margin-top:.25rem; max-height:22rem; overflow:auto">
                                @foreach ($res['avisos'] as $a)
                                    <li class="text-xs" style="padding:.15rem 0; color:{{ $color[$a['tipo']] ?? '#374151' }}"><b>{{ $a['tipo'] }}</b> · {{ $a['texto'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
    </div>
</div>
