{{-- Subida de ficheros base de SAGE desde cualquier pantalla (FicherosBaseSubida, 19-oct-2026). --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 16px;margin-bottom:12px">
    <details @if ($abierto) open @endif>
        <summary style="cursor:pointer;font-weight:600;color:#111827">📂 Ficheros base de SAGE de esta empresa
            <span class="text-xs font-normal text-gray-500">— los subes aquí y los usan todos los procesos (mayor, proveedores, plan de cuentas…)</span></summary>
        @if ($error)<div class="text-sm" style="color:#b91c1c;margin-top:6px">⛔ {{ $error }}</div>@endif
        @if ($msg)<div class="text-sm" style="color:#166534;margin-top:6px">✅ {{ $msg }}</div>@endif
        <table style="width:auto;border-collapse:collapse;font-size:13px;margin-top:8px">
            @foreach ($filas as $t => $f)
                <tr style="border-bottom:1px solid #f1f5f9">
                    <td style="padding:5px 8px;white-space:nowrap">{{ $f['icono'] }} <b>{{ $f['titulo'] }}</b></td>
                    <td style="padding:5px 8px;color:#4b5563;min-width:560px">
                        @if ($f['ultimo'])
                            {{ $f['ultimo']['nombre'] }} <span class="text-xs text-gray-500">· subido el {{ date('d/m/Y H:i', $f['ultimo']['fecha']) }}@if (! empty($f['ultimo']['periodo'])) · hasta {{ $f['ultimo']['periodo'] }}@endif</span>
                        @else
                            <span class="text-gray-400">todavía no hay ninguno</span>
                        @endif
                    </td>
                    <td style="padding:5px 8px;text-align:left;white-space:nowrap">
                        @if (! empty($f['por_periodo']))
                            <label class="text-xs text-gray-500">mes al que llega <input type="month" wire:model="periodoSys" class="border-gray-300 rounded-md text-sm" style="padding:1px 6px"></label>
                        @endif
                        <label class="inline-block cursor-pointer" style="padding:3px 12px;font-size:13px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151"
                               wire:loading.class="opacity-50" wire:target="subidas.{{ $t }}">
                            {{ $f['ultimo'] ? 'Subir uno nuevo…' : 'Subir…' }}
                            <input type="file" wire:model="subidas.{{ $t }}" accept=".xlsx" style="display:none">
                        </label>
                        <span wire:loading wire:target="subidas.{{ $t }}" class="text-xs text-indigo-700">⏳ subiendo…</span>
                    </td>
                </tr>
            @endforeach
        </table>
        <div class="text-xs text-gray-500" style="margin-top:6px">Se conserva el historial; cada proceso usa el último. Facturas OCR lo instala al abrir la empresa.</div>
    </details>
</div>
