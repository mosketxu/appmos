<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pestaña TO-DO (3-oct-2026): tareas tipo ticket. Cada usuario ve las que ha creado y las que le han
 * asignado; el Admin puede ver las de cualquier usuario. Creador y asignado añaden comentarios con fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->foreignId('creador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asignado_id')->constrained('users')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | en_curso | bloqueada | hecha | cancelada');
            $table->string('prioridad', 10)->default('normal')->comment('baja | normal | alta');
            $table->date('fecha_limite')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
            $table->index(['asignado_id', 'estado']);
            $table->index(['creador_id', 'estado']);
        });

        Schema::create('todo_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('todo_tareas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->text('texto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_comentarios');
        Schema::dropIfExists('todo_tareas');
    }
};
