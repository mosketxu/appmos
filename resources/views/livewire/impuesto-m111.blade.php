{{-- Impuestos → IRPF M111 (ImpuestoM111, 15-oct-2026): ficheros de nóminas y profesionales → fichero 111 para la AEAT + Excel resumen. --}}
<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_impuestos', ['activa' => 'm111'])

    <div class="p-4" style="max-width:1250px">
    <style>
        .m1-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:12px }
        .m1-h { font-weight:600; color:#111827; margin-bottom:8px }
        .m1-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer; display:inline-block }
        .m1-btn.pri { background:#059669; border-color:#059669; color:#fff; font-weight:700 }
        .m1-btn.on { background:#4f46e5; border-color:#4f46e5; color:#fff }
        .m1-t { width:100%; border-collapse:collapse; font-size:13px } .m1-t td, .m1-t th { padding:3px 8px; border-bottom:1px solid #f1f5f9; text-align:left }
        .m1-n { text-align:right !important; font-variant-numeric:tabular-nums }
        .m1-zona { border:1.5px dashed #c7d2fe; border-radius:8px; padding:10px; background:#fafaff }
        .m1-zona.encima { background:#eef2ff; border-color:#6366f1 }
        .m1-in { border:1px solid #d1d5db; border-radius:6px; padding:3px 8px; font-size:13px }
    </style>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">IRPF M111 <span class="text-sm font-normal text-gray-500">retenciones de trabajo y profesionales · fichero para importar en la AEAT</span></h1>

    @if ($error)<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#991b1b">⚠️ {{ $error }}</div>@endif
    @if ($msg)<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#166534">✅ {{ $msg }}</div>@endif

    <div class="m1-card">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="28rem" vacio="Ninguna empresa con el 111 en Impuestos" />
            <button type="button" wire:click="cambiarEjercicio(-1)" class="m1-btn">◀</button><b>{{ $ejercicio }}</b><button type="button" wire:click="cambiarEjercicio(1)" class="m1-btn">▶</button>
            @foreach ($periodosLista as $k => $t)
                <button type="button" wire:click="$set('periodo', '{{ $k }}')" class="m1-btn {{ $periodo === $k ? 'on' : '' }}">{{ $t }}</button>
            @endforeach
            @if (\App\Support\Impuestos::esGestor())
                <label class="text-xs text-gray-600" style="margin-left:auto"><input type="checkbox" wire:model.live="verTodos" class="border-gray-300 rounded"> ver todas las empresas</label>
            @endif
        </div>
        @if (! $empresas)<div class="mt-2 text-sm text-gray-500">No tienes empresas con el modelo 111 en Seguimiento de impuestos (se definen en Entidades → zona «Impuestos»).</div>@endif
    </div>

    @if ($entidadId)
    <div class="m1-card">
        <div class="m1-h">Ficheros <span class="text-xs font-normal text-gray-500">— cada uno en su sitio, con el nombre que tenga; arrastra o pulsa. Se recalcula solo.</span></div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:10px">
            @foreach (\App\Http\Livewire\ImpuestoM111::TIPOS as $t => [$titulo, $ext, $ayuda])
                <div class="m1-zona" x-data="{ encima: false }" :class="encima && 'encima'"
                     x-on:dragover.prevent="encima = true" x-on:dragleave="encima = false"
                     x-on:drop.prevent="encima = false; $wire.uploadMultiple('subidas.{{ $t }}', $event.dataTransfer.files)">
                    <div class="text-sm font-semibold text-gray-800">{{ $titulo }} <span class="text-xs font-normal text-gray-500">{{ $ext }}</span></div>
                    <div class="text-xs text-gray-500" style="margin:2px 0 6px">{{ $ayuda }}</div>
                    @foreach ($ficheros[$t] ?? [] as $n)
                        <div class="text-xs" style="display:flex;gap:6px;align-items:center">📄 <span style="flex:1;word-break:break-all">{{ $n }}</span>
                            <button type="button" wire:click="quitar('{{ $t }}', @js($n))" class="text-gray-400 hover:text-red-600" title="Quitar">✕</button></div>
                    @endforeach
                    <x-boton-subir como="label" :vacio="empty($ficheros[$t] ?? [])" target="subidas.{{ $t }}" style="margin-top:6px">Elegir…<input type="file" multiple wire:model="subidas.{{ $t }}" accept="{{ $t === 'txt' ? '.txt' : ($t === 'pdf' ? '.pdf' : '.xlsx,.xls') }}" style="display:none"></x-boton-subir>
                    @if ($t === 'mayor' && $mayorBase)
                        <label class="text-xs text-gray-600" style="display:block;margin-top:6px"><input type="checkbox" wire:model.live="usarMayorBase" class="border-gray-300 rounded">
                            usar también el mayor de ficheros base: «{{ $mayorBase['nombre'] }}» ({{ date('d/m/Y', $mayorBase['fecha']) }})</label>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="m1-card">
        <div class="m1-h">Ajustes <span class="text-xs font-normal text-gray-500">— vacío = lo que salga de los ficheros</span></div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center" class="text-sm">
            <label>Tipo <select wire:model.live="aj.tipo" class="m1-in"><option value="">automático</option><option value="I">I · ingreso</option><option value="U">U · domiciliación</option><option value="G">G · ingreso en CCT</option><option value="N">N · negativa</option></select></label>
            <label>IBAN <input type="text" wire:model.blur="aj.iban" class="m1-in" style="width:17rem" placeholder="ES.. (con domiciliación)"></label>
            <label>Nóminas · nº perceptores <input type="text" wire:model.blur="aj.perceptores_nominas" class="m1-in" style="width:5rem"></label>
            <label>percepciones <input type="text" wire:model.blur="aj.percepciones_nominas" class="m1-in" style="width:8rem"></label>
            <label>retenciones <input type="text" wire:model.blur="aj.retenciones_nominas" class="m1-in" style="width:8rem"></label>
            <label><input type="checkbox" wire:model.live="aj.complementaria" class="border-gray-300 rounded"> complementaria</label>
            @if ($aj['complementaria'])
                <label>nº justificante anterior <input type="text" wire:model.blur="aj.justificante_anterior" class="m1-in" style="width:10rem"></label>
                <label>resultado anterior (29) <input type="text" wire:model.blur="aj.resultado_anterior" class="m1-in" style="width:8rem"></label>
            @endif
        </div>
    </div>

    @if ($res)
        @php $cas = $res['casillas'] ?? []; $eur = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
        <div class="m1-card">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
                <div class="m1-h" style="margin:0">Resultado · {{ $res['periodo'] ?? '' }} {{ $res['ejercicio'] ?? '' }}</div>
                @if (! empty($res['fichero']))
                    <button type="button" wire:click="descargar('fichero')" class="m1-btn pri">⬇ Fichero 111 para la AEAT</button>
                @endif
                @if (! empty($res['excel']))
                    <button type="button" wire:click="descargar('excel')" class="m1-btn">⬇ Excel resumen</button>
                    <button type="button" wire:click="guardarEnCasilla" class="m1-btn" title="Deja el Excel en la casilla 111 del periodo en Seguimiento de impuestos">📌 Guardar en Seguimiento</button>
                @endif
                <span class="text-xs text-gray-500">Sede AEAT → modelo 111 → «Importar» el fichero; revisa el borrador antes de presentar.</span>
            </div>
            @foreach ($res['errores'] ?? [] as $e)<div class="text-sm" style="color:#b91c1c">⛔ {{ $e }}</div>@endforeach
            @foreach ($res['avisos'] ?? [] as $a)<div class="text-sm" style="color:#b45309">⚠️ {{ $a }}</div>@endforeach
            @foreach ($res['fuentes'] ?? [] as $f)<div class="text-xs text-gray-600">📎 {{ $f }}</div>@endforeach

            @if ($cas)
            <table class="m1-t" style="margin-top:8px; max-width:760px">
                <tr><th>Casilla</th><th>Concepto</th><th class="m1-n">Importe</th></tr>
                @foreach ($cas as $c => $v)
                    @if ($v || in_array($c, ['28', '30'], true))
                        <tr @if (in_array($c, ['28', '30'], true)) style="font-weight:700" @endif>
                            <td>{{ $c }}</td><td>{{ [
                                '01' => 'Trabajo · nº perceptores', '02' => 'Trabajo · percepciones', '03' => 'Trabajo · retenciones',
                                '04' => 'Trabajo en especie · nº perceptores', '05' => 'Trabajo en especie · valor', '06' => 'Trabajo en especie · ingresos a cuenta',
                                '07' => 'Actividades económicas · nº perceptores', '08' => 'Actividades económicas · percepciones', '09' => 'Actividades económicas · retenciones',
                                '28' => 'Total retenciones e ingresos a cuenta', '29' => 'A deducir (complementaria)', '30' => 'Resultado a ingresar'][$c] ?? 'Casilla '.$c }}</td>
                            <td class="m1-n">{{ in_array($c, ['01', '04', '07', '10', '13', '16', '19', '22', '25'], true) ? (int) $v : $eur($v) }}</td>
                        </tr>
                    @endif
                @endforeach
                <tr><td colspan="2">Tipo de declaración</td><td class="m1-n">{{ ['I' => 'Ingreso', 'U' => 'Domiciliación', 'G' => 'Ingreso en CCT', 'N' => 'Negativa'][$res['tipo'] ?? ''] ?? '' }} {{ $res['iban'] ?? '' }}</td></tr>
            </table>
            @endif
        </div>

        @if (! empty($res['perceptores_prof']))
            <div class="m1-card">
                <div class="m1-h">Profesionales por NIF</div>
                <table class="m1-t">
                    <tr><th>Perceptor</th><th>NIF</th><th class="m1-n">Facturas</th><th class="m1-n">Base</th><th class="m1-n">Retención</th></tr>
                    @foreach ($res['perceptores_prof'] as $g)
                        <tr><td>{{ $g['nombre'] }}</td><td>{{ $g['nif'] }}</td><td class="m1-n">{{ $g['facturas'] }}</td><td class="m1-n">{{ $eur($g['base']) }}</td><td class="m1-n">{{ $eur($g['retencion']) }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        @php $l4751 = $res['nominas']['lineas_4751'] ?? []; @endphp
        @if ($l4751)
            <div class="m1-card">
                <div class="m1-h">Mayor 4751 del periodo <span class="text-xs font-normal text-gray-500">— marca las líneas que son retenciones de nóminas{{ str_starts_with($res['nominas']['origen'] ?? '', 'mayor') ? '' : ' (solo para cuadrar: manda '.($res['nominas']['origen'] ?? '').')' }}</span></div>
                <table class="m1-t">
                    <tr><th></th><th>Fecha</th><th>Asiento</th><th>Cuenta</th><th>Comentario</th><th class="m1-n">Debe</th><th class="m1-n">Haber</th><th></th></tr>
                    @foreach ($l4751 as $l)
                        <tr wire:key="l-{{ md5($l['id']) }}" style="{{ $l['incluida'] ? '' : 'color:#9ca3af' }}">
                            <td><input type="checkbox" @checked($l['incluida']) wire:click="alternarLinea(@js($l['id']), {{ $l['incluida'] ? 'true' : 'false' }})" class="border-gray-300 rounded"></td>
                            <td>{{ $l['fecha'] }}</td><td>{{ $l['asiento'] }}</td><td>{{ $l['cuenta'] }}</td><td>{{ $l['comentario'] }}</td>
                            <td class="m1-n">{{ $l['debe'] ? $eur($l['debe']) : '' }}</td><td class="m1-n">{{ $l['haber'] ? $eur($l['haber']) : '' }}</td>
                            <td class="text-xs">{{ $l['motivo'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif
    @endif
    </div>
</div>
