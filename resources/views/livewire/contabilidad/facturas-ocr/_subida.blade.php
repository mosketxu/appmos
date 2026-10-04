{{-- Web: zona de arrastrar + facturas subidas con su estado y scroll (parte derecha de la tarjeta de arriba). Las facturas se suben SIEMPRE aquí
     (no dependen de OneDrive): el navegador calcula la huella SHA-1 de cada PDF y solo sube las que el servidor no conoce; al llegar se leen solas. --}}
<div class="flex items-center justify-between mb-1">
    <span class="text-sm font-semibold text-gray-700">Facturas subidas</span>
    @php $nLeidas = collect($entrada)->filter(fn ($e) => ! in_array($e[1], ['en el servidor', 'leyendo'], true))->count(); @endphp
    <span class="text-xs text-gray-500">{{ count($entrada) }} en la carpeta · {{ $nLeidas }} leídas</span>
</div>
                        <div x-data="focrSubida()" wire:key="subida">
                            <input type="file" multiple accept="application/pdf,.pdf" x-ref="f" style="display:none" x-on:change="elegir($event.target.files); $event.target.value = ''">
                            <div x-on:click="$refs.f.click()" x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false" x-on:drop.prevent="encima = false; elegir($event.dataTransfer.files)"
                                 :style="encima ? 'background:#eef2ff;border-color:#6366f1' : ''"
                                 style="border:2px dashed #cbd5e1; border-radius:.5rem; padding:.55rem; text-align:center; cursor:pointer; color:#475569">
                                📥 Arrastra aquí los PDF (o clic para elegirlos)
                            </div>
                            <template x-if="archivos.length">
                                <div class="mt-2 text-xs" style="max-height:6.5rem; overflow:auto">
                                    <template x-for="a in archivos" :key="a.clave">
                                        <div style="display:flex; gap:.5rem; align-items:center; padding:.1rem 0">
                                            <span style="min-width:7.5rem" x-text="a.estado === 'huella' ? '🔎 comprobando…' : a.estado === 'ya' ? '✔ ya la tengo' : a.estado === 'subiendo' ? '⏫ subiendo ' + a.pct + ' %' : a.estado === 'subida' ? '✅ en el servidor' : '⚠️ error'"></span>
                                            <span x-text="a.nombre" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"></span>
                                            <span style="color:#64748b" x-text="a.texto || ''"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

<div class="mt-1 text-xs text-gray-600" x-data="{ t0: {{ (int) $lecturaDesde }}, ahora: Math.floor(Date.now() / 1000) }" x-init="setInterval(() => ahora = Math.floor(Date.now() / 1000), 1000)">
    @if ($lecturaDesde)
        <b style="color:#b45309">⏳ {{ $esperandoOcr ? 'esperando el OCR de Windows de un PC (si tarda más de 3 min se lee con el servidor)' : 'leyendo en el servidor' }}… <span x-text="Math.max(0, ahora - t0) + ' s'"></span></b>
        (puedes seguir con otras)
    @elseif ($sinLeer > 0)
        <span style="color:#b45309">{{ $sinLeer }} sin leer{{ $ciclo === '' ? ': elige el IVA del cliente y se leen solas' : '' }}</span>
    @elseif (count($entrada))
        <span style="color:#15803d">✔ Todas leídas</span>
    @else
        <span class="text-gray-400">Aquí saldrán las facturas que subas, con lo que se está haciendo con cada una.</span>
    @endif
</div>
@if ($leyendo)
    <div wire:poll.3s="revisarLectura"></div>
@endif
@if ($esperandoOcr && ! $leyendo)
    <div wire:poll.3s="revisarTareas"></div>
    <div class="mt-1 text-xs" style="color:#b45309">⏳ Escaneo de calidad en un PC… (la factura se volverá a proponer sola al terminar)</div>
@endif

{{-- Lista con scroll: ocupa el alto que deje la columna de la izquierda --}}
<div style="position:relative; flex:1 1 auto; min-height:8rem; margin-top:.5rem">
    <div style="position:absolute; inset:0; overflow-y:auto; border:1px solid #e5e7eb; border-radius:.375rem">
        <table class="w-full text-xs">
            <tbody>
                @php
                    $chip = ['en el servidor' => ['🕓 sin leer', '#6b7280'], 'leyendo' => ['⏳ leyendo', '#b45309'], 'pendiente' => ['📝 por revisar', '#1d4ed8'],
                        'validando' => ['⏳ validando', '#b45309'], 'validada' => ['✅ validada', '#15803d'], 'rechazada' => ['🚫 rechazada', '#b91c1c'],
                        'duplicada' => ['⧉ duplicada', '#b45309'], 'ilegible' => ['⚠ ilegible', '#b91c1c']];
                @endphp
                @forelse ($entrada as [$nombre, $est])
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td class="px-2 py-1 whitespace-nowrap" style="width:1%; color:{{ $chip[$est][1] ?? '#6b7280' }}">{{ $chip[$est][0] ?? $est }}</td>
                        <td class="px-2 py-1" style="overflow:hidden; text-overflow:ellipsis; max-width:0; white-space:nowrap" title="{{ $nombre }}">{{ $nombre }}</td>
                    </tr>
                @empty
                    <tr><td class="px-2 py-3 text-center text-gray-400">Sin facturas en la carpeta de entrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="pt-2 mt-2 border-t border-gray-100" style="font-size:.7rem">
    @include('livewire.contabilidad.facturas-ocr._archivo-pc')
</div>
