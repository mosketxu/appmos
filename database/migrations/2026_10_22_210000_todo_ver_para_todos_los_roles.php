<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** TO-DO (22-oct-2026, decisión de Alex): lo ven todos (cada uno solo sus tareas y las asignadas); el permiso queda por si hay que quitarlo a alguien. */
return new class extends Migration
{
    public function up(): void
    {
        $p = Permission::findOrCreate('todo.ver', 'web');
        foreach (Role::where('guard_name', 'web')->get() as $rol) {
            $rol->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
