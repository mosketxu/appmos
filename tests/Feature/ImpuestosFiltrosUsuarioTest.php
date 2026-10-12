<?php

namespace Tests\Feature;

use App\Http\Livewire\Impuestos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Los filtros de Impuestos se guardan en el usuario (valen desde cualquier PC), no en la sesión. */
class ImpuestosFiltrosUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_filtro_se_recuerda_por_usuario_aunque_cambie_la_sesion(): void
    {
        $u = $this->adminUser(['activo' => 1]);
        $this->actingAs($u);
        Livewire::test(Impuestos::class)->set('vista', 'T3');
        $this->assertSame('T3', $u->fresh()->impuestos_filtros['vista']);

        $this->actingAs($u->fresh());   // otro PC = otra petición/sesión
        Livewire::test(Impuestos::class)->assertSet('vista', 'T3');
    }
}
