<div @if ($enCurso) wire:poll.5s @endif class="text-sm">
    <p class="text-gray-700">Borra los <b>«_anterior AAAAMMDD-HHMMSS …»</b> y el contenido de <b>_copias_libro_iva</b> de OneDrive (en cualquier cliente) que tengan más de los días que pongas. No toca nada más.</p>
    <div class="flex flex-wrap items-center gap-3 mt-3">
        <label class="flex items-center gap-2">Más de
            <input type="number" min="0" max="365" wire:model.live.debounce.400ms="dias" class="w-20 border-gray-300 rounded-md"> días
        </label>
        <button type="button" wire:click="lanzar(false)" @disabled($enCurso) class="px-3 py-1 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">🔍 Simular</button>
        <button type="button" wire:click="lanzar(true)" wire:confirm="¿Borrar de OneDrive los «_anterior …» y las copias de _copias_libro_iva de más de {{ $dias }} días?" @disabled($enCurso)
            class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">🧹 Limpiar ahora</button>
    </div>
    @if ($aviso)<p class="mt-2 text-indigo-700">{{ $aviso }}</p>@endif
    @if ($enCurso)
        <p class="mt-3 text-gray-600">⏳ Un PC está en ello…</p>
    @elseif ($tarea)
        <div class="mt-3">
            <p style="color:{{ $tarea->estado === 'ok' ? '#047857' : '#b91c1c' }}">
                {{ $tarea->estado === 'ok' ? '✓' : '⚠️' }} {{ $resultado['resumen'] ?? ($tarea->estado === 'ok' ? 'hecho' : 'la última limpieza falló (mira el aviso de tareas)') }}
                <span class="text-xs text-gray-500">· {{ \Illuminate\Support\Carbon::parse($tarea->terminada_at ?? $tarea->created_at)->format('d/m/Y H:i') }}{{ ! empty($resultado['pc']) ? ' · '.$resultado['pc'] : '' }}</span>
            </p>
            @if (! empty($resultado['lista']))
                <details class="mt-1"><summary class="text-xs text-gray-500 cursor-pointer">{{ count($resultado['lista']) }} ficheros{{ ! empty($resultado['borrado']) ? ' borrados' : ' que se borrarían' }}</summary>
                    <ul class="mt-1 text-xs text-gray-600 break-all">
                        @foreach ($resultado['lista'] as [$ruta, $mb]) <li>{{ $ruta }} ({{ $mb }} MB)</li> @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</div>
