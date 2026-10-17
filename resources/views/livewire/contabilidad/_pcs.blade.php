{{-- Panel de PCs (3-oct-2026; reducido el 17-oct-2026). Solo en la web (VPS): los procesos los hacen los PCs
     trabajadores por la cola de tareas. Mientras haya tareas pedidas se pregunta cada 3 s si ya han terminado
     (revisarTareas, trait EjecutaEnPcs). Pide en la pantalla: $pendientes, $this->pcs, sincronizarAhora(), cancelarTarea(). --}}
    @if ($pendientes)
        <div wire:poll.3s="revisarTareas"></div>
        {{-- «Pensando»: mientras un PC trabaja (no bloquea la pantalla; se quita solo al terminar) --}}
        <div style="position:fixed;right:1rem;bottom:1rem;z-index:9997;display:flex;align-items:center;gap:.75rem;max-width:22rem;padding:.75rem 1rem;background:#fff;border:1px solid #c7d2fe;border-radius:.75rem;box-shadow:0 10px 25px -5px rgba(0,0,0,.3)">
            <svg style="flex:none;width:2rem;height:2rem;color:#4f46e5;animation:procesando-giro 1s linear infinite" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/>
                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div>
                <div style="font-size:.875rem;font-weight:600;color:#1f2937">Pensando… un PC está trabajando</div>
                <div style="font-size:.75rem;color:#6b7280">{{ \Illuminate\Support\Str::limit(collect($pendientes)->pluck('etiquetas')->flatten()->implode(' · '), 90) }}</div>
                @foreach ($progresoEnCurso ?? [] as $prog)
                    <div style="margin-top:.35rem;font-size:.8rem;color:#3730a3">{{ $prog }}</div>
                @endforeach
                {{-- Cancelar lo que aún no ha cogido ningún PC (p. ej. pedido con todos apagados): antes estaba en el historial de tareas --}}
                <div style="margin-top:.35rem;font-size:.75rem">
                    @foreach (array_keys($pendientes) as $tid)
                        <button type="button" wire:click="cancelarTarea({{ $tid }})" wire:confirm="¿Cancelar la tarea #{{ $tid }}? Solo se puede si ningún PC la ha empezado." class="text-red-700 hover:underline">cancelar #{{ $tid }}</button>
                    @endforeach
                </div>
                @foreach ($avisosEnCurso ?? [] as $aviso)
                    <div style="margin-top:.35rem;font-size:.8rem;font-weight:600;color:#b45309">{{ $aviso }}</div>
                @endforeach
            </div>
            <style>@keyframes procesando-giro{to{transform:rotate(360deg)}}</style>
        </div>
    @endif
    @unless (config('contabilidad.ejecucion_local'))
        @php $cola = $this->pcs; @endphp
        {{-- 17-oct-2026 (Alex): solo lo útil. Los PCs en línea se ven en «▶ Ejecutar en» (barra de menú); el resultado de cada
             proceso, en su salida y en su check; los errores llegan a la campana; cancelar, en el aviso «Pensando…».
             Quitado el historial de tareas (nadie lo miraba). Queda el aviso si no hay ningún PC y «Sincronizar estado». --}}
        <div class="flex flex-wrap items-center justify-end text-xs gap-x-4 gap-y-1">
            @if ($cola['pcs'] && ! collect($cola['pcs'])->contains('conectado', true))
                <span class="mr-auto text-sm text-amber-700">⚠️ Ningún PC de trabajo conectado: lo que pidas esperará a que uno arranque.</span>
            @endif
            <button type="button" wire:click="sincronizarAhora" class="text-gray-500 hover:text-indigo-700 hover:underline" title="Pide a un PC que suba el estado actual (checklist, importes, Pagos fin de mes...)">↻ Sincronizar estado</button>
        </div>
    @endunless

