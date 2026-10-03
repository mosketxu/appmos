<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una tarea del TO-DO puede asignarse a varias personas (3-oct-2026). El asignado y el orden de prioridad
 * pasan de todo_tareas a la tabla intermedia todo_tarea_user: cada persona tiene su propio orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_tarea_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('todo_tareas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->unique(['tarea_id', 'user_id']);
        });
        foreach (DB::table('todo_tareas')->get(['id', 'asignado_id', 'orden']) as $t) {
            DB::table('todo_tarea_user')->insert(['tarea_id' => $t->id, 'user_id' => $t->asignado_id, 'orden' => $t->orden]);
        }
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->dropForeign(['asignado_id']);
            $table->dropIndex(['asignado_id', 'estado']);
            $table->dropColumn(['asignado_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->foreignId('asignado_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
        });
        foreach (DB::table('todo_tarea_user')->orderBy('id')->get() as $r) {
            DB::table('todo_tareas')->where('id', $r->tarea_id)->whereNull('asignado_id')->update(['asignado_id' => $r->user_id, 'orden' => $r->orden]);
        }
        Schema::dropIfExists('todo_tarea_user');
    }
};
