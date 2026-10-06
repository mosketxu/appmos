{{-- Modal del listado de Entidades: al cambiar el estado se pide la fecha y el motivo (entidad_historico). --}}
@if ($cambioId)
    @php $ent = \App\Models\Entidad::find($cambioId); @endphp
    <div style="position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center" wire:key="cambio-estado">
        <div class="p-5 bg-white rounded-lg shadow-xl" style="width:min(30rem,92vw)">
            <h2 class="mb-1 text-lg font-semibold text-gray-800">Cambiar el estado</h2>
            <p class="mb-3 text-sm text-gray-600">{{ $ent?->entidad }} · ahora: <b>{{ \App\Models\Entidad::ESTADOS[(int) $ent?->estado] ?? '—' }}</b></p>
            <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
                <label class="text-xs text-gray-600">Nuevo estado
                    <select wire:model="cambioEstado" class="block w-full py-1 text-sm border-gray-300 rounded-md">
                        @foreach (\App\Models\Entidad::ESTADOS as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select></label>
                <label class="text-xs text-gray-600">Fecha del cambio
                    <input type="date" wire:model="cambioFecha" class="block w-full py-1 text-sm border-gray-300 rounded-md">
                    @error('cambioFecha') <span class="text-red-600">{{ $message }}</span> @enderror</label>
            </div>
            <label class="block mt-3 text-xs text-gray-600">Motivo / comentario
                <textarea wire:model="cambioMotivo" rows="3" class="block w-full text-sm border-gray-300 rounded-md" placeholder="p. ej. cese de actividad, cliente se va a otra gestoría…"></textarea></label>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="cancelarCambioEstado" class="px-3 py-1 text-sm text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">Cancelar</button>
                <button type="button" wire:click="confirmarCambioEstado" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Guardar cambio</button>
            </div>
        </div>
    </div>
@endif
