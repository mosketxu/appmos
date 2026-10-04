<div class="space-y-3">
    @php
        $btn = 'px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
        $eur = fn ($x) => number_format((float) $x, 2, ',', '.');
        $fecha = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
        $nRev = count($res['revisadas'] ?? []);
    @endphp

    <p class="text-sm text-gray-600">Revisa el <b>mayor</b> de la empresa y propone conciliaciones y la aplicación de provisiones. <b>Solo informa</b>: no cambia nada en SAGE. «✔ Revisado» lo da por visto y no se vuelve a proponer.
        Usa los <b>ficheros base de la empresa</b> (los mismos que ven los demás procesos).</p>

    <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-semibold text-gray-700">Empresa</label>
        <select wire:model.live="entidadId" class="py-1 text-sm border-gray-300 rounded-md shadow-sm" style="min-width:18rem">
            @foreach ($empresas as $e) <option value="{{ $e->id }}">{{ $e->entidad }}</option> @endforeach
        </select>
    </div>
    @error('subMayor') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    @if ($error) <div style="padding:.4rem .6rem; background:#fee2e2; border:1px solid #dc2626; border-radius:.4rem; color:#7f1d1d; font-size:.85rem">⚠️ {{ $error }}</div> @endif

    {{-- Ficheros base de la empresa (centrales) --}}
    <div class="bg-white border rounded-lg shadow">
        <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">Ficheros base de la empresa <span class="font-normal text-gray-500">(se suben una vez y valen para todos los procesos)</span></div>
        @foreach ($filas as $tipo => $f)
            @php $prop = ['mayor' => 'subMayor', 'plan' => 'subPlan', 'proveedores' => 'subProveedores', 'clientes' => 'subClientes'][$tipo]; @endphp
            <div wire:key="fb-{{ $tipo }}-{{ $entidadId }}" x-data="{ encima: false, subiendo: false,
                    subir(files) { if (! files || ! files.length) return; this.subiendo = true; $wire.upload(@js($prop), files[0], () => { this.subiendo = false }, () => { this.subiendo = false }); } }"
                 x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                 :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''" style="display:flex; align-items:center; gap:.5rem; padding:.3rem .75rem; flex-wrap:wrap" title="{{ $f['ayuda'] }}">
                <input type="file" accept=".xlsx" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
                <span class="text-sm text-gray-800" style="width:9.5rem">{{ $f['icono'] }} {{ $f['titulo'] }}</span>
                <button type="button" x-on:click="$refs.input.click()" class="{{ $btn }}">⬆ Subir</button>
                <span class="text-xs" style="color:{{ $f['ultimo'] ? '#047857' : '#b91c1c' }}">
                    @if ($f['ultimo']) ✔ {{ \Illuminate\Support\Str::limit($f['ultimo']['nombre'], 44) }} · {{ $f['ultimo']['fecha'] }}@if ($f['ultimo']['origen']) <span class="text-gray-400">({{ $f['ultimo']['origen'] }})</span>@endif
                    @else sin subir @endif
                </span>
                <span x-show="subiendo" x-cloak class="text-xs text-gray-500">⏳</span>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button type="button" wire:click="revisar" wire:loading.attr="disabled" wire:target="revisar" class="px-4 py-1.5 font-bold text-white bg-green-600 rounded-md">
            <span wire:loading.remove wire:target="revisar">▶ Revisar el mayor</span><span wire:loading wire:target="revisar">Revisando… (puede tardar un minuto)</span>
        </button>
        @if ($res)
            <a wire:click.prevent="descargar" href="#" class="{{ $btn }}">⬇ Excel</a>
            <span class="text-xs text-gray-500">hecho el {{ $res['generado'] ?? '' }} con «{{ $mayorUsado }}»</span>
        @endif
    </div>

    @if ($res)
        @php
            $secciones = [
                'prov_aplicar' => ['🧾 Provisiones con la factura ya llegada: aplicarlas', 'Provisión de 410900 («Prov …») sin aplicar y con la factura del proveedor ya en el mayor. Asiento a hacer: D 410900 / H la cuenta de gasto de la provisión.'],
                'prov_puntear' => ['🔗 Provisión y aplicación del mismo importe: puntear', 'Ya están aplicadas; solo falta puntearlas entre sí en 410900.'],
                'p410000' => ['🏷 Pagos en 410000 con una factura abierta del mismo importe en otro proveedor', 'Quizá el pago es de ese proveedor: moverlo de cuenta y puntear.'],
                'pago_factura' => ['💶 Pago y factura abiertos del mismo importe en la misma cuenta', 'Puntear o cancelar entre sí.'],
                'pago_suma' => ['➕ Pago igual a la suma de 2-3 facturas abiertas', 'Puntear el pago con esas facturas.'],
                'prov_sin_factura' => ['⏳ Provisiones sin factura todavía', 'Esperan la factura.'],
                'colgados' => ['📌 Abiertos sueltos (sin pareja)', 'Lo colgado: pagos o facturas sin cruzar.'],
            ];
        @endphp
        <div class="flex flex-wrap gap-2">
            @foreach ($secciones as $k => [$titulo])
                <span class="px-2 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded-full">{{ \Illuminate\Support\Str::before($titulo, ':') }} <b>{{ count($res[$k] ?? []) }}</b></span>
            @endforeach
            @if ($nRev || $verRevisadas)
                <button type="button" wire:click="$toggle('verRevisadas')" class="{{ $btn }}">{{ $verRevisadas ? 'Ocultar' : 'Ver' }} las ya revisadas ({{ $nRev }})</button>
            @endif
        </div>

        @foreach ($secciones as $k => [$titulo, $ayuda])
            @php $filasSec = $res[$k] ?? []; @endphp
            @if ($filasSec)
                <details open class="bg-white border rounded-lg shadow" wire:key="sec-{{ $k }}">
                    <summary class="px-4 py-2 text-sm font-semibold text-gray-800 cursor-pointer">{{ $titulo }} ({{ count($filasSec) }})</summary>
                    <p class="px-4 text-xs text-gray-500">{{ $ayuda }}</p>
                    <div class="overflow-auto" style="max-height:24rem">
                        <table class="w-full text-xs">
                            <tbody>
                                @foreach ($filasSec as $x)
                                    <tr class="border-t" wire:key="r-{{ md5($x['clave']) }}">
                                        <td class="px-3 py-1">
                                            @switch($k)
                                                @case('prov_aplicar')
                                                    <b>{{ $x['provision']['texto'] }}</b> · {{ $eur($x['provision']['importe']) }} € ({{ $fecha($x['provision']['fecha']) }}, asiento {{ $x['provision']['asiento'] }}, gasto {{ $x['provision']['gasto'] }})
                                                    → factura <b>{{ $x['factura']['num'] ?: $x['factura']['asiento'] }}</b> de {{ $x['factura']['proveedor'] }} · {{ $eur($x['factura']['importe']) }} € ({{ $fecha($x['factura']['fecha']) }})
                                                    @if ($x['dif']) <span style="color:#b45309">dif. {{ $eur($x['dif']) }} €</span> @endif
                                                    <div class="text-gray-500">Asiento: D 410900 {{ $eur($x['provision']['importe']) }} / H {{ $x['provision']['gasto'] ?: '(gasto de la provisión)' }} {{ $eur($x['provision']['importe']) }}</div>
                                                    @break
                                                @case('prov_puntear')
                                                    {{ $eur($x['provision']['importe']) }} € · «{{ $x['provision']['texto'] }}» ({{ $fecha($x['provision']['fecha']) }}, asiento {{ $x['provision']['asiento'] }}) ↔ «{{ trim($x['aplicacion']['texto']) }}» ({{ $fecha($x['aplicacion']['fecha']) }}, asiento {{ $x['aplicacion']['asiento'] }})
                                                    @break
                                                @case('p410000')
                                                    {{ $eur($x['pago']['importe']) }} € el {{ $fecha($x['pago']['fecha']) }} · «{{ $x['pago']['texto'] }}» (asiento {{ $x['pago']['asiento'] }}) → posible: @foreach ($x['candidatas'] as $c) <b>{{ $c['cuenta'] }} {{ $c['proveedor'] }}</b> fra {{ $c['num'] ?: $c['asiento'] }} ({{ $fecha($c['fecha']) }}){{ ! $loop->last ? ' · ' : '' }} @endforeach
                                                    @break
                                                @case('pago_factura')
                                                    {{ $x['pago']['cuenta'] }} {{ $x['pago']['proveedor'] }} · {{ $eur($x['pago']['importe']) }} €: pago del {{ $fecha($x['pago']['fecha']) }} (asiento {{ $x['pago']['asiento'] }}, «{{ \Illuminate\Support\Str::limit($x['pago']['texto'], 30) }}») ↔ factura {{ $x['factura']['num'] ?: $x['factura']['asiento'] }} del {{ $fecha($x['factura']['fecha']) }} (asiento {{ $x['factura']['asiento'] }})
                                                    @break
                                                @case('pago_suma')
                                                    {{ $x['pago']['cuenta'] }} {{ $x['pago']['proveedor'] }} · pago {{ $eur($x['pago']['importe']) }} € ({{ $fecha($x['pago']['fecha']) }}) = @foreach ($x['facturas'] as $y) {{ $y['num'] ?: $y['asiento'] }} ({{ $eur($y['importe']) }}){{ ! $loop->last ? ' + ' : '' }} @endforeach
                                                    @break
                                                @case('prov_sin_factura')
                                                    {{ $x['provision']['texto'] }} · {{ $eur($x['provision']['importe']) }} € ({{ $fecha($x['provision']['fecha']) }}, asiento {{ $x['provision']['asiento'] }})
                                                    @break
                                                @default
                                                    {{ $x['tipo'] === 'pago' ? 'Pago' : 'Factura' }} · {{ $x['cuenta'] }} {{ $x['proveedor'] }} · {{ $eur($x['importe']) }} € · {{ $fecha($x['fecha']) }} · asiento {{ $x['asiento'] }} · {{ \Illuminate\Support\Str::limit($x['texto'], 40) }}
                                            @endswitch
                                        </td>
                                        <td class="px-2 py-1 text-right" style="white-space:nowrap; width:1%">
                                            <button type="button" x-on:click="const n = prompt('Nota (opcional). Aceptar = darlo por revisado y no volver a proponerlo:'); if (n !== null) $wire.marcarRevisado(@js($x['clave']), n)" class="px-2 py-0.5 text-white bg-green-600 rounded" title="Ya lo he mirado: que no me lo vuelva a proponer">✔ Revisado</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        @endforeach

        @if ($verRevisadas && $nRev)
            <div class="bg-white border rounded-lg shadow">
                <div class="px-4 py-2 text-sm font-semibold text-gray-700">Ya revisadas ({{ $nRev }})</div>
                <table class="w-full text-xs">
                    @foreach ($res['revisadas'] as $clave => $x)
                        <tr class="border-t" style="opacity:.7" wire:key="rv-{{ md5($clave) }}">
                            <td class="px-3 py-1">{{ $clave }} <i>· {{ $x['fecha'] ?? '' }} {{ $x['quien'] ?? '' }} {{ $x['nota'] ?? '' }}</i></td>
                            <td class="px-2 py-1 text-right"><button type="button" wire:click="desmarcarRevisado(@js($clave))" class="{{ $btn }}">↺ Quitar «revisado»</button></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500">Aún no se ha revisado el mayor de esta empresa: pulsa «Revisar el mayor».</p>
    @endif
</div>
