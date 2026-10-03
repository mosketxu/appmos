<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Orden de prioridad de las tareas de cada usuario (1 = lo primero); se mueve con ▲▼ en el TO-DO. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->unsignedInteger('orden')->default(0)->after('prioridad');
        });
        foreach (DB::table('todo_tareas')->orderBy('id')->get(['id', 'asignado_id']) as $t) {
            DB::table('todo_tareas')->where('id', $t->id)->update([
                'orden' => 1 + DB::table('todo_tareas')->where('asignado_id', $t->asignado_id)->where('id', '<', $t->id)->count(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('todo_tareas', fn (Blueprint $table) => $table->dropColumn('orden'));
    }
};
