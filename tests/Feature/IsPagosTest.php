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
        foreach (['lee_200.py', 'textos.py', 'lee_sage.py'] as $f) {
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
        Livewire::test(IsPagos::class)->set('todasEmpresas', true)
            ->set('clienteId', (string) $e->id)->set('ejercicio', 2026)->set('periodo', '2P')
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
        Livewire::test(IsPagos::class)->set('todasEmpresas', true)->set('ejercicio', 2026)->set('periodo', '2P')
            ->set('marcados', [$a->id => true, $b->id => true])->call('preparar')
            ->assertSee('sin cuota positiva')->assertSee('Falta el resultado contable');
        $this->assertFileDoesNotExist($this->dir.'/clientes/B11111111/2026/salida202/B11111111_2026_2P.202');
        $this->assertFileDoesNotExist($this->dir.'/clientes/B22222222/2026/salida202/B22222222_2026_2P.202');
    }

    public function test_el_desplegable_filtra_por_cliente_o_muestra_todos(): void
    {
        $this->actingAs($this->adminUser());
        $a = $this->cliente('B11111111', 'Alfa SL', $this->textoIs('B11111111', 'ALFA SL', '1.000,00', '10.000,00'));
        $b = $this->cliente('B22222222', 'Beta SL', $this->textoIs('B22222222', 'BETA SL', '2.000,00', '10.000,00'));
        $c = Livewire::test(IsPagos::class)->set('todasEmpresas', true)->set('clienteId', (string) $a->id)->set('ejercicio', 2026)->set('periodo', '2P');
        $c->assertSee('B11111111 · pendiente')->assertDontSee('B22222222 · pendiente');
        $c->set('clienteId', 'todos')->assertSee('B11111111 · pendiente')->assertSee('B22222222 · pendiente');
    }

    public function test_por_defecto_solo_salen_las_empresas_del_usuario_y_admin_puede_ver_todas(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        $mia = $this->cliente('A11111111', 'Mia SL', 'x');
        $ajena = $this->cliente('B22222222', 'Ajena SL', 'x');
        DB::table('entidad_user')->insert(['user_id' => $admin->id, 'entidad_id' => $mia->id]);
        $c = Livewire::test(IsPagos::class)->set('ejercicio', 2026)->set('periodo', '2P');
        $c->assertSee('Mia SL')->assertDontSee('Ajena SL');
        $c->set('todasEmpresas', true)->assertSee('Mia SL')->assertSee('Ajena SL');
    }

    public function test_modalidad_403_con_el_pyg_subido_reproduce_el_1p_de_espiral(): void
    {
        $this->actingAs($this->adminUser());
        $e = $this->cliente('B01756089', 'Espiral Trade SL', $this->textoIs('B01756089', 'ESPIRAL TRADE, S.L.', '427.929,91', '13.959.750,00'));
        $csv = "Cuenta;Descripcion;Debe;Haber;Saldo\n700000000;Ventas;0;1500000,00;\n600000000;Compras;1189025,39;0;\n630000000;Impuesto sobre beneficios;71524,16;0;\n";
        Livewire::test(IsPagos::class)->set('todasEmpresas', true)
            ->set('clienteId', (string) $e->id)->set('ejercicio', 2026)->set('periodo', '1P')
            ->set('subidaPyg.'.$e->id, \Illuminate\Http\UploadedFile::fake()->createWithContent('pyg.csv', $csv))
            ->call('preparar')
            ->assertSee('310.974,61')->assertSee('68.414,41')->assertSee('55.073,60');
        $d = $this->dir.'/clientes/B01756089/2026';
        $this->assertFileExists($d.'/fuentes/pyg202.csv');
        $t = file_get_contents($d.'/salida202/B01756089_2026_1P.202');
        $this->assertSame(328 + 700 + 900 + 18, strlen($t));
        $p1 = substr($t, 328, 700);
        $this->assertSame('B', substr($p1, 147, 1));                        // modalidad 40.3
        $this->assertSame('1', substr($p1, 146, 1));                        // 10-20 M€
        $this->assertSame('00000000023945045', substr($p1, 199, 17));       // resultado contable [04]
        $this->assertSame('00000000031097461', substr($p1, 352, 17));       // base imponible previa [13]
        $p2 = substr($t, 328 + 700, 900);
        $this->assertSame('02200', substr($p2, 29, 5));                     // porcentaje [17]
        $this->assertSame('00000000006841441', substr($p2, 102, 17));       // resultado previo [18]
        $this->assertSame('00000000006841441', substr($p2, 484, 17));       // a ingresar [34]
    }

    public function test_modalidad_403_con_importes_a_mano_y_pagos_anteriores(): void
    {
        $this->actingAs($this->adminUser());
        $e = $this->cliente('B01756089', 'Espiral Trade SL', $this->textoIs('B01756089', 'ESPIRAL TRADE, S.L.', '427.929,91', '13.959.750,00'));
        Livewire::test(IsPagos::class)->set('todasEmpresas', true)
            ->set('clienteId', (string) $e->id)->set('ejercicio', 2026)->set('periodo', '2P')
            ->set('ajustes403', [$e->id => ['resultado_contable' => '1.000.000,00', 'gasto_is' => '300.000', 'pagos_anteriores' => '68.414,41']])
            ->call('preparar')->assertSee('1.300.000,00');
        $j = json_decode(file_get_contents($this->dir.'/clientes/B01756089/2026/salida202/B01756089_2026_2P.json'), true);
        $this->assertSame(1300000.0, $j['detalle']['13']);
        $this->assertSame(285999.99 + 0.01, round($j['detalle']['18'], 2));      // 22 % de 1.300.000
        $this->assertSame(round(286000 - 68414.41, 2), round($j['detalle']['32'], 2));
        $this->assertSame(round(230000 - 68414.41, 2), round($j['detalle']['33'], 2));   // mínimo 23 % del resultado menos pagos anteriores
        $this->assertSame(round(286000 - 68414.41, 2), round($j['detalle']['34'], 2));
    }

    public function test_al_preparar_deja_un_borrador_en_outlook_con_lo_no_aplicado(): void
    {
        config(['contabilidad.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'sender' => 'alex.arregui@sumaempresa.com', 'redirect' => null, 'm202_revisores' => ['revisora@sumaempresa.com']]]);
        \Illuminate\Support\Facades\Cache::put('graph_token', 'tok', 60);
        \Illuminate\Support\Facades\Http::fake([
            'graph.microsoft.com/v1.0/users/*/messages' => \Illuminate\Support\Facades\Http::response(['id' => 'M1'], 201),
            'graph.microsoft.com/v1.0/users/*/messages/M1/attachments' => \Illuminate\Support\Facades\Http::response(['id' => 'A1'], 201),
        ]);
        $this->actingAs($this->adminUser());
        $e = $this->cliente('B01756089', 'Espiral Trade SL', $this->textoIs('B01756089', 'ESPIRAL TRADE, S.L.', '427.929,91', '13.959.750,00'));
        Livewire::test(IsPagos::class)->set('todasEmpresas', true)
            ->set('clienteId', (string) $e->id)->set('ejercicio', 2026)->set('periodo', '2P')
            ->set('ajustes403', [$e->id => ['resultado_contable' => '1000000', 'pagos_anteriores' => '68414,41']])
            ->call('preparar')->assertSee('borrador dejado en Outlook')->assertSee('Lo que NO se ha aplicado');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && $r['toRecipients'][0]['emailAddress']['address'] === 'revisora@sumaempresa.com'
            && str_contains($r['body']['content'], 'NO APLICADO') && str_contains($r['subject'], 'ESPIRAL TRADE SL'));
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/attachments') && str_ends_with($r['name'], '.202'));
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sendMail'));
    }
}
