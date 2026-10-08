<div class="">
    @livewire('menu',['entidad'=>$entidad,'ruta'=>$ruta],key($entidad->id))

    <div class="p-1 mx-2">
        <h1 class="text-2xl font-semibold text-gray-900">Historial de {{ $entidad->entidad }} <span class="text-lg text-gray-500 "> ({{ $entidad->nif }})</span></h1>
    </div>
    <div class="mx-2 text-gray-500">
                
                <div class="w-full form-item">
                    <x-jet-label>Historial y comentarios</x-jet-label>
                    <div class="p-2 border border-gray-300 rounded-md" style="background:#fafafa">
                        @if (auth()->user()->can('entidades.editar'))
                            <div class="flex flex-wrap items-end gap-2 mb-2">
                                <input type="date" wire:model="histFecha" class="py-1 text-xs border-gray-300 rounded-md">
                                <input type="number" step="0.01" wire:model="histImporte" class="py-1 text-xs border-gray-300 rounded-md" style="width:7.5rem" placeholder="Importe fact.">
                                <select wire:model="histPeriodo" class="py-1 text-xs border-gray-300 rounded-md" title="Periodo del importe">
                                    <option value="">Periodo</option>
                                    @foreach (App\Models\Entidad::CICLOS as $value=>$label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                                </select>
                                <input type="text" wire:model="histTexto" wire:keydown.enter.prevent="anadirHistorico" class="grow py-1 text-xs border-gray-300 rounded-md" placeholder="Comentario (o importe de facturación y su periodo)…">
                                <button type="button" wire:click="anadirHistorico" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">＋ Añadir</button>
                            </div>
                            @error('histTexto') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                        @endif
                        @forelse ($historico as $h)
                            <div class="flex items-start gap-2 py-1 text-xs border-t border-gray-200" wire:key="hist-{{ $h->id }}">
                                <span class="text-gray-500 whitespace-nowrap">{{ $h->fecha->format('d/m/Y') }}</span>
                                <span class="grow">
                                    @if ($h->tipo === 'estado')
                                        <b>{{ \App\Models\Entidad::ESTADOS[$h->estado_anterior] ?? '—' }} → {{ \App\Models\Entidad::ESTADOS[$h->estado_nuevo] ?? '—' }}</b>
                                    @endif
                                    @if ($h->importe_facturacion !== null)
                                        <b>Facturación: {{ number_format($h->importe_facturacion, 2, ',', '.') }} €{{ $h->periodo_facturacion !== null ? ' / '.(\App\Models\Entidad::CICLOS[$h->periodo_facturacion] ?? $h->periodo_facturacion) : '' }}</b>
                                    @endif
                                    {{ $h->comentario }}
                                    @if ($h->user) <span class="text-gray-400">· {{ $h->user->name }}</span> @endif
                                </span>
                                @if (auth()->user()->can('entidades.editar'))
                                    <button type="button" wire:click="borrarHistorico({{ $h->id }})" wire:confirm="¿Borrar esta línea del historial?" class="text-gray-400 hover:text-red-600">✕</button>
                                @endif
                            </div>
                        @empty
                            <div class="text-xs italic text-gray-400">Sin historial todavía.</div>
                        @endforelse
                    </div>
                </div>
                
    </div>
</div>
