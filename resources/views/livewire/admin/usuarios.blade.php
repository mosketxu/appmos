<div x-data="{ avisos: [] }"
     x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje }); setTimeout(() => avisos.shift(), 5000)">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="p-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg shadow-lg" x-text="aviso.mensaje"></div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'admin.usuarios'])
    @include('livewire.admin._subnav', ['activa' => 'admin.usuarios'])

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">

        {{-- Lista de usuarios --}}
        <div class="space-y-3" style="flex:45 1 0;min-width:0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Usuarios</h1>
                <input type="search" wire:model.live.debounce.300ms="filtroUsuarios" placeholder="Buscar…"
                       class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-48">
                <x-button.primary wire:click="nuevo">＋ Nuevo usuario</x-button.primary>
            </div>
            <p class="text-xs text-gray-500">
                <b>Admin</b>: todo y este panel. <b>Suma</b>: todas las entidades y las acciones de su rol, sin panel.
                <b>Usuario</b>: solo sus entidades, en consulta, y las acciones que se le den.
                Un usuario sin correo no puede entrar hasta que se le ponga correo y contraseña.
            </p>
            <div class="overflow-hidden bg-white border rounded-lg shadow">
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-left text-gray-600 bg-gray-100">
                        <tr>
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2">Correo</th>
                            <th class="px-3 py-2">Rol</th>
                            <th class="px-3 py-2">Responsable Suma</th>
                            <th class="px-3 py-2 text-right">Entidades</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $u)
                            <tr wire:key="u-{{ $u->id }}" wire:click="editar({{ $u->id }})"
                                class="border-t cursor-pointer hover:bg-indigo-50 {{ $editando === $u->id ? 'bg-indigo-100' : '' }} {{ $u->activo ? '' : 'text-gray-400 line-through' }}">
                                <td class="px-3 py-2 font-medium">{{ $u->name }}</td>
                                <td class="px-3 py-2">{!! $u->email ? e($u->email) : '<span class="text-amber-600">sin correo</span>' !!}</td>
                                <td class="px-3 py-2">
                                    @php $r = $u->getRoleNames()->first(); @endphp
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $r === 'Admin' ? 'bg-red-100 text-red-800' : ($r === 'Suma' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700') }}">{{ $r ?? '—' }}</span>
                                </td>
                                <td class="px-3 py-2">{{ $u->suma?->nombre }}</td>
                                <td class="px-3 py-2 text-right">{{ $vistas[$u->id] === null ? 'todas · gestiona '.$gestiona[$u->id] : $vistas[$u->id] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Ficha del usuario --}}
        <div class="w-full" style="flex:55 1 0;min-width:0">
            @if ($editando !== null)
                <div class="p-4 space-y-4 bg-white border rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">{{ $editando ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                        <button type="button" wire:click="cancelar" class="text-sm text-gray-500 hover:underline">Cerrar</button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-xs text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                            @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Correo (para entrar; vacío = aún no puede entrar)</label>
                            <input type="email" wire:model="email" class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                            @error('email') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Rol</label>
                            <select wire:model.live="rol" class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                                @foreach ($roles as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                            @error('rol') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Nueva contraseña {{ $editando ? '(vacío = no cambiar)' : '' }}</label>
                            <input type="text" wire:model="password" autocomplete="new-password" class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                            @error('password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Es el Responsable Suma…</label>
                            <select wire:model.live="sumaId" class="w-full py-1 text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($sumas as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}{{ $s->user_id && $s->user_id !== $editando ? ' (ya enlazado con otro usuario)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="activo" class="border-gray-300 rounded"> Activo (puede entrar)
                            </label>
                        </div>
                    </div>

                    @if ($rol !== 'Admin')
                        <details open class="border rounded-md">
                            <summary class="px-3 py-2 text-sm font-semibold text-gray-700 cursor-pointer select-none bg-gray-50">
                                Acceso a pestañas y procesos
                                <span class="text-xs font-normal text-gray-400">({{ count($permisosExtra) }} dados a este usuario además de su rol)</span>
                            </summary>
                            <div class="p-3 space-y-3">
                            <p class="text-xs text-gray-500">En gris: lo da su rol (se cambia en «Roles y permisos»). Lo demás se da solo a este usuario. Marcar una pestaña marca todos sus procesos; abre su flecha para quitar alguno suelto.</p>
                            @foreach ($arbol as $bloque => $items)
                                <details open class="border border-gray-200 rounded">
                                    <summary class="px-2 py-1 text-xs font-bold tracking-wide text-gray-600 uppercase cursor-pointer select-none bg-gray-100">{{ $bloque }}</summary>
                                    <div class="p-2 space-y-1">
                                        @foreach ($items as $it)
                                            @php
                                                $tiene = fn ($clave) => in_array($clave, $permisosDelRol, true)
                                                    || (($pd = \App\Support\Accesos::padreDeProceso($clave)) && ! \App\Support\Accesos::existe($clave) && in_array($pd, $permisosDelRol, true));
                                                $delRol = $tiene($it['clave']);
                                                $marcada = $delRol || in_array($it['clave'], $permisosExtra, true);
                                            @endphp
                                            <div wire:key="pe-{{ $it['clave'] }}">
                                                <div class="flex items-center gap-2 text-sm {{ $delRol ? 'text-gray-400' : '' }}">
                                                    <input type="checkbox" @checked($marcada) @disabled($delRol) class="border-gray-300 rounded"
                                                           wire:click="marcarPestana(@js($it['clave']), {{ $marcada ? 'false' : 'true' }})">
                                                    <span class="font-medium">{{ $it['texto'] }}</span>
                                                    @if ($it['hijos']) <span class="text-xs text-gray-400">· {{ count($it['hijos']) }} procesos</span> @endif
                                                </div>
                                                @if ($it['hijos'])
                                                    <details class="ml-6">
                                                        <summary class="text-xs text-gray-500 cursor-pointer select-none">procesos ({{ collect($it['hijos'])->keys()->filter(fn ($h) => $tiene($h) || in_array($h, $permisosExtra, true))->count() }}/{{ count($it['hijos']) }})</summary>
                                                        <div class="grid gap-x-4 md:grid-cols-2">
                                                            @foreach ($it['hijos'] as $hc => $ht)
                                                                @php $hRol = $tiene($hc); @endphp
                                                                <label class="flex items-start gap-2 text-xs {{ $hRol ? 'text-gray-400' : '' }}" wire:key="ph-{{ $hc }}">
                                                                    @if ($hRol)
                                                                        <input type="checkbox" checked disabled class="mt-0.5 border-gray-300 rounded">
                                                                    @else
                                                                        <input type="checkbox" wire:model="permisosExtra" value="{{ $hc }}" class="mt-0.5 border-gray-300 rounded">
                                                                    @endif
                                                                    <span>{{ $ht }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </details>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                            </div>
                        </details>

                    @else
                        <p class="text-sm text-red-700">Admin: acceso a todo, sin excepciones.</p>
                    @endif

                        <details open class="border rounded-md">
                            <summary class="px-3 py-2 text-sm font-semibold text-gray-700 cursor-pointer select-none bg-gray-50">
                                Acceso a entidades
                                <span class="text-xs font-normal text-gray-400">({{ count($porResponsable) }} como responsable + {{ count($entidadesAsignadas) }} marcadas)</span>
                            </summary>
                            <div class="p-3">
                            {{-- Admin y quien tiene "Ver TODAS" ven todas en Entidades, pero las que gestiona (Responsable Suma +
                                 marcadas aquí) son las que le salen en Proc.Mensuales y en «Mis empresas» (pedido 1-oct-2026) --}}
                            @if ($rol === 'Admin' || in_array('entidades.todas', $permisosDelRol, true) || in_array('entidades.todas', $permisosExtra, true))
                                <p class="mb-2 text-xs text-indigo-700">
                                    En Entidades ve todas. Las que gestiona son las {{ count($porResponsable) }} de las que es Responsable Suma
                                    y las que marques aquí ({{ count($entidadesAsignadas) }}): solo esas le salen en Proc.Mensuales y en «Mis empresas».
                                </p>
                            @else
                                <p class="mb-2 text-xs text-gray-500">
                                    Ve (y gestiona) las {{ count($porResponsable) }} entidades de las que es Responsable Suma
                                    @if (count($porResponsable)) <span class="text-gray-400">({{ \Illuminate\Support\Str::limit(implode(', ', $porResponsable), 160) }})</span> @endif
                                    y además las que marques aquí ({{ count($entidadesAsignadas) }}).
                                </p>
                            @endif
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <input type="search" wire:model.live.debounce.300ms="buscarEntidad" placeholder="Buscar entidad…"
                                           class="py-1 text-sm border-gray-300 rounded-md shadow-sm w-60">
                                    <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" wire:model.live="soloMarcadas" class="border-gray-300 rounded"> solo las marcadas</label>
                                </div>
                                <p class="mb-1 text-xs text-gray-400">
                                    Solo salen las entidades activas (una de baja ya marcada sigue saliendo, en gris, para poder quitarla). Casilla marcada y deshabilitada: ya la ve por ser su Responsable Suma (se cambia en la entidad).
                                </p>
                                <div class="overflow-auto border rounded-md" style="max-height:18rem">
                                    @forelse ($entidades as $e)
                                        <label wire:key="e-{{ $e->id }}" class="flex items-center gap-2 px-2 py-1 text-xs border-b hover:bg-gray-50 {{ $e->estado == 0 ? 'text-gray-400' : '' }}">
                                            @if (array_key_exists($e->id, $porResponsable))
                                                <input type="checkbox" checked disabled class="border-gray-300 rounded" title="Es su Responsable Suma">
                                            @else
                                                <input type="checkbox" wire:model="entidadesAsignadas" value="{{ $e->id }}" class="border-gray-300 rounded">
                                            @endif
                                            <span>{{ $e->entidad }}</span>
                                            @if ($e->alias) <span class="text-gray-400">({{ $e->alias }})</span> @endif
                                            @if (array_key_exists($e->id, $porResponsable)) <span class="text-indigo-600">· responsable</span> @endif
                                            @if ($e->estado == 0) <span class="text-red-400">· baja</span> @endif
                                        </label>
                                    @empty
                                        <p class="p-2 text-xs text-gray-400">Sin resultados.</p>
                                    @endforelse
                                </div>
                            </div>
                        </details>

                    <div class="flex items-center gap-3">
                        <x-button.primary wire:click="guardar">Guardar</x-button.primary>
                        <span wire:loading wire:target="guardar" class="text-sm text-amber-700">⏳ Guardando…</span>
                    </div>
                </div>
            @else
                <div class="p-6 text-sm text-gray-500 bg-white border rounded-lg shadow">Elige un usuario de la lista o crea uno nuevo.</div>
            @endif
        </div>
    </div>
    </div>
</div>
