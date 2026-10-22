<div class="space-y-2">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="mes(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
        <input type="month" wire:model.live="periodo" class="py-1 text-sm border-gray-300 rounded-md">
        <button type="button" wire:click="mes(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
        <select wire:model.live="estadoFiltro" class="py-1 text-sm border-gray-300 rounded-md">
            <option value="">Todas</option>
            <option value="pendiente">Pendientes</option>
            <option value="facturado">Facturadas</option>
        </select>
        @if ($puede)
            <button type="button" wire:click="editar" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">＋ Nueva línea</button>
        @endif
        <span class="text-sm text-gray-700">Pendiente: <b>{{ number_format($totalPendiente, 2, ',', '.') }} €</b> · Facturado: <b>{{ number_format($totalFacturado, 2, ',', '.') }} €</b></span>
    </div>
    <p class="text-xs text-gray-500">Periodo = mes en que hay que facturarlo. Clic en el estado para pasar de pendiente a facturado.</p>

    @if ($editando !== null)
        <div class="grid gap-2 p-3 bg-white border border-indigo-200 rounded-lg md:grid-cols-4">
            <div class="md:col-span-4">
                <x-buscador-select model="fEntidad" :valor="$fEntidad" :opciones="$empresas->pluck('entidad', 'id')" ancho="28rem" />
            </div>
            <input type="text" wire:model="fImporte" placeholder="Importe (€)" class="text-sm border-gray-300 rounded-md">
            <input type="month" wire:model="fPeriodo" class="text-sm border-gray-300 rounded-md" title="Periodo en que se factura">
            <input type="text" wire:model="fMotivo" placeholder="Motivo" class="text-sm border-gray-300 rounded-md md:col-span-2">
            <div class="flex flex-wrap items-center gap-2 md:col-span-4">
                <x-subir-fichero wire:model="fAdjunto" texto="Adjunto (albarán / factura)" :fichero="$fAdjunto" opcional neutro />
                <span wire:loading wire:target="fAdjunto" class="text-xs text-gray-500">Subiendo…</span>
                @error('fAdjunto') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="fAdjunto,guardar" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Guardar</button>
                <button type="button" wire:click="cancelar" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                @if ($aviso) <span class="text-sm text-red-600">{{ $aviso }}</span> @endif
            </div>
        </div>
    @endif

    <div class="overflow-x-auto bg-white border rounded-lg shadow">
        <table class="w-full text-xs">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-2 py-1">Cliente</th>
                    <th class="px-2 py-1 text-right">Importe</th>
                    <th class="px-2 py-1">Motivo</th>
                    <th class="px-2 py-1">Adjunto</th>
                    <th class="px-2 py-1">Estado</th>
                    <th class="px-2 py-1"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    <tr wire:key="pf-{{ $f->id }}" class="align-top hover:bg-gray-50">
                        <td class="px-2 py-1">{{ $f->cliente ?: '—' }}</td>
                        <td class="px-2 py-1 text-right whitespace-nowrap">{{ number_format($f->importe, 2, ',', '.') }} €</td>
                        <td class="px-2 py-1">{{ $f->motivo }}</td>
                        <td class="px-2 py-1 whitespace-nowrap">
                            @if ($f->adjunto_ruta)
                                <button type="button" wire:click="descargar({{ $f->id }})" class="text-indigo-700 hover:underline">📎 {{ $f->adjunto_nombre }}</button>
                                @if ($puede)<button type="button" wire:click="quitarAdjunto({{ $f->id }})" wire:confirm="¿Quitar el adjunto?" class="ml-1 text-gray-400 hover:text-red-600">✕</button>@endif
                            @endif
                        </td>
                        <td class="px-2 py-1">
                            <button type="button" @if ($puede) wire:click="alternarEstado({{ $f->id }})" @endif
                                class="px-2 border rounded {{ $f->estado === 'facturado' ? 'bg-green-500 border-green-600 text-white' : 'bg-yellow-300 border-yellow-500 text-yellow-900' }}">{{ $f->estado === 'facturado' ? 'Facturado' : 'Pendiente' }}</button>
                        </td>
                        <td class="px-2 py-1 whitespace-nowrap">
                            @if ($puede)
                                <button type="button" wire:click="editar({{ $f->id }})" class="text-gray-500 hover:text-gray-800" title="Editar">✎</button>
                                <button type="button" wire:click="borrar({{ $f->id }})" wire:confirm="¿Borrar esta línea?" class="ml-1 text-gray-500 hover:text-red-600" title="Borrar">🗑</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">Sin líneas en este periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
