<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\InformesBalerga;
use App\Http\Livewire\FicherosBaseSubida;
use App\Models\Entidad;
use App\Support\FicherosBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Informes de Balerga (4-nov-2026): pantalla de Proc.Mensuales que dice qué subir y lo guarda en los ficheros base de la entidad. */
class InformesBalergaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sube_pyg_y_balance_por_mes_y_no_salen_en_el_recuadro_estandar(): void
    {
        config(['contabilidad.ficheros_base_dir' => sys_get_temp_dir().'/fb-test-'.uniqid()]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'Balerga Digital SLU', 'nif' => InformesBalerga::NIF, 'estado' => 1]);
        $f = UploadedFile::fake()->createWithContent('pyg.xlsx', 'PK-contenido');

        Livewire::test(FicherosBaseSubida::class, ['entidadId' => $e->id, 'tipos' => ['pyg', 'balance'], 'periodo' => '2026-10'])
            ->set('subidas.pyg', $f)->assertSet('error', '')->assertDispatched('ficheros-base-cambiados');
        $u = FicherosBase::ultimo($e->id, 'pyg', '2026-10');
        $this->assertSame('pyg.xlsx', $u['nombre']);
        $this->assertNull(FicherosBase::ultimo($e->id, 'pyg', '2026-09'));

        $estandar = Livewire::test(FicherosBaseSubida::class, ['entidadId' => $e->id])->get('tipos');
        $this->assertSame(['plan', 'clientes', 'proveedores', 'mayor', 'sys'], $estandar);
    }

    public function test_la_pantalla_dice_que_falta_y_pide_permiso(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        Entidad::create(['entidad' => 'Balerga Digital SLU', 'nif' => InformesBalerga::NIF, 'estado' => 1]);
        Livewire::test(InformesBalerga::class)->set('periodo', '2026-09')
            ->assertSee('Mayor')->assertSee('falta subirlo')->assertSee('toca también el informe trimestral');
    }
}
