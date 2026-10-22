<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** TO-DO con permiso propio y una subpestaña de Impuestos por permiso (22-oct-2026). */
class PermisosTodoImpuestosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_todo_solo_lo_ve_quien_tiene_su_permiso(): void
    {
        $u = User::factory()->create(['activo' => true]);
        Permission::findOrCreate('todo.ver', 'web');
        $this->actingAs($u)->get(route('todo'))->assertForbidden();
        $u->givePermissionTo('todo.ver');
        $this->actingAs($u->fresh())->get(route('todo'))->assertOk();
    }

    public function test_una_subpestana_de_impuestos_se_puede_quitar_aunque_tenga_la_pestana(): void
    {
        $u = User::factory()->create(['activo' => true]);
        Permission::findOrCreate('impuestos.ver', 'web');
        Permission::findOrCreate('proceso.imp.m349', 'web');   // existe y no se la damos
        $u->givePermissionTo('impuestos.ver');
        $this->assertFalse($u->fresh()->can('proceso.imp.m349'));
        $this->assertTrue($u->fresh()->can('proceso.imp.m111'), 'sin permiso propio vale el de la pestaña');
        $this->actingAs($u->fresh())->get(route('impuestos.m349'))->assertForbidden();
    }

    public function test_pedir_mejora_cada_uno_ve_las_suyas_y_el_gestor_todas(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        Permission::findOrCreate('todo.ver', 'web');
        $jefe = User::factory()->create(['activo' => true, 'email' => 'jefe@example.test']);
        $ana = User::factory()->create(['activo' => true]);
        $bea = User::factory()->create(['activo' => true]);
        foreach ([$jefe, $ana, $bea] as $u) {
            $u->givePermissionTo('todo.ver');
        }
        \App\Models\TodoTarea::create(['titulo' => 'Mejora: botón de Ana', 'creador_id' => $ana->id, 'estado' => 'pendiente']);
        \App\Models\TodoTarea::create(['titulo' => 'Mejora: botón de Bea', 'creador_id' => $bea->id, 'estado' => 'pendiente']);
        \App\Models\TodoTarea::create(['titulo' => 'Otra cosa de Ana', 'creador_id' => $ana->id, 'estado' => 'pendiente']);

        $this->actingAs($ana)->get(route('todo.mejoras'))->assertOk()->assertSee('botón de Ana')->assertDontSee('botón de Bea')->assertDontSee('Otra cosa');
        $this->actingAs($jefe)->get(route('todo.mejoras'))->assertOk()->assertSee('botón de Ana')->assertSee('botón de Bea');
    }
}
