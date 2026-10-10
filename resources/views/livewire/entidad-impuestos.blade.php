<div class="mt-2 mb-4">
    <div class="px-2 mx-2 mt-2 mb-1 rounded-md bg-blue-50">
        <h3 class="font-semibold text-gray-600">Impuestos que presenta <span class="text-xs font-normal text-gray-500">— aparecen en TO-DO → Impuestos; se guarda al momento</span></h3>
    </div>
    <div class="mx-2 text-sm text-gray-600">
        @if ($aviso) <p class="px-1 py-1 text-xs text-red-700">{{ $aviso }}</p> @endif
        @if ($obs->isEmpty())
            <p class="px-1 py-1 text-xs text-gray-500">Todavía no tiene ninguno.</p>
        @else
            <table class="text-sm">
                <thead>
                    <tr class="text-xs text-left text-gray-500">
                        <th class="px-1 pr-4">Impuesto</th><th class="px-1 pr-4" title="Solo si el cliente presenta dos declaraciones del mismo impuesto">Etiqueta</th><th class="px-1 pr-4">Periodo</th><th class="px-1 pr-4">Lo lleva</th><th class="px-1 pr-4">Observaciones</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($obs as $o)
                        <tr wire:key="ei-{{ $o->id }}">
                            <td class="px-1 pr-4" style="white-space:nowrap"><b>{{ ctype_digit($o->codigo) ? 'M'.$o->codigo : $o->codigo }}</b> <span class="text-xs text-gray-500">{{ $o->nombre }}</span></td>
                            <td class="px-1 pr-4">
                                <input type="text" value="{{ $o->etiqueta }}" maxlength="60" placeholder="(la normal)" @disabled(! $editar) wire:change="cambiarEtiqueta({{ $o->id }}, $event.target.value)"
                                    class="py-0.5 text-sm border-gray-300 rounded-md" style="width:130px">
                            </td>
                            <td class="px-1 pr-4">
                                <select wire:change="cambiarPeriodicidad({{ $o->id }}, $event.target.value)" @disabled(! $editar) class="py-0.5 text-sm border-gray-300 rounded-md">
                                    @foreach (\App\Support\Impuestos::PERIODICIDADES as $k => $t)
                                        <option value="{{ $k }}" @selected($o->periodicidad === $k)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-1 pr-4">
                                <select wire:change="cambiarResponsable({{ $o->id }}, $event.target.value)" @disabled(! $editar) class="py-0.5 text-sm border-gray-300 rounded-md">
                                    <option value="">Responsable(s) de la entidad{{ $respEntidad ? ": $respEntidad" : '' }}</option>
                                    @foreach ($responsables as $r)
                                        <option value="{{ $r->id }}" @selected($o->user_id == $r->id)>{{ $r->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-1 pr-4">
                                <input type="text" value="{{ $o->observaciones }}" maxlength="255" @disabled(! $editar) wire:change="guardarObservaciones({{ $o->id }}, $event.target.value)"
                                    class="py-0.5 text-sm border-gray-300 rounded-md" style="min-width:220px">
                            </td>
                            <td style="white-space:nowrap">
                                @if ($o->baja_ejercicio)
                                    <span class="px-1.5 py-0.5 text-xs rounded" style="color:#92400e; background:#fef3c7" title="Desde ahí no se generan casillas; lo anterior se conserva">
                                        De baja desde {{ \App\Support\Impuestos::etiquetaPeriodo($o->baja_periodo, $o->baja_ejercicio) }}</span>
                                    @if ($editar) <button type="button" wire:click="reactivar({{ $o->id }})" class="text-xs text-indigo-700 hover:underline">Reactivar</button> @endif
                                @elseif ($editar)
                                    <button type="button" wire:click="abrirBaja({{ $o->id }})" class="text-xs text-indigo-700 hover:underline" title="Deja de presentarlo desde un periodo sin perder el historial">⏸ Dejar de presentar…</button>
                                    @if (! isset($conHistorial[$o->id]))
                                        <button type="button" wire:click="quitar({{ $o->id }})" wire:confirm="¿Borrar este impuesto de la entidad? Aún no tiene historial." class="text-gray-400 hover:text-red-600" title="Borrar (solo si no tiene historial)">✕</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @if ($bajaOb === $o->id)
                            <tr wire:key="baja-{{ $o->id }}">
                                <td colspan="6" class="px-2 py-2 rounded" style="background:#fffbeb; white-space:normal; max-width:640px">
                                    <span class="text-xs text-gray-700">Dejar de presentarlo <b>desde</b></span>
                                    <select wire:model="bajaEj" class="py-0.5 text-sm border-gray-300 rounded-md">
                                        @foreach ([now()->year - 1, now()->year, now()->year + 1] as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
                                    </select>
                                    <select wire:model="bajaPer" class="py-0.5 text-sm border-gray-300 rounded-md">
                                        @foreach (\App\Support\Impuestos::periodos($o->periodicidad) as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                                    </select>
                                    <button type="button" wire:click="darDeBaja" style="background:#d97706; color:#fff; padding:2px 10px; border-radius:4px; font-weight:600" class="text-sm">Dar de baja</button>
                                    <button type="button" wire:click="cerrarBaja" class="text-xs text-gray-500 hover:underline">Cancelar</button>
                                    <span class="text-xs text-gray-500">Se conserva todo lo anterior; las casillas pendientes de ahí en adelante desaparecen. Se puede reactivar.</span>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($editar)
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <select wire:model.live="nuevoModelo" class="py-1 text-sm border-gray-300 rounded-md">
                    <option value="">＋ Añadir impuesto…</option>
                    @foreach ($modelos as $m)
                        <option value="{{ $m->codigo }}">{{ ctype_digit($m->codigo) ? 'M'.$m->codigo : $m->codigo }} · {{ $m->nombre }}</option>
                    @endforeach
                </select>
                @if ($nuevoModelo !== '')
                    <select wire:model="nuevaPeriodicidad" class="py-1 text-sm border-gray-300 rounded-md">
                        @foreach (\App\Support\Impuestos::PERIODICIDADES as $k => $t)
                            <option value="{{ $k }}">{{ $t }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="nuevaEtiqueta" maxlength="60" placeholder="Etiqueta (si ya tiene otra igual)" class="py-1 text-sm border-gray-300 rounded-md" style="width:210px">
                    <button type="button" wire:click="anadir" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Añadir</button>
                @endif
                @role('Admin')
                    <button type="button" wire:click="$toggle('crearModelo')" class="text-xs text-indigo-700 hover:underline">¿No está? Crear un impuesto nuevo</button>
                @endrole
            </div>
            @if ($crearModelo)
                <div class="flex flex-wrap items-center gap-2 p-2 mt-2 bg-white border border-indigo-200 rounded-md">
                    <input type="text" wire:model="mCodigo" placeholder="Código (p. ej. 036)" maxlength="10" class="py-1 text-sm border-gray-300 rounded-md" style="width:130px">
                    <input type="text" wire:model="mNombre" placeholder="Nombre del impuesto" class="py-1 text-sm border-gray-300 rounded-md" style="min-width:260px">
                    <select wire:model="mPeriodicidad" class="py-1 text-sm border-gray-300 rounded-md">
                        @foreach (\App\Support\Impuestos::PERIODICIDADES as $k => $t)
                            <option value="{{ $k }}">{{ $t }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="guardarModelo" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crear</button>
                    @error('mCodigo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    @error('mNombre') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            @endif
        @endif
    </div>
</div>
