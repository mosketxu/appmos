<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_todo', ['activa' => 'impuestos'])

<div class="p-4" style="max-width:1100px" @if ($espera !== '') wire:poll.3s="revisar" @endif>
    <style>
        .li-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; margin-bottom:12px }
        .li-h { font-weight:600; color:#111827; margin-bottom:8px }
        .li-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer; text-decoration:none; display:inline-block }
        .li-btn.pri { background:#4f46e5; border-color:#4f46e5; color:#fff }
        .li-btn[disabled] { opacity:.5; cursor:default }
        .li-t { width:100%; border-collapse:collapse; font-size:13px } .li-t td, .li-t th { padding:3px 8px; border-bottom:1px solid #f1f5f9 }
        .li-n { text-align:right; font-variant-numeric:tabular-nums } .li-ok { color:#15803d } .li-mal { color:#b91c1c } .li-gris { color:#6b7280 }
        .li-sub { background:#fef3c7; font-weight:600 }
    </style>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h1 class="text-xl font-semibold">📒 Libro de IVA (303)</h1>
        <a href="{{ route('impuestos') }}" class="li-btn">← Impuestos</a>
    </div>

    @if ($error)<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#991b1b">{{ $error }}</div>@endif
    @if ($mensaje)
        <div style="background:#e0e7ff;border:1px solid #a5b4fc;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#3730a3;display:flex;justify-content:space-between;gap:12px">
            <span>⏳ {{ $mensaje }}</span>
            @if ($espera !== '')<button type="button" wire:click="cancelarEspera" class="li-btn" style="padding:0 8px">dejar de esperar</button>@endif
        </div>
    @endif

    <div class="li-card">
        <div class="li-h">1 · Empresa y periodo</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="26rem" />
            @if ($this->puedeTodos())
                <button type="button" wire:click="$toggle('verTodos')" class="li-btn" style="{{ $verTodos ? 'background:#4f46e5;border-color:#4f46e5;color:#fff' : '' }}"
                    title="Por defecto solo ves las empresas que llevas tú">{{ $verTodos ? '👥 Viendo todas' : '👤 Solo las mías · ver todas' }}</button>
            @endif
            <input type="number" wire:model.lazy="ejercicio" class="border-gray-300 rounded-md text-sm" style="width:5.5rem">
            <select wire:model="periodo" class="border-gray-300 rounded-md text-sm">
                @foreach (['1T','2T','3T','4T'] as $p)<option>{{ $p }}</option>@endforeach
                <option disabled>── mensual ──</option>
                @foreach (range(1, 12) as $m)<option>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
            </select>
        </div>
        @if ($ultimo)
            <div class="li-gris" style="font-size:12px;margin-top:6px">Último libro de esta empresa: {{ $ultimo['periodo'] }} · {{ $ultimo['tipo'] }} {{ number_format(abs($ultimo['resultado_final']), 2, ',', '.') }} € · {{ $ultimo['fecha'] }} ({{ $ultimo['pc'] }})</div>
        @endif
    </div>

    <div class="li-card">
        <div class="li-h">2 · Carpeta donde están los Excel</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;font-size:13px">{{ $carpeta ? 'OneDrive\\'.str_replace('/', '\\', $carpeta) : '— sin carpeta asignada a esta empresa —' }}</code>
            <button type="button" wire:click="elegirCarpeta" wire:loading.attr="disabled" class="li-btn" @disabled($espera !== '')>📁 {{ $carpeta ? 'Cambiar carpeta…' : 'Elegir carpeta…' }}</button>
            <button type="button" wire:click="comprobar" wire:loading.attr="disabled" class="li-btn" @disabled($espera !== '' || $carpeta === '')>🔍 Comprobar carpeta</button>
        </div>
        <div class="li-gris" style="font-size:12px;margin-top:6px">«Elegir carpeta» abre el selector de carpetas de Windows en el PC trabajador (la web no ve el disco). Se recuerda para esta empresa.</div>
        @if ($listado)
            @php $orig = $this->originalDelPeriodo(); @endphp
            <table class="li-t" style="margin-top:10px">
                <tr>
                    <td class="{{ $orig ? 'li-ok' : 'li-mal' }}" style="width:24px">{{ $orig ? '✔' : '✖' }}</td>
                    <td>{{ $orig['fichero'] ?? 'No hay original de '.$ejercicio.'-'.$periodo.' (un Excel «'.(str_ends_with($periodo, 'T') ? 'T'.substr($periodo, 0, 1) : $periodo).' '.$ejercicio.'…»)' }}</td>
                    <td class="li-gris">original del periodo</td>
                </tr>
                @foreach ($listado['libros'] as $l)
                    @php $res = $listado['resultados'][$l['periodo']] ?? null; @endphp
                    <tr><td class="li-ok">✔</td><td>{{ $l['fichero'] }}</td>
                        <td class="li-gris">libro hecho · {{ $l['periodo'] }}@if ($res) · final {{ number_format($res['resultado_final'], 2, ',', '.') }} €@endif</td></tr>
                @endforeach
                @if (! count($listado['libros']))<tr><td colspan="3" class="li-gris">Aún no hay libros «IVA …» en la carpeta.</td></tr>@endif
            </table>
        @endif
    </div>

    <div class="li-card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="button" wire:click="generar" wire:loading.attr="disabled" class="li-btn pri" @disabled($espera !== '' || $carpeta === '')>▶ Generar libro de IVA {{ $periodo }} {{ $ejercicio }}</button>
        @if ($this->presentaM216())<span style="font-size:12px;color:#4338ca">Esta empresa presenta el M216: el libro llevará la tabla por proveedor junto al bloque ISP.</span>@endif
        <span class="li-gris" style="font-size:12px">Lo hace un PC con OneDrive y deja el Excel «IVA …» en la misma carpeta, junto al original.</span>
    </div>

    @if ($libro)
        @php $f2 = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
        <div class="li-card">
            <div class="li-h">3 · Resultado {{ $libro['periodo'] }} <span class="li-gris" style="font-weight:400;font-size:12px">· hecho en {{ $libro['pc'] }}</span></div>
            <table class="li-t">
                <tr><td>IVA repercutido (emitidas: {{ $libro['emitidas']['emitida'] }} facturas)</td><td class="li-n">{{ $f2($libro['repercutido']) }}</td></tr>
                <tr><td>IVA soportado (nacionales {{ $libro['recibidas']['nacional'] }} + aduanas {{ $libro['recibidas']['aduanas'] }})</td><td class="li-n">{{ $f2($libro['soportado']) }}</td></tr>
                <tr><td class="li-gris">ADC {{ $libro['recibidas']['adc'] }} · ISP {{ $libro['recibidas']['isp'] }} · IVA 0 / a revisar {{ $libro['recibidas']['iva0'] }} — no computan (autoliquidado: {{ $f2($libro['adc']) }} / {{ $f2($libro['isp']) }})</td><td></td></tr>
                <tr><td><b>Resultado del periodo</b></td><td class="li-n"><b>{{ $f2($libro['resultado_periodo']) }}</b></td></tr>
                <tr><td>Resultado del periodo anterior</td><td class="li-n">{{ $libro['anterior'] === null ? 'no encontrado (0)' : $f2($libro['anterior']) }}</td></tr>
                <tr class="li-sub"><td>RESULTADO FINAL</td><td class="li-n">{{ $f2($libro['resultado_final']) }} · {{ $libro['tipo'] }}</td></tr>
            </table>
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($libro['ficheros'] ?? [] as $fi)
                    <a class="li-btn" href="{{ $this->enlace($fi) }}">⬇ {{ str_ends_with($fi, '.txt') ? 'Avisos (.txt)' : 'Excel del libro' }}</a>
                @endforeach
            </div>
            <div class="li-h" style="margin-top:12px">Avisos ({{ $libro['n_avisos'] ?? count($libro['avisos']) }})</div>
            @forelse ($libro['avisos'] as $a)<div class="li-gris" style="font-size:13px">• {{ $a }}</div>@empty<div class="li-gris" style="font-size:13px">Sin avisos.</div>@endforelse
            @if (($libro['n_avisos'] ?? 0) > count($libro['avisos']))<div class="li-gris" style="font-size:12px">… y {{ $libro['n_avisos'] - count($libro['avisos']) }} más en la pestaña AVISOS.</div>@endif
        </div>
    @endif
</div>
</div>
