<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Is;
use App\Support\FicherosBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** IS: puede usar como Mayor del ejercicio uno de los guardados en los ficheros base de la empresa (no se trae solo: cada ejercicio tiene el suyo). */
class IsMayorCentralTest extends TestCase
{
    use RefreshDatabase;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/is-'.uniqid();
        mkdir($this->dir, 0775, true);
        config(['contabilidad.is_dir' => $this->dir, 'contabilidad.is_ejecucion' => true, 'contabilidad.ficheros_base_dir' => $this->dir.'/fb']);
        Livewire::component('menu', new class extends \Livewire\Component {
            public $entidad;
            public $ruta;

            public function render()
            {
                return '<div></div>';
            }
        });
    }

    protected function tearDown(): void
    {
        $b = function (string $d) use (&$b) {
            foreach (glob($d.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) {
                is_dir($f) ? $b($f) : unlink($f);
            }
            @rmdir($d);
        };
        $b($this->dir);
        parent::tearDown();
    }

    public function test_usa_un_mayor_de_la_empresa_como_mayor_del_ejercicio_y_guarda_el_anterior(): void
    {
        $this->actingAs($this->adminUser());
        $id = DB::table('entidades')->insertGetId(['entidad' => 'Prueba SL', 'nif' => 'B12345678', 'estado' => 1, 'cliente' => 1]);
        $tmp = tempnam(sys_get_temp_dir(), 'm').'.xlsx';
        file_put_contents($tmp, 'mayor 2025');
        $ruta = FicherosBase::guardar($id, 'mayor', $tmp, 'Mayor 2025.xlsx', 'Facturas OCR');
        $c = Livewire::test(Is::class)->set('entidadId', (string) $id)->set('ejercicio', 2025);
        $c->assertSee('Mayor 2025.xlsx');   // sale en la lista de mayores de la empresa
        $c->call('usarMayorCentral', basename($ruta));
        $f = $this->dir.'/clientes/B12345678/2025/fuentes/mayor.xlsx';
        $this->assertSame('mayor 2025', file_get_contents($f));
        $this->assertStringContainsString('Mayor 2025.xlsx', (string) file_get_contents($this->dir.'/clientes/B12345678/2025/fuentes/mayor.origen.txt'));

        $c->call('usarMayorCentral', basename($ruta));   // otra vez: el anterior va a «anteriores»
        $this->assertCount(1, glob($this->dir.'/clientes/B12345678/2025/fuentes/anteriores/*'));
        $c->call('usarMayorCentral', '../../etc/passwd')->assertHasErrors(['subida.mayor']);
    }
}
