<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Pantalla «✉ Correo» abierta a todos (19-nov-2026, orden de Alex): permiso correo.ver para todos los roles. Cada uno ve solo su buzón. Idempotente. */
return new class extends Migration
{
    public function up(): void
    {
        $p = Permission::findOrCreate('correo.ver', 'web');
        foreach (['Admin', 'Suma', 'Usuario'] as $nombre) {
            if ($r = Role::where('name', $nombre)->where('guard_name', 'web')->first()) {
                if (! $r->hasPermissionTo($p)) {
                    $r->givePermissionTo($p);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'correo.ver')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
