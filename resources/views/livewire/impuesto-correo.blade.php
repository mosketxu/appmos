{{-- Ventana «Correo al cliente» de Impuestos → Seguimiento (componente ImpuestoCorreo, 15-oct-2026). Se abre desde el sobre ✉. --}}
@php $ciOps = $this->ciOps; @endphp
<div>
            <div class="imp-noprint" style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" wire:click.self="$dispatch('ci-cerrar')" data-pensando-discreto>
                <div style="background:#fff; border-radius:8px; padding:14px; width:min(1300px, 96vw); max-height:92vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.3)">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <div class="font-semibold text-gray-900">✉ Correo al cliente · {{ $nombreEnt }}</div>
                            <div class="text-xs text-gray-500">Déjalo como <b>borrador en tu Outlook</b> o envíalo desde aquí (dos pasos). Sale desde tu buzón.</div>
                        </div>
                        <button type="button" wire:click="$dispatch('ci-cerrar')" class="text-gray-400 hover:text-gray-700" title="Cerrar">✕</button>
                    </div>
                    @if ($ciOps)
                    <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start">
                        <div style="flex:1 1 520px; min-width:320px" class="text-sm">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-gray-600">Periodo</span>
                                @foreach (array_keys($ciOps) as $k)
                                    <button type="button" wire:click="$set('ciClave', '{{ $k }}')" class="imp-btn {{ $ciClave === (string) $k ? 'on' : '' }}" style="border-radius:6px">
                                        {{ $k === 'A' ? 'Anual' : \App\Support\Impuestos::MESES[(int) $k - 1] }}@php $otros = collect($ciOps[$k])->reject(fn ($p) => ctype_digit($p))->unique(); @endphp @if ($k !== 'A' && $otros->count()) <span style="opacity:.7">+{{ $otros->implode('/') }}</span>@endif</button>
                                @endforeach
                                <span class="ml-3 text-gray-600">Idioma</span>
                                <select wire:model.live="ciIdioma" class="py-0.5 text-sm border-gray-300 rounded-md"><option value="ES">Español</option><option value="EN">Inglés</option></select>
                            </div>
                            <table class="w-full text-sm" style="border-collapse:collapse">
                                <thead><tr class="text-xs text-gray-500"><th class="text-left">Modelo</th><th class="text-left">Texto en el correo</th><th class="text-right">Importe €</th><th class="text-left">Resultado</th><th class="text-left">Día de cargo</th></tr></thead>
                                <tbody>
                                @foreach ($ciFilas as $i => $f)
                                    <tr wire:key="ci-{{ $ciClave }}-{{ $i }}">
                                        <td class="pr-2 font-semibold whitespace-nowrap">M{{ $f['cod'] }} <span class="text-xs font-normal text-gray-500">{{ $f['per'] }}</span></td>
                                        <td class="pr-2"><input type="text" wire:model.live.debounce.400ms="ciFilas.{{ $i }}.texto" class="w-full py-0.5 text-sm border-gray-300 rounded"></td>
                                        <td class="pr-2 whitespace-nowrap"><input type="text" wire:model.live.debounce.400ms="ciFilas.{{ $i }}.imp" class="py-0.5 text-sm text-right border-gray-300 rounded" style="width:120px" placeholder="vacío = no sale">
                                            @if ($f['cod'] === '303') <button type="button" wire:click="ciBuscar303({{ $i }})" class="text-xs text-indigo-700 hover:underline" title="Del libro de IVA (Excel de la casilla) o del PDF presentado">🔎</button> @endif</td>
                                        <td class="pr-2"><select wire:model.live="ciFilas.{{ $i }}.tipo" class="py-0.5 text-sm border-gray-300 rounded">
                                            @foreach (\App\Support\ComunicarImpuestos::TIPOS as $tk => $tn) <option value="{{ $tk }}">{{ $tn }}</option> @endforeach</select></td>
                                        <td>@if (($f['tipo'] ?? 'pagar') === 'pagar')<input type="text" wire:model.live.debounce.400ms="ciFilas.{{ $i }}.fecha" class="py-0.5 text-sm border-gray-300 rounded" style="width:105px" title="Fin de plazo de la AEAT (= cargo si está domiciliado); sábado, domingo o festivo nacional → siguiente hábil">@else<span class="text-xs text-gray-400">sin cargo</span>@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="mt-1 text-xs text-gray-500">Día de cargo: fin de plazo según el calendario de la AEAT (domiciliado). Si cae en festivo autonómico o local, cámbialo a mano.</div>
                            <div class="grid gap-1 mt-3" style="grid-template-columns:70px 1fr">
                                <span class="text-gray-600">Para</span><input type="text" wire:model.live.debounce.500ms="ciPara" class="py-0.5 text-sm border-gray-300 rounded">
                                <span class="text-gray-600">CC</span><input type="text" wire:model.live.debounce.500ms="ciCc" class="py-0.5 text-sm border-gray-300 rounded">
                                <span class="text-gray-600">Asunto</span><input type="text" wire:model.live.debounce.500ms="ciAsunto" class="py-0.5 text-sm border-gray-300 rounded">
                                <span class="text-gray-600" title="Encabezado sobre la columna del día, p. ej. «BBVA DD» (vacío = no sale)">Banco</span><input type="text" wire:model.live.debounce.500ms="ciBanco" class="py-0.5 text-sm border-gray-300 rounded" placeholder="opcional, p. ej. BBVA DD">
                            </div>
                            <div class="p-2 mt-3 border border-gray-200 rounded" style="background:#fafafa">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                    <span class="font-semibold">📎 Adjuntos</span>
                                    <span class="text-gray-400">Excel de las casillas del correo (303, retenciones…) y lo que subas; desmarca lo que no quieras.</span>
                                    <label class="ml-auto imp-btn" style="border-radius:6px; cursor:pointer">⬆ Subir ficheros…
                                        <input type="file" wire:model="ciSubidas" multiple class="hidden"></label>
                                    <span wire:loading wire:target="ciSubidas" class="text-indigo-700">Subiendo…</span>
                                </div>
                                @error('ciSubidas.*') <div class="text-xs text-red-700">{{ $message }}</div> @enderror
                                @forelse ($ciAdjuntos as $j => $a)
                                    <div class="flex items-center gap-2 mt-1 text-sm" wire:key="ciadj-{{ md5($a['almacen']) }}">
                                        <input type="checkbox" wire:model.live="ciAdjuntos.{{ $j }}.on" class="rounded">
                                        <span class="{{ empty($a['on']) ? 'text-gray-400 line-through' : '' }}">{{ $a['nombre'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $a['de'] }} · {{ number_format($a['tam'] / 1024, 0, ',', '.') }} KB</span>
                                        @if ($a['de'] === 'subido') <button type="button" wire:click="ciQuitarAdjunto({{ $j }})" class="text-xs text-red-600 hover:underline" title="Quitar">✕</button> @endif
                                    </div>
                                @empty
                                    <div class="mt-1 text-xs text-gray-400">Sin adjuntos: no hay Excel en las casillas de este periodo. Puedes subir los que quieras.</div>
                                @endforelse
                            </div>
                            <div class="mt-2 text-xs text-gray-500">Texto · <code>{tabla}</code> = la tabla, <code>{periodo}</code>, <code>{mes}</code>, <code>{nombre}</code> = tu nombre, <code>{logo}</code> = logo de Suma. Lo que uses con este cliente queda para la próxima vez.</div>
                            <textarea wire:model.live.debounce.600ms="ciTexto" rows="12" class="w-full text-sm border-gray-300 rounded"></textarea>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <button type="button" wire:click="ciBorrador" wire:loading.attr="disabled" wire:target="ciBorrador,ciPrueba,ciArmar,ciEnviar" class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">✉ Crear borrador en Outlook</button>
                                <button type="button" wire:click="ciPrueba" wire:loading.attr="disabled" wire:target="ciBorrador,ciPrueba,ciArmar,ciEnviar" class="imp-btn" style="border-radius:6px" title="El mismo correo solo a ti, con [PRUEBA] en el asunto">🧪 Enviar prueba a mí</button>
                                <button type="button" wire:click="ciGuardarSinBorrador" class="imp-btn" style="border-radius:6px">Guardar sin borrador</button>
                                <span class="mx-1 text-gray-300">|</span>
                                @if ($ciArmado === '')
                                    <button type="button" wire:click="ciArmar" class="px-3 py-1.5 text-sm font-semibold text-red-700 bg-white border border-red-400 rounded-md hover:bg-red-50">Enviar al cliente…</button>
                                @else
                                    <button type="button" wire:click="ciEnviar" wire:loading.attr="disabled" wire:target="ciEnviar" class="px-3 py-1.5 text-sm font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">⚠️ Confirmar envío REAL a {{ \Illuminate\Support\Str::limit($ciPara, 60) }}</button>
                                    <button type="button" wire:click="$set('ciArmado', '')" class="text-xs text-gray-500 hover:underline">cancelar</button>
                                @endif
                                <span wire:loading wire:target="ciBorrador,ciPrueba,ciEnviar" class="text-xs text-indigo-700">Un momento…</span>
                            </div>
                        </div>
                        <div style="flex:1 1 460px; min-width:300px; border:1px solid #e5e7eb; border-radius:6px; padding:10px; background:#fafafa">
                            <div class="mb-1 text-xs font-semibold text-gray-500">Así queda el correo</div>
                            {!! $this->ciVista !!}
                        </div>
                    </div>
                    @endif
                    @if ($ciMsg)
                        <div class="mt-3 p-2 text-sm rounded {{ $ciOk ? 'bg-green-50 text-green-800 border border-green-300 font-semibold' : 'bg-indigo-50 text-indigo-800' }}">{{ $ciOk ? '✅ ' : '' }}{{ $ciMsg }}</div>
                    @endif
                </div>
            </div>
</div>
