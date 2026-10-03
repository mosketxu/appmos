<div wire:poll.30s>
    @if ($ec)
        <div class="flex items-center gap-x-3 px-3 py-1 text-xs bg-gray-50 border border-gray-200 rounded-lg whitespace-nowrap">
            <span class="font-semibold text-gray-700" title="Tareas del TO-DO que Claude hace solo desde los PCs">🤖 Claude</span>
            @foreach ($ec['trabajadores'] as $w)
                <span title="{{ $w['nombre'] }}: {{ $w['en_linea'] ? 'en línea' : 'sin señal' }}{{ $w['principal'] ? ' · PC principal' : ' · PC secundario' }}">
                    <span style="display:inline-block;width:.55rem;height:.55rem;border-radius:9999px;background:{{ $w['en_linea'] ? '#16a34a' : '#9ca3af' }}"></span>
                    <span class="{{ $w['principal'] ? 'font-semibold' : '' }}">{{ str_replace(['AlexMiniPC', 'PortalExomen'], ['MiniPC', 'Portal'], $w['nombre']) }}</span>
                </span>
            @endforeach
            <span title="Pasadas automáticas de Claude hoy ({{ $ec['hoy'] }} de un tope de {{ $ec['limite'] }}). Coste estimado de hoy: {{ number_format($ec['coste'], 2) }} $. El contador vuelve a 0 a las 00:00 (en {{ $ec['reinicio'] }}). No es el uso del plan de Claude (ese no se puede leer por programa).{{ $ec['en_curso'] ? ' Haciendo ahora: '.implode(' · ', $ec['en_curso']) : '' }}">
                Uso hoy:
                <b class="{{ $ec['porcentaje'] >= 100 ? 'text-red-600' : ($ec['porcentaje'] >= 70 ? 'text-amber-600' : 'text-gray-800') }}">{{ $ec['porcentaje'] }} %</b>
                <span class="text-gray-500">({{ $ec['hoy'] }}/{{ $ec['limite'] }}) · reinicia 00:00 (en {{ $ec['reinicio'] }})</span>
                @if ($ec['en_curso']) <span class="text-indigo-600">▶</span> @endif
            </span>
            @if ($ec['pausado'])
                <span class="px-1.5 py-0.5 font-semibold text-red-700 bg-red-100 rounded">EN PAUSA</span>
                <button type="button" wire:click="pausar(false)" class="px-2 py-0.5 text-white bg-green-600 rounded hover:bg-green-700">▶ Reanudar</button>
            @else
                <button type="button" wire:click="pausar(true)" wire:confirm="¿Pausar TODOS los desarrollos automáticos de Claude? (lo que esté en curso termina; nada nuevo empieza)" class="px-2 py-0.5 text-white bg-red-600 rounded hover:bg-red-700">⏸ Pausar todos</button>
            @endif
        </div>
    @endif
</div>
