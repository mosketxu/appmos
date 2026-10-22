<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** TO-DO con permiso propio (22-oct-2026): por defecto solo lo ven Admin y Suma; al resto se le da desde el panel de control. */
return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        $p = Permission::findOrCreate('todo.ver', 'web');
        foreach (['Admin', 'Suma'] as $rol) {
            if ($r = Role::where('name', $rol)->where('guard_name', 'web')->first()) {
                $r->givePermissionTo($p);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'todo.ver')->where('guard_name', 'web')->delete();
    }
};
