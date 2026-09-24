<?php

namespace App\Http\Livewire\Admin;

use App\Support\Accesos;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Panel de control (solo Admin): qué acciones da cada rol. Admin lo tiene todo
 * siempre (no se edita). Se pueden crear roles nuevos y borrar los que no sean
 * Admin / Suma / Usuario y no tengan usuarios.
 */
class Roles extends Component
{
    public string $nuevoRol = '';

    protected const FIJOS = ['Admin', 'Suma', 'Usuario'];

    public function alternar(string $rol, string $permiso): void
    {
        if ($rol === 'Admin') {
            return;
        }
        $role = Role::findByName($rol, 'web');
        Permission::findOrCreate($permiso, 'web');
        $role->hasPermissionTo($permiso) ? $role->revokePermissionTo($permiso) : $role->givePermissionTo($permiso);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Accesos::olvidar();
    }

    public function crear(): void
    {
        $this->validate(['nuevoRol' => 'required|string|max:50|unique:roles,name'], [
            'nuevoRol.unique' => 'Ya existe un rol con ese nombre.',
        ]);
        Role::create(['name' => trim($this->nuevoRol), 'guard_name' => 'web']);
        $this->dispatch('proceso-terminado', mensaje: '✅ Rol creado: '.$this->nuevoRol.' (sin acciones: márcalas en la tabla)');
        $this->nuevoRol = '';
    }

    public function borrar(string $rol): void
    {
        $role = Role::findByName($rol, 'web');
        if (in_array($rol, self::FIJOS, true) || $role->users()->exists()) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se puede borrar {$rol}: es un rol fijo o tiene usuarios.");
            return;
        }
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->dispatch('proceso-terminado', mensaje: "✅ Rol borrado: {$rol}");
    }

    public function render()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        return view('livewire.admin.roles', [
            'roles' => $roles,
            'gruposPermisos' => config('accesos.permisos'),
            'fijos' => self::FIJOS,
        ]);
    }
}
