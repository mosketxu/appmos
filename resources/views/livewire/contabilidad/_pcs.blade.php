{{-- Panel «PCs de trabajo» + «Tareas en los PCs» (3-oct-2026). Solo en la web (VPS): los procesos los hacen los PCs
     trabajadores por la cola de tareas. Mientras haya tareas pedidas se pregunta cada 3 s si ya han terminado
     (revisarTareas, trait EjecutaEnPcs). Pide en la pantalla: $pendientes, $this->pcs, sincronizarAhora(), cancelarTarea(). --}}
    @if ($pendientes)
        <div wire:poll.3s="revisarTareas"></div>
    @endif
    @unless (config('contabilidad.ejecucion_local'))
        @php $cola = $this->pcs; @endphp
        <div class="p-3 text-sm bg-white border rounded-lg shadow" x-data="{ abierto: {{ $pendientes ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="font-medium text-gray-800">PCs de trabajo:</span>
                @forelse ($cola['pcs'] as $pc)
                    <span title="{{ $pc['latido'] ? 'Última señal: '.$pc['latido'] : 'Sin señales todavía' }}">{{ $pc['conectado'] ? '🟢' : '⚪' }} {{ $pc['nombre'] }}</span>
                @empty
                    <span class="text-gray-500">ninguno dado de alta</span>
                @endforelse
                @if ($cola['pcs'] && ! collect($cola['pcs'])->contains('conectado', true))
                    <span class="text-amber-700">⚠️ Ningún PC conectado: lo que pidas esperará a que uno arranque.</span>
                @endif
                <button type="button" x-on:click="abierto = !abierto" class="text-xs text-indigo-700 hover:underline">Tareas en los PCs <span x-text="abierto ? '▴' : '▾'"></span></button>
                <button type="button" wire:click="sincronizarAhora" class="text-xs text-indigo-700 hover:underline" title="Pide a un PC que suba el estado actual (checklist, importes, Pagos fin de mes...)">↻ Sincronizar estado</button>
            </div>
            <div x-show="abierto" style="display:none" class="mt-2 overflow-x-auto">
                <table class="text-xs">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cola['tareas'] as $t)
                            @php
                                $pasosT = json_decode($t->parametros ?? '[]', true)['pasos'] ?? [];
                                $quePide = $t->proceso === 'pc.script'
                                    ? collect($pasosT)->map(fn ($p) => $p['script'].' '.implode(' ', array_map(fn ($a) => mb_strlen($a) > 30 ? mb_substr($a, 0, 30).'…' : $a, $p['args'] ?? [])))->implode(' + ')
                                    : (config('contabilidad.tareas_procesos')[$t->proceso] ?? $t->proceso);
                            @endphp
                            <tr wire:key="tarea-{{ $t->id }}">
                                <td class="px-2 py-0.5 text-gray-400">#{{ $t->id }}</td>
                                <td class="px-2 py-0.5 text-gray-500">{{ $t->created_at }}</td>
                                <td class="px-2 py-0.5">{{ ['pendiente' => '⏳ en cola', 'en_curso' => '⚙️ en curso', 'ok' => '✅ ok', 'error' => '❌ error', 'cancelada' => '🚫 cancelada'][$t->estado] ?? $t->estado }}</td>
                                <td class="px-2 py-0.5 text-gray-500">{{ $t->pc }}</td>
                                <td class="px-2 py-0.5 font-mono text-gray-700">{{ \Illuminate\Support\Str::limit($quePide, 110) }}</td>
                                <td class="px-2 py-0.5">
                                    @if ($t->estado === 'pendiente')
                                        <button type="button" wire:click="cancelarTarea({{ $t->id }})" class="text-red-700 hover:underline">cancelar</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-2 py-1 text-gray-500">Todavía no se ha pedido nada a los PCs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless

