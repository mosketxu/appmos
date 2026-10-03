<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos por tarea para Claude automático (3-oct-2026): lista de claves que solo Alex concede
 * (scripts, correo, desplegar, ssh, borrar). Sin permisos, Claude solo lee, edita ficheros del proyecto, hace tests y commits locales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->json('claude_permisos')->nullable()->after('claude_pausada');
        });
    }

    public function down(): void
    {
        Schema::table('todo_tareas', fn (Blueprint $table) => $table->dropColumn('claude_permisos'));
    }
};
