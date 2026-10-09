{{--
    Explorador de carpetas para la web (la web no ve el disco: la lista de carpetas la sube un PC trabajador). Se navega con clics (camino de migas
    para subir), hay buscador en todos los niveles y un aviso 📗 en las carpetas con Excel de IVA. «Elegir esta carpeta» escribe la ruta (relativa a
    OneDrive) en la propiedad Livewire $model. Uso: <x-explorador-carpetas model="carpetaElegida" :dirs="$dirs" :marcas="$marcas" :actual="$carpeta" />
--}}
@props(['model', 'dirs' => [], 'marcas' => [], 'actual' => ''])
<div wire:key="exp-{{ $model }}-{{ count($dirs) }}" x-data="{
        abierto: false, cur: '', q: '', dirs: (() => { const t = new Set(); @js(array_values($dirs)).forEach(d => { const p = d.split('/'); for (let i = 1; i <= p.length; i++) t.add(p.slice(0, i).join('/')) }); return [...t].sort((a, b) => a.localeCompare(b, 'es', { numeric: true })) })(), marcas: new Set(@js(array_values($marcas))), actual: @js((string) $actual),
        norm(t) { return (t || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        padre(d) { const i = d.lastIndexOf('/'); return i < 0 ? '' : d.slice(0, i) },
        nombre(d) { return d.slice(d.lastIndexOf('/') + 1) },
        get hijas() { return this.dirs.filter(d => this.padre(d) === this.cur) },
        get migas() { const p = this.cur ? this.cur.split('/') : []; return p.map((n, i) => ({ n, ruta: p.slice(0, i + 1).join('/') })) },
        get encontradas() { const w = this.norm(this.q).split(/\s+/).filter(Boolean); if (!w.length) return []; return this.dirs.filter(d => { const n = this.norm(d); return w.every(x => n.includes(x)) }).slice(0, 80) },
        abrir() { this.abierto = true; this.q = ''; this.cur = this.actual && this.dirs.includes(this.actual) ? this.padre(this.actual) : '' },
        ir(d) { this.cur = d; this.q = '' },
        elegir(d) { this.abierto = false; this.actual = d; $wire.set('{{ $model }}', d) },
    }" style="display:inline-block">
    <button type="button" class="li-btn" x-on:click="abrir()">📁 {{ $actual ? 'Cambiar carpeta…' : 'Buscar carpeta…' }}</button>
    <div x-show="abierto" x-cloak x-on:keydown.escape.window="abierto = false" style="position:fixed; inset:0; z-index:70; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center" x-on:click.self="abierto = false">
        <div style="background:#fff; border-radius:8px; width:min(760px, 96vw); max-height:86vh; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(0,0,0,.3)">
            <div style="padding:12px 14px; border-bottom:1px solid #e5e7eb; display:flex; gap:10px; align-items:center">
                <b style="white-space:nowrap">📁 Elegir carpeta</b>
                <input type="text" x-model="q" placeholder="Buscar en todas las carpetas… (p. ej. aldribo iva)" class="border-gray-300 rounded-md text-sm" style="flex:1" x-ref="buscar" x-init="$watch('abierto', v => v && $nextTick(() => $refs.buscar.focus()))">
                <button type="button" x-on:click="abierto = false" style="color:#6b7280">✕</button>
            </div>
            <div x-show="!q" style="padding:8px 14px; font-size:13px; border-bottom:1px solid #f1f5f9">
                <a href="#" x-on:click.prevent="ir('')" style="color:#4338ca">OneDrive</a>
                <template x-for="m in migas" :key="m.ruta"><span> › <a href="#" x-on:click.prevent="ir(m.ruta)" x-text="m.n" style="color:#4338ca"></a></span></template>
            </div>
            <div style="overflow:auto; flex:1; min-height:12rem">
                <template x-if="!q">
                    <div>
                        <template x-for="d in hijas" :key="d">
                            <div class="text-sm" style="display:flex; align-items:center; gap:8px; padding:5px 14px; border-bottom:1px solid #f8fafc">
                                <a href="#" x-on:click.prevent="ir(d)" style="flex:1; color:#111827"><span>📁 </span><span x-text="nombre(d)"></span><span x-show="marcas.has(d)" title="Contiene Excel de IVA"> 📗</span></a>
                                <button type="button" x-on:click="elegir(d)" class="li-btn" style="padding:0 8px; font-size:12px">Elegir</button>
                            </div>
                        </template>
                        <div x-show="!hijas.length" class="text-sm" style="padding:10px 14px; color:#6b7280">Sin subcarpetas.</div>
                    </div>
                </template>
                <template x-if="q">
                    <div>
                        <template x-for="d in encontradas" :key="d">
                            <div class="text-sm" style="display:flex; align-items:center; gap:8px; padding:5px 14px; border-bottom:1px solid #f8fafc">
                                <a href="#" x-on:click.prevent="ir(d)" style="flex:1; color:#111827; word-break:break-all"><span>📁 </span><span x-text="d.replaceAll('/', ' › ')"></span><span x-show="marcas.has(d)" title="Contiene Excel de IVA"> 📗</span></a>
                                <button type="button" x-on:click="elegir(d)" class="li-btn" style="padding:0 8px; font-size:12px">Elegir</button>
                            </div>
                        </template>
                        <div x-show="!encontradas.length" class="text-sm" style="padding:10px 14px; color:#6b7280">Ninguna carpeta con ese texto.</div>
                    </div>
                </template>
            </div>
            <div style="padding:8px 14px; border-top:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12px; color:#6b7280">
                <span>📗 = tiene Excel de IVA (T3 …, 07 …, IVA T3 …). Clic en una carpeta para entrar; «Elegir» para quedarte con ella.</span>
                <button type="button" x-show="cur && !q" x-on:click="elegir(cur)" class="li-btn pri" style="white-space:nowrap">Elegir la carpeta actual</button>
            </div>
        </div>
    </div>
</div>
