{{-- Selector de cliente con búsqueda: se escribe y se filtra (Facturas OCR y Bancos). Cambia $cliente; las entidades sin carpeta llevan «e:<id>». --}}
<div class="relative inline-block font-normal" style="min-width:20rem"
     x-data="{ abierto: false, q: '', sel: -1, ops: @js(collect($clientes)->map(fn ($t, $v) => ['v' => (string) $v, 't' => $t])->values()), actual: @js((string) $cliente),
        get lista() { const n = this.norm(this.q); return this.ops.filter(o => this.norm(o.t).includes(n)).slice(0, 80) },
        get etiqueta() { const o = this.ops.find(o => o.v === this.actual); return o ? o.t : '— elige una entidad —' },
        norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        elegir(o) { this.abierto = false; this.q = ''; if (o.v !== this.actual) { this.actual = o.v; $wire.set('cliente', o.v) } } }"
     x-on:click.outside="abierto = false; q = ''" x-on:keydown.escape="abierto = false; q = ''">
    <input type="text" class="w-full text-base border-gray-300 rounded-md shadow-sm" autocomplete="off"
           :placeholder="etiqueta" :value="abierto ? q : etiqueta"
           x-on:focus="abierto = true; q = ''; sel = -1; $event.target.select()"
           x-on:input="q = $event.target.value; abierto = true; sel = 0"
           x-on:keydown.arrow-down.prevent="sel = Math.min(sel + 1, lista.length - 1)"
           x-on:keydown.arrow-up.prevent="sel = Math.max(sel - 1, 0)"
           x-on:keydown.enter.prevent="lista[Math.max(sel, 0)] && elegir(lista[Math.max(sel, 0)])">
    <div x-show="abierto" x-cloak class="absolute z-40 w-full mt-1 overflow-auto bg-white border border-gray-300 rounded-md shadow-lg" style="max-height:20rem">
        <template x-for="(o, i) in lista" :key="o.v">
            <div class="px-3 py-1.5 text-sm cursor-pointer" :class="i === sel ? 'bg-indigo-100' : 'hover:bg-gray-100'"
                 x-on:mousedown.prevent="elegir(o)" x-text="o.t"></div>
        </template>
        <div x-show="lista.length === 0" class="px-3 py-2 text-sm text-gray-500">Ninguna entidad coincide</div>
    </div>
</div>
