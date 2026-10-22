<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\ProcesosMensuales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Proc.Mensuales: usuarios (etiquetas) a los que pertenece cada proceso. */
class ProcesosMensualesUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_asigna_y_se_quita_un_usuario_de_un_proceso(): void
    {
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        $yo = User::factory()->create(['activo' => true, 'email' => 'jefe@example.test']);
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $yo->assignRole('Suma');
        $otro = User::factory()->create(['activo' => true]);
        $pid = DB::table('seguimiento_procesos')->insertGetId([
            'clave' => 'prueba', 'nombre' => 'Prueba', 'ambito' => 'general', 'ejecucion' => 'web', 'orden' => 1, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::actingAs($yo)->test(ProcesosMensuales::class)
            ->call('asignar', $pid)
            ->set('addUsuario', (string) $otro->id)
            ->assertSet('asignando', null);
        $this->assertDatabaseHas('seguimiento_proceso_user', ['proceso_id' => $pid, 'user_id' => $otro->id]);

        Livewire::actingAs($yo)->test(ProcesosMensuales::class)->call('quitarUsuario', $pid, $otro->id);
        $this->assertDatabaseMissing('seguimiento_proceso_user', ['proceso_id' => $pid, 'user_id' => $otro->id]);
    }

    public function test_quien_no_es_gestor_no_puede_cambiar_los_usuarios(): void
    {
        config(['auth.defaults.guard' => 'sanctum', 'contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        $marta = User::factory()->create(['activo' => true]);
        $marta->assignRole('Suma');
        $pid = DB::table('seguimiento_procesos')->insertGetId([
            'clave' => 'prueba2', 'nombre' => 'Prueba2', 'ambito' => 'general', 'ejecucion' => 'web', 'orden' => 1, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::actingAs($marta)->test(ProcesosMensuales::class)->call('asignar', $pid)->assertSet('asignando', null);
        DB::table('seguimiento_proceso_user')->insert(['proceso_id' => $pid, 'user_id' => $marta->id]);
        Livewire::actingAs($marta)->test(ProcesosMensuales::class)->call('quitarUsuario', $pid, $marta->id);
        $this->assertDatabaseHas('seguimiento_proceso_user', ['proceso_id' => $pid, 'user_id' => $marta->id]);
    }

    public function test_un_proceso_con_permiso_propio_solo_lo_ve_quien_lo_tiene(): void
    {
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Permission::findOrCreate('proceso.pm.limpieza', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        DB::table('seguimiento_procesos')->updateOrInsert(['clave' => 'limpieza'], [
            'nombre' => 'Limpieza de OneDrive', 'ambito' => 'general', 'ejecucion' => 'web', 'orden' => 5, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $marta = User::factory()->create(['activo' => true]);
        $marta->assignRole('Suma');

        Livewire::actingAs($marta)->test(ProcesosMensuales::class)->assertDontSee('Limpieza de OneDrive');

        $marta->givePermissionTo('proceso.pm.limpieza');
        Livewire::actingAs($marta->fresh())->test(ProcesosMensuales::class)->assertSee('Limpieza de OneDrive');
    }
}
