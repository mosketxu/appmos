<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\IsPagos;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** IS · «Preparar pagos a cuenta»: del texto del IS anterior sale la cuota (casilla 00599) y el fichero .202 (modalidad 40.2, 18 %). */
class IsPagosTest extends TestCase
{
    use RefreshDatabase;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (! trim((string) shell_exec('command -v python3'))) {
            $this->markTestSkipped('Sin python3.');
        }
        $this->dir = sys_get_temp_dir().'/ispagos-'.uniqid();
        mkdir($this->dir.'/motor', 0775, true);
        // el motor del IS (lee_200, textos) y el del 202 están en la carpeta Claude, junto a appmos
        $raiz = dirname(base_path()).'/Contabilidad/Impuestos';
        if (! is_file($raiz.'/M202/motor/prepara202.py')) {
            $this->markTestSkipped('No está el motor del 202 junto a appmos.');
        }
        foreach (['lee_200.py', 'textos.py'] as $f) {
            copy($raiz.'/IS/motor/'.$f, $this->dir.'/motor/'.$f);
        }
        config(['contabilidad.is_dir' => $this->dir, 'contabilidad.is_ejecucion' => true, 'contabilidad.is_motor202' => $raiz.'/M202/motor']);
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
        if (isset($this->dir)) {
            $b($this->dir);
        }
        parent::tearDown();
    }

    protected function cliente(string $nif, string $nombre, string $texto): Entidad
    {
        $e = Entidad::create(['entidad' => $nombre, 'nif' => $nif]);
        $m = DB::table('impuesto_modelos')->where('codigo', '202')->value('id');
        $ob = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'P', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $ob, 'ejercicio' => 2026, 'periodo' => 'P2', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
        $d = $this->dir.'/clientes/'.$nif.'/2025/fuentes';
        mkdir($d, 0775, true);
        file_put_contents($d.'/m200_presentado.txt', $texto);

        return $e;
    }

    protected function textoIs(string $nif, string $nombre, string $cuota, string $incn): string
    {
        return "Impuesto sobre Sociedades  2025\n{$nif}  {$nombre}  Código CNAE (2009) actividad principal . 6812\n"
            ."Importe neto de la cifra de negocios (N, A, P) . ......... 00255  {$incn}\n"
            ."Cuota del ejercicio a ingresar o a devolver ... 00599  {$cuota}  00600\n";
    }

    public function test_genera_el_202_con_el_18_por_ciento_de_la_cuota(): void
    {
        $this->actingAs($this->adminUser());
        $e = $this->cliente('B06931570', 'Apanemi Invest SL', $this->textoIs('B06931570', 'APANEMI INVEST, S.L.', '60.164,66', '261.737,80'));
        Livewire::test(IsPagos::class, ['entidadActual' => $e->id])
            ->set('abierto', true)->set('ejercicio', 2026)->set('periodo', '2P')
            ->set('cnae', [$e->id => '4101'])
            ->call('preparar')
            ->assertSee('60.164,66')->assertSee('10.829,64');
        $f = $this->dir.'/clientes/B06931570/2026/salida202/B06931570_2026_2P.202';
        $this->assertFileExists($f);
        $t = file_get_contents($f);
        $this->assertSame(328 + 700 + 900 + 18, strlen($t));
        $p1 = substr($t, 328, 700);
        $this->assertSame('B06931570', substr($p1, 13, 9));
        $this->assertSame('APANEMI INVEST SL', rtrim(substr($p1, 22, 60)));
        $this->assertSame('4101', substr($p1, 117, 4));
        $this->assertSame('X', substr($p1, 129, 1));                        // cifra de negocios < 1 M€
        $this->assertSame('A', substr($p1, 147, 1));                        // modalidad 40.2
        $this->assertSame('00000000006016466', substr($p1, 148, 17));       // base [01]
        $this->assertSame('00000000001082964', substr($p1, 182, 17));       // a ingresar [03]
    }

    public function test_no_genera_si_la_cuota_no_es_positiva_o_es_gran_empresa(): void
    {
        $this->actingAs($this->adminUser());
        $a = $this->cliente('B11111111', 'A SL', $this->textoIs('B11111111', 'A SL', '-1.505,58', '10.000,00'));
        $b = $this->cliente('B22222222', 'B SL', $this->textoIs('B22222222', 'B SL', '5.000,00', '13.959.750,00'));
        Livewire::test(IsPagos::class)->set('abierto', true)->set('alcance', 'todos')->set('ejercicio', 2026)->set('periodo', '2P')
            ->set('marcados', [$a->id => true, $b->id => true])->call('preparar')
            ->assertSee('sin cuota positiva')->assertSee('modalidad 40.3');
        $this->assertFileDoesNotExist($this->dir.'/clientes/B11111111/2026/salida202/B11111111_2026_2P.202');
        $this->assertFileDoesNotExist($this->dir.'/clientes/B22222222/2026/salida202/B22222222_2026_2P.202');
    }

    public function test_por_defecto_solo_muestra_el_cliente_elegido_arriba(): void
    {
        $this->actingAs($this->adminUser());
        $a = $this->cliente('B11111111', 'Alfa SL', $this->textoIs('B11111111', 'ALFA SL', '1.000,00', '10.000,00'));
        $b = $this->cliente('B22222222', 'Beta SL', $this->textoIs('B22222222', 'BETA SL', '2.000,00', '10.000,00'));
        $c = Livewire::test(IsPagos::class, ['entidadActual' => $a->id])->set('abierto', true)->set('ejercicio', 2026)->set('periodo', '2P');
        $c->assertSee('Alfa SL')->assertDontSee('Beta SL');
        $c->set('alcance', 'todos')->assertSee('Alfa SL')->assertSee('Beta SL');
    }
}
