<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Prefacturacion;
use App\Models\Entidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Prefacturación: motivos habituales que se van aprendiendo y ayudan a rellenar el motivo. */
class PrefacturacionMotivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_motivo_nuevo_se_propone_y_se_puede_elegir_despues_sin_perder_lo_escrito(): void
    {
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('Suma');

        $c = Livewire::actingAs($u)->test(Prefacturacion::class)
            ->call('editar')
            ->set('fEntidad', '1')->set('fImporte', '56,00')->set('fMotivo', 'Tasas del registro')
            ->call('guardar')
            ->assertSet('proponerMotivo', 'Tasas del registro');
        $this->assertDatabaseCount('prefacturas', 1);
        $this->assertDatabaseCount('prefactura_motivos', 0);

        $c->call('guardarMotivoBase')->assertSet('proponerMotivo', '');
        $this->assertDatabaseHas('prefactura_motivos', ['texto' => 'Tasas del registro']);

        $id = DB::table('prefactura_motivos')->value('id');
        Livewire::actingAs($u)->test(Prefacturacion::class)
            ->call('editar')
            ->set('fMotivo', 'Cliente X:')
            ->set('motivoBase', (string) $id)
            ->assertSet('fMotivo', 'Cliente X: Tasas del registro')
            ->assertSet('motivoBase', '');
    }

    public function test_un_motivo_con_errata_se_propone_corregirlo_con_el_habitual(): void
    {
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('Suma');
        DB::table('prefactura_motivos')->insert(['texto' => 'Factura FNMT', 'created_at' => now(), 'updated_at' => now()]);

        $c = Livewire::actingAs($u)->test(Prefacturacion::class)->call('editar')
            ->set('fEntidad', '1')->set('fImporte', '10')->set('fMotivo', 'factura fnmt ')
            ->call('guardar')->assertSet('motivoParecido', 'Factura FNMT');
        $c->call('usarMotivoParecido')->assertSet('proponerMotivo', '');
        $this->assertDatabaseHas('prefacturas', ['motivo' => 'Factura FNMT']);

        $c2 = Livewire::actingAs($u)->test(Prefacturacion::class)->call('editar')
            ->set('fEntidad', '1')->set('fImporte', '10')->set('fMotivo', 'Fcatura FNMT')->call('guardar')
            ->assertSet('motivoParecido', 'Factura FNMT');
        $c2->call('editar')->set('fEntidad', '1')->set('fImporte', '5')->set('fMotivo', 'Tasas del registro mercantil')->call('guardar')
            ->assertSet('motivoParecido', '')->assertSet('proponerMotivo', 'Tasas del registro mercantil');
    }
}
