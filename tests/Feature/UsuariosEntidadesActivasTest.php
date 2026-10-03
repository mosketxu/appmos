<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Usuarios;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Panel de control: al asignar entidades a un colaborador solo salen las activas (y las de baja ya marcadas, para poder quitarlas). */
class UsuariosEntidadesActivasTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_salen_entidades_activas_y_las_de_baja_ya_marcadas(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('Admin');
        $colab = User::factory()->create(['activo' => true]);
        $activa = DB::table('entidades')->insertGetId(['entidad' => 'BBVA Activa', 'estado' => 1]);
        $baja = DB::table('entidades')->insertGetId(['entidad' => 'BBVA De Baja', 'estado' => 0]);
        $bajaMarcada = DB::table('entidades')->insertGetId(['entidad' => 'BBVA Baja Marcada', 'estado' => 0]);
        DB::table('entidad_user')->insert(['user_id' => $colab->id, 'entidad_id' => $bajaMarcada]);

        $this->actingAs($admin);
        Livewire::test(Usuarios::class)->call('editar', $colab->id)->set('buscarEntidad', 'BBVA')
            ->assertSee('BBVA Activa')->assertSee('BBVA Baja Marcada')->assertDontSee('BBVA De Baja');
    }
}
