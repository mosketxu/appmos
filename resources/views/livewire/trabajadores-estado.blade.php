<div wire:poll.30s>
    @if ($e)
        @php
            $color = $e['enlinea'] === 0 ? '#9ca3af' : ($e['enlinea'] < count($e['pcs']) ? '#f59e0b' : '#16a34a');
            $tip = $e['enlinea'] === 0
                ? 'Ningún PC de trabajo conectado: lo que pidas desde la web esperará a que alguno arranque.'
                : $e['enlinea'].' de '.count($e['pcs']).' PC de trabajo conectados.';
            $tip .= ' '.collect($e['pcs'])->map(fn ($p) => ($p['en_linea'] ? '🟢 ' : '⚪ ').$p['nombre'])->implode(' · ');
            if ($e['activas']) { $tip .= ' — Tareas en cola o en curso: '.$e['activas'].($e['mias'] ? ' (tuyas: '.$e['mias'].')' : ''); }
        @endphp
        <span title="{{ $tip }}" class="inline-flex items-center px-2 py-1 text-xs text-gray-600 border border-gray-200 rounded-md gap-x-1 whitespace-nowrap" data-sin-tema>
            <span style="font-size:1rem;line-height:1">🖥</span>
            <span style="display:inline-block;width:.55rem;height:.55rem;border-radius:9999px;background:{{ $color }}"></span>
            <span>{{ $e['enlinea'] }}/{{ count($e['pcs']) }}</span>
            @if ($e['activas'])<span class="text-indigo-600">· ⏳ {{ $e['activas'] }}</span>@endif
        </span>
    @endif
</div>
