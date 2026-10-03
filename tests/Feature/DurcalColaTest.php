<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Durcal;
use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Durcal en la web: el PC trabajador hace activarDurcal.py con Excel/OneDrive; las nóminas no se suben a Appmos. */
class DurcalColaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['contabilidad.ejecucion_local' => false, 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'app.cipher' => 'AES-256-CBC']);
        \Tests\Support\TablasCola::crear();
        DB::table('users')->insert(['id' => 1]);
        $u = new \App\Models\User();
        $u->id = 1;
        $this->actingAs($u);
        $dir = sys_get_temp_dir().'/vistas_test_'.getmypid();
        @mkdir($dir.'/livewire/contabilidad', 0777, true);
        file_put_contents($dir.'/livewire/contabilidad/_subnav.blade.php', '');
        view()->getFinder()->prependLocation($dir);
        Livewire::component('menu', MenuFalsoDurcal::class);
        $this->token = ColaTareas::crearTrabajador('PortalExomen');
    }

    protected function tearDown(): void
    {
        $b = function (string $d) use (&$b) {
            foreach (glob($d.'/*') ?: [] as $f) {
                is_dir($f) ? $b($f) : unlink($f);
            }
            @rmdir($d);
        };
        $b(storage_path('app/tareas'));
        parent::tearDown();
    }

    protected string $token;

    protected function trabajar(array $resultado): int
    {
        $h = ['X-Token' => $this->token];
        $t = $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.script']], $h)->assertOk()->json('tarea');
        $this->assertNotNull($t);
        $this->postJson("/api/trabajador/tareas/{$t['id']}/fin", ['ok' => true, 'resultado' => $resultado], $h)->assertOk();

        return $t['id'];
    }

    public function test_sin_estado_del_pc_no_se_puede_ejecutar(): void
    {
        Livewire::test(Durcal::class)->call('ejecutar')->assertSee('El PC no ha visto el fichero de nómina');
        $this->assertSame(0, DB::table('tareas')->where('proceso', 'pc.script')->count());
    }

    public function test_ejecutar_pide_activarDurcal_real_al_pc_y_enseña_los_ficheros_sin_subirlos(): void
    {
        ColaTareas::guardarEstado('durcal.estado', [
            'pc' => 'AlexMiniPC', 'onedrive' => true, 'personal' => ['fecha' => '18/09/2026 10:00'],
            'nominas' => ['08' => ['nombre' => '08 00602_DURCAL SOFTWARE, S.L..XLS', 'bytes' => 25600, 'fecha' => '18/09/2026 11:20']],
            'amort' => ['nombre' => 'Amortizacion Alpify 2026.xlsm', 'bytes' => 9999999, 'fecha' => '01/10/2026 09:00', 'abierto' => false],
        ]);
        $c = Livewire::test(Durcal::class)->set('mes', 8)->assertSee('08 00602_DURCAL SOFTWARE')->assertSee('cerrado');
        $c->call('ejecutar')->assertSee('pedido a los PCs');

        $t = DB::table('tareas')->where('proceso', 'pc.script')->first();
        $p = json_decode($t->parametros, true);
        $this->assertSame('durcal', $p['grupo']);
        $this->assertSame('activarDurcal.py', $p['pasos'][0]['script']);
        $this->assertSame(['08', '--real'], $p['pasos'][0]['args']);

        $this->trabajar(['ok' => true, 'pc' => 'AlexMiniPC', 'pasos' => [['script' => 'activarDurcal.py', 'ok' => true, 'codigo' => 0,
            'salida' => 'Activados 7 empleados', 'ficheros' => [['ruta' => '/mnt/e/OneDrive/_Clientes/2026/Durcal 2026/Laboral/08 00602_DURCAL SOFTWARE, S.L..XLS', 'nombre' => 'x.XLS', 'subido' => false]]]]]);
        $c->call('revisarTareas')->assertSee('Activados 7 empleados')->assertSee('E:\OneDrive\_Clientes\2026\Durcal 2026\Laboral');
        $this->assertCount(0, glob(storage_path('app/tareas/*/*') ?: []) ?: []);   // nada subido a la web
    }

    public function test_con_el_amortizacion_abierto_avisa(): void
    {
        ColaTareas::guardarEstado('durcal.estado', ['pc' => 'AlexMiniPC', 'nominas' => ['09' => ['nombre' => 'n', 'bytes' => 1, 'fecha' => 'f']],
            'amort' => ['nombre' => 'a', 'bytes' => 1, 'fecha' => 'f', 'abierto' => true]]);
        Livewire::test(Durcal::class)->set('mes', 9)->assertSee('ABIERTO en Excel');
    }
}

class MenuFalsoDurcal extends \Livewire\Component
{
    public $entidad;
    public $ruta;

    public function render()
    {
        return '<div></div>';
    }
}
