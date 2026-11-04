<div class="text-sm">
    @if (! $puede)
        <p class="text-gray-600">No tienes permiso para este proceso.</p>
    @elseif (! $ent)
        <p style="color:#b91c1c">⚠️ No encuentro la entidad Balerga Digital (NIF {{ \App\Http\Livewire\Contabilidad\InformesBalerga::NIF }}) en Entidades.</p>
    @else
    <p class="text-gray-700">Albena (Balerga Digital) quiere <b>cada mes</b> un resumen de los alquileres <b>facturados, cobrados y pendientes de cobro</b> y de los principales gastos,
        y <b>cada trimestre</b> un Profit &amp; Loss detallado (ingresos, gastos operativos, impuestos y resultado). Se prepara a principios de mes, para el mes anterior.</p>

    <div class="mt-3 p-3 rounded-md" style="background:#f8fafc;border:1px solid #e2e8f0">
        <b>Qué hay que subir (Excel de SAGE de Balerga Digital SLU):</b>
        <ol class="mt-1 ml-5 list-decimal">
            <li><b>📒 Mayor</b> — de <u>todas las cuentas</u> (incluidos bancos, clientes y gastos), <u>desde el 1 de enero del año hasta el último día del mes</u> que se informa, en columnas (el de «Código cuenta, Fecha asiento…»). De aquí salen lo facturado, lo cobrado y los gastos principales, y se contrastan los recibos de la comunidad.</li>
            <li><b>⚖️ Sumas y saldos</b> — <u>acumulado</u> a fin del mes. Da el resultado del mes y el saldo de clientes (lo pendiente de cobro).</li>
            <li><b>📈 Pérdidas y ganancias</b> — del periodo (año hasta el mes). <b>Opcional</b> si subes el sumas y saldos; en el cierre de <b>trimestre</b> (marzo, junio, septiembre, diciembre) mejor sube también este.</li>
            <li><b>🧾 Balance de situación</b> — a fin de mes. <b>Opcional</b>: solo si quieres incluirlo en el informe.</li>
        </ol>
        <p class="mt-1 text-xs text-gray-600">Mínimo imprescindible: <b>mayor + sumas y saldos</b>. Con eso se puede sacar todo; el P&amp;G y el balance sirven para cuadrar. Se guardan en los ficheros base de la empresa (con historial); no se envía nada a nadie.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 mt-3">
        <label class="flex items-center gap-2">Mes que se informa
            <input type="month" wire:model.live="periodo" class="border-gray-300 rounded-md">
        </label>
        @if ($trimestre) <span class="px-2 py-0.5 rounded-md" style="background:#e0e7ff;color:#3730a3">📆 Cierre del {{ $trimestre }}.º trimestre de {{ $anio }}: toca también el informe trimestral</span> @endif
    </div>

    @if ($valido)
    <table class="mt-3" style="border-collapse:collapse">
        @foreach (['mayor' => ['📒 Mayor', true], 'sys' => ['⚖️ Sumas y saldos', true], 'pyg' => ['📈 Pérdidas y ganancias', (bool) $trimestre], 'balance' => ['🧾 Balance de situación', false]] as $t => [$nombre, $obligatorio])
            @php($e = $estado[$t] ?? null)
            <tr style="border-bottom:1px solid #f1f5f9">
                <td class="px-2 py-1 whitespace-nowrap">{{ $nombre }}</td>
                <td class="px-2 py-1">
                    @if ($e && $e['ultimo'])
                        @if ($e['antiguo'])<span style="color:#b45309">⚠️ {{ $e['ultimo']['nombre'] }} — subido el {{ date('d/m/Y', $e['ultimo']['fecha']) }}: es anterior al 1 de este mes, ¿es el del cierre?</span>
                        @else<span style="color:#047857">✓ {{ $e['ultimo']['nombre'] }} <span class="text-xs text-gray-500">· subido el {{ date('d/m/Y H:i', $e['ultimo']['fecha']) }}</span></span>@endif
                    @elseif ($obligatorio)
                        <span style="color:#b91c1c">falta subirlo</span>
                    @else
                        <span class="text-gray-500">opcional — no hay</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    @endif

    <div class="mt-3">
        @livewire('ficheros-base-subida', ['entidadId' => (int) $ent->id, 'tipos' => ['mayor', 'sys', 'pyg', 'balance'], 'abierto' => true, 'periodo' => $periodo], key('fb-informes-balerga-'.$ent->id.'-'.$periodo))
    </div>

    <p class="mt-2 text-xs text-gray-500">Cuando estén subidos, abre una sesión de Claude en <i>Contabilidad/Balerga</i> y pide el informe del mes; las cuotas de la comunidad se contrastan con lo cobrado (ver <i>Balerga/PLAN.md</i>).</p>
    @endif
</div>
