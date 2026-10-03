<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Schema;

/** Tablas mínimas (sqlite en memoria) que necesita la cola de tareas, incluidas las que consulta reservar() del TO-DO de Claude. */
class TablasCola
{
    public static function crear(): void
    {
        Schema::create('users', function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('email')->nullable(); });
        Schema::create('todo_tarea_user', function ($t) { $t->id(); $t->unsignedBigInteger('tarea_id'); $t->unsignedBigInteger('user_id'); $t->unsignedInteger('orden')->default(0); });
        (require base_path('database/migrations/2026_10_02_140000_create_trabajadores_tareas_tables.php'))->up();
        (require base_path('database/migrations/2026_10_03_210000_create_estado_procesos_table.php'))->up();
        Schema::table('tareas', fn ($t) => $t->timestamp('no_antes_de')->nullable());
        Schema::create('todo_ajustes', function ($t) { $t->string('clave')->primary(); $t->string('valor')->nullable(); $t->timestamps(); });
        Schema::create('claude_ejecuciones', function ($t) { $t->id(); $t->timestamps(); });
        Schema::create('claude_uso', function ($t) { $t->id(); $t->string('pc')->unique(); $t->unsignedSmallInteger('sesion_pct')->nullable(); $t->string('sesion_reinicia')->nullable(); $t->unsignedSmallInteger('semana_pct')->nullable(); $t->string('semana_reinicia')->nullable(); $t->timestamp('leido_at')->nullable(); $t->timestamps(); });
        Schema::create('todo_tareas', function ($t) { $t->id(); $t->boolean('claude_pausada')->default(false); });
    }
}
