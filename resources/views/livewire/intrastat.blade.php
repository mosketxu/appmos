<div x-data="{ avisos: [] }" x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_impuestos', ['activa' => 'intrastat'])

    <div class="p-4" style="max-width:1100px">
    <style>
        .in-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; margin-bottom:12px }
        .in-h { font-weight:600; color:#111827; margin-bottom:8px }
        .in-btn { padding:4px 12px; font-size:13px; border:1px solid #d1d5db; background:#fff; border-radius:6px; color:#374151; cursor:pointer; text-decoration:none; display:inline-block }
        .in-btn.pri { background:#059669; border-color:#059669; color:#fff; font-weight:700; padding:6px 18px }
        .in-t { width:100%; border-collapse:collapse; font-size:13px } .in-t td, .in-t th { padding:3px 8px; border-bottom:1px solid #f1f5f9 }
        .in-n { text-align:right; font-variant-numeric:tabular-nums }
    </style>
    @php
        $cl = $clientes[$cliente] ?? null;
        $entradas = $this->entradas();
        $res = $resultado;
        $meses = collect(range(0, 14))->map(fn ($i) => date('Y-m', strtotime("first day of -{$i} month")))->push($periodo)->unique()->sort()->reverse()->values();
    @endphp

    <div wire:loading.flex wire:target="generar, procesarSubidas, borrarEntradas"
         style="position:fixed; top:1rem; left:50%; transform:translateX(-50%); z-index:70; align-items:center; gap:.75rem; padding:.75rem 1.5rem; background:#f59e0b; color:#fff; font-weight:700; border-radius:.5rem; box-shadow:0 6px 20px rgba(0,0,0,.3)">
        <span class="animate-pulse" style="font-size:1.6rem">⏳</span><span>Trabajando… no cierres la página.</span>
    </div>

    <h1 class="text-xl font-semibold" style="margin-bottom:10px">🚚 Intrastat <span class="text-sm font-normal text-gray-500">fichero de partidas para la Sede de la AEAT</span></h1>

    @if ($error)<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#991b1b">⚠️ {{ $error }}</div>@endif
    @error('subidas') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    <div class="in-card">
        <div class="in-h">1 · Cliente y mes</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <select wire:model.live="cliente" class="border-gray-300 rounded-md text-sm" style="min-width:16rem">
                @forelse ($clientes as $id => $c)
                    <option value="{{ $id }}">{{ $c['nombre'] }}{{ $c['nif'] ? ' · '.$c['nif'] : '' }}</option>
                @empty
                    <option value="">(sin clientes configurados)</option>
                @endforelse
            </select>
            <select wire:model.live="periodo" class="border-gray-300 rounded-md text-sm">
                @foreach ($meses as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
            </select>
            <span class="text-xs text-gray-500">Se presenta hasta el día 12 del mes siguiente. El cliente decide cómo se leen los ficheros (formato).</span>
        </div>
        @if ($cl && $cl['ayuda'])<div class="text-xs text-gray-500" style="margin-top:6px">📎 {{ $cl['ayuda'] }}</div>@endif
    </div>

    <div class="in-card">
        <div class="in-h">2 · Ficheros del cliente</div>
        <div wire:key="subida"
             x-data="{ encima: false, subiendo: false,
                 subir(files) { if (! files || ! files.length) return; this.subiendo = true;
                     $wire.uploadMultiple('subidas', files, () => { $wire.procesarSubidas().finally(() => this.subiendo = false); }, () => { this.subiendo = false; }); } }"
             x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
             :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''"
             style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; padding:6px; border:1px dashed #d1d5db; border-radius:6px">
            <input type="file" multiple accept=".xlsx,.xlsm" class="hidden" x-ref="input" x-on:change="subir($event.target.files); $event.target.value = ''">
            <button type="button" x-on:click="$refs.input.click()" class="in-btn">⬆ Subir Excel</button>
            <span class="text-xs text-gray-500">o arrástralos aquí (se pueden subir varios)</span>
            @foreach ($entradas as $e)<span class="text-xs" style="color:#047857">✔ {{ \Illuminate\Support\Str::limit($e, 60) }}</span>@endforeach
            @if ($entradas)<button type="button" wire:click="borrarEntradas" wire:confirm="¿Quitar los ficheros subidos de este mes?" class="in-btn" title="Quitar los ficheros">🗑</button>@endif
        </div>
        <div style="margin-top:10px">
            <button type="button" wire:click="generar" wire:loading.attr="disabled" wire:target="generar" class="in-btn pri">▶ Generar el CSV de {{ $periodo }}</button>
        </div>
    </div>

    @if ($res)
        <div class="in-card">
            <div class="in-h">3 · Resultado <span class="font-normal text-gray-500">{{ $res['periodo'] }} · generado el {{ $res['generado'] }}</span></div>
            <div style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:flex-start">
                <div class="text-sm">
                    <div><b>{{ $res['partidas'] }}</b> partidas · {{ $res['lineas'] }} líneas leídas</div>
                    <div>Masa neta: <b>{{ number_format($res['kg'], 0, ',', '.') }}</b> kg · Unidades: <b>{{ number_format($res['uds'], 0, ',', '.') }}</b> · Valor: <b>{{ number_format($res['valor'], 2, ',', '.') }} €</b></div>
                    <div class="text-xs text-gray-500">{{ $res['pesos_minimos'] }} partidas con peso inferior a 1 kg (se declara 1 kg) · de: {{ implode(', ', $res['entradas']) }}</div>
                </div>
                <div>
                    <a class="in-btn pri" href="{{ route('impuestos.intrastat.descargar', [$cliente, $periodo, $res['fichero']]) }}">⬇ {{ $res['fichero'] }}</a>
                    <div class="text-xs text-gray-500" style="margin-top:4px">Es el que se sube en la Sede: Aduanas → Intrastat → Gestiones → Presentación por fichero (CSV).</div>
                </div>
            </div>

            @if (! empty($res['avisos']))
                <div style="margin-top:10px; padding-top:8px; border-top:1px solid #e5e7eb">
                    <div class="text-sm font-semibold text-gray-700">Qué mirar antes de subirlo ({{ count($res['avisos']) }})</div>
                    <ul style="margin-top:.25rem; max-height:16rem; overflow:auto">
                        @foreach ($res['avisos'] as $a)<li class="text-xs" style="padding:.15rem 0; color:#92400e">• {{ $a }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <details style="margin-top:10px">
                <summary class="text-sm font-semibold text-gray-700" style="cursor:pointer">Partidas ({{ $res['partidas'] }}) — el nº de partida es el de la Sede</summary>
                <div style="max-height:28rem; overflow:auto; margin-top:6px">
                    <table class="in-t">
                        <thead><tr style="text-align:left; background:#f9fafb; position:sticky; top:0"><th>Partida</th><th>Prov.</th><th>Nat.</th><th>NC</th><th class="in-n">Kg</th><th class="in-n">Unidades</th><th class="in-n">Valor</th><th class="in-n">Líneas</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($res['tabla'] as $p)
                            <tr style="{{ $p['aviso'] ? 'background:#fef3c7' : '' }}">
                                <td>{{ $p['n'] }}</td><td>{{ $p['provincia'] }}</td><td>{{ $p['naturaleza'] }}</td><td>{{ $p['nc'] }}</td>
                                <td class="in-n">{{ number_format($p['kg'], 0, ',', '.') }}</td><td class="in-n">{{ number_format($p['uds'], 0, ',', '.') }}</td>
                                <td class="in-n">{{ number_format($p['valor_est'], 2, ',', '.') }}</td><td class="in-n">{{ $p['lineas'] }}</td>
                                <td class="text-xs" style="color:#92400e">{{ $p['aviso'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif
    </div>
</div>
