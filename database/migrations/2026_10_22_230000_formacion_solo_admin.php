<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Formación (22-oct-2026, decisión de Alex): la pantalla es solo para él; se quita formacion.ver a los roles (Admin la sigue viendo por Gate::before). */
return new class extends Migration
{
    public function up(): void
    {
        if ($p = Permission::where('name', 'formacion.ver')->where('guard_name', 'web')->first()) {
            $p->roles()->detach();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
