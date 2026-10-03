<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Neteges;
use App\Support\ColaTareas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Neteges en la web: cada acción es una tarea para el PC que guarda la base; los ficheros subidos viajan como entrada. */
class NetegesColaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['contabilidad.ejecucion_local' => false, 'contabilidad.pc_grupos.neteges.pc' => null,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'app.cipher' => 'AES-256-CBC']);
        Schema::create('users', fn ($t) => $t->id());
        (require base_path('database/migrations/2026_10_02_140000_create_trabajadores_tareas_tables.php'))->up();
        (require base_path('database/migrations/2026_10_03_210000_create_estado_procesos_table.php'))->up();
        $this->token = ColaTareas::crearTrabajador('AlexMiniPC');
        $this->h = ['X-Token' => $this->token];
        $this->cap = ['capacidades' => ['pc.script', 'pc.estado', 'pc.fichero']];
    }

    protected string $token;
    protected array $h;
    protected array $cap;

    protected function trabajar(array $resultado, bool $ok = true): array
    {
        $t = $this->postJson('/api/trabajador/siguiente', $this->cap, $this->h)->assertOk()->json('tarea');
        $this->assertNotNull($t);
        $this->postJson("/api/trabajador/tareas/{$t['id']}/fin", ['ok' => $ok, 'resultado' => $resultado, 'log' => ''], $this->h)->assertJson(['ok' => true]);

        return $t;
    }

    protected function estadoDelPc(): array
    {
        return ['neteges.estado' => [
            'estado' => ['base' => ['cuentas' => ['572000000' => ['desde' => '01/01/2026', 'hasta' => '30/09/2026', 'apuntes' => 10]], 'otras_cuentas' => []],
                'ventas' => [], 'plugin' => [], 'neteges' => [], 'extractos' => ['extractos' => [], 'cuentas' => []]],
            'listados' => ['hayBase' => true, 'recibidos' => ['20261002 Mayor.xlsx'], 'remesas' => [], 'plugins' => [], 'sustitucion' => null,
                'conciliacion' => '02/10 18:30', 'carpeta' => 'F:\Claude\Contabilidad\Neteges'],
        ]];
    }

    protected function componente(): Neteges
    {
        $c = new Neteges();
        $c->mount();
        $this->trabajar(['ok' => true, 'estado' => $this->estadoDelPc()]);   // la tarea de estado que pide al entrar
        $c->revisarTareas();

        return $c;
    }

    public function test_al_entrar_pide_el_estado_y_lo_lee_de_la_copia(): void
    {
        $c = $this->componente();
        $this->assertSame([], $c->pendientes);
        $this->assertSame(['572000000'], array_map('strval', array_keys($c->estadoBase['cuentas'])));
        $this->assertSame('02/10 18:30', $c->listadosPc['conciliacion']);
    }

    public function test_subir_el_plan_manda_los_ficheros_al_pc_y_encadena_la_identificacion(): void
    {
        $c = $this->componente();
        $c->subidas = [UploadedFile::fake()->create('Plan de Cuentas.xlsx', 5)];
        $c->procesarSubidas('plan');

        $tid = array_key_first($c->pendientes);
        $t = DB::table('tareas')->find($tid);
        $p = json_decode($t->parametros, true);
        $this->assertSame('neteges', $p['grupo']);
        $this->assertSame([['archivo' => 'e0', 'nombre' => 'Plan de Cuentas.xlsx', 'dir' => 'Base/Recibidos', 'sello' => true, 'unico' => false]], $p['entradas']);
        $this->assertSame(['--espera', 'plan', '{E0}'], $p['pasos'][0]['args']);
        $this->assertSame('neteges_ventas.py', $p['pasos'][1]['script']);
        $this->assertTrue($p['pasos'][1]['solo_si_ok']);

        // el PC se baja la entrada (solo la tarea que tiene en curso) y la tarea termina
        $this->postJson('/api/trabajador/siguiente', $this->cap, $this->h)->assertOk();
        $this->get("/api/trabajador/tareas/$tid/entrada/e0", $this->h)->assertOk();
        $this->get("/api/trabajador/tareas/$tid/entrada/e9", $this->h)->assertNotFound();
        $this->postJson("/api/trabajador/tareas/$tid/fin", ['ok' => true, 'resultado' => [
            'ok' => true, 'pc' => 'AlexMiniPC',
            'pasos' => [['script' => 'neteges_base.py', 'ok' => true, 'codigo' => 0, 'salida' => 'Plan cargado', 'ficheros' => []],
                ['script' => 'neteges_ventas.py', 'ok' => true, 'codigo' => 0, 'salida' => 'Cuentas rehechas', 'ficheros' => []]],
            'estado' => $this->estadoDelPc(),
        ]], $this->h)->assertJson(['ok' => true]);
        $c->revisarTareas();

        $this->assertStringStartsWith('===== Neteges · plan de cuentas =====', $c->salida);
        $this->assertStringContainsString('Plan cargado', $c->salida);
        $this->assertStringContainsString('Cuentas rehechas', $c->salida);
        $this->assertStringNotContainsString('pedido a los PCs', $c->salida);
        $this->assertSame([], $c->pendientes);
        // limpieza: la carpeta de entradas de la tarea
        foreach (glob(ColaTareas::carpetaEntradas($tid).'/*') as $f) {
            unlink($f);
        }
        @rmdir(ColaTareas::carpetaEntradas($tid));
        @rmdir(ColaTareas::carpetaFicheros($tid));
    }

    public function test_la_tarea_de_neteges_va_al_pc_fijado(): void
    {
        config(['contabilidad.pc_grupos.neteges.pc' => 'PortalExomen']);
        $c = new Neteges();
        $c->mount();
        $this->assertSame('PortalExomen', DB::table('tareas')->where('proceso', 'pc.estado')->value('destino'));
        // AlexMiniPC no la coge
        $this->assertNull($this->postJson('/api/trabajador/siguiente', $this->cap, $this->h)->json('tarea'));
    }

    public function test_descargar_pide_el_fichero_al_pc_y_lo_ofrece_firmado(): void
    {
        $c = $this->componente();
        $this->assertNull($c->descargar('Output/Conciliacion cobros Neteges.xlsx'));
        $tid = array_key_first($c->pendientes);
        $p = json_decode(DB::table('tareas')->find($tid)->parametros, true);
        $this->assertSame(['grupo' => 'neteges', 'relativa' => 'Output/Conciliacion cobros Neteges.xlsx'], $p);

        $this->postJson('/api/trabajador/siguiente', $this->cap, $this->h)->assertOk();
        $this->call('POST', "/api/trabajador/tareas/$tid/fichero", [], [], [], ['HTTP_X-Token' => $this->token, 'HTTP_X-Nombre' => base64_encode('Conciliacion cobros Neteges.xlsx')], 'xlsx!')->assertOk();
        $this->postJson("/api/trabajador/tareas/$tid/fin", ['ok' => true, 'resultado' => ['ok' => true, 'nombre' => 'Conciliacion cobros Neteges.xlsx']], $this->h)->assertOk();
        $c->revisarTareas();
        $this->assertSame([], $c->pendientes);

        $u = new \App\Models\User();
        $u->id = 1;
        $u->activo = true;
        $u->debe_cambiar_password = false;
        $this->actingAs($u);
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('contabilidad.tarea-fichero', now()->addMinutes(5), ['id' => $tid, 'nombre' => 'Conciliacion cobros Neteges.xlsx']);
        $this->get($url)->assertOk()->assertDownload('Conciliacion cobros Neteges.xlsx');
        $this->get('/contabilidad/tarea-fichero/'.$tid.'/Conciliacion%20cobros%20Neteges.xlsx')->assertForbidden();   // sin firma
        unlink(ColaTareas::carpetaFicheros($tid).'/Conciliacion cobros Neteges.xlsx');
        rmdir(ColaTareas::carpetaFicheros($tid));
    }

    public function test_rutas_inseguras_no_se_aceptan(): void
    {
        foreach (['../x', '/etc/passwd', 'C:\\x', 'a/../../b', 'a\\..\\b', ''] as $r) {
            $this->assertFalse(ColaTareas::rutaRelativaSegura($r), $r);
        }
        $this->assertTrue(ColaTareas::rutaRelativaSegura('Output/Conciliacion cobros Neteges.xlsx'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        ColaTareas::crear('pc.fichero', ['grupo' => 'neteges', 'relativa' => '../../.env']);
    }

    public function test_la_pantalla_se_pinta_en_la_web(): void
    {
        DB::table('users')->insert(['id' => 1]);
        $u = new \App\Models\User();
        $u->id = 1;
        $this->actingAs($u);
        $dir = sys_get_temp_dir().'/vistas_test_'.getmypid();
        @mkdir($dir.'/livewire/contabilidad', 0777, true);
        file_put_contents($dir.'/livewire/contabilidad/_subnav.blade.php', '');
        view()->getFinder()->prependLocation($dir);
        \Livewire\Livewire::component('menu', MenuFalsoNeteges::class);
        ColaTareas::guardarEstado('neteges.estado', $this->estadoDelPc()['neteges.estado']);
        \Livewire\Livewire::test(Neteges::class)
            ->assertSee('PCs de trabajo')
            ->assertSee('AlexMiniPC')
            ->assertSee('572000000')
            ->assertSee('F:\Claude\Contabilidad\Neteges');
    }
}

class MenuFalsoNeteges extends \Livewire\Component
{
    public $entidad;
    public $ruta;

    public function render()
    {
        return '<div></div>';
    }
}
