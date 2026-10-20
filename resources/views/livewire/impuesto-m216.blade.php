{{-- Impuestos → M216 no residentes, declaración negativa (ImpuestoM216, 20-oct-2026). --}}
<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_impuestos', ['activa' => 'm216'])

    <div class="p-4" style="max-width:1000px">
    <style>
        .m3-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:12px }
        .m3-h { font-weight:600; color:#111827; margin-bottom:8px }
        .m3-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer }
        .m2-t { border-collapse:collapse; font-size:13px; width:100% } .m2-t th, .m2-t td { border-bottom:1px solid #e5e7eb; padding:3px 8px; text-align:left }
        .m2-t .n { text-align:right; font-variant-numeric:tabular-nums }
    </style>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">M216 <span class="text-sm font-normal text-gray-500">IRPF no residentes · declaración negativa (sin retenciones)</span></h1>

    <div class="m3-card">
        <div class="m3-h">1 · Cliente y periodo</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="28rem"/>
            @if ($entidadId)
                <button type="button" wire:click="cambiarEjercicio(-1)" class="m3-btn">◀</button><b>{{ $ejercicio }}</b><button type="button" wire:click="cambiarEjercicio(1)" class="m3-btn">▶</button>
                <select wire:model.live="periodo" class="border-gray-300 rounded-md text-sm" style="padding-top:4px;padding-bottom:4px">
                    @foreach ($periodosLista as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach
                </select>
            @endif
            @if (\App\Support\Impuestos::esGestor())
                <label class="text-xs text-gray-600" style="margin-left:auto"><input type="checkbox" wire:model.live="verTodos" class="border-gray-300 rounded"> ver todos los clientes</label>
            @endif
        </div>
        @if (! $empresas)<div class="text-sm text-gray-500" style="margin-top:8px">Ninguna empresa tiene el modelo 216 activo en Entidades → Impuestos.</div>@endif
    </div>

    @if ($error)<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#92400e">⚠️ {{ $error }}</div>@endif

    @if ($res)
        @php $eur = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
        <div class="m3-card">
            <div class="m3-h">2 · Casillas para la Sede <span class="text-xs font-normal text-gray-500">— modelo 216 · {{ $periodo }}/{{ $ejercicio }} · «Declaración negativa»</span></div>
            <table class="m2-t" style="max-width:640px">
                <tr><th>Casilla</th><th>Qué es</th><th class="n">Valor</th><th></th></tr>
                <tr><td>II · 15 / 17</td><td>Resto de rentas no sometidas · número de rentas</td><td class="n"><b>{{ $res['n'] }}</b></td><td><x-copiar :texto="(string) $res['n']" titulo="Copiar" /></td></tr>
                <tr><td>II · 18 y 19</td><td>Base (suma de «Total factura» de la tabla)</td><td class="n"><b>{{ $eur($res['total']) }}</b></td><td><x-copiar :texto="$eur($res['total'])" titulo="Copiar" /></td></tr>
                <tr><td>21</td><td>Resultado a ingresar</td><td class="n"><b>0,00</b></td><td></td></tr>
            </table>
            <div class="text-xs text-gray-500" style="margin-top:8px">Se marca «Declaración negativa». Todo sale del libro «{{ $libro->nombre }}» (tabla M216 de RECIBIDAS).
                @if ($res['coef'] != 1) La tabla del libro también enseña el total × {{ rtrim(rtrim(number_format($res['coef'], 4, ',', ''), '0'), ',') }} ({{ $eur($res['total_coef']) }}): el 1T y el 3T de 2026 se presentaron <b>sin</b> coeficiente, el 2T con él. @endif
                @if (($res['control'] ?? 0) != 0)<br><span style="color:#b91c1c">⛔ El control del libro no es 0 ({{ $eur($res['control']) }}): la tabla no suma lo mismo que el bloque ISP; revísalo antes de presentar.</span>@endif</div>
            @if ($presentado)
                <div class="text-sm" style="margin-top:8px">
                    @if ($presentado['datos'])
                        @if ($presentado['datos']['n'] === $res['n'] && abs($presentado['datos']['base'] - $res['total']) < 0.005)
                            <span style="color:#166534">✅ Coincide con lo presentado («{{ $presentado['nombre'] }}»: {{ $presentado['datos']['n'] }} rentas, {{ $eur($presentado['datos']['base']) }}).</span>
                        @else
                            <span style="color:#b91c1c">⛔ Distinto de lo presentado («{{ $presentado['nombre'] }}»: {{ $presentado['datos']['n'] }} rentas, {{ $eur($presentado['datos']['base']) }}). Si el libro cambió después, haría falta complementaria.</span>
                        @endif
                    @else
                        <span class="text-gray-600">Hay un M216 presentado de este periodo («{{ $presentado['nombre'] }}») pero no pude leer sus casillas.</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="m3-card">
            <div class="m3-h">3 · Rentas por proveedor</div>
            <table class="m2-t">
                <tr><th>Proveedor</th><th>País</th><th class="n">Total factura</th></tr>
                @foreach ($res['proveedores'] as $p)
                    <tr><td>{{ $p['nombre'] }}</td><td>{{ $p['pais'] ?: '—' }}</td><td class="n">{{ $eur($p['total']) }}</td></tr>
                @endforeach
                <tr><td colspan="2"><b>Total · {{ $res['n'] }} proveedores</b></td><td class="n"><b>{{ $eur($res['total']) }}</b></td></tr>
            </table>
            @if (collect($res['proveedores'])->contains(fn ($p) => $p['pais'] === ''))
                <div class="text-xs" style="margin-top:6px;color:#b45309">⚠️ Proveedores sin país en el libro: {{ collect($res['proveedores'])->where('pais', '')->pluck('nombre')->implode(', ') }}. No cambia la cifra, pero comprueba que son no residentes.</div>
            @endif
        </div>
    @endif
    </div>
</div>
