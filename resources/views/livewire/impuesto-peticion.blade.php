{{-- Ventana «Petición de documentación» de Impuestos → Seguimiento (componente ImpuestoPeticion, 15-oct-2026). Se abre desde el icono de documento. --}}
<div>
    <div class="imp-noprint" style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" wire:click.self="$dispatch('pd-cerrar')" data-pensando-discreto>
        <div style="background:#fff; border-radius:8px; padding:14px; width:min(1300px, 96vw); max-height:92vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.3)">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <div class="font-semibold text-gray-900">📄 Petición de documentación · {{ $nombreEnt }} · {{ $textoPeriodo }}</div>
                    <div class="text-xs text-gray-500">Déjala como <b>borrador en tu Outlook</b> o envíala desde aquí (dos pasos). Sale desde {{ $remitente }}.</div>
                </div>
                <button type="button" wire:click="$dispatch('pd-cerrar')" class="text-gray-400 hover:text-gray-700" title="Cerrar">✕</button>
            </div>
            <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start">
                <div style="flex:1 1 520px; min-width:320px" class="text-sm">
                    <div class="grid gap-1" style="grid-template-columns:70px 1fr">
                        <span class="text-gray-600">Para</span><input type="text" wire:model.live.debounce.500ms="pdPara" class="py-0.5 text-sm border-gray-300 rounded" placeholder="Email Adm del cliente">
                        <span class="text-gray-600">CC</span><input type="text" wire:model.live.debounce.500ms="pdCc" class="py-0.5 text-sm border-gray-300 rounded">
                        <span class="text-gray-600">Idioma</span>
                        <div><select wire:model.live="pdIdioma" class="py-0.5 text-sm border-gray-300 rounded-md"><option value="ES">Español</option><option value="EN">Inglés</option></select>
                            <button type="button" wire:click="pdPlantilla" class="ml-2 text-xs text-indigo-700 hover:underline" title="Sustituye asunto y texto por la plantilla del idioma">↺ Poner la plantilla</button></div>
                        <span class="text-gray-600">Asunto</span><input type="text" wire:model.live.debounce.500ms="pdAsunto" class="py-0.5 text-sm border-gray-300 rounded">
                    </div>
                    <div class="p-2 mt-3 border border-gray-200 rounded" style="background:#fafafa">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                            <span class="font-semibold">📎 Adjuntos</span>
                            <x-boton-subir como="label" target="pdSubidas" class="ml-auto">Subir ficheros…
                                <input type="file" wire:model="pdSubidas" multiple class="hidden"></x-boton-subir>
                        </div>
                        @error('pdSubidas.*') <div class="text-xs text-red-700">{{ $message }}</div> @enderror
                        @foreach ($pdAdjuntos as $j => $a)
                            <div class="flex items-center gap-2 mt-1 text-sm" wire:key="pdadj-{{ md5($a['almacen']) }}">
                                <span>{{ $a['nombre'] }}</span><span class="text-xs text-gray-400">{{ number_format($a['tam'] / 1024, 0, ',', '.') }} KB</span>
                                <button type="button" wire:click="pdQuitarAdjunto({{ $j }})" class="text-xs text-red-600 hover:underline" title="Quitar">✕</button>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 text-xs text-gray-500">Texto · <code>{periodo}</code> = el mes o el trimestre según el ciclo del cliente, <code>{empresa}</code>, <code>{logo}</code> = logo de Suma.</div>
                    <textarea wire:model.live.debounce.600ms="pdTexto" rows="12" class="w-full text-sm border-gray-300 rounded"></textarea>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <button type="button" wire:click="pdBorrador" wire:loading.attr="disabled" wire:target="pdBorrador,pdPrueba,pdArmar,pdEnviar" class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">✉ Crear borrador en Outlook</button>
                        <button type="button" wire:click="pdPrueba" wire:loading.attr="disabled" wire:target="pdBorrador,pdPrueba,pdArmar,pdEnviar" class="imp-btn" style="border-radius:6px" title="El mismo correo solo a ti, con [PRUEBA] en el asunto">🧪 Enviar prueba a mí</button>
                        <button type="button" wire:click="pdGuardar" class="imp-btn" style="border-radius:6px" title="Para, CC, idioma, asunto y texto quedan en la ficha del cliente">Guardar para este cliente</button>
                        <button type="button" wire:click="pdGuardarPlantilla" wire:confirm="¿Guardar este asunto y texto como plantilla general en {{ $pdIdioma === 'EN' ? 'inglés' : 'español' }}? La usarán los clientes que no tienen texto propio." class="imp-btn" style="border-radius:6px">Guardar como plantilla</button>
                        <span class="mx-1 text-gray-300">|</span>
                        @if ($pdArmado === '')
                            <button type="button" wire:click="pdArmar" class="px-3 py-1.5 text-sm font-semibold text-red-700 bg-white border border-red-400 rounded-md hover:bg-red-50">Enviar al cliente…</button>
                        @else
                            <button type="button" wire:click="pdEnviar" wire:loading.attr="disabled" wire:target="pdEnviar" class="px-3 py-1.5 text-sm font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">⚠️ Confirmar envío REAL a {{ \Illuminate\Support\Str::limit($pdPara, 60) }}</button>
                            <button type="button" wire:click="$set('pdArmado', '')" class="text-xs text-gray-500 hover:underline">cancelar</button>
                        @endif
                        <span wire:loading wire:target="pdBorrador,pdPrueba,pdEnviar" class="text-xs text-indigo-700">Un momento…</span>
                    </div>
                    @if ($enviados->count())
                        <div class="mt-3 text-xs text-gray-500">
                            <div class="font-semibold">Últimas peticiones enviadas</div>
                            @foreach ($enviados as $m)
                                <div>{{ $m->periodo }} · {{ $m->enviado_at->format('d/m/Y H:i') }} · {{ $m->destinatarios }} · {{ $m->asunto }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div style="flex:1 1 460px; min-width:300px; border:1px solid #e5e7eb; border-radius:6px; padding:10px; background:#fafafa">
                    <div class="mb-1 text-xs font-semibold text-gray-500">Así queda el correo</div>
                    {!! $this->pdVista !!}
                </div>
            </div>
            @if ($pdMsg)
                <div class="mt-3 p-2 text-sm rounded {{ $pdOk ? 'bg-green-50 text-green-800 border border-green-300 font-semibold' : 'bg-indigo-50 text-indigo-800' }}">{{ $pdOk ? '✅ ' : '' }}{{ $pdMsg }}</div>
            @endif
        </div>
    </div>
</div>
