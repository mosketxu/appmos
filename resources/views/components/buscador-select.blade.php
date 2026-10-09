{{--
    Desplegable con buscador: se escribe y la lista se va filtrando (sin acentos, por palabras en cualquier orden). Es el modelo por defecto
    cuando hay muchas opciones (decisión de Alex, 10-oct-2026). Uso:
        <x-buscador-select model="entidadId" :valor="$entidadId" :opciones="$empresas" ancho="22rem" />
    $opciones = [id => texto]. Al elegir, se escribe el id en la propiedad Livewire $model (con petición inmediata, como wire:model.live).
--}}
@props(['model', 'valor' => '', 'opciones' => [], 'ancho' => '22rem', 'vacio' => 'Sin resultados'])
@php $lista = collect($opciones)->map(fn ($n, $id) => ['id' => (string) $id, 'n' => (string) $n])->values(); @endphp
<div wire:key="bs-{{ $model }}-{{ md5($lista->toJson()) }}-{{ $valor }}" style="position:relative; display:inline-block; width:{{ $ancho }}; max-width:100%"
    x-data="{
        abierto: false, q: '', i: 0, ops: @js($lista), sel: @js((string) $valor),
        norm(t) { return (t || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        get filtradas() { const p = this.norm(this.q).split(/\s+/).filter(Boolean); return this.ops.filter(o => p.every(w => this.norm(o.n).includes(w))) },
        get actual() { const o = this.ops.find(o => o.id === this.sel); return o ? o.n : '' },
        abrir() { this.abierto = true; this.q = ''; this.i = Math.max(0, this.filtradas.findIndex(o => o.id === this.sel)); this.$nextTick(() => this.$refs.q.select()) },
        elegir(o) { if (!o) return; this.abierto = false; this.q = ''; if (o.id !== this.sel) { this.sel = o.id; $wire.set('{{ $model }}', o.id) } },
        mover(d) { const n = this.filtradas.length; if (n) { this.i = (this.i + d + n) % n; this.$nextTick(() => this.$refs.lista?.children[this.i]?.scrollIntoView({ block: 'nearest' })) } },
    }"
    x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
    <input type="text" x-ref="q" autocomplete="off" placeholder="Escribe para buscar…" class="border-gray-300 rounded-md text-sm" style="width:100%; padding-right:1.75rem"
        :value="abierto ? q : actual" x-on:focus="abrir()" x-on:click="if (!abierto) abrir()"
        x-on:input="q = $event.target.value; i = 0; abierto = true"
        x-on:keydown.arrow-down.prevent="abierto ? mover(1) : abrir()" x-on:keydown.arrow-up.prevent="mover(-1)"
        x-on:keydown.enter.prevent="elegir(filtradas[i])" x-on:keydown.tab="abierto = false">
    <span style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%); pointer-events:none; color:#6b7280; font-size:.7rem">▼</span>
    <div x-show="abierto" x-cloak style="position:absolute; z-index:50; left:0; right:0; margin-top:2px; max-height:18rem; overflow:auto; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 8px 20px rgba(0,0,0,.15)">
        <div x-ref="lista">
            <template x-for="(o, k) in filtradas" :key="o.id">
                <div x-on:click="elegir(o)" x-on:mouseenter="i = k" x-text="o.n" class="text-sm"
                    :style="'padding:5px 10px; cursor:pointer;' + (k === i ? 'background:#eef2ff;' : '') + (o.id === sel ? 'font-weight:700;' : '')"></div>
            </template>
        </div>
        <div x-show="!filtradas.length" class="text-sm" style="padding:6px 10px; color:#6b7280">{{ $vacio }}</div>
    </div>
</div>
