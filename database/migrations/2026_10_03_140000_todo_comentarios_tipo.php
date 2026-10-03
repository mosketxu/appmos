<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Los comentarios del TO-DO pasan a ser «respuestas»; además guardan eventos (asignaciones, cambios de estado): tipo = respuesta | evento. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_comentarios', function (Blueprint $table) {
            $table->string('tipo', 10)->default('respuesta')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('todo_comentarios', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
