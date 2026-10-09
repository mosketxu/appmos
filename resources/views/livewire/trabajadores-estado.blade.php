<div wire:poll.30s style="display:flex; flex-direction:column; align-items:flex-start; gap:2px; line-height:1.1">
    <div style="display:flex; align-items:center; gap:.3rem">
    @if ($e)
        @php
            $color = $e['enlinea'] === 0 ? '#9ca3af' : ($e['enlinea'] < count($e['pcs']) ? '#f59e0b' : '#16a34a');
            $tip = $e['enlinea'] === 0
                ? 'Ningún PC de trabajo conectado: lo que pidas desde la web esperará a que alguno arranque.'
                : $e['enlinea'].' de '.count($e['pcs']).' PC de trabajo conectados.';
            $tip .= ' '.collect($e['pcs'])->map(fn ($p) => ($p['en_linea'] ? '🟢 ' : '⚪ ').$p['nombre'])->implode(' · ');
            if ($e['activas']) { $tip .= ' — Tareas en cola o en curso: '.$e['activas'].($e['mias'] ? ' (tuyas: '.$e['mias'].')' : ''); }
        @endphp
        <span title="{{ $tip }}" class="inline-flex items-center px-2 text-xs text-gray-600 border border-gray-200 rounded-md gap-x-1 whitespace-nowrap" style="padding-top:.1rem; padding-bottom:.1rem" data-sin-tema>
            <span style="font-size:1rem;line-height:1">🖥</span>
            <span style="display:inline-block;width:.55rem;height:.55rem;border-radius:9999px;background:{{ $color }}"></span>
            <span>{{ $e['enlinea'] }}/{{ count($e['pcs']) }}</span>
            @if ($e['activas'])<span class="text-indigo-600">· ⏳ {{ $e['activas'] }}</span>@endif
        </span>
    @endif
    @if ($e)
        {{-- Selector «Ejecutar en»: el PC desde el que trabajas. Se recuerda en ESTE navegador (cookie appmos_pc, 1 año). Si ese PC no responde, el reparto vuelve a automático.
             Desplegable propio (9-oct-2026): nombre corto (Serv/Mini/Port) y, al abrirlo, dos columnas: corto + completo. --}}
        @php
            $corto = ['miniServ' => 'Serv', 'AlexMiniPC' => 'Mini', 'PortalExomen' => 'Port'];
            $orden = array_flip(array_keys($corto));
            $lista = collect($e['pcs'])->sortBy(fn ($p) => $orden[$p['nombre']] ?? 99)->values()
                ->map(fn ($p) => ['n' => $p['nombre'], 'c' => $corto[$p['nombre']] ?? $p['nombre'], 'on' => $p['en_linea']])->all();
        @endphp
        <span wire:ignore class="relative inline-flex items-center text-xs text-gray-600 whitespace-nowrap" data-sin-tema
              x-data="{ open: false, pcs: @js($lista),
                        pc: (document.cookie.match(/(?:^|; )appmos_pc=([^;]*)/) || [])[1] ? decodeURIComponent(document.cookie.match(/(?:^|; )appmos_pc=([^;]*)/)[1]) : '',
                        elegir(v) { this.pc = v; this.open = false; document.cookie = 'appmos_pc=' + encodeURIComponent(v) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); },
                        actual() { return this.pcs.find(p => p.n === this.pc) },
                        etiqueta() { return this.pc ? (this.actual() ? this.actual().c : this.pc) : 'Auto' } }"
              x-on:click.outside="open = false" x-on:keydown.escape="open = false">
            <button type="button" x-on:click="open = !open" class="inline-flex items-center px-2 text-xs text-gray-600 bg-white border border-gray-300 rounded-md gap-x-1" style="height:1.5rem"
                    title="Ejecutar en: los procesos que lances desde Appmos se ejecutan en este PC (con su Outlook, su OneDrive y sus ventanas). «Auto»: el PC de cada proceso o el que esté libre.">
                <span style="font-size:.85rem;line-height:1">▶</span>
                <span style="display:inline-block;width:.5rem;height:.5rem;border-radius:9999px" x-bind:style="'background:' + (!pc ? '#9ca3af' : (actual() && actual().on ? '#16a34a' : '#9ca3af'))"></span>
                <span x-text="etiqueta()"></span>
                <span style="font-size:.6rem">▾</span>
            </button>
            <div x-show="open" class="absolute right-0 z-50 bg-white border border-gray-200 rounded-md shadow-lg" style="display:none; top:1.7rem; min-width:11rem; font-size:.75rem">
                <button type="button" x-on:click="elegir('')" class="flex items-center w-full px-2 py-1 text-left hover:bg-gray-100" style="gap:.5rem" x-bind:class="!pc ? 'font-semibold' : ''">
                    <span style="width:.5rem;display:inline-block"></span><span style="width:2.2rem">Auto</span><span class="text-gray-500">Automático</span>
                </button>
                <template x-for="p in pcs" x-bind:key="p.n">
                    <button type="button" x-on:click="elegir(p.n)" class="flex items-center w-full px-2 py-1 text-left hover:bg-gray-100" style="gap:.5rem" x-bind:class="pc === p.n ? 'font-semibold' : ''">
                        <span style="display:inline-block;width:.5rem;height:.5rem;border-radius:9999px" x-bind:style="'background:' + (p.on ? '#16a34a' : '#9ca3af')"></span>
                        <span style="width:2.2rem" x-text="p.c"></span><span class="text-gray-500" x-text="p.n"></span>
                    </button>
                </template>
            </div>
        </span>
    @endif
        <x-selector-tema />
    </div>
</div>
