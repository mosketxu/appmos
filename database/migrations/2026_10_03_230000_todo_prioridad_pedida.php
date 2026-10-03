<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «Pedir prioridad» (3-oct-2026): quien creó una tarea (o un Admin) avisa a los asignados de que la prioricen; no toca el orden de nadie. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->timestamp('prioridad_pedida_at')->nullable()->after('claude_permisos');
            $table->foreignId('prioridad_pedida_por')->nullable()->after('prioridad_pedida_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->dropForeign(['prioridad_pedida_por']);
            $table->dropColumn(['prioridad_pedida_at', 'prioridad_pedida_por']);
        });
    }
};
