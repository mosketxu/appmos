<div @if ($enCurso) wire:poll.5s @endif class="text-sm">
    @if (! $puede)
        <p class="text-gray-600">No tienes permiso para este proceso.</p>
    @else
    <p class="text-gray-700">Del Excel <b>CabeceraAlbaranCliente</b> que exportas de SAGE deja en OneDrive (<i>_Suma/Facturas/2026</i>) el <b>«Prevision facturacion 2026 {{ sprintf('%02d', $mes) }}.xlsx»</b>
        con el mismo formato que el del mes anterior, y después lo envía a <b>Susana y Marta</b>.</p>

    <div class="flex flex-wrap items-center gap-3 mt-3">
        <label class="flex items-center gap-2">Mes
            <select wire:model.live="mes" @disabled($enCurso) class="border-gray-300 rounded-md">
                @foreach ($meses as $i => $nombre) <option value="{{ $i + 1 }}">{{ ucfirst($nombre) }}</option> @endforeach
            </select>
        </label>
    </div>

    <div class="flex flex-wrap items-center gap-3 mt-3">
        <x-subir-fichero wire:model="excel" accept=".xlsx" :fichero="$excel" texto="Excel de SAGE (CabeceraAlbaranCliente)" />
        <button type="button" wire:click="generar" wire:loading.attr="disabled" wire:target="excel" @disabled($enCurso || ! $excel)
            class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">📑 Generar el Excel</button>
        @error('excel') <span class="text-red-700">{{ $message }}</span> @enderror
        @if (! $excel && ! $generado) <span class="text-gray-600">falta subir el Excel de SAGE</span> @endif
    </div>

    @if ($aviso)<p class="mt-2 text-indigo-700">{{ $aviso }}</p>@endif

    @if ($enCurso)
        <p class="mt-3 text-gray-600">⏳ Un PC está en ello…</p>
    @endif

    @if ($ultimaGenerar && $ultimaGenerar->estado !== 'ok' && ! $enCurso)
        <p class="mt-3" style="color:#b91c1c">⚠️ No se pudo generar{{ ! empty($ultimaGenerar->r['error']) ? ': '.$ultimaGenerar->r['error'] : ' (mira el aviso de tareas)' }}</p>
    @endif

    @if ($generado)
        <div class="mt-3 p-3 border border-gray-300 rounded-md">
            <p style="color:#047857">✓ {{ $generado->r['resumen'] ?? 'Generado' }}
                <span class="text-xs text-gray-500">· {{ $generado->r['fecha'] ?? '' }}{{ ! empty($generado->r['pc']) ? ' · '.$generado->r['pc'] : '' }}{{ ! empty($generado->r['origen']) ? ' · '.$generado->r['origen'] : '' }}</span></p>
            @foreach ($generado->r['avisos'] ?? [] as $av) <p class="mt-1" style="color:#b45309">⚠️ {{ $av }}</p> @endforeach
            <div class="flex flex-wrap items-center gap-3 mt-2">
                @if ($enlace)<a href="{{ $enlace }}" download class="text-indigo-700 underline">⬇ Descargar {{ $generado->r['fichero'] ?? 'el Excel' }}</a>@endif
                @if (! $confirmarEnvio)
                    <button type="button" wire:click="pedirEnvio" @disabled($enCurso) class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">✉ Enviar a Susana y Marta…</button>
                @endif
            </div>

            @if ($confirmarEnvio)
                <div class="mt-3 p-3 rounded-md" style="background:#fef9c3;border:1px solid #eab308">
                    <p><b>¿Seguro que lo envío?</b> @if ($yaEnviado) Mira que <b>ya se envió</b> el {{ $yaEnviado->r['fecha'] ?? '' }}; se mandaría otra vez. @endif</p>
                    <p class="mt-1">Para: susana.martinez@sumaempresa.com, marta.ruiz@sumaempresa.com · Asunto: <b>Prefacturacion {{ ucfirst($meses[$mes - 1]) }}</b> ·
                        adjunto: {{ $generado->r['fichero'] ?? '' }} (el que hay ahora en OneDrive) · con tu firma.</p>
                    <div class="flex flex-wrap gap-3 mt-2">
                        <button type="button" wire:click="confirmarEnviar" class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Sí, enviar</button>
                        <button type="button" wire:click="cancelarEnvio" class="px-3 py-1 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($envio && ! $enCurso)
        <p class="mt-3" style="color:{{ $envio->estado === 'ok' ? '#047857' : '#b91c1c' }}">
            {{ $envio->estado === 'ok' ? '✉ '.($envio->r['resumen'] ?? 'Enviado').' · '.($envio->r['fecha'] ?? '') : '⚠️ El último envío falló (mira el aviso de tareas)' }}</p>
    @endif
    @endif
</div>
