<?php

namespace Tests\Feature;

use App\Models\TodoTarea;
use App\Support\ColaTareas;
use App\Support\ErroresApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Los errores llegan solos a Alex (campana) y a Claude (tarea del TO-DO), sin duplicarse. */
class ErroresATodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['contabilidad.claude_todo_gestores' => ['alex@example.com']]);
        \Tests\Support\TablasCola::crear();
        Schema::table('users', function ($t) { $t->string('password')->nullable(); $t->boolean('activo')->default(true); $t->timestamps(); });
        // Esquema mínimo del TO-DO (las migraciones reales usan cosas que sqlite no soporta)
        Schema::dropIfExists('todo_tareas');
        Schema::dropIfExists('todo_tarea_user');
        Schema::create('todo_tareas', function ($t) {
            $t->id(); $t->string('titulo'); $t->text('descripcion')->nullable(); $t->unsignedBigInteger('creador_id'); $t->string('estado')->default('pendiente');
            $t->string('prioridad')->default('normal'); $t->date('fecha_limite')->nullable(); $t->timestamp('cerrada_at')->nullable();
            $t->timestamp('claude_autorizada_at')->nullable(); $t->unsignedBigInteger('claude_autorizada_por')->nullable(); $t->boolean('claude_pausada')->default(false);
            $t->text('claude_permisos')->nullable(); $t->timestamp('prioridad_pedida_at')->nullable(); $t->unsignedBigInteger('prioridad_pedida_por')->nullable(); $t->timestamps();
        });
        Schema::create('todo_tarea_user', function ($t) { $t->id(); $t->unsignedBigInteger('tarea_id'); $t->unsignedBigInteger('user_id'); $t->unsignedInteger('orden')->default(0); });
        Schema::create('todo_comentarios', function ($t) {
            $t->id(); $t->unsignedBigInteger('tarea_id'); $t->unsignedBigInteger('user_id'); $t->string('tipo')->default('comentario'); $t->date('fecha'); $t->text('texto'); $t->timestamps();
        });
        Schema::create('todo_avisos', function ($t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('tarea_id'); $t->unsignedBigInteger('origen_id')->nullable(); $t->text('texto'); $t->timestamp('leido_at')->nullable(); $t->timestamps();
        });
        DB::table('users')->insert([['id' => 1, 'name' => 'Alex', 'email' => 'alex@example.com'], ['id' => 2, 'name' => 'Claude', 'email' => null]]);
    }

    public function test_un_error_distinto_abre_una_tarea_y_el_mismo_no_se_duplica(): void
    {
        if (! Schema::hasTable('todo_tarea_user') || ! Schema::hasColumn('todo_tareas', 'claude_permisos')) {
            $this->markTestSkipped('El esquema del TO-DO no se pudo montar en sqlite');
        }
        ErroresApp::registrar('prueba|1', 'Algo ha fallado', "Salida del error\nlinea 2");
        $t = TodoTarea::where('descripcion', 'like', '%[err-%')->first();
        $this->assertNotNull($t);
        $this->assertStringStartsWith('⚠ Algo ha fallado', $t->titulo);
        $this->assertSame([1, 2], $t->asignados()->pluck('users.id')->sort()->values()->all());   // Alex y Claude
        $this->assertSame(1, DB::table('todo_avisos')->where('user_id', 1)->count());               // campana de Alex
        $this->assertNotNull($t->fresh()->claude_autorizada_at);                                    // Claude puede cogerla sin permisos

        ErroresApp::registrar('prueba|1', 'Algo ha fallado', 'otra vez');
        $this->assertSame(1, TodoTarea::where('descripcion', 'like', '%[err-%')->count());
        ErroresApp::registrar('prueba|2', 'Otro fallo', 'x');
        $this->assertSame(2, TodoTarea::where('descripcion', 'like', '%[err-%')->count());
    }

    public function test_un_error_de_una_tarea_del_pc_llega_con_su_salida(): void
    {
        if (! Schema::hasTable('todo_tarea_user') || ! Schema::hasColumn('todo_tareas', 'claude_permisos')) {
            $this->markTestSkipped('El esquema del TO-DO no se pudo montar en sqlite');
        }
        $token = ColaTareas::crearTrabajador('PC');
        $id = ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'monthlyFIQ.js', 'args' => ['09']]]]);
        $h = ['X-Token' => $token];
        $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.script']], $h)->assertOk();
        $this->postJson("/api/trabajador/tareas/$id/fin", ['ok' => false, 'log' => '', 'resultado' => ['ok' => false, 'pasos' => [
            ['script' => 'monthlyFIQ.js', 'ok' => false, 'codigo' => 2, 'salida' => "Error: falta el fichero SyS 09.xlsx\nmás detalle"],
        ]]], $h)->assertOk();
        $t = TodoTarea::where('descripcion', 'like', '%[err-%')->first();
        $this->assertNotNull($t);
        $this->assertStringContainsString('falta el fichero SyS 09.xlsx', $t->titulo.$t->descripcion);
        $this->assertStringContainsString('monthlyFIQ.js', $t->descripcion);
    }

    public function test_un_error_de_la_web_no_rompe_si_no_hay_todo(): void
    {
        Schema::dropIfExists('todo_tareas');
        ErroresApp::registrar('x', 'y', 'z');   // sin tablas: no hace nada y no lanza
        $this->assertTrue(true);
    }
}
