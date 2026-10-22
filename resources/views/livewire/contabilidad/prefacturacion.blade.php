<div class="space-y-2">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="mes(-1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">◀</button>
        <input type="month" wire:model.live="periodo" class="py-1 text-base border-gray-300 rounded-md">
        <button type="button" wire:click="mes(1)" class="px-2 py-0.5 bg-white border border-gray-300 rounded hover:bg-gray-50">▶</button>
        <select wire:model.live="estadoFiltro" class="py-1 text-base border-gray-300 rounded-md">
            <option value="">Todas</option>
            <option value="pendiente">Pendientes</option>
            <option value="facturado">Facturadas</option>
        </select>
        @if ($puede)
            <button type="button" wire:click="editar" class="px-4 py-2 text-base font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">＋ Nuevo cargo</button>
        @endif
        <span class="text-base text-gray-700">Pendiente: <b>{{ number_format($totalPendiente, 2, ',', '.') }} €</b> · Facturado: <b>{{ number_format($totalFacturado, 2, ',', '.') }} €</b></span>
    </div>
    <p class="text-sm text-gray-500">Periodo = mes en que hay que facturarlo. Clic en el estado para pasar de pendiente a facturado.</p>

    @if ($proponerMotivo !== '' && $puede)
        <div class="flex flex-wrap items-center gap-2 p-2 text-base bg-yellow-50 border border-yellow-300 rounded-lg">
            <span>¿Añadir «<b>{{ $proponerMotivo }}</b>» a los motivos habituales para rellenar más rápido la próxima vez?</span>
            <button type="button" wire:click="guardarMotivoBase" class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Sí, añadir</button>
            <button type="button" wire:click="descartarMotivoBase" class="px-3 py-1 bg-white border border-gray-300 rounded-md hover:bg-gray-50">No</button>
        </div>
    @endif

    @if ($editando !== null)
        <style>
            .pf-subir > div { display: flex; width: 100%; }
            .pf-subir .truncate { max-width: none !important; }
            .pf-subir .subir-btn { width: 100%; justify-content: center; padding: .9rem 1rem; font-size: .85rem; border-style: dashed; border-width: 2px; }
        </style>
        <div class="grid gap-3 p-4 bg-white border border-indigo-200 rounded-lg md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="block mb-1 text-sm text-gray-500">Cliente</label>
                <x-buscador-select model="fEntidad" :valor="$fEntidad" :opciones="$empresas->pluck('entidad', 'id')" ancho="100%" />
            </div>
            <div class="md:col-span-2">
                <label class="block mb-1 text-sm text-gray-500">Periodo a facturar</label>
                <select wire:model="fPeriodo" class="w-full text-base border-gray-300 rounded-md">
                    @foreach ($mesesAnio as $v => $n)
                        <option value="{{ $v }}">{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block mb-1 text-sm text-gray-500">Se factura</label>
                <select wire:model="fRecurrencia" class="w-full text-base border-gray-300 rounded-md">
                    <option value="puntual">Solo esta vez (puntual)</option>
                    <option value="mensual">Todos los meses</option>
                    <option value="trimestral">Todos los trimestres</option>
                </select>
            </div>
            <div class="flex items-start gap-3 md:col-span-2">
                <div>
                    <label class="block mb-1 text-sm text-gray-500">Suplido</label>
                    <div class="flex items-center justify-center" style="height:38px"><input type="checkbox" wire:model="fSuplido" class="border-gray-300 rounded" title="Marca si el importe es un suplido"></div>
                </div>
                <div>
                    <label class="block mb-1 text-sm text-gray-500">Importe (€)</label>
                    <input type="text" wire:model="fImporte" placeholder="0,00" class="text-base text-right border-gray-300 rounded-md" style="width:7.5rem">
                </div>
            </div>
            <div class="md:col-span-4">
                <label class="block mb-1 text-sm text-gray-500">Motivo</label>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    @if ($motivosBase->isNotEmpty())
                        <x-buscador-select model="motivoBase" :valor="$motivoBase" :opciones="$motivosBase" ancho="22rem" />
                        <button type="button" wire:click="$toggle('gestionarMotivos')" class="text-sm text-gray-500 hover:text-gray-800">✎ Gestionar motivos habituales</button>
                    @else
                        <span class="text-sm text-gray-400">Aún no hay motivos habituales: al guardar te preguntaré si quieres añadir este.</span>
                    @endif
                </div>
                @if ($gestionarMotivos && $motivosBase->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-1">
                        @foreach ($motivosBase as $id => $t)
                            <span wire:key="mb-{{ $id }}" class="inline-flex items-center gap-1 px-2 text-sm text-gray-700 bg-gray-100 rounded-full">{{ $t }}<button type="button" wire:click="quitarMotivoBase({{ $id }})" wire:confirm="¿Quitar este motivo habitual?" class="text-gray-400 hover:text-red-600">✕</button></span>
                        @endforeach
                    </div>
                @endif
                <textarea wire:model="fMotivo" rows="2" class="w-full text-base border-gray-300 rounded-md" style="resize:vertical; min-height:38px"></textarea>
            </div>
            <div class="pf-subir md:col-span-6">
                <x-subir-fichero wire:model="fAdjunto" texto="Adjunto (albarán / factura): pulsa o arrastra aquí el archivo" :fichero="$fAdjunto" opcional neutro />
                <span wire:loading wire:target="fAdjunto" class="text-sm text-gray-500">Subiendo…</span>
                @error('fAdjunto') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex flex-wrap items-center gap-3 md:col-span-6">
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="fAdjunto,guardar" class="px-6 py-2 text-base font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Guardar</button>
                <button type="button" wire:click="cancelar" class="px-6 py-2 text-base bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancelar</button>
                @if ($aviso) <span class="text-base text-red-600">{{ $aviso }}</span> @endif
            </div>
        </div>
    @endif

    <div class="bg-white border rounded-lg shadow">
        <table class="w-full text-base" style="table-layout:auto">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-3 py-2">Cliente</th>
                    <th class="px-3 py-2">Suplido</th>
                    <th class="px-3 py-2 text-right">Importe</th>
                    <th class="px-3 py-2">Se factura</th>
                    <th class="px-3 py-2">Motivo</th>
                    <th class="px-3 py-2">Adjunto</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    <tr wire:key="pf-{{ $f->id }}" class="align-top hover:bg-gray-50">
                        <td class="px-3 py-2">{{ $f->cliente ?: '—' }}</td>
                        <td class="px-3 py-2">{{ ($f->suplido ?? false) ? '✓ Sí' : '' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ number_format($f->importe, 2, ',', '.') }} €</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ ['mensual' => 'Todos los meses', 'trimestral' => 'Todos los trimestres'][$f->recurrencia ?? ''] ?? 'Puntual' }}</td>
                        <td class="px-3 py-2" style="white-space:pre-line">{{ ($f->suplido ?? false) ? 'Suplido: ' : '' }}{{ $f->motivo }}</td>
                        <td class="px-3 py-2" style="max-width:240px; word-break:break-word">
                            @if ($f->adjunto_ruta)
                                <button type="button" wire:click="descargar({{ $f->id }})" class="text-indigo-700 hover:underline">📎 {{ $f->adjunto_nombre }}</button>
                                @if ($puede)<button type="button" wire:click="quitarAdjunto({{ $f->id }})" wire:confirm="¿Quitar el adjunto?" class="ml-1 text-gray-400 hover:text-red-600">✕</button>@endif
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <button type="button" @if ($puede) wire:click="alternarEstado({{ $f->id }})" @endif
                                class="px-2 border rounded {{ $f->estado === 'facturado' ? 'bg-green-500 border-green-600 text-white' : 'bg-yellow-300 border-yellow-500 text-yellow-900' }}">{{ $f->estado === 'facturado' ? 'Facturado' : 'Pendiente' }}</button>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if ($puede)
                                <button type="button" wire:click="editar({{ $f->id }})" class="text-gray-500 hover:text-gray-800" title="Editar">✎</button>
                                <button type="button" wire:click="borrar({{ $f->id }})" wire:confirm="¿Borrar esta línea?" class="ml-1 text-gray-500 hover:text-red-600" title="Borrar">🗑</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-2 py-3 text-center text-gray-400">Sin líneas en este periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
