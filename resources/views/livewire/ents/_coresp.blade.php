{{-- Co-responsables («Otro Resp.»). $modo = 'etiquetas' (de la entidad $e, con ✕) | 'modal' (el abierto).
     Necesita $coResp (entidad_id => [[suma_id, nombre]]) y $sumas. --}}
@php $puede = auth()->user()->can('entidades.editar'); @endphp
@if ($modo === 'etiquetas')
    <span class="inline-flex flex-wrap items-center gap-1">
        @foreach ($coResp[$e->id] ?? [] as $cr)
            <span class="inline-flex items-center px-1.5 text-xs text-indigo-800 bg-indigo-100 rounded-full whitespace-nowrap">
                {{ $cr['nombre'] }}
                @if ($puede)
                    <button type="button" wire:click="alternarCoResp({{ $e->id }}, {{ $cr['suma_id'] }}, false)" title="Quitar" class="ml-1 text-indigo-400 hover:text-red-600">✕</button>
                @endif
            </span>
        @endforeach
    </span>
@elseif ($modo === 'modal' && $coRespDe)
    @php
        $ent = \App\Models\Entidad::find($coRespDe);
        $marcados = array_column($coResp[$coRespDe] ?? \App\Http\Livewire\Concerns\CoResponsables::coResponsablesDe([$coRespDe])[$coRespDe] ?? [], 'suma_id');
    @endphp
    @if ($ent)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.4)" wire:click.self="cerrarCoResp">
            <div class="w-full max-w-sm p-4 bg-white rounded-lg shadow-xl">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">Otros responsables</h3>
                        <p class="text-xs text-gray-500">{{ $ent->entidad }} · principal: {{ $sumas->firstWhere('id', $ent->suma_id)->nombre ?? '— sin resp. —' }}</p>
                    </div>
                    <button type="button" wire:click="cerrarCoResp" class="text-xl leading-none text-gray-400 hover:text-gray-700">&times;</button>
                </div>
                <div class="overflow-y-auto divide-y divide-gray-100" style="max-height:60vh">
                    @foreach ($sumas as $s)
                        @continue($s->id == $ent->suma_id || ! $s->user_id)
                        <label class="flex items-center gap-2 px-1 py-1.5 text-sm cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" @checked(in_array($s->id, $marcados)) wire:click="alternarCoResp({{ $ent->id }}, {{ $s->id }})" class="text-indigo-600 border-gray-300 rounded">
                            {{ $s->nombre }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-400">Se guarda al momento. Les sale en Proc.Mensuales y en «Mis empresas».</p>
            </div>
        </div>
    @endif
@endif
