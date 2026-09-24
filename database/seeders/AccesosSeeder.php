<?php

namespace Database\Seeders;

use App\Models\Suma;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos de config/accesos.php. Se puede pasar las veces que haga
 * falta: crea lo que falte y NO toca los permisos que ya se hayan cambiado a
 * mano en el panel de control (solo se dan los de por defecto a un rol nuevo).
 *
 *   php artisan db:seed --class=AccesosSeeder
 *
 * La primera vez (--usuarios) además crea/ajusta los usuarios iniciales
 * (24-sep-2026): Alex admin, Marta y Susana Suma, el resto del combo
 * "Responsable Suma" como Usuario sin correo, y enlaza cada Responsable Suma con
 * su usuario.
 */
class AccesosSeeder extends Seeder
{
    public bool $usuarios = false;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $todos = [];
        foreach (config('accesos.permisos') as $grupo) {
            foreach (array_keys($grupo) as $nombre) {
                Permission::findOrCreate($nombre, 'web');
                $todos[] = $nombre;
            }
        }
        foreach (config('accesos.roles') as $rol => $porDefecto) {
            $nuevo = ! Role::where('name', $rol)->where('guard_name', 'web')->exists();
            $role = Role::findOrCreate($rol, 'web');
            if ($nuevo) {
                $role->syncPermissions($porDefecto === '*' ? $todos : $porDefecto);
            }
        }
        // Admin siempre con todo (Gate::before ya lo garantiza; así se ve marcado en el panel)
        Role::findByName('Admin', 'web')->syncPermissions($todos);

        if ($this->usuarios || in_array('--usuarios', $_SERVER['argv'] ?? [], true) || env('ACCESOS_USUARIOS')) {
            $this->usuariosIniciales();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function usuariosIniciales(): void
    {
        // [nombre en el combo Responsable Suma => [rol, email del usuario existente o null]]
        $plan = [
            'Alex' => ['Admin', 'mosketxu@gmail.com'],
            'Marta' => ['Suma', 'marta.ruiz@sumaempresa.com'],
            'Susana' => ['Suma', null],
        ];
        foreach (Suma::orderBy('id')->get() as $suma) {
            [$rol, $email] = $plan[$suma->nombre] ?? ['Usuario', null];
            $user = $email ? User::where('email', $email)->first() : null;
            $user ??= $suma->user_id ? User::find($suma->user_id) : null;
            $user ??= User::whereNull('email')->where('name', $suma->nombre)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $suma->nombre,
                    'email' => null,
                    'password' => Hash::make(Str::random(40)),
                    'activo' => true,
                ]);
                $this->command?->info("Creado usuario {$suma->nombre} (sin correo) como {$rol}");
            }
            if (! $user->roles()->exists()) {
                $user->assignRole($rol);
            }
            if ($suma->user_id !== $user->id) {
                $suma->user_id = $user->id;
                $suma->save();
            }
        }
    }
}
