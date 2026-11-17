<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tareas internas (p. ej. el chat de «Confidencialidad») que el trabajador de Claude ve pero que no salen en el TO-DO ni en la campana. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('todo_tareas', 'oculta')) {
            Schema::table('todo_tareas', function (Blueprint $t) {
                $t->boolean('oculta')->default(false)->after('claude_pausada');
            });
        }
    }

    public function down(): void
    {
        Schema::table('todo_tareas', fn (Blueprint $t) => $t->dropColumn('oculta'));
    }
};
