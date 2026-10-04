<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Bancos;
use App\Support\FicherosBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Bancos usa los ficheros base centrales de la empresa: lo que se subió en otro proceso se pasa por bancos_base.py una sola vez. */
class BancosCentralTest extends TestCase
{
    use RefreshDatabase;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/bc-'.uniqid();
        mkdir($this->dir.'/Acme/Base', 0775, true);
        file_put_contents($this->dir.'/Acme/cliente.json', json_encode(['entidad_id' => 55]));
        file_put_contents($this->dir.'/Acme/Base/Base Acme.xlsx', 'base');
        file_put_contents($this->dir.'/bancos_base.py', <<<'PY'
import sys
if '--estado' in sys.argv:
    print('{}')
else:
    open('/tmp/bancos_base_llamadas.log', 'a').write(' '.join(sys.argv[1:]) + '\n')
    print('ok')
PY);
        @unlink('/tmp/bancos_base_llamadas.log');
        config(['contabilidad.bancos_dir' => $this->dir, 'contabilidad.bancos_ejecucion' => true, 'contabilidad.ficheros_base_dir' => $this->dir.'/fb']);
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
        @unlink('/tmp/bancos_base_llamadas.log');
        parent::tearDown();
    }

    public function test_el_mayor_central_se_procesa_una_sola_vez_con_copia_de_la_base(): void
    {
        $this->actingAs($this->adminUser());
        $tmp = tempnam(sys_get_temp_dir(), 'm').'.xlsx';
        file_put_contents($tmp, 'mayor nuevo');
        FicherosBase::guardar(55, 'mayor', $tmp, 'Mayor Acme.xlsx', 'Facturas OCR');

        Livewire::test(Bancos::class);
        $llamadas = (string) @file_get_contents('/tmp/bancos_base_llamadas.log');
        $this->assertStringContainsString('Acme --espera mayor', $llamadas);
        $this->assertSame(1, substr_count($llamadas, '--espera mayor'));
        $this->assertCount(1, glob($this->dir.'/Acme/Base/copias/*.xlsx'), 'copia de seguridad de la base antes de añadirle filas');
        $this->assertCount(1, glob($this->dir.'/Acme/Base/Recibidos/* Mayor Acme.xlsx'));

        Livewire::test(Bancos::class);   // abrirlo otra vez no lo vuelve a procesar
        $this->assertSame(1, substr_count((string) file_get_contents('/tmp/bancos_base_llamadas.log'), '--espera mayor'));
    }
}
