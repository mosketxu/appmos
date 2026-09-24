{{-- Panel de control (solo Admin): Usuarios / Roles y permisos --}}
<div class="flex gap-4 px-4 pt-4 text-sm font-medium border-b border-gray-200">
    @foreach (['admin.usuarios' => 'Usuarios y acceso a entidades', 'admin.roles' => 'Roles y permisos'] as $ruta => $titulo)
        <a href="{{ route($ruta) }}"
           class="pb-2 -mb-px border-b-2 {{ (($activa ?? null) === $ruta || request()->routeIs($ruta)) ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $titulo }}
        </a>
    @endforeach
</div>
