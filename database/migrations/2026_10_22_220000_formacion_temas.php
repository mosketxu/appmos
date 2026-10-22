<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Pestaña Formación (22-oct-2026): listado de temas que Alex quiere explicar a sus compañeros. Ven todos; edita Admin (o quien tenga formacion.editar). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formacion_temas', function (Blueprint $t) {
            $t->id();
            $t->string('titulo');
            $t->text('descripcion')->nullable();
            $t->string('estado', 20)->default('pendiente'); // pendiente | explicado
            $t->date('fecha_explicado')->nullable();
            $t->unsignedInteger('orden')->default(0);
            $t->timestamps();
        });
        $ver = Permission::findOrCreate('formacion.ver', 'web');
        Permission::findOrCreate('formacion.editar', 'web');
        foreach (Role::where('guard_name', 'web')->get() as $rol) {
            $rol->givePermissionTo($ver);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('formacion_temas');
        Permission::whereIn('name', ['formacion.ver', 'formacion.editar'])->where('guard_name', 'web')->delete();
    }
};
