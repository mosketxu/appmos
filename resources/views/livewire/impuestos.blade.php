<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_todo', ['activa' => 'impuestos'])

    @php
        $color = \App\Support\Impuestos::COLOR;
        $letra = \App\Support\Impuestos::LETRA;
        $etq = \App\Support\Impuestos::ESTADOS;
        $vistas = ['anio' => 'Año', 'T1' => 'T1', 'T2' => 'T2', 'T3' => 'T3', 'T4' => 'T4'];
        $sinAsignar = $this->sinAsignar;
        $tarea = $this->tareaPdfsEstado;
    @endphp

    <style>
        [x-cloak] { display: none !important; }
        .imp-tabla { border-collapse: separate; border-spacing: 0; font-size: 12px; background: #fff; }
        .imp-tabla th { background: #f9fafb; color: #6b7280; font-weight: 600; padding: 3px 4px; border-bottom: 1px solid #d1d5db; text-align: center; white-space: nowrap; }
        .imp-tabla td { padding: 2px 3px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .imp-tabla tr.imp-ult td { border-bottom: 1px solid #d1d5db; }
        .imp-sticky { position: sticky; left: 0; z-index: 2; background: #fff; min-width: 190px; max-width: 260px; border-right: 1px solid #e5e7eb; }
        th.imp-sticky { background: #f9fafb; z-index: 3; text-align: left; }
        .imp-qb { border-left: 2px solid #9ca3af; }
        .imp-cel { display: inline-flex; align-items: center; gap: 1px; justify-content: center; min-width: 38px; }
        .imp-m { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; border: 1px solid transparent;
                 color: #fff; font-weight: 700; font-size: 13px; line-height: 1; cursor: pointer; padding: 0; }
        .imp-m.no { background: #fff; border: 1px dashed #d1d5db; }
        .imp-m.no:hover { border-color: #6b7280; }
        .imp-m:hover { filter: brightness(1.12); }
        .imp-pdf { display: inline-flex; padding: 0; margin: 0; background: none; border: 0; cursor: pointer; line-height: 0; }
        .imp-pdf svg { width: 16px; height: 16px; }
        .imp-pdf.gris { color: #cbd5e1 !important; }
        .imp-pdf.gris:hover { color: #94a3b8 !important; }
        .imp-pdfw { position: relative; display: inline-flex; }
        .imp-pop { position: absolute; top: 22px; right: -6px; z-index: 20; background: #fff; border: 1px solid #9ca3af; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,.2);
                   padding: 4px; min-width: 220px; max-width: 340px; text-align: left; white-space: normal; }
        .imp-pop a, .imp-pop button { display: block; width: 100%; text-align: left; padding: 3px 6px; font-size: 12px; color: #1f2937; border-radius: 4px; background: none; border: 0; cursor: pointer; }
        .imp-pop a:hover, .imp-pop button:hover { background: #f3f4f6; }
        .imp-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #374151; }
        .imp-chip i { display: inline-block; width: 14px; height: 14px; border-radius: 3px; font-style: normal; text-align: center; line-height: 18px; color: #fff; font-weight: 700; }
        .imp-btn { padding: 3px 10px; font-size: 13px; border: 1px solid #d1d5db; background: #fff; color: #374151; }
        .imp-btn.on { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        .imp-btn:first-child { border-radius: 6px 0 0 6px; } .imp-btn:last-child { border-radius: 0 6px 6px 0; }
    </style>

    <div class="p-3 space-y-2">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">Impuestos</h1>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="cambiarEjercicio(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
                <span class="px-2 font-semibold">{{ $ejercicio }}</span>
                <button type="button" wire:click="cambiarEjercicio(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
            </div>
            <div class="inline-flex">
                @foreach ($vistas as $k => $t)
                    <button type="button" wire:click="$set('vista','{{ $k }}')" class="imp-btn {{ $vista === $k ? 'on' : '' }}">{{ $t }}</button>
                @endforeach
            </div>
            <div class="inline-flex">
                @foreach (\App\Support\Impuestos::MESES as $i => $t)
                    <button type="button" wire:click="$set('vista','{{ sprintf('%02d', $i + 1) }}')" class="imp-btn {{ $vista === sprintf('%02d', $i + 1) ? 'on' : '' }}" style="padding:3px 7px">{{ $t }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar cliente…" class="py-1 text-sm border-gray-300 rounded-md">
            <select wire:model.live="filtroModelo" class="py-1 text-sm border-gray-300 rounded-md">
                <option value="">Todos los impuestos</option>
                @foreach ($modelos as $m)
                    <option value="{{ $m->codigo }}">{{ $m->codigo }} · {{ $m->nombre }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-1"><input type="checkbox" wire:model.live="soloPendientes" class="border-gray-300 rounded"> Solo con pendientes</label>
            <label class="inline-flex items-center gap-1"><input type="checkbox" wire:model.live="incluirBajas" class="border-gray-300 rounded"> Incluir bajas e inactivos</label>
            @if ($this->puedeTodos())
                <button type="button" wire:click="$toggle('verTodos')" class="imp-btn {{ $verTodos ? 'on' : '' }}" style="border-radius:6px"
                    title="Por defecto solo ves tus impuestos (los de tus clientes o asignados a ti)">{{ $verTodos ? '👥 Viendo los de todos' : '👤 Solo los míos · ver todos' }}</button>
                <button type="button" wire:click="actualizarPdfs" wire:loading.attr="disabled" class="imp-btn" style="border-radius:6px"
                    title="Un PC busca en OneDrive los PDF de impuestos de {{ $ejercicio }} y los sube aquí">🔄 Buscar PDF en OneDrive</button>
                @if ($sinAsignar->count())
                    <button type="button" wire:click="$toggle('mostrarSinAsignar')" class="imp-btn {{ $mostrarSinAsignar ? 'on' : '' }}" style="border-radius:6px; border-color:#f59e0b">
                        📄 {{ $sinAsignar->sum('n') }} PDF sin cliente</button>
                @endif
            @endif
        </div>

        {{-- Leyenda de las marcas (con su letra) y cuántas hay en la vista --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-1.5 bg-white border border-gray-200 rounded-md">
            <b class="text-xs text-gray-500">LEYENDA</b>
            <span class="imp-chip"><i class="imp-m no" style="width:18px; height:18px"></i>sin marcar = no tiene que presentarlo</span>
            @foreach (['pendiente', 'revision', 'revisado', 'presentado', 'visto'] as $e)
                <span class="imp-chip"><i class="imp-m" style="width:18px; height:18px; font-size:11px; background:{{ $color[$e] }}">{{ $letra[$e] }}</i>{{ $etq[$e] }} <b>({{ $cuenta[$e] }})</b></span>
            @endforeach
            <span class="text-xs text-gray-500">Un clic en la casilla pasa al siguiente estado; «visto por Marta» solo lo marca ella. Icono PDF: color = hay PDF; gris = pulsa para subirlo (borrador en revisión/revisado, el presentado después).</span>
        </div>

        @if ($aviso)
            <div class="text-xs text-indigo-700">{{ $aviso }}</div>
        @endif
        @if ($this->puedeTodos() && $tarea)
            <div class="text-xs text-gray-500">
                Última búsqueda de PDF en OneDrive: {{ \Illuminate\Support\Carbon::parse($tarea->terminada_at ?? $tarea->created_at)->diffForHumans() }} ·
                {{ ['pendiente' => 'en cola', 'en_curso' => 'en marcha…', 'ok' => 'terminada', 'error' => 'con error'][$tarea->estado] ?? $tarea->estado }}
                @php $res = json_decode($tarea->resultado ?? 'null', true); @endphp
                @if (is_array($res) && isset($res['resumen'])) · {{ $res['resumen'] }} @endif
            </div>
        @endif
        @error('archivo') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
        <div wire:loading wire:target="archivo" class="text-xs text-indigo-700">Subiendo el PDF…</div>

        {{-- PDF de OneDrive que no se han podido atribuir a un cliente --}}
        @if ($mostrarSinAsignar && $sinAsignar->count())
            <div class="p-3 bg-white border border-amber-300 rounded-lg" style="max-width:900px">
                <div class="mb-1 text-sm font-semibold">PDF sin cliente <span class="font-normal text-gray-500">— elige un nombre y busca la entidad: se asignan todos los de ese nombre y se recuerda para los próximos</span></div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sinAsignar as $s)
                        <button type="button" wire:click="$set('asignarTexto', @js($s->cliente_texto))"
                            class="imp-btn {{ $asignarTexto === $s->cliente_texto ? 'on' : '' }}" style="border-radius:6px">{{ $s->cliente_texto ?: '(sin nombre)' }} <b>{{ $s->n }}</b></button>
                    @endforeach
                </div>
                @if ($asignarTexto !== '')
                    <div class="mt-2 text-sm">
                        «{{ $asignarTexto }}» es →
                        <input type="text" wire:model.live.debounce.300ms="buscarEnt" placeholder="Buscar entidad…" class="py-1 text-sm border-gray-300 rounded-md" autofocus>
                        <input type="text" wire:model="asignarEtiqueta" placeholder="Etiqueta (solo si es otra declaración del mismo cliente)" class="py-1 text-sm border-gray-300 rounded-md" style="width:340px">
                        @foreach ($this->resultadosEntidad as $e)
                            <button type="button" wire:click="asignar({{ $e->id }})" class="imp-btn" style="border-radius:6px; margin-left:4px">{{ $e->entidad }}{{ $e->estado == 1 ? '' : ' (baja)' }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <input id="imp-fichero" type="file" accept="application/pdf" wire:model="archivo" style="display:none">

        <div class="overflow-x-auto bg-white border rounded-lg shadow">
            <table class="imp-tabla">
                <thead>
                    <tr>
                        <th class="imp-sticky" rowspan="2">Cliente</th>
                        @foreach ($grupos as $g)
                            <th colspan="{{ count($g->columnas) }}" class="imp-qb" title="{{ $g->nombre }}">{{ ctype_digit($g->codigo) ? 'M'.$g->codigo : $g->codigo }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($grupos as $g)
                            @foreach ($g->columnas as $i => $col)
                                <th class="{{ $i === 0 ? 'imp-qb' : '' }}">{{ $col['t'] }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $entId => $f)
                        @php $ent = $f['ent']; @endphp
                        <tr wire:key="c-{{ $entId }}" class="imp-ult">
                            <td class="imp-sticky" style="white-space:nowrap" title="Responsable: {{ $ent->resp ?: '—' }}">
                                <a href="{{ route('entidad.edit', $entId) }}" class="font-medium text-gray-900 hover:underline" title="Abrir la entidad (aquí se define qué impuestos presenta). Responsable: {{ $ent->resp ?: '—' }}">{{ $ent->entidad }}</a>
                                @if ($ent->estado_ent != 1) <span class="text-xs text-amber-700">(baja)</span> @endif
                            </td>
                            @foreach ($grupos as $cod => $g)
                                @foreach ($g->columnas as $i => $col)
                                    <td class="{{ $i === 0 ? 'imp-qb' : '' }}" style="text-align:left; white-space:nowrap">
                                        @foreach ($f['por'][$cod] ?? [] as $o)
                                            @php $ob = $o['ob']; @endphp
                                            @if ($ob->etiqueta !== '' && count($o['celdas'][$col['k']] ?? []))
                                                <span class="text-xs font-semibold text-indigo-700" title="{{ $ob->etiqueta }}">{{ mb_substr($ob->etiqueta, 0, 3) }}</span>
                                            @endif
                                            @foreach ($o['celdas'][$col['k']] ?? [] as $per)
                                                @php $e = $estados[$ob->id][$per] ?? null; @endphp
                                                @if ($e !== null)
                                                    @include('livewire._impuesto-celda', ['ob' => $ob, 'per' => $per, 'e' => $e, 'docs' => $docs, 'color' => $color, 'letra' => $letra, 'etq' => $etq])
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="2" class="p-4 text-gray-500">
                            Nada que mostrar. @if (! $verTodos && $this->puedeTodos()) Pulsa «Solo los míos · ver todos» para ver los de todos. @endif
                            Los impuestos de cada cliente se definen en Entidades → zona «Impuestos».
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">
            Una fila por cliente; cada impuesto tiene sus columnas T1–T4 (los mensuales llevan tres casillas por trimestre: ene-feb-mar…, el periodo que se declara).
            IS, depósito de cuentas y legalización muestran el ejercicio anterior (IS {{ $ejercicio - 1 }} se sigue en {{ $ejercicio }}). 202: pagos 1P (T1), 2P (T3) y 3P (T4).
            Si un cliente tiene dos declaraciones del mismo impuesto, la segunda lleva delante las 3 primeras letras de su etiqueta.
        </p>
    </div>
</div>
