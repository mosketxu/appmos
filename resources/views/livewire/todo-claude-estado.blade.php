<div wire:poll.30s>
    @if ($ec)
        @php
            $p = $ec['plan'];
            $corto = ['miniServ' => 'Serv', 'AlexMiniPC' => 'Mini', 'PortalExomen' => 'Port'];
            $orden = array_flip(['miniServ', 'AlexMiniPC', 'PortalExomen']);
            $pcs = collect($ec['trabajadores'])->sortBy(fn ($w) => $orden[$w['nombre']] ?? 99)->values();
            $hoverClaude = 'Tareas del TO-DO que Claude hace solo desde los PCs.';
            if ($p) {
                $hoverClaude .= ' Uso real de tu plan de Claude, leído con /usage en '.$p['pc'].' hace '.$p['hace_min'].' min. Si sube del '.$ec['freno'].' % Claude no empieza tareas solo.'
                    .' Hoy Claude ha hecho '.$ec['hoy'].' tareas por su cuenta ('.number_format($ec['coste'], 2).' $ estimados; red de seguridad: máx. '.$ec['limite'].' al día).'
                    .' La sesión se reinicia '.($p['sesion_reinicia'] ?? '?').' y la semana '.($p['semana_reinicia'] ?? '?').'.'
                    .($ec['en_curso'] ? ' Haciendo ahora: '.implode(' · ', $ec['en_curso']) : '');
            } else {
                $hoverClaude .= ' Todavía ningún PC ha leído el uso del plan (lo hace cada pocos minutos).';
            }
            $color = fn ($v) => $v === null ? 'text-gray-500' : ($v >= $ec['freno'] ? 'text-red-600' : ($v >= 60 ? 'text-amber-600' : 'text-gray-800'));
        @endphp
        {{-- Compacto (4-oct-2026): dos líneas sin crecer en vertical: arriba Claude y los PCs, debajo el uso del plan; a la derecha el botón de pausa --}}
        <div class="flex items-center px-2 text-xs bg-gray-50 border border-gray-200 rounded-lg whitespace-nowrap" style="gap:.5rem; padding-top:.12rem; padding-bottom:.12rem; line-height:1.15">
            <div style="display:flex; flex-direction:column; gap:1px">
                <div class="flex items-center" style="gap:.6rem">
            <span class="font-semibold text-gray-700" title="{{ $hoverClaude }}">🤖 Claude</span>
            @foreach ($pcs as $w)
                <span title="{{ $w['nombre'] }}: {{ $w['en_linea'] ? 'en línea' : 'sin señal' }}{{ $w['principal'] ? ' · PC principal' : ' · PC secundario' }}">
                    <span style="display:inline-block;width:.55rem;height:.55rem;border-radius:9999px;background:{{ $w['en_linea'] ? '#16a34a' : '#9ca3af' }}"></span>
                    <span class="{{ $w['principal'] ? 'font-semibold' : '' }}">{{ $corto[$w['nombre']] ?? $w['nombre'] }}</span>
                </span>
            @endforeach
                </div>
                <div>
            @if ($p)
                <span>
                    <span title="% Uso de sesión">Ss</span> <b class="{{ $color($p['sesion']) }}">{{ $p['sesion'] ?? '?' }} %</b><span class="text-gray-500"> (↻ {{ preg_match('/\d{1,2}:\d{2}\s*$/', (string) ($p['sesion_reinicia'] ?? ''), $hm) ? $hm[0] : ($p['sesion_reinicia'] ?? '?') }})</span>
                    · <span title="% Uso semana">Sm</span> <b class="{{ $color($p['semana']) }}">{{ $p['semana'] ?? '?' }} %</b><span class="text-gray-500"> (↻ {{ $p['semana_reinicia'] ?? '?' }})</span>
                    @if ($ec['en_curso']) <span class="text-indigo-600">▶</span> @endif
                </span>
            @else
                <span class="text-gray-400">Uso del plan: sin datos</span>
            @endif
                </div>
            </div>
            <div class="flex items-center" style="gap:.3rem">
            @if ($ec['pausado'])
                <span class="px-1.5 py-0.5 font-semibold text-red-700 bg-red-100 rounded">EN PAUSA</span>
                <button type="button" wire:click="pausar(false)" class="px-2 py-0.5 text-white bg-green-600 rounded hover:bg-green-700">▶ Reanudar</button>
            @else
                @if ($ec['agotado']) <span class="px-1.5 py-0.5 font-semibold text-amber-800 bg-amber-100 rounded" title="Uso del plan por encima del {{ $ec['freno'] }} %: Claude no empieza tareas nuevas hasta que baje">FRENO</span> @endif
                <button type="button" wire:click="pausar(true)" wire:confirm="¿Pausar TODOS los desarrollos automáticos de Claude? (lo que esté en curso termina; nada nuevo empieza)" title="Pausar todos los desarrollos automáticos de Claude: lo que esté en curso termina y no empieza nada nuevo" aria-label="Pausar todos" class="inline-flex items-center justify-center text-white bg-red-600 rounded hover:bg-red-700" style="width:1.6rem;height:1.6rem"><svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><rect x="1" y="1" width="10" height="10" rx="1.5"/></svg></button>
            @endif
            </div>
        </div>
    @endif
</div>
