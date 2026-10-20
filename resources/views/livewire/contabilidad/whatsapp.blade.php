<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.whatsapp'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.whatsapp'])

    @php
        $btn = 'px-2 py-1 text-xs text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50';
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $imagenes = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $audios = ['opus', 'ogg', 'mp3', 'm4a', 'aac'];
        $kb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : number_format(max(1, $b / 1024), 0, ',', '.').' KB';
        $resultado = $tarea && $tarea->resultado ? json_decode($tarea->resultado, true) : null;
    @endphp

    <div class="p-4 space-y-3">
        <h1 class="text-2xl font-semibold text-gray-900">WhatsApp <span class="text-sm font-normal text-gray-500">conversaciones con clientes guardadas en Appmos</span></h1>

        <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center">
            <label class="text-sm font-medium text-gray-700" for="wa-chat">Cliente</label>
            <select id="wa-chat" wire:model.live="chat" class="text-sm border-gray-300 rounded" style="min-width:14rem">
                @foreach ($chats as $c)
                    <option value="{{ $c->clave }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            @if ($actual)
                <button type="button" wire:click="importar" wire:loading.attr="disabled" class="{{ $btn }}" style="background:#059669; color:#fff; border-color:#047857; font-weight:600">⬇ Importar ahora</button>
                <input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar en la conversación…" class="text-sm border-gray-300 rounded" style="min-width:16rem">
            @endif
            <details style="margin-left:auto">
                <summary class="text-xs text-gray-500 cursor-pointer">＋ Nuevo cliente</summary>
                <form wire:submit="crearChat" style="display:flex; gap:.4rem; margin-top:.3rem">
                    <input type="text" wire:model="nuevoNombre" placeholder="Nombre del cliente" class="text-sm border-gray-300 rounded">
                    <button type="submit" class="{{ $btn }}">Crear</button>
                </form>
            </details>
        </div>

        @if ($aviso)
            <div style="padding:.5rem .75rem; background:#eef2ff; border:1px solid #a5b4fc; border-radius:.4rem; color:#312e81; font-size:.85rem">{{ $aviso }}</div>
        @endif

        @if ($actual)
            @php $carpetaWin = 'OneDrive\\_ClaudeDesarrollo\\WhatsApp\\'.$actual->carpeta; @endphp
            <div class="text-xs text-gray-600" style="display:flex; flex-wrap:wrap; gap:.3rem 1.2rem; align-items:center">
                <span>📂 Exporta el chat desde el móvil (Exportar chat → Incluir archivos) y deja el .zip en <code>{{ $carpetaWin }}</code></span>
                <span>{{ number_format($total, 0, ',', '.') }} mensajes{{ trim($buscar) !== '' ? ' con «'.$buscar.'»' : '' }}</span>
                @if ($actual->importado_at)<span>última importación: {{ \Illuminate\Support\Carbon::parse($actual->importado_at)->format('d/m/Y H:i') }}</span>@endif
                @if ($tarea && in_array($tarea->estado, ['pendiente', 'en_curso']))
                    <span wire:poll.4s style="color:#4338ca">⏳ importando…</span>
                @elseif ($tarea)
                    <span wire:poll.30s style="color:{{ $tarea->estado === 'ok' ? '#047857' : '#b91c1c' }}">
                        {{ $tarea->estado === 'ok' ? '✓' : '⚠️' }} {{ $resultado['resumen'] ?? ($tarea->estado === 'ok' ? 'importado' : 'la última importación falló (mira el aviso de tareas)') }}
                    </span>
                @endif
            </div>

            <div x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                 style="max-height:72vh; overflow-y:auto; padding:.8rem; background:#efeae2; border:1px solid #d6d3d1; border-radius:.5rem">
                @if ($hay)
                    <div style="text-align:center; margin-bottom:.6rem">
                        <button type="button" wire:click="masAntiguos" class="{{ $btn }}">↑ Cargar mensajes más antiguos ({{ number_format($total - $mensajes->count(), 0, ',', '.') }} más)</button>
                    </div>
                @endif
                @php $diaAnterior = null; @endphp
                @forelse ($mensajes as $m)
                    @php
                        $f = \Illuminate\Support\Carbon::parse($m->fecha);
                        $dia = $f->format('Y-m-d');
                        $mio = $m->autor !== null && $actual->yo && $m->autor === $actual->yo;
                        $ext = $m->adjunto ? strtolower(pathinfo($m->adjunto, PATHINFO_EXTENSION)) : '';
                        $url = $m->adjunto ? route('contabilidad.whatsapp.adjunto', [$actual->clave, $m->adjunto]) : null;
                        $tengo = $m->adjunto && isset($adjuntos[$m->adjunto]);
                        $omitido = trim($m->texto) === '<Multimedia omitido>';
                    @endphp
                    @if ($dia !== $diaAnterior)
                        <div style="text-align:center; margin:.8rem 0 .4rem"><span style="display:inline-block; padding:.1rem .7rem; font-size:.72rem; color:#44403c; background:#e7e5e4; border-radius:.6rem">{{ $dias[$f->dayOfWeek] }} {{ $f->format('d/m/Y') }}</span></div>
                        @php $diaAnterior = $dia; @endphp
                    @endif
                    @if ($m->autor === null)
                        <div style="text-align:center; margin:.3rem 0; font-size:.72rem; color:#78716c">{{ $m->texto }}</div>
                    @else
                        <div wire:key="wa-{{ $m->id }}" style="display:flex; justify-content:{{ $mio ? 'flex-end' : 'flex-start' }}; margin:.2rem 0">
                            <div style="max-width:min(34rem, 85%); padding:.35rem .6rem; border-radius:.5rem; background:{{ $mio ? '#d9fdd3' : '#fff' }}; box-shadow:0 1px 1px rgba(0,0,0,.12)">
                                @if (! $mio)<div style="font-size:.7rem; font-weight:600; color:#0f766e">{{ \Illuminate\Support\Str::before($m->autor, ' - ') }}</div>@endif
                                @if ($m->adjunto)
                                    @if (! $tengo)
                                        <div style="font-size:.8rem; color:#78716c">📎 {{ $m->adjunto }} <em>(adjunto no disponible)</em></div>
                                    @elseif (in_array($ext, $imagenes, true))
                                        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="{{ $m->adjunto }}" loading="lazy" style="max-height:{{ str_starts_with($m->adjunto, 'STK-') ? '90' : '220' }}px; max-width:100%; border-radius:.3rem"></a>
                                    @elseif (in_array($ext, $audios, true))
                                        <audio controls preload="none" src="{{ $url }}" style="max-width:100%; height:2.2rem"></audio>
                                    @else
                                        <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:.85rem; color:#1d4ed8; text-decoration:underline">{{ $ext === 'pdf' ? '📄' : '📎' }} {{ $m->adjunto }}</a>
                                        <span style="font-size:.7rem; color:#78716c">{{ $kb($adjuntos[$m->adjunto]) }}</span>
                                    @endif
                                @endif
                                @if (trim($m->texto) !== '' && ! ($omitido && $m->adjunto))
                                    <div style="font-size:.875rem; color:{{ $omitido ? '#78716c' : '#1c1917' }}; white-space:pre-wrap; word-break:break-word">{{ $omitido ? '📎 adjunto (no incluido en el export)' : $m->texto }}</div>
                                @endif
                                <div style="text-align:right; font-size:.65rem; color:#78716c">{{ $f->format('H:i') }}</div>
                            </div>
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-gray-500" style="text-align:center">
                        {{ trim($buscar) !== '' ? 'Nada coincide con la búsqueda.' : 'Todavía no hay mensajes de este cliente: deja el chat exportado en la carpeta de OneDrive y pulsa «Importar ahora».' }}
                    </p>
                @endforelse
            </div>
        @else
            <p class="text-sm text-gray-500">No hay ningún chat. Crea uno con «＋ Nuevo cliente».</p>
        @endif
    </div>
</div>
