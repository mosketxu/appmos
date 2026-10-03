<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\FacturacionPdf;
use App\Support\ColaTareas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Facturación PDF (Suma/Balerga) en la web: el PDF viaja al PC trabajador, que lo procesa con su OneDrive. */
class FacturacionPdfColaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
        Livewire::component('menu', MenuFalsoFacturacion::class);
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

    public function test_separar_y_enviar_pasan_por_el_pc(): void
    {
        $c = Livewire::test(FacturacionPdf::class)
            ->set('archivo.Suma', UploadedFile::fake()->create('listado.pdf', 20, 'application/pdf'))
            ->call('separarPdf', 'Suma')
            ->assertSee('pedido a los PCs');
        $this->assertSame('vacio', $c->get('estado.Suma.fase'));   // aún no ha contestado el PC

        $t = DB::table('tareas')->where('proceso', 'pc.script')->first();
        $p = json_decode($t->parametros, true);
        $this->assertSame('facturacion', $p['grupo']);
        $this->assertSame(['--client', 'Suma', '--input', '{E0}', '--no-mail'], $p['pasos'][0]['args']);
        $this->assertSame('Suma/Entrada', $p['entradas'][0]['dir']);

        $this->trabajar(['ok' => true, 'pc' => 'PortalExomen', 'pasos' => [['script' => 'procesar_facturas.py', 'ok' => true, 'codigo' => 0,
            'salida' => '12 facturas separadas', 'ficheros' => [['ruta' => '/mnt/f/OneDrive/Facturas/2026-09', 'nombre' => '2026-09', 'subido' => false]]]]]);
        $c->call('revisarTareas')
            ->assertSet('estado.Suma.fase', 'separado')
            ->assertSee('12 facturas separadas')
            ->assertSee('F:\OneDrive\Facturas\2026-09');

        $c->call('enviarCorreos', 'Suma');
        $p2 = json_decode(DB::table('tareas')->where('proceso', 'pc.script')->orderByDesc('id')->first()->parametros, true);
        $this->assertSame(['--client', 'Suma', '--input', '{E0}', '--send'], $p2['pasos'][0]['args']);
        $this->trabajar(['ok' => true, 'pasos' => [['script' => 'procesar_facturas.py', 'ok' => true, 'codigo' => 0, 'salida' => 'enviados', 'ficheros' => []]]]);
        $c->call('revisarTareas')->assertSet('estado.Suma.fase', 'enviado');
    }

    public function test_si_el_pc_falla_la_fase_no_avanza(): void
    {
        $c = Livewire::test(FacturacionPdf::class)
            ->set('archivo.Balerga', UploadedFile::fake()->create('l.pdf', 20, 'application/pdf'))
            ->call('separarPdf', 'Balerga');
        $this->trabajar(['ok' => false, 'pasos' => [['script' => 'procesar_facturas.py', 'ok' => false, 'codigo' => 1, 'salida' => 'no encuentro el PDF', 'ficheros' => []]]]);
        $c->call('revisarTareas')->assertSet('estado.Balerga.fase', 'vacio')->assertSee('código de salida 1');
    }

    public function test_cargar_destinatarios_lee_el_json_del_pc_sin_ensuciar_la_salida(): void
    {
        $c = Livewire::test(FacturacionPdf::class)->call('cargarDestinatarios', 'Suma');
        $json = json_encode(['filas' => [['enviar' => true, 'cliente' => 'X', 'mail' => 'x@y.es', 'idioma' => '']], 'xlsx_path' => '/mnt/f/OneDrive/ToDO.xlsx', 'avisos' => []]);
        $this->trabajar(['ok' => true, 'pasos' => [['script' => 'herramientas/listar_destinatarios.py', 'ok' => true, 'codigo' => 0, 'salida' => $json, 'ficheros' => []]]]);
        $c->call('revisarTareas');
        $this->assertSame('F:\OneDrive\ToDO.xlsx', $c->get('destinatarios.Suma.xlsxPathWindows'));
        $this->assertCount(1, $c->get('destinatarios.Suma.filas'));
        $this->assertStringNotContainsString('filas', $c->get('salida'));
        $this->assertStringNotContainsString('pedido a los PCs', $c->get('salida'));
    }
}

class MenuFalsoFacturacion extends \Livewire\Component
{
    public $entidad;
    public $ruta;

    public function render()
    {
        return '<div></div>';
    }
}
