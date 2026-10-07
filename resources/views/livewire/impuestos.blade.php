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
        .imp-tabla th[wire\:click]:hover { background: #eef2ff; }
        .imp-cel { display: inline-flex; align-items: center; gap: 1px; justify-content: center; min-width: 38px; }
        .imp-m { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; border: 1px solid transparent;
                 color: #fff; font-weight: 700; font-size: 13px; line-height: 1; cursor: pointer; padding: 0; }
        .imp-m.no { background: #fff; border: 1px dashed transparent; }
        .imp-cel:hover .imp-m.no { border-color: #9ca3af; }
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
        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            nav, header, .hojas, .imp-noprint, .imp-pdfw, .imp-pdf, .imp-com:not(.tiene), #imp-fichero, button[title="Información"] { display: none !important; }
            body { background: #fff !important; }
            .imp-solo-print { display: block !important; }
            #imp-pagina { padding: 0 !important; }
            .overflow-x-auto { overflow: visible !important; border: 0 !important; box-shadow: none !important; }
            .imp-tabla { font-size: 9px; }
            .imp-tabla th, .imp-tabla td { padding: 1px 2px; }
            .imp-sticky { position: static !important; min-width: 0; max-width: 150px; white-space: normal !important; }
            .imp-m { width: 15px; height: 15px; font-size: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .imp-chip i { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .imp-cel { min-width: 0; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
        }
        .imp-cel { position: relative; }
        .imp-com { background: none; border: 0; padding: 0; margin: 0; line-height: 1; font-size: 12px; cursor: pointer; }
        .imp-com.tiene sup { font-size: 9px; font-weight: 700; color: #92400e; }
        .imp-com:not(.tiene) { opacity: .7; }
        .imp-com:not(.tiene)::before { content: '💬'; font-size: 13px; }
        .imp-com:not(.tiene):hover { opacity: 1; }
        .imp-com.oculta:not(.tiene) { visibility: hidden; }
        .imp-cel:hover .imp-com.oculta:not(.tiene) { visibility: visible; }
        .imp-btn:first-child { border-radius: 6px 0 0 6px; } .imp-btn:last-child { border-radius: 0 6px 6px 0; }
    </style>

    <div class="p-3 space-y-2" id="imp-pagina">
        <div class="imp-solo-print" style="display:none; font-size:12px">
            <b style="font-size:16px">Impuestos {{ $ejercicio }}</b> · vista: {{ $vista === 'anio' ? 'año completo' : (ctype_digit($vista) ? 'mes '.$vista : $vista) }}
            @if ($filtroModelo !== '') · impuesto {{ $filtroModelo }} @endif @if (trim($buscar) !== '') · «{{ $buscar }}» @endif @if ($soloPendientes) · solo con pendientes @endif
            · {{ $verTodos ? 'todos los clientes' : 'mis clientes' }} · impreso el {{ now()->format('d/m/Y H:i') }}
            <div style="display:flex; flex-wrap:wrap; gap:2px 12px; margin-top:3px; font-size:10px">
                @foreach (['pendiente', 'revision', 'revisado', 'presentado', 'visto', 'nopresenta'] as $e)
                    <span class="imp-chip"><i class="imp-m" style="width:13px; height:13px; font-size:9px; background:{{ $color[$e] }}">{{ $letra[$e] }}</i>{{ $etq[$e] }}</span>
                @endforeach
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 imp-noprint">
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
                    @php $cierra = ($i + 1) % 3 === 0; @endphp
                    <button type="button" wire:click="$set('vista','{{ sprintf('%02d', $i + 1) }}')" class="imp-btn {{ $vista === sprintf('%02d', $i + 1) ? 'on' : '' }}" style="padding:3px 7px"
                        title="{{ $cierra ? 'Mes '.$t.' y, como cierra el trimestre, también los trimestrales T'.(($i + 1) / 3).' y los pagos del 202 que toquen' : 'Solo los impuestos mensuales de '.$t }}">{{ $t }}@if ($cierra) <span style="opacity:.75">+T{{ ($i + 1) / 3 }}</span>@endif</button>
                @endforeach
            </div>
            {{-- Leyenda: botón junto a los meses; el detalle en un modal --}}
            <div x-data="{ ley: false }" x-on:keydown.escape.window="ley = false" class="imp-noprint">
                <button type="button" x-on:click="ley = true" class="imp-btn" style="border-radius:6px" title="Qué significa cada color y cómo se usa">❓ Leyenda</button>
                <div x-show="ley" x-cloak style="display:none; position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); align-items:center; justify-content:center" x-bind:style="ley ? 'display:flex' : 'display:none'" x-on:click.self="ley = false">
                    <div class="p-4 bg-white rounded-lg shadow-xl" style="width:560px; max-width:94vw; max-height:90vh; overflow:auto">
                        <div class="flex items-center justify-between mb-2">
                            <b class="text-lg">Leyenda</b>
                            <button type="button" x-on:click="ley = false" class="px-2 text-xl leading-none text-gray-500 hover:text-gray-800">×</button>
                        </div>
                        <div class="flex flex-col gap-1.5 mb-3">
<span class="imp-chip"><i class="imp-m" style="width:18px; height:18px; border:1px dashed #9ca3af; background:#fff"></i>vacío = no tiene que presentarlo</span>
            @foreach (['pendiente', 'revision', 'revisado', 'presentado', 'visto', 'nopresenta'] as $e)
                <span class="imp-chip"><i class="imp-m" style="width:18px; height:18px; font-size:11px; background:{{ $color[$e] }}">{{ $letra[$e] }}</i>{{ $etq[$e] }} <b>({{ $cuenta[$e] }})</b></span>
            @endforeach
                        </div>
                        <div class="text-sm text-gray-700">Un clic en la casilla pasa al siguiente estado: vacío → pendiente → listo para revisión → revisado → presentado → visto por Marta (este último solo lo marca ella).
                Mayús+clic en una casilla = «no se presenta este periodo» (gris –: p. ej. un 111, 115 o 349 sin movimientos; otro Mayús+clic o un clic lo reabre como pendiente).
                La casilla vacía (no hay obligación) solo se dibuja al pasar el ratón por encima, para poder activarla.<br>
                Icono PDF: de color = hay PDF; gris = pulsa para subirlo (el borrador en revisión/revisado, el presentado después). Hojas apiladas = varios documentos.
                💬 = comentarios de la casilla.</div>
                    </div>
                </div>
            </div>
            @if ($this->puedeTodos() && $sinAsignar->count())
                <button type="button" wire:click="$toggle('mostrarSinAsignar')" class="imp-btn {{ $mostrarSinAsignar ? 'on' : '' }}" style="border-radius:6px; border-color:#f59e0b; margin-left:auto"
                    title="PDF de OneDrive que no se han podido atribuir a ningún cliente">📄 {{ $sinAsignar->sum('n') }} PDF sin cliente</button>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm imp-noprint">
            <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar cliente…" class="py-1 text-sm border-gray-300 rounded-md">
            <select wire:model.live="filtroModelo" class="py-1 text-sm border-gray-300 rounded-md">
                <option value="">Todos los impuestos</option>
                @foreach ($modelos as $m)
                    <option value="{{ $m->codigo }}">{{ $m->codigo }} · {{ $m->nombre }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-1"><input type="checkbox" wire:model.live="soloPendientes" class="border-gray-300 rounded"> Solo con pendientes</label>
            <label class="inline-flex items-center gap-1"><input type="checkbox" wire:model.live="incluirBajas" class="border-gray-300 rounded"> Incluir bajas e inactivos</label>
            <button type="button" onclick="window.print()" class="imp-btn" style="border-radius:6px" title="Imprimir la lista tal como está en pantalla (A4 apaisado)" aria-label="Imprimir">🖨</button>
            <button type="button" wire:click="exportarExcel" wire:loading.attr="disabled" wire:target="exportarExcel" class="imp-btn" style="border-radius:6px" title="Descargar en Excel la lista tal como está filtrada, con colores y comentarios" aria-label="Excel"><svg width="18" height="18" viewBox="0 0 18 18" style="display:block"><rect width="18" height="18" rx="3" fill="#217346"/><path d="M5 4.5l8 9M13 4.5l-8 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg></button>
            @if ($this->puedeTodos())
                <button type="button" wire:click="$toggle('verTodos')" class="imp-btn {{ $verTodos ? 'on' : '' }}" style="border-radius:6px"
                    title="Por defecto solo ves tus impuestos (los de tus clientes o asignados a ti)">{{ $verTodos ? '👥 Viendo los de todos' : '👤 Solo los míos · ver todos' }}</button>
                <button type="button" wire:click="actualizarPdfs" wire:loading.attr="disabled" class="imp-btn" style="border-radius:6px"
                    title="Un PC busca en OneDrive los PDF de impuestos de {{ $ejercicio }} y los sube aquí">🔄 Buscar PDF en OneDrive</button>
            @endif
        </div>

        @if ($aviso)
            <div class="text-xs text-indigo-700 imp-noprint">{{ $aviso }}</div>
        @endif
        @if ($this->puedeTodos() && $tarea)
            <div class="text-xs text-gray-500 imp-noprint">
                Última búsqueda de PDF en OneDrive: {{ \Illuminate\Support\Carbon::parse($tarea->terminada_at ?? $tarea->created_at)->diffForHumans() }} ·
                {{ ['pendiente' => 'en cola', 'en_curso' => 'en marcha…', 'ok' => 'terminada', 'error' => 'con error'][$tarea->estado] ?? $tarea->estado }}
                @php $res = json_decode($tarea->resultado ?? 'null', true); @endphp
                @if (is_array($res) && isset($res['resumen'])) · {{ $res['resumen'] }} @endif
            </div>
        @endif
        @error('archivo') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
        <div wire:loading wire:target="archivo" class="text-xs text-indigo-700 imp-noprint">Subiendo el PDF…</div>

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

        @if ($editEnt)
            <div class="imp-noprint" style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" wire:click.self="cerrarEntidad">
                <div style="background:#fff; border-radius:8px; padding:14px; width:min(900px, 96vw); max-height:86vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.3)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-semibold text-gray-900">{{ \Illuminate\Support\Facades\DB::table('entidades')->where('id', $editEnt)->value('entidad') }}</div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('entidad.edit', $editEnt) }}" class="text-xs text-indigo-700 hover:underline" target="_blank">Abrir la ficha de la entidad ↗</a>
                            <button type="button" wire:click="cerrarEntidad" class="text-gray-400 hover:text-gray-700" title="Cerrar">✕</button>
                        </div>
                    </div>
                    @livewire('entidad-impuestos', ['entidadId' => $editEnt], key('ei-modal-'.$editEnt))
                </div>
            </div>
        @endif

        @if ($comOb)
            @php $cd = $this->comentariosAbiertos; @endphp
            <div class="imp-noprint" style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" wire:click.self="cerrarComentarios">
                <div style="background:#fff; border-radius:8px; padding:14px; width:min(560px, 94vw); max-height:80vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.3)">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <div class="font-semibold text-gray-900">{{ $cd['ob']->entidad }}</div>
                            <div class="text-xs text-gray-500">{{ ctype_digit($cd['ob']->codigo) ? 'M'.$cd['ob']->codigo : $cd['ob']->codigo }}@if ($cd['ob']->etiqueta !== '') · {{ $cd['ob']->etiqueta }}@endif · {{ \App\Support\Impuestos::etiquetaPeriodo($comPer, $ejercicio, $cd['ob']->desfase) }}</div>
                        </div>
                        <button type="button" wire:click="cerrarComentarios" class="text-gray-400 hover:text-gray-700" title="Cerrar">✕</button>
                    </div>
                    @forelse ($cd['lista'] as $c)
                        <div class="py-1.5 border-b border-gray-100" wire:key="cm-{{ $c->id }}">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span><b>{{ $c->name ?: '—' }}</b> · {{ \Illuminate\Support\Carbon::parse($c->created_at)->format('d/m/Y H:i') }}</span>
                                @if ($c->user_id === auth()->id() || $this->puedeTodos())
                                    <button type="button" wire:click="borrarComentario({{ $c->id }})" wire:confirm="¿Borrar este comentario?" class="text-gray-400 hover:text-red-600">✕</button>
                                @endif
                            </div>
                            <div class="text-sm text-gray-800" style="white-space:pre-wrap">{{ $c->texto }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Todavía no hay comentarios.</p>
                    @endforelse
                    <form wire:submit="comentar" class="mt-2">
                        <textarea wire:model="comTexto" rows="2" placeholder="Nuevo comentario…" class="w-full text-sm border-gray-300 rounded-md" autofocus
                            x-on:keydown.ctrl.enter.prevent="$wire.comentar()"></textarea>
                        <div class="flex items-center gap-2 mt-1">
                            <button type="submit" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Añadir comentario</button>
                            <span class="text-xs text-gray-400">Ctrl+Intro también lo añade</span>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <input id="imp-fichero" type="file" accept="application/pdf" wire:model="archivo" style="display:none">

        <div class="overflow-x-auto bg-white border rounded-lg shadow">
            <table class="imp-tabla">
                <thead>
                    <tr>
                        <th class="imp-sticky" rowspan="3">Cliente</th>
                        @foreach ($bloques as $bl)
                            @php $plegado = $bl['plegable'] && in_array($bl['k'], $plegados, true); @endphp
                            <th colspan="{{ $plegado ? 1 : count($bl['cols']) }}" @if ($plegado) rowspan="3" @endif class="imp-qb"
                                style="{{ $bl['plegable'] ? 'cursor:pointer' : '' }}"
                                @if ($bl['plegable']) wire:click="alternarBloque('{{ $bl['k'] }}')" title="{{ $plegado ? 'Desplegar' : 'Comprimir' }} {{ $bl['titulo'] }}" @endif>
                                {{ $bl['titulo'] }} @if ($bl['plegable']) <span class="text-gray-400">{{ $plegado ? '▸' : '▾' }}</span> @endif</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($bloques as $bl)
                            @if (! ($bl['plegable'] && in_array($bl['k'], $plegados, true)))
                                @php $gr = collect($bl['cols'])->groupBy('g'); @endphp
                                @foreach ($gr as $g => $cs)
                                    <th colspan="{{ $cs->count() }}" class="{{ $loop->first ? 'imp-qb' : '' }}" style="border-left:1px solid #cbd5e1">{{ $g }}</th>
                                @endforeach
                            @endif
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($bloques as $bl)
                            @if (! ($bl['plegable'] && in_array($bl['k'], $plegados, true)))
                                @php $gAnt = null; @endphp
                                @foreach ($bl['cols'] as $col)
                                    @php $barra = $gAnt !== null && $col['g'] !== $gAnt; $gAnt = $col['g']; @endphp
                                    <th style="font-weight:500{{ $barra ? '; border-left:1px solid #cbd5e1' : '' }}" title="{{ $col['cod'] }}">{{ $col['cod'] }}</th>
                                @endforeach
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $entId => $f)
                        @php $ent = $f['ent']; @endphp
                        <tr wire:key="c-{{ $entId }}" class="imp-ult">
                            <td class="imp-sticky" style="white-space:nowrap">
                                <button type="button" wire:click="abrirEntidad({{ $entId }})" class="font-medium text-left text-gray-900 hover:underline" style="background:none; border:0; padding:0; cursor:pointer"
                                    title="Añadir o quitar los impuestos de este cliente. Responsable: {{ $ent->resp ?: '—' }}">{{ $ent->entidad }}</button>
                                @if ($ent->estado_ent != 1) <span class="text-xs text-amber-700">(baja)</span> @endif
                            </td>
                            @foreach ($bloques as $bl)
                                @if ($bl['plegable'] && in_array($bl['k'], $plegados, true))
                                    @php
                                        $res = $f['resumen'][$bl['k']] ?? [];
                                        $peor = collect(['pendiente', 'revision', 'revisado', 'presentado', 'visto', 'nopresenta'])->first(fn ($e) => ($res[$e] ?? 0) > 0);
                                        $tit = collect($res)->map(fn ($n, $e) => $n.' '.strtolower($etq[$e]))->implode(' · ');
                                    @endphp
                                    <td class="imp-qb" style="text-align:center" title="{{ $tit }}">
                                        @if ($peor)
                                            <span class="imp-m" style="background:{{ $color[$peor] }}; cursor:default">{{ $peor === 'pendiente' ? $res['pendiente'] : $letra[$peor] }}</span>
                                        @endif
                                    </td>
                                @else
                                    @php $gAnt = null; @endphp
                                    @foreach ($bl['cols'] as $ci => $col)
                                        @php $barra = $gAnt !== null && $col['g'] !== $gAnt; $gAnt = $col['g']; @endphp
                                        <td class="{{ $loop->first ? 'imp-qb' : '' }}" style="text-align:left; white-space:nowrap{{ $barra ? '; border-left:1px solid #cbd5e1' : '' }}">
                                            @foreach ($f['matriz'][$bl['k']][$ci] ?? [] as [$ob, $per, $e])
                                                @if ($ob->etiqueta !== '')
                                                    <span class="text-xs font-semibold text-indigo-700" title="{{ $ob->etiqueta }}">{{ mb_substr($ob->etiqueta, 0, 3) }}</span>
                                                @endif
                                                @include('livewire._impuesto-celda', ['ob' => $ob, 'per' => $per, 'e' => $e, 'docs' => $docs, 'coment' => $coment, 'color' => $color, 'letra' => $letra, 'etq' => $etq])
                                            @endforeach
                                        </td>
                                    @endforeach
                                @endif
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
        @if ($coment)
            <div class="imp-solo-print" style="display:none; font-size:9px">
                <b>Comentarios</b>
                @foreach ($coment as $clave => $lista)
                    @php [$oid, $pp] = explode('|', $clave); $oo = $obsPorId[(int) $oid] ?? null; @endphp
                    @if ($oo)
                        @foreach ($lista as $c)
                            <div>{{ $oo->entidad }} · {{ ctype_digit($oo->codigo) ? 'M'.$oo->codigo : $oo->codigo }}{{ $oo->etiqueta !== '' ? ' ('.$oo->etiqueta.')' : '' }} {{ $pp }}: {{ $c[0] }} <span style="color:#6b7280">— {{ $c[1] }}, {{ \Illuminate\Support\Carbon::parse($c[2])->format('d/m/y') }}</span></div>
                        @endforeach
                    @endif
                @endforeach
            </div>
        @endif
        <p class="text-xs text-gray-500">
            Una fila por cliente. Orden: trimestre → mes → impuesto (01 02 03 | T1, 04 05 06 | T2…; el D2 entre T1 y 04) y al final las anuales. Pulsa el título de un trimestre
            o de «Anuales» para comprimirlo (queda una casilla con lo peor que haya dentro: rojo con el nº de pendientes). Los meses solo salen si algún cliente declara por meses.
            IS, depósito de cuentas y legalización muestran el ejercicio anterior (IS {{ $ejercicio - 1 }} se sigue en {{ $ejercicio }}). 202: pagos 1P (T1), 2P (T3) y 3P (T4).
            Si un cliente tiene dos declaraciones del mismo impuesto, la segunda lleva delante las 3 primeras letras de su etiqueta.
        </p>
    </div>
</div>

@script
<script>
    // Al imprimir, encoge la tabla (zoom) para que quepa en el ancho del folio apaisado
    const ajustar = () => {
        const t = document.querySelector('.imp-tabla'); if (!t) return;
        t.style.zoom = ''; const ancho = t.scrollWidth, util = 1040; if (ancho > util) t.style.zoom = (util / ancho).toFixed(3);
    };
    window.addEventListener('beforeprint', ajustar);
    window.addEventListener('afterprint', () => { const t = document.querySelector('.imp-tabla'); if (t) t.style.zoom = ''; });
</script>
@endscript
