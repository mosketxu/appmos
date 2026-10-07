{{-- Tema: Automático (el del sistema, por defecto) / Claro / Oscuro. Se recuerda en este navegador. --}}
<div wire:ignore class="relative" x-data="{ abierto: false, t: window.appmosTema ? appmosTema.get() : 'auto' }" x-on:click.outside="abierto = false">
    <button type="button" x-on:click="abierto = !abierto" title="Tema de colores: automático, claro u oscuro"
            class="px-2 py-1 text-lg leading-none text-gray-500 rounded-md hover:bg-gray-100" data-sin-tema
            x-text="t === 'oscuro' ? '🌙' : (t === 'claro' ? '☀️' : '🌓')"></button>
    <div x-show="abierto" x-cloak style="display:none;min-width:11rem;z-index:60" class="absolute right-0 py-1 mt-2 text-sm bg-white border border-gray-200 rounded-md shadow-lg">
        <template x-for="o in [['auto','🌓 Automático (el del sistema)'],['claro','☀️ Claro'],['oscuro','🌙 Oscuro']]" :key="o[0]">
            <button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-gray-100" :class="t === o[0] ? 'font-semibold text-indigo-700' : 'text-gray-700'"
                    x-on:click="t = o[0]; appmosTema.set(o[0]); abierto = false" x-text="o[1]"></button>
        </template>
    </div>
</div>
