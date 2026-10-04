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

    /** Concede/quita un permiso a un rol. Una pestaña con procesos se lleva todos sus procesos. */
    public function alternar(string $rol, string $permiso): void
    {
        if ($rol === 'Admin') {
            return;
        }
        $role = Role::findByName($rol, 'web');
        $this->aplicarRol($role, $this->conHijos($permiso), ! $this->tiene($role->permissions->pluck('name')->all(), $permiso));
    }

    protected function aplicarRol(Role $role, array $claves, bool $dar): void
    {
        foreach ($claves as $c) {
            Accesos::asegurarProceso($c);
            Permission::findOrCreate($c, 'web');
            $role->refresh();
            $dar ? $role->givePermissionTo($c) : $role->revokePermissionTo($c);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Accesos::olvidar();
    }

    /** Todos los accesos de la tabla: cada pestaña y cada uno de sus procesos. */
    protected function todasClaves(): array
    {
        $out = [];
        foreach (Accesos::arbol() as $items) {
            foreach ($items as $it) {
                $out = array_merge($out, [$it['clave']], array_keys($it['hijos']));
            }
        }

        return $out;
    }

    /** Check bajo el nombre del rol: marca todo o, si ya lo tiene todo, lo quita todo. */
    public function marcarColumnaRol(string $rol): void
    {
        if ($rol === 'Admin') {
            return;
        }
        $role = Role::findByName($rol, 'web');
        $tiene = $role->permissions->pluck('name')->all();
        $todo = collect($this->todasClaves())->every(fn ($c) => $this->tiene($tiene, $c));
        $this->aplicarRol($role, $this->todasClaves(), ! $todo);
    }

    /** Check bajo el nombre del usuario: marca todo (lo que no da su rol, como permiso directo; lo denegado, se vuelve a dar) o lo quita todo. */
    public function marcarColumnaUsuario(int $id): void
    {
        $u = User::findOrFail($id);
        if ($u->hasRole('Admin') || ! $u->activo) {
            return;
        }
        $claves = $this->todasClaves();
        $this->asegurarTodo($claves);
        $u = User::with('roles', 'permissions')->findOrFail($id);
        $delRol = $u->getPermissionsViaRoles()->pluck('name')->all();
        $directos = $u->getDirectPermissions()->pluck('name')->all();
        $denegados = DB::table('permisos_denegados')->where('user_id', $id)->pluck('permiso')->all();
        $todo = collect($claves)->every(fn ($c) => ($this->tiene($delRol, $c) && ! in_array($c, $denegados, true)) || $this->tiene($directos, $c));
        $this->aplicarUsuario($u, $claves, ! $todo);
    }

    protected function asegurarTodo(array $claves): void
    {
        foreach ($claves as $c) {   // primero existen todos los permisos (los de proceso se crean al tocarlos la primera vez)
            Accesos::asegurarProceso($c);
            Permission::findOrCreate($c, 'web');
        }
    }

    /**
     * Lo mismo para un usuario concreto. Si el acceso lo da su rol, «quitarlo» lo deniega solo a esta persona (permisos_denegados)
     * y volver a marcarlo quita esa denegación; si no, es un permiso directo.
     */
    public function alternarUsuario(int $id, string $permiso): void
    {
        $u = User::findOrFail($id);
        if ($u->hasRole('Admin') || ! $u->activo) {   // Admin lo tiene todo; un inactivo no se toca: queda su historial
            return;
        }
        $claves = $this->conHijos($permiso);
        $this->asegurarTodo($claves);
        $u = User::with('roles', 'permissions')->findOrFail($id);
        $delRol = $u->getPermissionsViaRoles()->pluck('name')->all();
        $directos = $u->getDirectPermissions()->pluck('name')->all();
        $denegados = DB::table('permisos_denegados')->where('user_id', $id)->pluck('permiso')->all();
        $dar = ! (($this->tiene($delRol, $permiso) && ! in_array($permiso, $denegados, true)) || $this->tiene($directos, $permiso));
        $this->aplicarUsuario($u, $claves, $dar);
    }

    /** Da o quita estos accesos a una persona: lo que da su rol se deniega/vuelve a dar; lo demás es permiso directo. */
    protected function aplicarUsuario(User $u, array $claves, bool $dar): void
    {
        $delRol = $u->getPermissionsViaRoles()->pluck('name')->all();
        foreach ($claves as $c) {
            $porRol = in_array($c, $delRol, true);
            if ($dar) {
                DB::table('permisos_denegados')->where('user_id', $u->id)->where('permiso', $c)->delete();
                if (! $porRol) {
                    $u->givePermissionTo($c);
                }
            } else {
                if ($porRol) {
                    DB::table('permisos_denegados')->updateOrInsert(['user_id' => $u->id, 'permiso' => $c], ['updated_at' => now(), 'created_at' => now()]);
                }
                $u->revokePermissionTo($c);
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

    /**
     * «Inicial. Apellido» para ganar sitio en la cabecera. Si dos salen iguales se van añadiendo letras del nombre hasta que dejan
     * de coincidir (A. García / Al. García); si aun así coinciden (mismo nombre y apellido) se queda con el nombre entero.
     * El primer palabra es el nombre y el resto el apellido.
     */
    protected function nombresCortos(array $nombres): array
    {
        $partes = [];
        foreach ($nombres as $id => $n) {
            $p = preg_split('/\s+/u', trim($n), 2);
            $partes[$id] = [$p[0] ?? '', $p[1] ?? ''];
        }
        $largo = array_fill_keys(array_keys($partes), 1);
        $etiqueta = function ($id) use (&$largo, $partes) {   // por referencia: $largo va creciendo
            return $partes[$id][1] === '' ? $partes[$id][0] : mb_substr($partes[$id][0], 0, $largo[$id]).'. '.$partes[$id][1];
        };
        do {
            $etq = array_combine(array_keys($partes), array_map(fn ($id) => $etiqueta($id), array_keys($partes)));
            $grupos = [];
            foreach ($etq as $id => $e) {
                $grupos[mb_strtolower($e)][] = $id;
            }
            $cambio = false;
            foreach ($grupos as $ids) {   // solo se alargan los que coinciden
                if (count($ids) > 1) {
                    foreach ($ids as $id) {
                        if ($largo[$id] < mb_strlen($partes[$id][0])) {
                            $largo[$id]++;
                            $cambio = true;
                        }
                    }
                }
            }
        } while ($cambio);
        foreach ($grupos as $ids) {   // idénticos aun con el nombre entero: nombre completo
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $etq[$id] = $nombres[$id];
                }
            }
        }
        return $etq;
    }

    public function render()
    {
        // Nº de usuarios por rol sin withCount('users'): esa relación de Spatie falla si el guard por defecto de la petición no es «web»
        $usuariosPorRol = DB::table('model_has_roles')->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');
        $roles = Role::with('permissions')->orderBy('id')->get()->each(fn ($r) => $r->users_count = (int) ($usuariosPorRol[$r->id] ?? 0));
        // Columnas: primero los roles y, tras una barra, los usuarios uno por uno (todo en la misma pantalla)
        $cols = [];
        foreach ($roles as $r) {
            $cols[] = ['tipo' => 'rol', 'id' => $r->name, 'nombre' => $r->name, 'completo' => $r->name, 'sub' => '('.$r->users_count.')', 'admin' => $r->name === 'Admin',
                'rol' => [], 'directos' => $r->permissions->pluck('name')->all(), 'fijo' => in_array($r->name, self::FIJOS, true)];
        }
        $usuarios = User::with('roles', 'permissions')->orderByDesc('activo')->orderBy('name')->get();   // los inactivos al final, con su historial de accesos
        $cortos = $this->nombresCortos($usuarios->pluck('name', 'id')->all());
        foreach ($usuarios as $i => $u) {
            $cols[] = ['tipo' => 'usuario', 'inicio' => $i === 0, 'id' => $u->id, 'nombre' => $cortos[$u->id], 'completo' => $u->name, 'sub' => $u->activo ? $u->getRoleNames()->first() : 'inactivo',
                'activo' => (bool) $u->activo, 'denegados' => Accesos::denegados($u->id), 'admin' => $u->hasRole('Admin'), 'rol' => $u->getPermissionsViaRoles()->pluck('name')->all(), 'directos' => $u->getDirectPermissions()->pluck('name')->all()];
        }
        $arbol = Accesos::arbol();
        $estado = [];   // [columna][permiso] => 0 no · 1 sí · 2 sí, por su rol (solo vista usuarios)
        foreach ($cols as $k => $c) {
            foreach ($arbol as $items) {
                foreach ($items as $it) {
                    foreach (array_merge([$it['clave'] => 1], array_fill_keys(array_keys($it['hijos']), 1)) as $clave => $_) {
                        $tenia = $c['admin'] || $this->tiene($c['rol'], $clave) || $this->tiene($c['directos'], $clave);
                        // 3 = usuario inactivo que tenía este acceso (no puede entrar, pero se conserva su historial)
                        // 0 no · 1 directo · 2 admin (fijo) · 3 inactivo que lo tenía · 4 lo da el rol (se puede quitar a esta persona) · 5 el rol lo da pero está denegado a esta persona
                        $porRol = $this->tiene($c['rol'], $clave);
                        $estado[$k][$clave] = ($c['activo'] ?? true)
                            ? ($c['admin'] ? 2 : ($porRol ? (in_array($clave, $c['denegados'] ?? [], true) ? 5 : 4) : ($this->tiene($c['directos'], $clave) ? 1 : 0)))
                            : ($tenia ? 3 : 0);
                    }
                }
            }
        }
        foreach ($cols as $k => $c) {   // estado del check bajo el nombre: todo / algo / nada
            $n = count(array_filter($estado[$k] ?? [], fn ($e) => in_array($e, [1, 2, 3, 4], true)));
            $cols[$k]['todo'] = $estado && $n === count($estado[$k]);
            $cols[$k]['algunos'] = $n > 0 && ! $cols[$k]['todo'];
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
