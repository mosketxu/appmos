<div x-data="{ avisos: [] }"
     x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje }); setTimeout(() => avisos.shift(), 5000)">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="p-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg shadow-lg" x-text="aviso.mensaje"></div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'admin.roles'])
    @include('livewire.admin._subnav', ['activa' => 'admin.roles'])

    <div class="p-4 space-y-4" x-data="{ cerrado: {}, alt(k) { this.cerrado[k] = ! this.cerrado[k] },
            todo(c) { const k = {}; document.querySelectorAll('[data-nodo]').forEach(e => { if (c) k[e.dataset.nodo] = true }); this.cerrado = k } }">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900">Roles y permisos</h1>
            <button type="button" x-on:click="todo(true)" class="px-2 py-1 text-xs bg-white border border-gray-300 rounded">▸ Comprimir todo</button>
            <button type="button" x-on:click="todo(false)" class="px-2 py-1 text-xs bg-white border border-gray-300 rounded">▾ Descomprimir todo</button>
        </div>
        <p class="text-xs text-gray-500">
            Los accesos están ordenados por bloques de la aplicación: cada <b>pestaña</b> y, debajo, sus <b>procesos</b>. Marcar una pestaña marca también todos sus procesos;
            puedes quitar procesos sueltos. Se guarda al hacer clic. <b>Admin</b> tiene siempre todo (y es el único que ve este panel).
            A la izquierda de la barra, los <b>roles</b> (dar un acceso a un rol se lo da a todos sus usuarios); a la derecha, los <b>usuarios</b> uno por uno: sirven para dar un acceso a una persona
            concreta además de los de su rol (en gris: lo da su rol; para quitárselo, quítalo al rol). Los usuarios <b>inactivos</b> (no pueden entrar) salen al final, tachados y en gris claro: conservan su historial de accesos (casilla gris = tenía ese acceso) y no se tocan.
            Sin «Ver TODAS las entidades», el usuario solo ve las suyas; sin «Crear, modificar y borrar», solo consulta.
        </p>

        <div class="overflow-auto bg-white border rounded-lg shadow">
            <table class="text-sm">
                <thead class="text-xs text-gray-600 bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left" style="min-width:17rem">Acceso</th>
                        @foreach ($cols as $c)
                            <th class="px-1 py-1 text-center leading-tight" style="width:3.4rem; min-width:3.4rem; {{ ! empty($c['inicio']) ? 'border-left:2px solid #9ca3af' : '' }}" wire:key="th-{{ $c['tipo'] }}-{{ $c['id'] }}"
                                title="{{ $c['completo'] ?? $c['nombre'] }}">
                                <div class="font-semibold whitespace-nowrap {{ ($c['activo'] ?? true) ? '' : 'text-gray-400 line-through' }}">
                                    {{ $c['nombre'] }}
                                    @if ($c['tipo'] === 'rol' && empty($c['fijo']) && ! $c['admin'])
                                        <button type="button" class="ml-1 text-red-500 hover:text-red-700" title="Borrar rol"
                                                x-on:click="confirm('¿Borrar el rol ' + @js($c['nombre']) + '?') && $wire.borrar(@js($c['nombre']))">&times;</button>
                                    @endif
                                </div>
                                <div class="font-normal whitespace-nowrap {{ ($c['activo'] ?? true) ? 'text-gray-400' : 'text-amber-600' }}">{{ $c['sub'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($arbol as $bloque => $items)
                        @php $kb = 'b:'.$bloque; @endphp
                        <tr class="bg-gray-100 cursor-pointer" data-nodo="{{ $kb }}" x-on:click="alt(@js($kb))" wire:key="b-{{ $bloque }}">
                            <td colspan="{{ count($cols) + 1 }}" class="px-3 py-1.5 text-xs font-bold tracking-wide text-gray-700 uppercase">
                                <span x-text="cerrado[@js($kb)] ? '▸' : '▾'"></span> {{ $bloque }}
                            </td>
                        </tr>
                        @foreach ($items as $it)
                            @php $kp = 'p:'.$it['clave']; @endphp
                            <tr class="border-t" x-show="! cerrado[@js($kb)]" wire:key="p-{{ $it['clave'] }}" data-nodo="{{ $kp }}">
                                <td class="px-3 py-1.5 font-medium" style="min-width:17rem">
                                    @if ($it['hijos'])
                                        <button type="button" class="mr-1 text-gray-500" x-on:click="alt(@js($kp))"><span x-text="cerrado[@js($kp)] ? '▸' : '▾'"></span></button>
                                    @else <span class="inline-block w-4"></span> @endif
                                    {{ $it['texto'] }}
                                    @if ($it['hijos']) <span class="text-xs font-normal text-gray-400">· {{ count($it['hijos']) }} procesos</span> @endif
                                </td>
                                @foreach ($cols as $k => $c)
                                    @include('livewire.admin._casilla', ['clave' => $it['clave'], 'k' => $k, 'c' => $c, 'est' => $estado[$k][$it['clave']] ?? 0, 'vista' => $c['tipo']])
                                @endforeach
                            </tr>
                            @foreach ($it['hijos'] as $hc => $ht)
                                <tr class="border-t border-gray-100 bg-gray-50" x-show="! cerrado[@js($kb)] && ! cerrado[@js($kp)]" wire:key="h-{{ $hc }}">
                                    <td class="py-1 pl-10 pr-3 text-xs text-gray-700" style="min-width:17rem">{{ $ht }}</td>
                                    @foreach ($cols as $k => $c)
                                        @include('livewire.admin._casilla', ['clave' => $hc, 'k' => $k, 'c' => $c, 'est' => $estado[$k][$hc] ?? 0, 'vista' => $c['tipo']])
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs text-gray-600">Nuevo rol</label>
                <input type="text" wire:model="nuevoRol" wire:keydown.enter="crear" placeholder="p.ej. Gestoría"
                       class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-60">
                @error('nuevoRol') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-button.primary wire:click="crear">＋ Crear rol</x-button.primary>
        </div>
    </div>
</div>
