<div class="p-4" style="max-width:1100px" x-data="{ explorador: false }">
    <style>
        .li-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; margin-bottom:12px }
        .li-h { font-weight:600; color:#111827; margin-bottom:8px }
        .li-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer }
        .li-btn.pri { background:#4f46e5; border-color:#4f46e5; color:#fff }
        .li-t { width:100%; border-collapse:collapse; font-size:13px } .li-t td, .li-t th { padding:3px 8px; border-bottom:1px solid #f1f5f9 }
        .li-n { text-align:right; font-variant-numeric:tabular-nums } .li-ok { color:#15803d } .li-gris { color:#6b7280 }
        .li-bloque { background:#dbeafe; font-weight:600 } .li-sub { background:#fef3c7; font-weight:600 }
    </style>

    <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px">
        <b>PROPUESTA DE PANTALLA</b> · aún no genera nada. Los números de abajo son el resultado real de Alex Arregui 3T-2026 (ejemplo). Se abre desde <a href="{{ route('impuestos') }}" style="color:#4338ca">Impuestos</a> → 📒 Libro de IVA, o desde la casilla 303 de cada cliente.
    </div>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">📒 Libro de IVA (303)</h1>

    <div class="li-card">
        <div class="li-h">1 · Empresa y periodo</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <select wire:model="entidadId" class="border-gray-300 rounded-md text-sm" style="min-width:22rem">
                @foreach ($empresas as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach
            </select>
            <input type="number" wire:model="ejercicio" class="border-gray-300 rounded-md text-sm" style="width:5.5rem">
            <select wire:model="periodo" class="border-gray-300 rounded-md text-sm">
                @foreach (['1T','2T','3T','4T'] as $p)<option>{{ $p }}</option>@endforeach
                <option disabled>── mensual ──</option>
                @foreach (range(1, 12) as $m)<option>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="li-card">
        <div class="li-h">2 · Carpeta donde están los Excel</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;font-size:13px">{{ $carpeta ?: '— sin carpeta asignada a esta empresa —' }}</code>
            <button type="button" class="li-btn" x-on:click="explorador = true">📁 Cambiar carpeta…</button>
        </div>
        <div class="li-gris" style="font-size:12px;margin-top:6px">Se recuerda por empresa. El explorador lo rellena un PC trabajador (la web no ve el disco).</div>
        <table class="li-t" style="margin-top:10px">
            <tr><td class="li-ok">✔</td><td>T3 202630635861ECALEXANDER ARREGUI ARRIOLA.XLSX</td><td class="li-gris">original del periodo</td></tr>
            <tr><td class="li-ok">✔</td><td>IVA T2 …XLSX</td><td class="li-gris">histórico · resultado anterior: 2.593,55 a pagar</td></tr>
            <tr><td class="li-ok">✔</td><td>IVA T1 …XLSX</td><td class="li-gris">histórico</td></tr>
            <tr><td class="li-gris">○</td><td>IVA T3 …XLSX</td><td class="li-gris">se creará al generar</td></tr>
        </table>
    </div>

    <div class="li-card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="button" wire:click="generar" class="li-btn pri">▶ Generar libro de IVA</button>
        <span class="li-gris" style="font-size:12px">Lo hace un PC con OneDrive; al terminar marca el 303 como «listo para revisión» y deja el Excel enlazado.</span>
        @if ($aviso)<div style="width:100%;color:#b45309;font-size:13px">{{ $aviso }}</div>@endif
    </div>

    <div class="li-card">
        <div class="li-h">3 · Resultado (ejemplo)</div>
        <table class="li-t">
            <tr class="li-bloque"><td colspan="3">EMITIDAS · 4 facturas (sin ADC ni ISP)</td></tr>
            <tr><td>Tipo 21 % · importes &gt; 0 / &lt; 0</td><td class="li-n">12.927,23</td><td class="li-n">2.714,72 / 0,00</td></tr>
            <tr class="li-sub"><td>TOTAL EMITIDAS (cuota)</td><td></td><td class="li-n">2.714,72</td></tr>
            <tr class="li-bloque"><td colspan="3">RECIBIDAS NACIONALES · 6 facturas</td></tr>
            <tr><td>Tipo 10 % · &gt; 0 / &lt; 0</td><td class="li-n">43,27</td><td class="li-n">4,33 / 0,00</td></tr>
            <tr><td>Tipo 21 % · &gt; 0 / &lt; 0</td><td class="li-n">112,66</td><td class="li-n">23,65 / 0,00</td></tr>
            <tr class="li-sub"><td>TOTAL RECIBIDAS NACIONALES (cuota)</td><td></td><td class="li-n">27,98</td></tr>
            <tr class="li-gris"><td colspan="3">… ADC · Intracomunitarias · Aduanas · IVA 0 (al final): vacíos en este cliente, pero siempre presentes</td></tr>
            <tr class="li-bloque"><td colspan="3">RESULTADO (al pie de RECIBIDAS, como en tus IVA T1/T2)</td></tr>
            <tr><td>Resultado del periodo anterior (2T)</td><td></td><td class="li-n">2.593,55 a pagar</td></tr>
            <tr class="li-sub"><td>RESULTADO FINAL</td><td></td><td class="li-n">2.686,74 · A PAGAR</td></tr>
        </table>
        <div class="li-h" style="margin-top:12px">Avisos</div>
        <div class="li-gris" style="font-size:13px">Sin avisos. (Aquí saldrán: facturas excluidas, total ≠ base + cuota, duplicadas, resultado anterior no encontrado…)</div>
    </div>

    <div x-show="explorador" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:60;display:flex;align-items:center;justify-content:center" x-on:click.self="explorador = false">
        <div style="background:#fff;border-radius:10px;padding:16px;width:560px;max-width:95vw">
            <div class="li-h">Elegir carpeta (PC con OneDrive)</div>
            <div style="font-size:13px;line-height:1.7;border:1px solid #e5e7eb;border-radius:6px;padding:8px;max-height:260px;overflow:auto">
                🖥 Este equipo<br>
                &nbsp;&nbsp;💽 E:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;📁 OneDrive<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📁 _Clientes<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📂 _RUR_Marta_Alex<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📂 2026 RMA<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📂 _Alex 2026<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b style="background:#e0e7ff">📂 IVA</b>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px">
                <button type="button" class="li-btn" x-on:click="explorador = false">Cancelar</button>
                <button type="button" class="li-btn pri" x-on:click="explorador = false">Usar esta carpeta</button>
            </div>
        </div>
    </div>
</div>
