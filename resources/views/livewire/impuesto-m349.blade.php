{{-- Impuestos → M349 operaciones intracomunitarias (ImpuestoM349, 16-oct-2026). Solo NAE de momento; el resto, «en construcción». --}}
<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_impuestos', ['activa' => 'm349'])

    <div class="p-4" style="max-width:1100px" @if ($esperandoCarpetas) wire:poll.3s="revisar" @endif>
    <style>
        .m3-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:12px }
        .m3-h { font-weight:600; color:#111827; margin-bottom:8px }
        .li-btn, .m3-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer; display:inline-block }
        .m3-btn.on { background:#4f46e5; border-color:#4f46e5; color:#fff }
        .m3-btn[disabled] { opacity:.5; cursor:default }
        .m3-zona { border:1.5px dashed #c7d2fe; border-radius:8px; padding:10px; background:#fafaff }
        .m3-zona.encima { background:#eef2ff; border-color:#6366f1 }
    </style>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">M349 <span class="text-sm font-normal text-gray-500">declaración recapitulativa de operaciones intracomunitarias</span></h1>

    @if ($error)<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#991b1b">⚠️ {{ $error }}</div>@endif
    @if ($msg)<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#166534">✅ {{ $msg }}</div>@endif

    <div class="m3-card">
        <div class="m3-h">1 · Cliente y periodo</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="28rem"/>
            @if ($esOperativo)
                <button type="button" wire:click="cambiarEjercicio(-1)" class="m3-btn">◀</button><b>{{ $ejercicio }}</b><button type="button" wire:click="cambiarEjercicio(1)" class="m3-btn">▶</button>
                @foreach ($periodosLista as $k => $t)
                    <button type="button" wire:click="$set('periodo', '{{ $k }}')" class="m3-btn {{ $periodo === $k ? 'on' : '' }}">{{ $t }}</button>
                @endforeach
            @endif
            @if (\App\Support\Impuestos::esGestor())
                <label class="text-xs text-gray-600" style="margin-left:auto"><input type="checkbox" wire:model.live="verTodos" class="border-gray-300 rounded"> ver todos los clientes</label>
            @endif
        </div>
        @if (! $empresas)<div class="mt-2 text-sm text-gray-500">No tienes clientes con el modelo 349 en Seguimiento de impuestos (se definen en Entidades → zona «Impuestos»).</div>@endif
    </div>

    @if ($entidadId && ! $esOperativo)
        <div class="m3-card" style="text-align:center;padding:40px 16px">
            <div style="font-size:40px">🚧</div>
            <div class="text-lg font-semibold text-gray-700">En construcción</div>
            <div class="text-sm text-gray-500">El proceso del 349 de momento solo está preparado para NAE Comunicacions.</div>
        </div>
    @elseif ($entidadId)
        <div class="m3-card">
            <div class="m3-h">2 · Carpeta de trabajo</div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;font-size:13px">{{ $carpeta ? 'OneDrive\\'.str_replace('/', '\\', $carpeta) : '— sin carpeta de trabajo —' }}</code>
                @if ($arbol['dirs'])
                    <x-explorador-carpetas model="carpetaElegida" :dirs="$arbol['dirs']" :marcas="[]" :actual="$carpeta" />
                @endif
                <button type="button" wire:click="actualizarCarpetas" class="m3-btn" @disabled($esperandoCarpetas)
                    title="Un PC vuelve a leer las carpetas de OneDrive (unos 30 s)">🔄 {{ $arbol['dirs'] ? 'Actualizar lista' : 'Leer carpetas de OneDrive' }}</button>
                @if ($esperandoCarpetas)<span class="text-xs text-indigo-700">⏳ un PC está leyendo las carpetas…</span>@endif
            </div>
            <div class="text-xs text-gray-500" style="margin-top:6px">Navega por OneDrive (o escribe para buscar) y elige la carpeta del 349; se recuerda para este cliente.{{ $arbol['fecha'] ? ' Lista leída el '.$arbol['fecha'].'.' : '' }}</div>
        </div>

        <div class="m3-card">
            <div class="m3-h">3 · Fichero a tratar <span class="text-xs font-normal text-gray-500">— {{ $periodosLista[$periodo] ?? $periodo }} {{ $ejercicio }}</span></div>
            <div class="m3-zona" x-data="{ encima: false }" :class="encima && 'encima'"
                 x-on:dragover.prevent="encima = true" x-on:dragleave="encima = false"
                 x-on:drop.prevent="encima = false; $wire.upload('subida', $event.dataTransfer.files[0])">
                @forelse ($ficherosLista as $f)
                    <div class="text-sm" style="display:flex;gap:8px;align-items:center">📄 <span style="flex:1;word-break:break-all">{{ $f['nombre'] }}</span>
                        <span class="text-xs text-gray-500">{{ number_format($f['tam'] / 1024, 0, ',', '.') }} KB · {{ date('d/m/Y H:i', $f['fecha']) }}</span>
                        <button type="button" wire:click="quitar(@js($f['nombre']))" class="text-gray-400 hover:text-red-600" title="Quitar">✕</button></div>
                @empty
                    <div class="text-sm text-gray-500">Arrastra aquí el fichero o pulsa «Elegir…» (Excel, CSV, txt o PDF).</div>
                @endforelse
                <label class="m3-btn" style="margin-top:8px">＋ {{ $ficherosLista ? 'Cambiar fichero…' : 'Elegir…' }}<input type="file" wire:model="subida" accept=".xlsx,.xls,.csv,.txt,.pdf" style="display:none"></label>
                <span wire:loading wire:target="subida" class="text-xs text-indigo-700">subiendo…</span>
            </div>
        </div>
    @endif
    </div>
</div>
