<div wire:poll.30s>
    @if ($ec)
        @php
            $p = $ec['plan'];
            $color = fn ($v) => $v === null ? 'text-gray-500' : ($v >= $ec['freno'] ? 'text-red-600' : ($v >= 60 ? 'text-amber-600' : 'text-gray-800'));
        @endphp
        <div class="flex items-center gap-x-3 px-3 py-1 text-xs bg-gray-50 border border-gray-200 rounded-lg whitespace-nowrap">
            <span class="font-semibold text-gray-700" title="Tareas del TO-DO que Claude hace solo desde los PCs">🤖 Claude</span>
            @foreach ($ec['trabajadores'] as $w)
                <span title="{{ $w['nombre'] }}: {{ $w['en_linea'] ? 'en línea' : 'sin señal' }}{{ $w['principal'] ? ' · PC principal' : ' · PC secundario' }}">
                    <span style="display:inline-block;width:.55rem;height:.55rem;border-radius:9999px;background:{{ $w['en_linea'] ? '#16a34a' : '#9ca3af' }}"></span>
                    <span class="{{ $w['principal'] ? 'font-semibold' : '' }}">{{ str_replace(['AlexMiniPC', 'PortalExomen'], ['MiniPC', 'Portal'], $w['nombre']) }}</span>
                </span>
            @endforeach
            @if ($p)
                <span title="Uso real de tu plan de Claude, leído con /usage en {{ $p['pc'] }} hace {{ $p['hace_min'] }} min. Si sube del {{ $ec['freno'] }} % Claude no empieza tareas solo. Hoy Claude ha hecho {{ $ec['hoy'] }} tareas por su cuenta ({{ number_format($ec['coste'], 2) }} $ estimados; red de seguridad: máx. {{ $ec['limite'] }} al día).{{ $ec['en_curso'] ? ' Haciendo ahora: '.implode(' · ', $ec['en_curso']) : '' }}">
                    Sesión <b class="{{ $color($p['sesion']) }}">{{ $p['sesion'] ?? '?' }} %</b><span class="text-gray-500" title="Se reinicia {{ $p['sesion_reinicia'] ?? '?' }}"> (↻ {{ preg_match('/\d{1,2}:\d{2}\s*$/', (string) ($p['sesion_reinicia'] ?? ''), $hm) ? $hm[0] : ($p['sesion_reinicia'] ?? '?') }})</span>
                    · Semana <b class="{{ $color($p['semana']) }}">{{ $p['semana'] ?? '?' }} %</b><span class="text-gray-500" title="Se reinicia {{ $p['semana_reinicia'] ?? '?' }}"> (↻ {{ $p['semana_reinicia'] ?? '?' }})</span>
                    @if ($ec['en_curso']) <span class="text-indigo-600">▶</span> @endif
                </span>
            @else
                <span class="text-gray-400" title="Todavía ningún PC ha leído el uso del plan (lo hace cada pocos minutos).">Uso del plan: sin datos</span>
            @endif
            @if ($ec['pausado'])
                <span class="px-1.5 py-0.5 font-semibold text-red-700 bg-red-100 rounded">EN PAUSA</span>
                <button type="button" wire:click="pausar(false)" class="px-2 py-0.5 text-white bg-green-600 rounded hover:bg-green-700">▶ Reanudar</button>
            @else
                @if ($ec['agotado']) <span class="px-1.5 py-0.5 font-semibold text-amber-800 bg-amber-100 rounded" title="Uso del plan por encima del {{ $ec['freno'] }} %: Claude no empieza tareas nuevas hasta que baje">FRENO</span> @endif
                <button type="button" wire:click="pausar(true)" wire:confirm="¿Pausar TODOS los desarrollos automáticos de Claude? (lo que esté en curso termina; nada nuevo empieza)" class="px-2 py-0.5 text-white bg-red-600 rounded hover:bg-red-700">⏸ Pausar todos</button>
            @endif
        </div>
    @endif
</div>
