<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos del TO-DO (la campana de la barra de menú): se crean cuando alguien te asigna una tarea, responde
 * o cambia su estado, y se marcan leídos al abrir la tarea. Son solo un registro: no disparan nada más.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('todo_tareas')->cascadeOnDelete();
            $table->foreignId('origen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('texto');
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'leido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_avisos');
    }
};
