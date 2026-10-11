<?php

namespace Tests\Feature;

use App\Http\Livewire\Ent;
use App\Http\Livewire\Impuestos;
use App\Models\Entidad;
use App\Models\EntidadGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Grupos de entidades (11-oct-2026): se elige en la ficha, «Nuevo grupo» no duplica y Impuestos filtra por grupo. */
class EntidadGrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_nuevo_grupo_desde_la_ficha_y_no_duplica_por_mayusculas(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        foreach (['Grupo Zeta', '  grupo   zeta '] as $i => $nombre) {
            Livewire::test(Ent::class, ['entidad' => new Entidad, 'contacto' => new Entidad, 'ruta' => 'entidad.nueva'])
                ->set('entidad.entidad', 'Empresa '.$i)->set('entidad.tipoiva', 21)
                ->set('entidad.grupo_id', '__nuevo')->set('grupoNuevo', $nombre)->call('save');
        }
        $this->assertSame(1, EntidadGrupo::where('nombre', 'Grupo Zeta')->count());
        $g = EntidadGrupo::where('nombre', 'Grupo Zeta')->first();
        $this->assertSame(2, Entidad::where('grupo_id', $g->id)->count());
    }

    public function test_impuestos_ofrece_el_filtro_de_grupo(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $g = EntidadGrupo::create(['nombre' => 'Grupo Prueba']);
        Livewire::test(Impuestos::class)->assertSee('Todos los grupos')->assertSee('Grupo Prueba')
            ->set('filtroGrupo', (string) $g->id)->assertOk()->set('filtroGrupo', 'ninguno')->assertOk();
    }
}
