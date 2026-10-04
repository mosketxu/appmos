<?php

namespace App\Http\Livewire\Admin;

use App\Models\User;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
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

    /** Columnas de la tabla: los roles o los usuarios (uno por uno). */
    public string $vista = 'roles';

    /** Concede/quita un permiso a un rol. Una pestaña con procesos se lleva todos sus procesos. */
    public function alternar(string $rol, string $permiso): void
    {
        if ($rol === 'Admin') {
            return;
        }
        $role = Role::findByName($rol, 'web');
        $claves = $this->conHijos($permiso);
        $dar = ! $this->tiene($role->permissions->pluck('name')->all(), $permiso);
        foreach ($claves as $c) {
            Accesos::asegurarProceso($c);
            Permission::findOrCreate($c, 'web');
            $role->refresh();
            $dar ? $role->givePermissionTo($c) : $role->revokePermissionTo($c);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Accesos::olvidar();
    }

    /** Lo mismo para un usuario concreto (permiso directo, además de los de su rol). */
    public function alternarUsuario(int $id, string $permiso): void
    {
        $u = User::with('roles', 'permissions')->findOrFail($id);
        if ($u->hasRole('Admin')) {
            return;
        }
        $delRol = $u->getPermissionsViaRoles()->pluck('name')->all();
        if ($this->tiene($delRol, $permiso)) {
            return;   // lo da su rol: se cambia en la vista de roles
        }
        $dar = ! $this->tiene($u->getDirectPermissions()->pluck('name')->all(), $permiso);
        foreach ($this->conHijos($permiso) as $c) {
            Accesos::asegurarProceso($c);
            Permission::findOrCreate($c, 'web');
            if (! in_array($c, $delRol, true)) {
                $dar ? $u->givePermissionTo($c) : $u->revokePermissionTo($c);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Accesos::olvidar();
    }

    /** El permiso y, si es una pestaña con procesos, los de sus procesos. */
    protected function conHijos(string $permiso): array
    {
        return array_merge([$permiso], array_keys(Accesos::procesosDe($permiso)));
    }

    /** ¿Lo tiene quien tiene esta lista de permisos? Un proceso sin permiso propio todavía vale lo que su pestaña. */
    protected function tiene(array $permisos, string $clave): bool
    {
        if (in_array($clave, $permisos, true)) {
            return true;
        }
        $padre = Accesos::padreDeProceso($clave);
        return $padre && ! Accesos::existe($clave) && in_array($padre, $permisos, true);
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
        if (in_array($rol, self::FIJOS, true) || DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se puede borrar {$rol}: es un rol fijo o tiene usuarios.");
            return;
        }
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->dispatch('proceso-terminado', mensaje: "✅ Rol borrado: {$rol}");
    }

    public function render()
    {
        // Nº de usuarios por rol sin withCount('users'): esa relación de Spatie falla si el guard por defecto de la petición no es «web»
        $usuariosPorRol = DB::table('model_has_roles')->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');
        $roles = Role::with('permissions')->orderBy('id')->get()->each(fn ($r) => $r->users_count = (int) ($usuariosPorRol[$r->id] ?? 0));
        $cols = [];
        if ($this->vista === 'usuarios') {
            foreach (User::with('roles', 'permissions')->where('activo', true)->orderBy('name')->get() as $u) {
                $admin = $u->hasRole('Admin');
                $cols[] = ['id' => $u->id, 'nombre' => $u->name, 'sub' => $u->getRoleNames()->first(), 'admin' => $admin,
                    'rol' => $u->getPermissionsViaRoles()->pluck('name')->all(), 'directos' => $u->getDirectPermissions()->pluck('name')->all()];
            }
        } else {
            foreach ($roles as $r) {
                $cols[] = ['id' => $r->name, 'nombre' => $r->name, 'sub' => $r->users_count, 'admin' => $r->name === 'Admin',
                    'rol' => [], 'directos' => $r->permissions->pluck('name')->all(), 'fijo' => in_array($r->name, self::FIJOS, true)];
            }
        }
        $arbol = Accesos::arbol();
        $estado = [];   // [columna][permiso] => 0 no · 1 sí · 2 sí, por su rol (solo vista usuarios)
        foreach ($cols as $k => $c) {
            foreach ($arbol as $items) {
                foreach ($items as $it) {
                    foreach (array_merge([$it['clave'] => 1], array_fill_keys(array_keys($it['hijos']), 1)) as $clave => $_) {
                        $estado[$k][$clave] = $c['admin'] ? 2 : ($this->tiene($c['rol'], $clave) ? 2 : ($this->tiene($c['directos'], $clave) ? 1 : 0));
                    }
                }
            }
        }
        return view('livewire.admin.roles', [
            'roles' => $roles,
            'cols' => $cols,
            'estado' => $estado,
            'arbol' => $arbol,
            'fijos' => self::FIJOS,
        ]);
    }
}
