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
        {{-- Selector «Ejecutar en»: el PC desde el que trabajas. Se recuerda en ESTE navegador (cookie appmos_pc, 1 año). Si ese PC no responde, el reparto vuelve a automático --}}
        <span wire:ignore class="inline-flex items-center ml-1 text-xs text-gray-600 gap-x-1 whitespace-nowrap" data-sin-tema
              x-data="{ pc: (document.cookie.match(/(?:^|; )appmos_pc=([^;]*)/) || [])[1] ? decodeURIComponent(document.cookie.match(/(?:^|; )appmos_pc=([^;]*)/)[1]) : '',
                        guardar() { document.cookie = 'appmos_pc=' + encodeURIComponent(this.pc) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); } }">
            <span title="Los procesos que lances desde Appmos se ejecutan en este PC (con su Outlook, su OneDrive y sus ventanas). «Automático»: el PC de cada proceso o el que esté libre.">Ejecutar en</span>
            <select x-model="pc" x-on:change="guardar()" class="py-0 pl-1 pr-6 text-xs border-gray-300 rounded-md" style="height:1.7rem">
                <option value="">Automático</option>
                @foreach ($e['pcs'] as $p)
                    <option value="{{ $p['nombre'] }}">{{ $p['en_linea'] ? '🟢' : '⚪' }} {{ $p['nombre'] }}</option>
                @endforeach
            </select>
        </span>
    @endif
</div>
