<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Confidencialidad (17-nov-2026): documentos que Alex anonimiza en Appmos y le pide a Claude que trabaje ya anonimizados (como una tarea del TO-DO).
 * Aquí solo van los metadatos; los ficheros anonimizados y el mapa etiqueta -> dato real viven en storage/app/confidencialidad/<id> (nunca en la BD ni en git).
 * La pantalla es solo para Admin (Gate::before): el permiso no se da a ningún rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confidencialidad_docs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('titulo')->nullable();
            $t->string('estado', 20)->default('procesando'); // procesando | listo | error
            $t->text('error')->nullable();
            $t->json('informe')->nullable();   // cuántos datos de cada tipo se han etiquetado (sin contenido)
            $t->json('archivos')->nullable();  // ficheros anonimizados, relativos a la carpeta del documento
            $t->unsignedBigInteger('tarea_id')->nullable(); // tarea del TO-DO asignada a Claude
            $t->timestamps();
        });
        Permission::findOrCreate('confidencialidad.ver', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('confidencialidad_docs');
        Permission::where('name', 'confidencialidad.ver')->where('guard_name', 'web')->delete();
    }
};
