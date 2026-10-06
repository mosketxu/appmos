{{-- Ficheros base: listado de proveedores, mayor y plan --}}
            <div wire:loading.flex wire:target="subidaProv,subidaMayor,subidaPlan,quitarBase" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(255,255,255,.8);align-items:center;justify-content:center;flex-direction:column;gap:1rem">
                <div style="width:80px;height:80px;border:9px solid #c7d2fe;border-top-color:#4f46e5;border-radius:50%;animation:focr-giro 1s linear infinite"></div>
                <div style="font-weight:600;color:#3730a3;font-size:1.25rem">Procesando el fichero y rehaciendo los proveedores…</div>
                <div style="color:#4b5563">Puede tardar un par de minutos. No cierres la página.</div>
                <style>@keyframes focr-giro{to{transform:rotate(360deg)}}</style>
            </div>
            <details class="p-3 focr-card">
                <summary class="text-sm font-semibold text-gray-700 cursor-pointer">
                    Ficheros base: listado de proveedores, mayor y plan de cuentas de SAGE
                    @if ($base)
                        <span class="font-normal text-gray-500">— {{ $base['n'] }} proveedores, actualizado {{ $base['generado'] }}</span>
                    @endif
                </summary>
                <div class="mt-2 text-xs text-gray-600">
                    <p>El <b>listado de proveedores</b> (…lisProveedores….xlsx) da cuenta, CIF, contrapartida, código de IVA (910/921 CEE, 810/821 extranjero:
                        inversión del sujeto pasivo), transacción, retención, canal y nación. El <b>mayor</b> (Mayor….xlsx) sirve para comprobar las
                        contrapartidas (manda la más usada el último año; distinta del listado en {{ $base['difieren'] ?? 0 }} proveedores),
                        reconocer el formato del nº de factura y avisar de facturas ya contabilizadas. El <b>plan de cuentas</b>
                        (…Plan….xlsx, exportado de SAGE con "Código cuenta" y "Descripción") llena el combo de contrapartida con todas las cuentas.</p>
                    <table class="mt-2 focr-tabla" style="font-size:.78rem">
                        @foreach ([
                            'prov' => ['Listado de proveedores', 'subidaProv', 'Vale el último que subes; el anterior pasa a Base/OLD. Lo puesto a mano aquí (●, proveedores nuevos) no se toca.'],
                            'mayor' => ['Mayor', 'subidaMayor', 'El anterior pasa a Base/OLD pero se sigue sumando: uno de los últimos meses completa al de años anteriores (un asiento que esté en los dos se toma del más reciente).'],
                            'plan' => ['Plan de cuentas', 'subidaPlan', 'Vale el último que subes; el anterior pasa a Base/OLD.'],
                        ] as $tipo => [$titulo, $prop, $ayuda])
                            <tr>
                                <td style="width:11rem; vertical-align:top"><b>{{ $titulo }}</b></td>
                                <td style="vertical-align:top">
                                    @forelse ($ficherosBase[$tipo] ?? [] as $k => $fb)
                                        <div class="{{ $k && $tipo === 'plan' ? 'text-gray-400' : '' }}">
                                            <a href="#" wire:click.prevent="descargar(@js('Base/'.$fb['nombre']))" class="text-indigo-600 underline" title="Abrir (se descarga una copia)">📄 {{ $fb['nombre'] }}</a> <span class="text-gray-500">· {{ $fb['fecha'] }} · {{ $fb['mb'] }} MB</span>
                                            <button type="button" wire:click="quitarBase(@js($fb['nombre']))" wire:confirm="¿Quitar {{ $fb['nombre'] }}? Se borra y vuelve el anterior de Base/OLD (si hay)."
                                                    class="text-gray-400 hover:text-red-600" title="Quitar este fichero">✕</button>
                                        </div>
                                    @empty
                                        <span class="text-gray-400">(ninguno)</span>
                                    @endforelse
                                    <div class="text-gray-500" style="font-size:.7rem">{{ $ayuda }}
                                        @if ($ficherosBase['old_'.$tipo] ?? 0) ({{ $ficherosBase['old_'.$tipo] }} en OLD{{ $tipo === 'mayor' ? ', sumándose' : '' }}.) @endif
                                        ✕ = quitar el de arriba (vuelve el anterior de OLD).</div>
                                </td>
                                <td style="width:15rem; vertical-align:top">
                                    <label class="focr-btn b-gris" style="padding:.2rem .6rem; font-size:.75rem; cursor:pointer">
                                        <span wire:loading.remove wire:target="{{ $prop }}">📂 Elegir uno nuevo…</span>
                                        <span wire:loading wire:target="{{ $prop }}">Subiendo y rehaciendo…</span>
                                        <input type="file" wire:model="{{ $prop }}" accept=".xlsx" style="display:none">
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </details>