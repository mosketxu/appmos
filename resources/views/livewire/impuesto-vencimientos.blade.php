<div class="imp-noprint" style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" wire:click.self="cerrar" x-on:keydown.escape.window="$wire.cerrar()">
    <div style="background:#fff; border-radius:8px; padding:14px; width:min(1000px, 96vw); max-height:90vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.3)" class="space-y-3">
        @php
            $eur = fn ($x) => number_format((float) $x, 2, ',', '.');
            $fecha = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
        @endphp
        <div class="flex items-start justify-between gap-3">
            <div class="font-semibold text-gray-900">📅 Vencimientos · {{ $empresa }} · {{ $periodo }}</div>
            <button type="button" wire:click="cerrar" class="text-gray-400 hover:text-gray-700" title="Cerrar">✕</button>
        </div>
        <p class="text-sm text-gray-600">Propuesta de pagos del mes a partir del <b>mayor con los bancos</b>: facturas de proveedores y acreedores aún abiertas. Vencimiento = día 25 del mes siguiente a la factura (se puede cambiar en el Excel). Es una propuesta: no cambia nada en SAGE ni guarda nada en OneDrive.</p>

        @if ($error) <div style="padding:.4rem .6rem; background:#fee2e2; border:1px solid #dc2626; border-radius:.4rem; color:#7f1d1d; font-size:.85rem">⚠️ {{ $error }}</div> @endif

        <div x-data="{ encima: false, subiendo: false, subir(files) { if (! files || ! files.length) return; this.subiendo = true; $wire.upload('subMayor', files[0], () => { this.subiendo = false }, () => { this.subiendo = false }); } }"
             x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
             :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap">
            <input type="file" accept=".xlsx" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
            <span class="text-sm text-gray-800" style="width:9.5rem">📒 Mayor (con bancos)</span>
            <x-boton-subir :vacio="! $mayor" cargando="subiendo" x-on:click="$refs.input.click()">Subir</x-boton-subir>
            <span class="text-xs" style="color:{{ $mayor ? '#047857' : '#b91c1c' }}">{{ $mayor ? '✔ '.\Illuminate\Support\Str::limit($mayor['nombre'], 50).' · '.$mayor['fecha'] : 'sin subir' }}</span>
            <span x-show="subiendo" x-cloak class="text-xs text-gray-500">⏳</span>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-800">
            <input type="checkbox" wire:model.live="bancos" class="border-gray-300 rounded"> Los bancos de este mes están pasados y puenteados en el mayor
        </label>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @if (! $bancos) x-on:click.prevent="if (confirm('¿Estás seguro? Mira que no has marcado que los bancos estén pasados: los pagos sin pasar aparecerán como facturas pendientes.')) $wire.preparar()" @else wire:click="preparar" @endif
                wire:loading.attr="disabled" wire:target="preparar" class="px-4 py-1.5 font-bold text-white bg-green-600 rounded-md">
                <span wire:loading.remove wire:target="preparar">▶ Preparar vencimientos</span><span wire:loading wire:target="preparar">Preparando…</span>
            </button>
            @if ($res)
                <a wire:click.prevent="descargar" href="#" class="px-2 py-0.5 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">⬇ Excel (formato Vtos)</a>
                <span class="text-xs text-gray-500">con «{{ $mayorUsado }}»</span>
            @endif
        </div>
        @if ($salida) <p class="text-xs text-gray-600">{{ $salida }}</p> @endif

        @if ($res)
            <div class="text-sm"><b>{{ count($res['facturas']) }}</b> facturas a pagar hasta el {{ $fecha($res['limite']) }} · <b>{{ $eur($res['total']) }} €</b> · {{ count($res['pagos']) }} pagos/abonos abiertos por puntear</div>
            <div class="overflow-auto" style="max-height:22rem">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-gray-50"><tr><th class="px-2 py-1 text-left">Factura</th><th class="px-2 py-1 text-left">Vence</th><th class="px-2 py-1 text-left">Proveedor</th><th class="px-2 py-1 text-left">Nº</th><th class="px-2 py-1 text-right">Importe</th></tr></thead>
                    <tbody>
                        @foreach ($res['facturas'] as $x)
                            <tr class="border-t" @if ($x['vencida']) style="background:#fff7d6" @endif>
                                <td class="px-2 py-1">{{ $fecha($x['fecha']) }}</td><td class="px-2 py-1">{{ $fecha($x['vence']) }}{{ $x['vencida'] ? ' ⚠' : '' }}</td>
                                <td class="px-2 py-1">{{ $x['cuenta'] }} {{ $x['proveedor'] }}</td><td class="px-2 py-1">{{ $x['num'] }}</td><td class="px-2 py-1 text-right">{{ $eur($x['importe']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500">⚠ amarillo = ya vencida. Los impuestos a pagar (AEAT…) de momento se añaden a mano en el Excel.</p>
        @else
            <p class="text-sm text-gray-500">Aún no se ha preparado: sube el mayor y pulsa «Preparar vencimientos».</p>
        @endif
    </div>
</div>
