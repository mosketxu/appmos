            {{-- Revisión / histórico --}}
            <div>
                <div class="focr-tabs">
                    <button type="button" wire:click="$set('vista','revisar')" class="{{ $vista === 'revisar' ? 'on' : '' }}">
                        Por revisar ({{ $cuenta['pendiente'] ?? 0 }} pendientes{{ ($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0) ? ', '.(($cuenta['rechazada'] ?? 0) + ($cuenta['ilegible'] ?? 0)).' al final' : '' }})
                    </button>
                    <button type="button" wire:click="$set('vista','historico')" class="{{ $vista === 'historico' ? 'on' : '' }}">
                        Validadas ({{ $enExcel }} sin guardar{{ array_sum($procesos) ? ' · '.array_sum($procesos).' ya guardadas' : '' }})
                        @if ($guardando) <span class="focr-chip c-gris" title="Excel, mover el PDF y aprender, en segundo plano">💾 {{ $guardando }}…</span> @endif
                    </button>
                    <button type="button" wire:click="$set('vista','proveedores')" class="{{ $vista === 'proveedores' ? 'on' : '' }}">Proveedores</button>
                    <button type="button" wire:click="$set('vista','chequeo')" class="{{ $vista === 'chequeo' ? 'on' : '' }}" title="Comprueba que las facturas de las carpetas de un periodo están en el mayor, y al revés">Chequeo mayor</button>
                    <button type="button" wire:click="$set('vista','ordenar')" class="{{ $vista === 'ordenar' ? 'on' : '' }}" title="Facturas sueltas en la raíz de _Facturas: renombrarlas y moverlas a su mes según el mayor (primero simula)">Ordenar sueltas</button>
                    @if ($cuenta['duplicada'] ?? 0)
                        <button type="button" wire:click="$set('vista','duplicadas')" class="{{ $vista === 'duplicadas' ? 'on' : '' }}">
                            Duplicadas ({{ $cuenta['duplicada'] }})
                        </button>
                    @endif
                </div>

                <div class="overflow-auto focr-card" style="border-top-left-radius:0; max-height:70vh">
                    @if ($vista === 'chequeo')
                        <div class="p-3" style="display:flex; flex-direction:column; gap:.6rem">
                            <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap">
                                <b class="text-sm">Chequeo contra el mayor</b>
                                <input type="text" wire:model="chequeoPeriodo" wire:keydown.enter="chequearMayor" placeholder="2026-3T o 2026-09" class="focr-in" style="max-width:9rem">
                                <button type="button" wire:click="chequearMayor" wire:loading.attr="disabled" wire:target="chequearMayor" class="focr-btn b-verde" style="padding:.25rem .8rem">
                                    <span wire:loading.remove wire:target="chequearMayor">▶ Chequear</span><span wire:loading wire:target="chequearMayor">Comprobando…</span>
                                </button>
                                @if ($chequeo)
                                    <a wire:click.prevent="descargar('Output/Chequeo_mayor_{{ $chequeo['periodo'] }}.xlsx')" href="#" class="focr-btn b-gris" style="padding:.2rem .6rem">⬇ Excel</a>
                                    <span class="text-xs text-gray-500">hecho el {{ $chequeo['fecha'] }}</span>
                                @endif
                            </div>
                            @error('chequeo') <div class="text-xs" style="color:#b91c1c">{{ $message }}</div> @enderror
                            <p class="text-xs text-gray-500">Mira los PDF de las carpetas del periodo (las de «Registro», sin subcarpetas) y comprueba que cada una está en el mayor de SAGE por su nº de factura
                                (y que el importe coincide); y al revés, que cada factura de proveedor del mayor del periodo tiene su PDF. Solo lee: no cambia nada. Usa el último mayor que hayas subido en «Ficheros base».</p>
                            @if ($chequeo)
                                @if ($chequeo['aviso_mayor'])
                                    <div style="padding:.4rem .6rem; border:2px solid #f59e0b; background:#fffbeb; border-radius:.4rem; color:#78350f; font-size:.8rem">⚠️ {{ $chequeo['aviso_mayor'] }} Sube un mayor más reciente y vuelve a chequear.</div>
                                @endif
                                <div style="display:flex; gap:.4rem; flex-wrap:wrap; align-items:center">
                                    <span class="focr-chip c-gris">{{ $chequeo['pdf'] }} PDF</span>
                                    @foreach ($chequeo['estados'] as $est => $n)
                                        <span class="focr-chip {{ in_array($est, ['OK', 'DIVISA (cuadra)']) ? 'c-ok' : (in_array($est, ['DIVISA (probable)', 'PROBABLE']) ? 'c-revisar' : 'c-falta') }}">{{ $est }} {{ $n }}</span>
                                    @endforeach
                                    <span class="focr-chip {{ $chequeo['mayor_sin_pdf'] ? 'c-falta' : 'c-ok' }}">Mayor sin PDF {{ $chequeo['mayor_sin_pdf'] }}</span>
                                </div>
                                @if ($chequeo['problemas'])
                                    @php
                                        $filtrar = fn ($filas, $hechas) => array_values(array_filter($filas, fn ($x) => (isset($revisados[$x['clave'] ?? 'pdf|'.$x['fichero']]) ) === $hechas));
                                        $pendientesChq = $filtrar($chequeo['problemas'], false);
                                        $hechasChq = $filtrar($chequeo['problemas'], true);
                                        $nRevTotal = count($hechasChq) + ($chequeo['revisadas'] ?? 0);
                                    @endphp
                                    <div class="text-sm font-semibold" style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap">
                                        Para revisar ({{ count($pendientesChq) }})
                                        @if ($nRevTotal || $verRevisadas)
                                            <button type="button" wire:click="$toggle('verRevisadas')" class="focr-btn b-gris" style="padding:.1rem .5rem; font-size:.72rem; font-weight:400">{{ $verRevisadas ? 'Ocultar' : 'Ver' }} las ya revisadas ({{ $nRevTotal }})</button>
                                        @endif
                                    </div>
                                    <table class="focr-tabla">
                                        <thead><tr><th>Estado</th><th>Mes</th><th>Fichero</th><th>Proveedor</th><th>Nº factura</th><th style="text-align:right">Total</th><th>Detalle</th><th></th></tr></thead>
                                        <tbody>
                                            @foreach (array_merge($pendientesChq, $verRevisadas ? $hechasChq : []) as $x)
                                                @php $kx = $x['clave'] ?? 'pdf|'.$x['fichero']; $hecha = isset($revisados[$kx]); @endphp
                                                <tr wire:key="chq-{{ md5($kx) }}" @if ($hecha) style="opacity:.55" @endif>
                                                    <td><span class="focr-chip {{ in_array($x['estado'], ['DIVISA (probable)', 'PROBABLE']) ? 'c-revisar' : 'c-falta' }}" style="white-space:nowrap">{{ $x['estado'] }}</span></td>
                                                    <td>{{ $x['mes'] }}</td><td style="max-width:260px; word-break:break-all"><a href="{{ route('contabilidad.facturas-ocr.archivo', [$cliente, $x['mes'], $x['fichero']]) }}?a={{ substr($chequeo['periodo'], 0, 4) }}" target="_blank" style="color:#1d4ed8; text-decoration:underline" title="Abrir la factura">{{ $x['fichero'] }}</a></td>
                                                    <td>{{ $x['cuenta'] }} {{ $x['proveedor'] }}</td><td>{{ $x['num'] }}</td>
                                                    <td style="text-align:right">{{ $x['total'] !== null ? number_format((float) $x['total'], 2, ',', '.') : '' }}</td>
                                                    <td class="text-xs text-gray-600">{{ $x['detalle'] }}@if ($hecha) <br><i>revisada {{ $revisados[$kx]['fecha'] ?? '' }} {{ $revisados[$kx]['quien'] ?? '' }} {{ $revisados[$kx]['nota'] ?? '' }}</i>@endif</td>
                                                    <td style="white-space:nowrap">
                                                        @if ($hecha)
                                                            <button type="button" wire:click="desmarcarRevisado(@js($kx))" class="focr-btn b-gris" style="padding:.05rem .4rem; font-size:.7rem" title="Volver a proponerla">↺ Quitar «revisado»</button>
                                                        @else
                                                            <button type="button" x-on:click="const n = prompt('Nota (opcional). Aceptar = darla por revisada y no volver a proponerla:'); if (n !== null) $wire.marcarRevisado(@js($kx), n)" class="focr-btn b-verde" style="padding:.05rem .4rem; font-size:.7rem" title="Ya la he mirado: que no me la vuelva a proponer">✔ Revisado</button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div class="text-sm" style="color:#047857">✔ Todas las facturas de las carpetas están en el mayor.</div>
                                @endif
                                @if ($chequeo['sin_pdf'])
                                    <details>
                                        @php $sinPdfPend = array_values(array_filter($chequeo['sin_pdf'], fn ($x) => ! isset($revisados[$x['clave'] ?? '-']))); @endphp
                                        <summary class="text-sm font-semibold cursor-pointer">En el mayor sin PDF en las carpetas ({{ count($sinPdfPend) }})</summary>
                                        <table class="focr-tabla">
                                            <thead><tr><th>Cuenta</th><th>Proveedor</th><th>Nº factura</th><th>Fecha asiento</th><th style="text-align:right">Total</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach ($sinPdfPend as $x)
                                                    <tr wire:key="sp-{{ md5($x['clave'] ?? json_encode($x)) }}"><td>{{ $x['cuenta'] }}</td><td>{{ $x['proveedor'] }}</td><td>{{ $x['num'] }}</td><td>{{ $x['fecha'] }}</td><td style="text-align:right">{{ number_format((float) $x['total'], 2, ',', '.') }}</td>
                                                        <td>@if (isset($x['clave']))<button type="button" x-on:click="const n = prompt('Nota (opcional). Aceptar = darla por revisada y no volver a proponerla:'); if (n !== null) $wire.marcarRevisado(@js($x['clave']), n)" class="focr-btn b-verde" style="padding:.05rem .4rem; font-size:.7rem">✔ Revisado</button>@endif</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </details>
                                @endif
                            @else
                                <p class="text-sm text-gray-500">Aún no hay resultado para ese periodo: pulsa «Chequear».</p>
                            @endif

                        </div>
                    @elseif ($vista === 'ordenar')
                        <div class="p-3" style="display:flex; flex-direction:column; gap:.6rem">
                                                        <div style="border-top:1px solid #e5e7eb; padding-top:.7rem; display:flex; flex-direction:column; gap:.5rem">
                                <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap">
                                    <b class="text-sm">Facturas sueltas en la raíz de «_Facturas»</b>
                                    <button type="button" wire:click="ordenarSueltas(false)" wire:loading.attr="disabled" wire:target="ordenarSueltas" class="focr-btn b-gris" style="padding:.25rem .8rem">
                                        <span wire:loading.remove wire:target="ordenarSueltas">🔎 Simular (no toca nada)</span><span wire:loading wire:target="ordenarSueltas">Trabajando…</span>
                                    </button>
                                    @if ($ordenar && ! $ordenar['aplicado'] && (($ordenar['acciones']['MOVER'] ?? 0) + ($ordenar['acciones']['QUITAR'] ?? 0) + ($ordenar['acciones']['A PROCESAR'] ?? 0)))
                                        <button type="button" wire:click="ordenarSueltas(true)" wire:loading.attr="disabled" wire:target="ordenarSueltas"
                                                wire:confirm="¿Aplicar lo que dice la simulación? Se renombran y mueven las del mayor, se quitan las duplicadas ya ordenadas y las que faltan vuelven a Por revisar. Después se llevarán al OneDrive de tu PC."
                                                class="focr-btn b-verde" style="padding:.25rem .8rem">✅ Aplicar</button>
                                    @endif
                                    @if ($ordenar) <span class="text-xs text-gray-500">{{ $ordenar['aplicado'] ? 'aplicado' : 'simulación' }} del {{ $ordenar['fecha'] }}</span> @endif
                                </div>
                                <p class="text-xs text-gray-500">Mira los PDF sueltos: <b>MOVER</b> = está en el mayor (se renombra y va a la carpeta del mes del asiento del mayor) ·
                                    <b>QUITAR</b> = ya está ordenada en su mes (mismo contenido) · <b>A PROCESAR</b> = el mayor no la tiene (vuelve a «Por revisar» si estaba quitada de la lista) · IMAGEN = escaneada, no se identifica.
                                    Primero simula y revisa; «Aplicar» es lo único que toca ficheros.</p>
                                @if ($ordenar)
                                    <div style="display:flex; gap:.4rem; flex-wrap:wrap">
                                        @foreach ($ordenar['acciones'] as $ac => $n)
                                            <span class="focr-chip {{ $ac === 'MOVER' ? 'c-ok' : (in_array($ac, ['QUITAR']) ? 'c-gris' : 'c-revisar') }}">{{ $ac }} {{ $n }}</span>
                                        @endforeach
                                        @foreach (($ordenar['aplicadas'] ?? []) as $k => $n) <span class="focr-chip c-ok">hecho: {{ $n }} {{ $k }}</span> @endforeach
                                    </div>
                                    @if (! $ordenar['aplicado'])
                                        <p class="text-xs" style="color:#1e3a8a">✎ Puedes cambiar lo que se hará con cada factura <b>pulsando sobre su acción</b> (va rotando entre las posibles: MOVER → A PROCESAR → NO TOCAR…). Si es MOVER, elige la carpeta (mes) en la lista. «Aplicar» hace solo lo que veas aquí.</p>
                                    @endif
                                    @php $meses = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic']; @endphp
                                    <table class="focr-tabla">
                                        <thead><tr><th>Acción{{ ! $ordenar['aplicado'] ? ' (pulsa)' : '' }}</th><th>Fichero</th><th>Proveedor</th><th>Nº</th><th style="text-align:right">Total</th><th>Destino</th><th>Detalle</th></tr></thead>
                                        <tbody>
                                            @foreach ($ordenar['filas'] as $x)
                                                @php
                                                    $ef = $ordenar['aplicado'] ? $x['accion'] : ($ordenarAccion[$x['fichero']] ?? $x['accion']);
                                                    $cambiable = ! $ordenar['aplicado'] && count($x['permitidas'] ?? []) > 1;
                                                    $col = $ef === 'MOVER' ? 'c-ok' : ($ef === 'QUITAR' || $ef === 'NADA' ? 'c-gris' : 'c-revisar');
                                                    $partes = explode('/', $x['destino']);
                                                @endphp
                                                <tr wire:key="ord-{{ md5($x['fichero']) }}">
                                                    <td>
                                                        @if ($cambiable)
                                                            <button type="button" wire:click="ciclarAccion(@js($x['fichero']))" class="focr-chip {{ $col }}" style="white-space:nowrap; cursor:pointer" title="Pulsa para cambiar la acción ({{ implode(' → ', $x['permitidas']) }})">{{ $ef === 'NADA' ? 'NO TOCAR' : $ef }} ⟳</button>
                                                        @else
                                                            <span class="focr-chip {{ $col }}" style="white-space:nowrap">{{ $ef === 'NADA' ? 'NO TOCAR' : $ef }}</span>
                                                        @endif
                                                    </td>
                                                    <td style="max-width:240px; word-break:break-all"><a href="{{ route('contabilidad.facturas-ocr.archivo', [$cliente, 'raiz', $x['fichero']]) }}?a={{ substr($chequeoPeriodo, 0, 4) }}" target="_blank" style="color:#1d4ed8; text-decoration:underline" title="Abrir la factura">{{ $x['fichero'] }}</a></td>
                                                    <td>{{ $x['cuenta'] }} {{ $x['proveedor'] }}</td><td>{{ $x['num'] }}</td>
                                                    <td style="text-align:right">{{ $x['total'] !== null ? number_format((float) $x['total'], 2, ',', '.') : '' }}</td>
                                                    <td class="text-xs" style="word-break:break-all">
                                                        @if ($ef === 'MOVER' && ! $ordenar['aplicado'])
                                                            @php $mesSel = $ordenarMes[$x['fichero']] ?? ''; $mesProp = $x['accion'] === 'MOVER' ? ($partes[0] ?? '') : ($x['mes_def'] ?? ''); @endphp
                                                            <select x-on:change="$wire.set('ordenarMes.' + @js($x['fichero']), $event.target.value)" class="py-0 text-xs border-gray-300 rounded" style="height:1.5rem" title="Carpeta de destino">
                                                                <option value="">{{ $mesProp }} · {{ $meses[$mesProp] ?? '' }} (propuesta)</option>
                                                                @foreach ($meses as $mm => $nm) <option value="{{ $mm }}" @selected($mesSel === $mm)>{{ $mm }} · {{ $nm }}</option> @endforeach
                                                            </select>
                                                            <span class="text-gray-500">/ {{ $x['accion'] === 'MOVER' ? ($partes[1] ?? '') : $x['fichero'] }}</span>
                                                        @elseif ($ef === 'NADA') <span class="text-gray-400">se queda donde está</span>
                                                        @elseif ($ef === 'A PROCESAR') <span class="text-gray-500">vuelve a «Por revisar»</span>
                                                        @else {{ $x['destino'] }} @endif
                                                    </td>
                                                    <td class="text-xs text-gray-600">{{ $x['detalle'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    @elseif ($vista === 'proveedores')
                        <div class="flex flex-wrap items-center gap-2 p-2 border-b border-gray-200">
                            <input type="search" wire:model.live.debounce.300ms="filtroProv" placeholder="Buscar cuenta, nombre, CIF o contrapartida…" class="focr-in" style="max-width:340px">
                            <span class="text-xs text-gray-500">{{ count($provs) }} proveedores · <b>●</b> = puesto aquí (manda sobre la ficha de SAGE). Clic en cualquier parte de la fila para editarlo.</span>
                            <button type="button" wire:click="descargarProveedores" class="focr-btn b-gris ml-auto" style="padding:.2rem .5rem; font-size:.75rem" title="El listado tal cual se ve (con el filtro), para abrir en Excel">💾 Listado (CSV)</button>
                        </div>
                        @if ($provSel !== '')
                            <div class="p-3 border-b border-gray-200" style="background:#eef2ff">
                                <div class="text-sm" style="margin-bottom:.4rem"><b>{{ $provSel }}</b> {{ $provForm['nombre'] ?? '' }}
                                    @if ($esNuevoProv) <span class="focr-chip c-revisar">nuevo (aún no está en SAGE)</span> @endif</div>
                                <div class="flex flex-wrap gap-2 items-end">
                                    @if ($esNuevoProv)
                                        <label class="text-xs">Nombre<br><input type="text" wire:model="provForm.nombre" class="focr-in" style="width:220px"></label>
                                        <label class="text-xs">CIF<br><input type="text" wire:model="provForm.cif" class="focr-in" style="width:130px"></label>
                                    @else
                                        <label class="text-xs">Nombre (vacío = el de SAGE)<br><input type="text" wire:model="provForm.nombre" class="focr-in" style="width:260px"></label>
                                    @endif
                                    <label class="text-xs">Contrapartida<br><input type="text" wire:model="provForm.contrapartida" list="focr-cuentas-prov" class="focr-in" style="width:110px"></label>
                                    <label class="text-xs">Cód. transacción<br><input type="text" wire:model="provForm.codigo_transaccion" class="focr-in" style="width:80px"></label>
                                    <label class="text-xs">Clave operación<br><input type="text" wire:model="provForm.clave_operacion" class="focr-in" style="width:80px"></label>
                                    <label class="text-xs">Cód. retención<br><input type="text" wire:model="provForm.codigo_retencion" class="focr-in" style="width:80px"></label>
                                    <button type="button" wire:click="guardarProveedor" wire:loading.attr="disabled" class="focr-btn b-verde">
                                        <span wire:loading.remove wire:target="guardarProveedor">💾 Guardar</span><span wire:loading wire:target="guardarProveedor">Guardando…</span>
                                    </button>
                                    <button type="button" wire:click="cerrarProveedor" class="focr-btn b-gris">Cancelar</button>
                                </div>
                                <datalist id="focr-cuentas-prov">
                                    @foreach ($nombresCuentas as $c => $n) <option value="{{ $c }}">{{ $n }}</option> @endforeach
                                </datalist>
                                <div class="text-xs text-gray-600" style="margin-top:.35rem">
                                    Se usará en las próximas facturas de este proveedor (las pendientes sin tocar se vuelven a proponer al guardar).
                                    Contrapartida vacía = la de la ficha de SAGE o la más usada en el mayor; cód. de transacción/retención igual al de la ficha = el de la ficha.
                                    En SAGE no se cambia nada.
                                </div>
                                @error('proveedor') <pre class="focr-avisos" style="white-space:pre-wrap; background:#fef2f2; border-color:#fca5a5; color:#991b1b; margin-top:.4rem">{{ $message }}</pre> @enderror
                            </div>
                        @endif
                        <table class="focr-tabla">
                            <thead><tr><th>Cuenta</th><th>Proveedor</th><th>CIF</th><th>Contrapartida</th><th>Cód. trans.</th><th>Clave op.</th><th>Cód. ret.</th><th style="text-align:right">Validadas aquí</th><th>Última</th></tr></thead>
                            <tbody>
                            @forelse (array_slice($provs, 0, 300, true) as $p)
                                <tr class="clic" wire:click="abrirProveedor('{{ $p['cuenta'] }}')" wire:key="p-{{ $p['cuenta'] }}" @if ($provSel === $p['cuenta']) style="background:#eef2ff" @endif>
                                    <td>{{ $p['cuenta'] }}</td>
                                    <td>{{ $p['nombre'] }} @if ($p['nuevo']) <span class="focr-chip c-revisar">nuevo</span> @endif</td>
                                    <td>{{ $p['cif'] }}</td>
                                    <td title="{{ $nombresCuentas[$p['contrapartida']] ?? '' }}{{ $p['contrapartida_sage'] !== '' ? ' · en SAGE: '.$p['contrapartida_sage'] : '' }}">{{ $p['contrapartida'] }} <span class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($nombresCuentas[$p['contrapartida']] ?? '', 22) }}</span>@if ($p['contrapartida_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td title="{{ $p['transaccion_sage'] !== '' ? 'En SAGE: '.$p['transaccion_sage'] : '' }}">{{ $p['codigo_transaccion'] }}@if ($p['transaccion_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td>{{ $p['clave_operacion'] }}</td>
                                    <td>{{ $p['codigo_retencion'] }}@if ($p['retencion_aqui']) <b title="Puesto aquí">●</b>@endif</td>
                                    <td style="text-align:right">{{ $p['validadas'] ?: '' }}</td>
                                    <td class="text-xs">{{ $p['ultima'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-500">Ningún proveedor{{ $filtroProv ? ' con ese filtro' : ' (falta subir el listado de proveedores en Ficheros base)' }}.</td></tr>
                            @endforelse
                            @if (count($provs) > 300)
                                <tr><td colspan="9" class="p-2 text-center text-xs text-gray-500">… y {{ count($provs) - 300 }} más: afina la búsqueda.</td></tr>
                            @endif
                            </tbody>
                        </table>
                    @elseif ($vista === 'duplicadas')
                        <table class="focr-tabla">
                            <thead><tr><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th style="text-align:right">Total</th><th>Motivo</th><th>Fichero (en Duplicadas)</th><th>Cuándo</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($duplicadas as $f)
                                @php $d = $f['datos'] ?? []; @endphp
                                <tr wire:key="d-{{ $f['id'] }}">
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td class="text-xs">{{ $f['motivo_rechazo'] ?? '' }} {{ collect($f['avisos'] ?? [])->filter(fn ($a) => str_starts_with($a, 'DUPLICADA'))->implode(' · ') }}</td>
                                    <td class="text-xs" style="max-width:340px; word-break:break-all">
                                        <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $f['id']]) }}" target="_blank" class="text-indigo-600 underline">{{ $f['ruta'] }}</a>
                                    </td>
                                    <td class="text-xs">{{ $f['duplicada_el'] ?? '' }}</td>
                                    <td><button type="button" wire:click="reabrir('{{ $f['id'] }}')" class="focr-btn b-gris" style="padding:.15rem .5rem; font-size:.72rem" title="Vuelve a la carpeta de la que se leyó y a pendientes">↺ No es duplicada</button>
                                        <button type="button" wire:click="quitarDeLista('{{ $f['id'] }}')" class="focr-btn b-gris" style="padding:.15rem .5rem; font-size:.72rem" title="Quitarla de la lista (el PDF sigue en Duplicadas)">✕ Quitar</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @elseif ($vista === 'revisar')
                        @if ($cola)
                            <div class="flex items-center gap-2 p-2 border-b border-gray-200 text-xs text-gray-500">
                                <button type="button" wire:click="marcarSeguras" class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.8rem"
                                        title="Marca las que se leyeron con todo «ok», cuadran, tienen cuenta y contrapartida y no son duplicadas">☑ Marcar las seguras</button>
                                @if ($marcadas)
                                    <button type="button" wire:click="validarMarcadas" wire:loading.attr="disabled" wire:target="validarMarcadas"
                                            wire:confirm="¿Validar las {{ count($marcadas) }} marcadas tal como están? Las que no cuadren o sean duplicadas se dejan sin validar."
                                            class="focr-btn b-verde" style="padding:.15rem .7rem; font-size:.8rem">
                                        <span wire:loading.remove wire:target="validarMarcadas">✅ Validar las {{ count($marcadas) }} marcadas</span>
                                        <span wire:loading wire:target="validarMarcadas">Validando…</span>
                                    </button>
                                    <button type="button" wire:click="$set('marcadas', [])" class="focr-btn b-gris" style="padding:.15rem .5rem; font-size:.75rem">Desmarcar</button>
                                @endif
                                <button type="button" wire:click="revisarTodas" wire:loading.attr="disabled"
                                        wire:confirm="Volver a proponer todas las facturas por revisar con los ficheros base y las reglas de ahora? En las empezadas se conserva lo que has cambiado a mano."
                                        class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.8rem" title="Volver a proponer todas las pendientes (también las empezadas: lo tocado a mano se conserva)">
                                    <span wire:loading.remove wire:target="revisarTodas">↻ Revisar todas</span>
                                    <span wire:loading wire:target="revisarTodas">↻ Revisando…</span>
                                </button>
                                <div wire:loading.flex wire:target="revisarTodas" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(255,255,255,.75);align-items:center;justify-content:center;flex-direction:column;gap:1rem">
                                    <div style="width:64px;height:64px;border:7px solid #c7d2fe;border-top-color:#4f46e5;border-radius:50%;animation:focr-giro 1s linear infinite"></div>
                                    <div style="font-weight:600;color:#3730a3;font-size:1.1rem">Revisando todas las facturas… puede tardar un minuto</div>
                                    <style>@keyframes focr-giro{to{transform:rotate(360deg)}}</style>
                                </div>
                                @if ($quitables)
                                    <span class="ml-auto">Rechazadas, no legibles y duplicadas ya vistas:</span>
                                    <button type="button" wire:click="quitarDeLista" wire:confirm="¿Quitar de la lista todas las rechazadas, no legibles y duplicadas ({{ $quitables }})? No se borra ningún PDF." class="focr-btn b-gris" style="padding:.15rem .6rem; font-size:.75rem">✕ Quitarlas de la lista ({{ $quitables }})</button>
                                @endif
                            </div>
                        @endif
                        <table class="focr-tabla">
                            <thead><tr>
                                <th></th><th>Fichero</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th>F. registro</th>
                                <th>Contrap.</th><th style="text-align:right">Base</th><th style="text-align:right">% IVA</th><th style="text-align:right">IVA</th>
                                <th style="text-align:right">Total</th><th>Lectura</th><th>Avisos</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($cola as $f)
                                @php $d = $f['datos'] ?? []; $e = $etiqEstado[$f['estado']] ?? ['', $f['estado'], 'c-gris']; @endphp
                                <tr class="clic" wire:click="abrir('{{ $f['id'] }}')" wire:key="c-{{ $f['id'] }}">
                                    <td style="white-space:nowrap"><span class="focr-chip {{ $e[2] }}">{{ $e[0] }} {{ $e[1] }}</span>
                                        @if ($f['estado'] === 'pendiente')
                                            <input type="checkbox" wire:model.live="marcadas" value="{{ $f['id'] }}" wire:click.stop title="Marcar para validar varias de una vez">
                                            <button type="button" wire:click.stop="validarFila('{{ $f['id'] }}')" wire:loading.attr="disabled" wire:target="validarFila('{{ $f['id'] }}')"
                                                    class="focr-btn b-verde" style="padding:.05rem .4rem; font-size:.75rem" title="Validarla tal como está, sin abrirla (si no cuadra o es duplicada, no lo hace y te lo dice)">✅</button>
                                        @endif
                                    </td>
                                    <td style="max-width:260px; word-break:break-all">{{ basename($f['ruta']) }} @if (! empty($f['ocr'])) <span class="focr-chip c-revisar">OCR</span> @endif
                                        @if (collect($f['avisos'] ?? [])->contains(fn ($a) => str_starts_with($a, 'DUPLICADA'))) <span class="focr-chip c-falta">DUPLICADA</span> @endif
                                        @if (! empty($f['editada'])) <span class="focr-chip c-gris" title="Tocada a mano el {{ $f['editada'] }}; se guarda sola">✎ a medias</span> @endif</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    @php $im = $importes($d); @endphp
                                    <td>{{ $d['contrapartida'] ?? '' }}</td>
                                    <td style="text-align:right">{{ $eur($im['base']) }}</td>
                                    <td style="text-align:right" title="{{ $im['detalle'] }}">{!! $im['pct'] === 'varios' ? '<span class="focr-chip c-revisar">varios</span>' : e($im['pct']) !!}</td>
                                    <td style="text-align:right">{{ $eur($im['iva']) }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td style="white-space:nowrap">
                                        @foreach (($f['confianza'] ?? []) as $k => $v)
                                            <span class="focr-chip {{ $chip($v) }}" title="{{ $k }}: {{ $v }}">{{ ['proveedor' => 'P', 'su_factura' => 'Nº', 'fecha' => 'F', 'importes' => '€'][$k] ?? $k }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-xs text-gray-600">{{ implode(' · ', $f['avisos'] ?? []) }}
                                        @if ($f['estado'] === 'rechazada')
                                            @php $mr = trim((string) ($f['motivo_rechazo'] ?? '')); $tipoR = preg_match('/^(ISP|ADC)\b/u', $mr, $mm) ? $mm[1] : 'Otros'; @endphp
                                            <span class="focr-chip {{ $tipoR === 'Otros' ? 'c-gris' : 'c-revisar' }}" title="{{ $mr !== '' ? $mr : 'Sin motivo indicado' }}" style="cursor:help">Rechazo: {{ $tipoR }}</span>
                                        @elseif (! empty($f['motivo_rechazo'])) <b>Rechazo:</b> {{ $f['motivo_rechazo'] }} @endif
                                        <button type="button" wire:click.stop="quitarDeLista('{{ $f['id'] }}')"
                                                @if ($f['estado'] === 'pendiente') wire:confirm="¿Quitar {{ basename($f['ruta']) }} de la lista? (p.ej. si la contabilizas a mano en SAGE). El PDF no se toca." @endif
                                                class="focr-btn b-gris" style="padding:.05rem .4rem; font-size:.7rem" title="Quitarla de la lista (el PDF no se toca)">✕ Quitar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="p-4 text-center text-gray-500">No hay facturas por revisar. Elige la carpeta y pulsa «Leer las facturas».</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="flex flex-wrap items-center gap-2 p-2 border-b border-gray-200">
                            <input type="search" wire:model.live.debounce.300ms="filtro" placeholder="Buscar proveedor, cuenta, nº, fichero…" class="focr-in" style="max-width:320px">
                            <select wire:model.live="filtroProceso" class="focr-in" style="max-width:260px" title="Cada vez que guardas el Excel para SAGE se cierra un proceso">
                                <option value="">Proceso en curso (sin guardar: {{ $enExcel }})</option>
                                @foreach ($procesos as $x => $n)
                                    <option value="{{ $x }}">Guardado el {{ \App\Http\Livewire\Contabilidad\FacturasOcr::fechaProceso($x) }} ({{ $n }})</option>
                                @endforeach
                                <option value="todas">Todas</option>
                            </select>
                            <select wire:model.live="filtroMes" class="focr-in" style="max-width:200px">
                                <option value="">Todos los meses de registro</option>
                                @foreach ($mesesReg as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                            <span class="ml-auto text-xs text-gray-500">
                                @if ($ultimoExcel) Último guardado: {{ $ultimoExcel['fecha'] }} ({{ $ultimoExcel['facturas'] }} facturas) · @endif
                            </span>
                            @if ($enExcel)
                                <button type="button" wire:click="guardarExcel" wire:loading.attr="disabled" class="focr-btn b-verde" style="padding:.3rem .8rem; font-size:.85rem"
                                        title="Te pregunta dónde guardarlo (ventana de Windows) y cierra el proceso: lo que valides después irá a un Excel nuevo. Copia en {{ $dirDatos }}/Output/Guardados">
                                    <span wire:loading.remove wire:target="guardarExcel">💾 Guardar el Excel para SAGE ({{ $enExcel }} facturas)</span>
                                    <span wire:loading wire:target="guardarExcel">Elige dónde guardarlo…</span>
                                </button>
                            @else
                                <span class="text-xs text-gray-400">Nada nuevo para SAGE</span>
                            @endif
                        </div>
                        <table class="focr-tabla">
                            <thead><tr>
                                <th>F. registro</th><th>Proveedor</th><th>Nº factura</th><th>F. factura</th><th>Contrap.</th>
                                <th style="text-align:right">Base</th><th style="text-align:right">% IVA</th><th style="text-align:right">IVA</th><th style="text-align:right">Total</th>
                                <th>Excel</th><th>Fichero</th><th>Validada</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($validadas as $f)
                                @php $d = $f['datos']; @endphp
                                <tr wire:key="v-{{ $f['id'] }}" @if ($f['estado'] === 'validada') class="clic" wire:click="abrir('{{ $f['id'] }}')" title="Ver lo validado (y volverla a pendiente para corregirla)" @endif>
                                    <td>{{ $fmt($d['fecha_registro'] ?? '') }}</td>
                                    <td>{{ $d['cuenta'] ?? '' }} {{ $d['proveedor'] ?? '' }}</td>
                                    <td>{{ $d['su_factura'] ?? '' }}</td>
                                    <td>{{ $fmt($d['fecha_expedicion'] ?? '') }}</td>
                                    @php $im = $importes($d); @endphp
                                    <td>{{ $d['contrapartida'] ?? '' }}</td>
                                    <td style="text-align:right">{{ $eur($im['base']) }}</td>
                                    <td style="text-align:right" title="{{ $im['detalle'] }}">{!! $im['pct'] === 'varios' ? '<span class="focr-chip c-revisar">varios</span>' : e($im['pct']) !!}</td>
                                    <td style="text-align:right">{{ $eur($im['iva']) }}</td>
                                    <td style="text-align:right">{{ $eur($d['total'] ?? null) }}</td>
                                    <td class="text-xs" style="white-space:nowrap">
                                        @if (str_starts_with($f['excel'] ?? '', 'Guardados/'))
                                            <span class="focr-chip c-gris" title="{{ $f['excel'] }}">Guardado {{ \App\Http\Livewire\Contabilidad\FacturasOcr::fechaProceso($f['excel']) }}</span>
                                        @elseif (! empty($f['excel']))
                                            <span class="focr-chip c-ok">En curso</span>
                                        @endif
                                        fila {{ $f['fila_excel'] ?? '' }}</td>
                                    <td class="text-xs" style="max-width:340px; word-break:break-all">
                                        <a href="{{ route('contabilidad.facturas-ocr.pdf', [$cliente, $f['id']]) }}" target="_blank" class="text-indigo-600 underline">{{ $f['ruta'] }}</a>
                                    </td>
                                    <td class="text-xs" style="white-space:nowrap">{{ $f['estado'] === 'validando' ? 'guardando…' : ($f['validada_el'] ?? '') }}
                                        @if (! empty($f['automatica'])) <span class="focr-chip c-ok" title="Validada sola: el proveedor se validó 3 veces seguidas sin tocar nada">auto</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="p-4 text-center text-gray-500">{{ $filtroProceso === '' && ! $filtro && ! $filtroMes ? 'Nada validado en este proceso todavía (lo guardado antes está en el desplegable de procesos).' : 'Ninguna factura validada'.($filtro || $filtroMes || $filtroProceso ? ' con ese filtro' : '').'.' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
