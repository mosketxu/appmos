<?php

namespace Tests\Feature;

use App\Http\Livewire\Todo;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** TO-DO: cada usuario ve solo sus tareas (creadas o asignadas); el Admin puede ver las de otros. */
class TodoTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(string $nombre): User
    {
        return User::factory()->create(['name' => $nombre, 'email' => strtolower($nombre).'@sumaempresa.com', 'activo' => true]);
    }

    public function test_crear_tarea_asignada_y_comentar(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);

        Livewire::test(Todo::class)
            ->set('titulo', 'Revisar IVA')->set('asignadosIds', [$bea->id])->call('crear')
            ->assertSee('Revisar IVA');
        $t = TodoTarea::first();
        $this->assertSame($ana->id, $t->creador_id);

        $this->actingAs($bea);
        Livewire::test(Todo::class)
            ->set('vista', 'mias')->assertSee('Revisar IVA')
            ->call('abrir', $t->id)
            ->set('comentario', 'En ello')->call('comentar', $t->id)
            ->call('cambiarEstado', $t->id, 'hecha');
        $this->assertDatabaseHas('todo_comentarios', ['tarea_id' => $t->id, 'user_id' => $bea->id, 'texto' => 'En ello']);
        $this->assertSame('hecha', $t->fresh()->estado);
        $this->assertNotNull($t->fresh()->cerrada_at);
    }

    public function test_un_tercero_no_ve_ni_toca_la_tarea(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $cai = $this->usuario('Cai');
        $t = $t = TodoTarea::create(['titulo' => 'Secreta', 'creador_id' => $ana->id]);
        $t->asignados()->attach($bea->id, ['orden' => 1]);

        $this->actingAs($cai);
        Livewire::test(Todo::class)->set('vista', 'todas')->assertDontSee('Secreta')
            ->set('verUsuario', $ana->id)->assertDontSee('Secreta');
        Livewire::test(Todo::class)->call('cambiarEstado', $t->id, 'hecha')->assertForbidden();
    }

    public function test_el_admin_ve_las_tareas_de_otro_usuario(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = $this->usuario('Alex');
        $admin->assignRole('Admin');
        $ana = $this->usuario('Ana');
        TodoTarea::create(['titulo' => 'De Ana', 'creador_id' => $ana->id])->asignados()->attach($ana->id, ['orden' => 1]);

        $this->actingAs($admin);
        Livewire::test(Todo::class)->assertDontSee('De Ana')
            ->set('verUsuario', $ana->id)->assertSee('De Ana');
    }

    public function test_la_pagina_carga(): void
    {
        $this->actingAs($this->usuario('Ana'))->get(route('todo'))->assertOk()->assertSee('TO-DO');
    }

    public function test_mover_cambia_el_orden_de_prioridad(): void
    {
        $ana = $this->usuario('Ana');
        $this->actingAs($ana);
        $c = Livewire::test(Todo::class);
        foreach (['Uno', 'Dos', 'Tres'] as $titulo) {
            $c->set('titulo', $titulo)->set('asignadosIds', [$ana->id])->call('crear');
        }
        $orden = fn () => \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')->where('p.user_id', $ana->id)->orderBy('p.orden')->pluck('t.titulo', 't.id')->all();
        $ids = $orden();
        $this->assertSame(['Uno', 'Dos', 'Tres'], array_values($ids));
        $tres = array_search('Tres', $ids);

        $c->set('vista', 'mias')->call('mover', $tres, -1);
        $this->assertSame(['Uno', 'Tres', 'Dos'], array_values($orden()));
        $c->set('vista', 'mias')->call('mover', $tres, -1)->call('mover', $tres, -1);   // ya es la primera: no pasa nada
        $this->assertSame(['Tres', 'Uno', 'Dos'], array_values($orden()));
    }

    public function test_una_tarea_para_varias_personas_y_cada_una_con_su_orden(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);
        Livewire::test(Todo::class)->set('titulo', 'Para dos')->set('asignadosIds', [$ana->id, $bea->id])->call('crear');
        $t = TodoTarea::first();
        $this->assertCount(2, $t->asignados);

        $this->actingAs($bea);
        Livewire::test(Todo::class)->assertSee('Para dos')                       // vista «todas» por defecto
            ->call('alternarAsignado', $t->id, $ana->id);                        // la quita
        $this->assertCount(1, $t->fresh()->asignados);
        Livewire::test(Todo::class)->call('alternarAsignado', $t->id, $bea->id);  // no se puede quitar a la última
        $this->assertCount(1, $t->fresh()->asignados);
    }

    public function test_el_selector_de_personas_del_formulario(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);
        Livewire::test(Todo::class)->set('nueva', true)
            ->assertSee('Añadir persona')
            ->call('alternarNuevo', $bea->id)->assertSet('asignadosIds', [$ana->id, $bea->id])
            ->call('alternarNuevo', $ana->id)->assertSet('asignadosIds', [$bea->id])
            ->call('alternarNuevo', $bea->id)->assertSet('asignadosIds', [$bea->id]);   // la última no se quita
    }

    public function test_las_respuestas_registran_quien_asigna_y_no_se_encadenan(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);
        $c = Livewire::test(Todo::class)->set('titulo', 'Hilo')->set('asignadosIds', [$ana->id])->call('crear');
        $t = TodoTarea::first();

        $c->set('comentario', 'Mira esto, Bea')
            ->call('alternarRespuesta', $bea->id)->call('comentar', $t->id)->assertSee('Bea')->assertSee('responde');
        $this->assertTrue($t->fresh()->estaAsignadaA($bea->id));
        $this->assertDatabaseHas('todo_comentarios', ['tarea_id' => $t->id, 'tipo' => 'respuesta', 'user_id' => $ana->id]);
        $this->assertDatabaseHas('todo_comentarios', ['tarea_id' => $t->id, 'tipo' => 'evento', 'texto' => 'asignó a Bea']);

        $antes = \App\Models\TodoComentario::count();
        $c->call('cambiarEstado', $t->id, 'en_curso');
        $this->assertSame($antes + 1, \App\Models\TodoComentario::count());   // solo el evento del cambio: nada se dispara solo
        $c->call('cambiarEstado', $t->id, 'en_curso');                             // sin cambio, sin evento
        $this->assertSame($antes + 1, \App\Models\TodoComentario::count());
    }

    public function test_reordenar_arrastrando(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);
        $c = Livewire::test(Todo::class);
        foreach (['Uno', 'Dos', 'Tres'] as $titulo) {
            $c->set('titulo', $titulo)->set('asignadosIds', [$ana->id])->call('crear');
        }
        $ajena = TodoTarea::create(['titulo' => 'Ajena', 'creador_id' => $bea->id]);
        $ajena->asignados()->attach($bea->id, ['orden' => 1]);
        $id = fn ($t) => TodoTarea::where('titulo', $t)->value('id');
        $orden = fn () => \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')->where('p.user_id', $ana->id)->orderBy('p.orden')->pluck('t.titulo')->all();

        $c->call('reordenar', [$id('Tres'), $id('Uno'), $id('Dos'), $ajena->id]);   // la ajena se ignora
        $this->assertSame(['Tres', 'Uno', 'Dos'], $orden());
        $this->assertSame(1, (int) \DB::table('todo_tarea_user')->where('tarea_id', $ajena->id)->value('orden'));
        $c->assertSee('data-handle', false);
    }

    public function test_la_prioridad_de_cada_usuario_es_suya_y_solo_el_admin_ordena_la_de_otro(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('Admin', 'web');
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $alex = $this->usuario('Alex');
        $alex->assignRole('Admin');
        $this->actingAs($ana);
        $c = Livewire::test(Todo::class);
        foreach (['Uno', 'Dos', 'Tres'] as $titulo) {
            $c->set('titulo', $titulo)->set('asignadosIds', [$bea->id])->call('crear');
        }
        $id = fn ($t) => TodoTarea::where('titulo', $t)->value('id');
        $orden = fn () => \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')->where('p.user_id', $bea->id)->orderBy('p.orden')->pluck('t.titulo')->all();

        // Ana creó las tareas pero la prioridad es de Bea: sin ⠿ y sin poder ordenarlas
        $c->assertDontSee('data-handle', false)->call('reordenar', [$id('Tres'), $id('Uno'), $id('Dos')], $bea->id);
        $this->assertSame(['Uno', 'Dos', 'Tres'], $orden());

        // Bea sí: ⠿ en sus tareas y orden suyo
        $this->actingAs($bea);
        Livewire::test(Todo::class)->assertSee('data-handle', false)->call('reordenar', [$id('Tres'), $id('Uno'), $id('Dos')]);
        $this->assertSame(['Tres', 'Uno', 'Dos'], $orden());

        // El Admin puede elegir la lista de Bea y ordenarla
        $this->actingAs($alex);
        Livewire::test(Todo::class)->set('verUsuario', $bea->id)->assertSee('data-handle', false)
            ->call('reordenar', [$id('Dos'), $id('Tres'), $id('Uno')], $bea->id);
        $this->assertSame(['Dos', 'Tres', 'Uno'], $orden());
    }

    public function test_la_campana_avisa_de_asignaciones_y_respuestas(): void
    {
        $ana = $this->usuario('Ana');
        $bea = $this->usuario('Bea');
        $this->actingAs($ana);
        Livewire::test(Todo::class)->set('titulo', 'Para Bea')->set('asignadosIds', [$ana->id, $bea->id])->call('crear');
        $t = TodoTarea::first();
        $this->assertSame(0, \App\Models\TodoAviso::where('user_id', $ana->id)->count());   // a uno mismo no se avisa
        $this->assertSame(1, \App\Models\TodoAviso::where('user_id', $bea->id)->sinLeer()->count());

        $this->actingAs($bea);
        Livewire::test(\App\Http\Livewire\TodoCampana::class)->assertSee('Ana')->assertSee('te ha asignado');
        Livewire::test(Todo::class)->call('abrir', $t->id);                      // abrir la tarea la marca leída
        $this->assertSame(0, \App\Models\TodoAviso::where('user_id', $bea->id)->sinLeer()->count());

        Livewire::test(Todo::class)->set('comentario', 'Hecho')->call('comentar', $t->id);
        $this->assertSame(1, \App\Models\TodoAviso::where('user_id', $ana->id)->sinLeer()->where('texto', 'ha respondido')->count());
        $this->assertSame(0, \App\Models\TodoAviso::where('user_id', $bea->id)->sinLeer()->count());
    }

    public function test_el_buscador_filtra_las_filas_y_reordenar_funciona_con_un_subconjunto(): void
    {
        $ana = $this->usuario('Ana');
        $this->actingAs($ana);
        $c = Livewire::test(Todo::class);
        foreach (['Factura IVA', 'Llamar a Pedro', 'Revisar IVA trimestral'] as $titulo) {
            $c->set('titulo', $titulo)->set('asignadosIds', [$ana->id])->call('crear');
        }
        $c->assertSee('Llamar a Pedro')->set('buscar', 'iva')->assertSee('Factura IVA')->assertSee('Revisar IVA trimestral')->assertDontSee('Llamar a Pedro')
            ->set('buscar', 'ana')->assertSee('Llamar a Pedro')                  // también busca por nombre (creador/asignado)
            ->set('buscar', '50%')->assertDontSee('Llamar a Pedro');             // el % no es comodín

        // ordenar solo las visibles: las otras no se mueven de su sitio
        $id = fn ($t) => TodoTarea::where('titulo', $t)->value('id');
        $orden = fn () => \DB::table('todo_tarea_user as p')->join('todo_tareas as t', 't.id', '=', 'p.tarea_id')->where('p.user_id', $ana->id)->orderBy('p.orden')->pluck('t.titulo')->all();
        $c->call('reordenar', [$id('Revisar IVA trimestral'), $id('Factura IVA')]);
        $this->assertSame(['Revisar IVA trimestral', 'Llamar a Pedro', 'Factura IVA'], $orden());
    }
}
