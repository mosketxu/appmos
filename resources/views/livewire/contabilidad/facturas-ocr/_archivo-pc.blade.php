{{-- Web: archivo de lo contabilizado en el OneDrive de un PC --}}
                        {{-- Archivo: lo contabilizado se lleva al OneDrive de un PC, comprobando huellas --}}
                        <div class="mt-2 text-xs text-gray-600" style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap">
                            <span>📦 Archivo en OneDrive del PC:
                                @if ($sync)
                                    último envío {{ $sync['fecha'] }} ({{ $sync['pc'] }}) · {{ $sync['total'] }} ficheros comprobados, {{ $sync['nuevos'] }} nuevos @if (! empty($sync['originales_quitados'])), {{ $sync['originales_quitados'] }} originales movidos (quitados de la raíz de _Facturas)@endif
                                    @if (! empty($sync['conflictos'])) · <b style="color:#b45309">⚠ {{ count($sync['conflictos']) }} cambiados en el PC (el del servidor queda como «.vps»)</b>@endif
                                    @if (! empty($sync['fallidos'])) · <b style="color:#b91c1c">❌ {{ count($sync['fallidos']) }} sin llegar bien: {{ implode(', ', array_slice($sync['fallidos'], 0, 3)) }}</b>@endif
                                @else
                                    todavía no se ha enviado
                                @endif
                            </span>
                            <button type="button" wire:click="enviarAlPc" wire:loading.attr="disabled" class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.75rem" @disabled($sincronizando)>
                                {{ $sincronizando ? '⏳ enviando al PC…' : '↻ Enviar ahora al PC' }}
                            </button>
                            @if ($sincronizando)
                                <span wire:poll.3s="revisarSync"></span>
                            @endif
                        </div>
