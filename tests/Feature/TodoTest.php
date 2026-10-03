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
}
