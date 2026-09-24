<div x-data="{ avisos: [] }"
     x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje }); setTimeout(() => avisos.shift(), 5000)">
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="p-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg shadow-lg" x-text="aviso.mensaje"></div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'admin.roles'])
    @include('livewire.admin._subnav', ['activa' => 'admin.roles'])

    <div class="p-4 space-y-4">
        <h1 class="text-2xl font-semibold text-gray-900">Roles y permisos</h1>
        <p class="text-xs text-gray-500">
            Marca qué acciones da cada rol; se guarda al hacer clic. <b>Admin</b> tiene siempre todo (y es el único que ve este panel).
            A un usuario concreto se le pueden dar acciones de más desde su ficha en "Usuarios".
            Sin "Ver TODAS las entidades", el usuario solo ve las suyas; sin "Crear, modificar y borrar", solo consulta.
        </p>

        <div class="overflow-auto bg-white border rounded-lg shadow">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-600 bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Acción</th>
                        @foreach ($roles as $r)
                            <th class="px-3 py-2 text-center whitespace-nowrap">
                                {{ $r->name }} <span class="font-normal text-gray-400">({{ $r->users_count }})</span>
                                @unless (in_array($r->name, $fijos, true))
                                    <button type="button" class="ml-1 text-red-500 hover:text-red-700" title="Borrar rol"
                                            x-on:click="confirm('¿Borrar el rol ' + @js($r->name) + '?') && $wire.borrar(@js($r->name))">&times;</button>
                                @endunless
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gruposPermisos as $grupo => $permisos)
                        <tr class="bg-gray-50"><td colspan="{{ count($roles) + 1 }}" class="px-3 py-1 text-xs font-semibold text-gray-600">{{ $grupo }}</td></tr>
                        @foreach ($permisos as $clave => $texto)
                            <tr class="border-t" wire:key="p-{{ $clave }}">
                                <td class="px-3 py-1.5">{{ $texto }} <span class="text-xs text-gray-400">{{ $clave }}</span></td>
                                @foreach ($roles as $r)
                                    @php $tiene = $r->name === 'Admin' || $r->permissions->contains('name', $clave); @endphp
                                    <td class="px-3 py-1.5 text-center">
                                        <input type="checkbox" @checked($tiene) @disabled($r->name === 'Admin')
                                               wire:click="alternar(@js($r->name), @js($clave))"
                                               class="border-gray-300 rounded {{ $r->name === 'Admin' ? 'opacity-50' : 'cursor-pointer' }}">
                                    </td>
                                @endforeach
                            </tr>
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
