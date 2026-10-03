<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Procesos;
use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Procesos FIQ desde la web (VPS): cada botón deja una tarea `fiq.script` para los PCs trabajadores y, al terminar,
 * se hace lo mismo que en local (salida, ficheros, checklist, importes...). Sqlite en memoria, sin PCs reales.
 */
class ProcesosColaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['contabilidad.ejecucion_local' => false,   // el .env de este PC lo tiene a true
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'app.cipher' => 'AES-256-CBC']);
        Schema::create('users', fn ($t) => $t->id());
        (require base_path('database/migrations/2026_10_02_140000_create_trabajadores_tareas_tables.php'))->up();
        (require base_path('database/migrations/2026_10_03_210000_create_estado_procesos_table.php'))->up();
    }

    protected function pc(string $nombre = 'AlexMiniPC'): array
    {
        $token = ColaTareas::crearTrabajador($nombre);
        $cap = ['capacidades' => ['pc.script', 'pc.estado', 'fiq.checklist']];

        return [['X-Token' => $token], $cap];
    }

    protected function componente(): Procesos
    {
        $c = new Procesos();
        $c->mount();

        return $c;
    }

    /** Hace de PC: coge la siguiente tarea y la cierra con $resultado. */
    protected function trabajar(array $h, array $cap, array $resultado, bool $ok = true): array
    {
        $tarea = $this->postJson('/api/trabajador/siguiente', $cap, $h)->assertOk()->json('tarea');
        $this->assertNotNull($tarea);
        $this->postJson("/api/trabajador/tareas/{$tarea['id']}/fin", ['ok' => $ok, 'resultado' => $resultado, 'log' => ''], $h)->assertJson(['ok' => true]);

        return $tarea;
    }

    public function test_buscar_cash_in_store_en_la_web_pasa_por_la_cola_y_carga_el_resultado(): void
    {
        [$h, $cap] = $this->pc();
        $c = $this->componente();
        $mm = str_pad((string) $c->mes, 2, '0', STR_PAD_LEFT);
        // al entrar en la pantalla pide el estado a un PC (no hay copia): primero esa tarea
        $this->assertCount(1, $c->pendientes);
        $this->trabajar($h, $cap, ['ok' => true, 'estado' => ['fiq.checklist_def' => ['procesos' => []]]]);
        $c->revisarTareas();
        $this->assertSame([], $c->pendientes);

        $c->buscarCashInStore();
        $tareas = DB::table('tareas')->where('proceso', 'pc.script')->get();
        $this->assertCount(1, $tareas);
        $params = json_decode($tareas[0]->parametros, true);
        $this->assertSame('CashInStore/cashInStore.py', $params['pasos'][0]['script']);
        $this->assertSame([(string) $c->mes, '--buscar'], $params['pasos'][0]['args']);
        $this->assertStringContainsString('pedido a los PCs', $c->salida);

        // el mismo botón dos veces no duplica
        $c->buscarCashInStore();
        $this->assertSame(1, DB::table('tareas')->where('proceso', 'pc.script')->count());

        $this->trabajar($h, $cap, [
            'ok' => true, 'pc' => 'AlexMiniPC',
            'pasos' => [['script' => 'CashInStore/cashInStore.py', 'ok' => true, 'codigo' => 0, 'salida' => "Buscando...\nCASH_JSON: {}\nHecho", 'ficheros' => []]],
            'estado' => ["fiq.cashInStore.{$mm}" => ['BCN' => ['cash' => 1234.5, 'petty' => 100, 'asunto' => 'Cierre', 'recibido' => '01/10', 'lineas' => ['x'], 'anterior' => [0, 50]]]],
        ]);
        $c->revisarTareas();

        $this->assertSame([], $c->pendientes);
        $this->assertStringContainsString('Buscando...', $c->salida);
        $this->assertStringNotContainsString('CASH_JSON', $c->salida);
        $this->assertStringContainsString('en AlexMiniPC', $c->salida);
        $this->assertSame('1.234,50', $c->cisFilas['BCN']['cash']);
        $this->assertTrue($c->cisFilas['BCN']['encontrado']);
    }

    public function test_proceso_de_la_tabla_marca_el_checklist_al_terminar_bien(): void
    {
        [$h, $cap] = $this->pc();
        ColaTareas::guardarEstado('fiq.checklist_def', ['procesos' => [['id' => 'monthly_sales', 'nombre' => 'MS']]]);
        $c = $this->componente();
        $c->mes = 9;
        $c->ejecutar('monthly_sales');
        $tarea = $this->trabajar($h, $cap, [
            'ok' => true, 'pc' => 'AlexMiniPC',
            'pasos' => [['script' => 'monthlyFIQ.js', 'ok' => true, 'codigo' => 0, 'salida' => 'ok', 'ficheros' => [['ruta' => '/mnt/e/x/a.xlsx', 'nombre' => 'a.xlsx', 'subido' => true]]]],
        ]);
        $this->assertSame(['09', '--real'], json_decode(DB::table('tareas')->find($tarea['id'])->parametros, true)['pasos'][0]['args']);
        $c->revisarTareas();

        $this->assertSame('ok', ColaTareas::estado('fiq.checklist_estado')['marcas']['2026-09']['monthly_sales']['estado']);
        // y se pide a un PC que lo apunte también en OneDrive
        $cl = DB::table('tareas')->where('proceso', 'fiq.checklist')->first();
        $this->assertSame('marca', json_decode($cl->parametros, true)['op']);
        $this->assertSame('E:\x\a.xlsx (AlexMiniPC)', $c->resultados['monthly_sales'][0]['ruta']);
        $this->assertSame($tarea['id'], $c->resultados['monthly_sales'][0]['tarea']);
    }

    public function test_si_falla_no_marca_y_enseña_el_error(): void
    {
        [$h, $cap] = $this->pc();
        ColaTareas::guardarEstado('fiq.checklist_def', ['procesos' => [['id' => 'monthly_sales', 'nombre' => 'MS']]]);
        $c = $this->componente();
        $c->ejecutar('monthly_sales');
        $this->trabajar($h, $cap, ['ok' => false, 'pasos' => [['script' => 'monthlyFIQ.js', 'ok' => false, 'codigo' => 2, 'salida' => 'boom', 'ficheros' => []]]], false);
        $c->revisarTareas();

        $this->assertStringContainsString('código de salida 2', $c->salida);
        $this->assertNull(ColaTareas::estado('fiq.checklist_estado'));
        $this->assertSame(0, DB::table('tareas')->where('proceso', 'fiq.checklist')->count());
    }

    public function test_cancelar_una_tarea_pendiente(): void
    {
        $this->pc();
        $c = $this->componente();
        $c->buscarCashInStore();
        $tid = DB::table('tareas')->where('proceso', 'pc.script')->value('id');
        $c->cancelarTarea($tid);
        $this->assertSame('cancelada', DB::table('tareas')->find($tid)->estado);
        $this->assertSame([], array_filter($c->pendientes, fn ($p) => $p['tipo'] === 'script'));
    }

    public function test_la_web_no_acepta_scripts_fuera_de_la_lista(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => '../../etc/passwd', 'args' => []]]]);
    }

    public function test_subida_y_descarga_de_ficheros_de_una_tarea(): void
    {
        [$h, $cap] = $this->pc();
        $id = ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'monthlyFIQ.js', 'args' => ['09']]]]);
        $this->postJson('/api/trabajador/siguiente', $cap, $h)->assertOk();
        $this->call('POST', "/api/trabajador/tareas/$id/fichero", [], [], [], ['HTTP_X-Token' => $h['X-Token'], 'HTTP_X-Nombre' => base64_encode('a b.xlsx')], 'contenido')->assertOk();
        $f = ColaTareas::carpetaFicheros($id).'/a b.xlsx';
        $this->assertSame('contenido', file_get_contents($f));
        // un nombre con ruta no sale de la carpeta de la tarea
        $this->call('POST', "/api/trabajador/tareas/$id/fichero", [], [], [], ['HTTP_X-Token' => $h['X-Token'], 'HTTP_X-Nombre' => base64_encode('../../x.txt')], 'z')->assertOk();
        $this->assertFileExists(ColaTareas::carpetaFicheros($id).'/x.txt');

        $c = $this->componente();
        $this->assertNotNull($c->descargarDeTarea($id, 'a b.xlsx'));
        $this->assertNull($c->descargarDeTarea($id, '../a b.xlsx.nada'));
        array_map('unlink', glob(ColaTareas::carpetaFicheros($id).'/*'));
        rmdir(ColaTareas::carpetaFicheros($id));
    }

    public function test_el_pc_preferido_solo_vale_si_esta_conectado(): void
    {
        [$ha, $cap] = $this->pc('AlexMiniPC');
        [$hb] = $this->pc('PortalExomen');
        // A hace una tarea FIQ → la siguiente va preferentemente a A
        ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'monthlyFIQ.js', 'args' => ['09']]]]);
        $this->trabajar($ha, $cap, ['ok' => true, 'pasos' => []]);
        $this->assertSame('AlexMiniPC', ColaTareas::preferido());

        $id = ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'sysSplit.js', 'args' => ['09']]]], null, null, ColaTareas::preferido());
        // B no la coge mientras A esté conectado...
        $this->assertNull($this->postJson('/api/trabajador/siguiente', $cap, $hb)->json('tarea'));
        // ...pero si A se apaga (sin latido), sí
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(10)]);
        $this->assertSame($id, $this->postJson('/api/trabajador/siguiente', $cap, $hb)->json('tarea.id'));
    }

    /** En un PC (ejecucion_local) todo sigue igual: se ejecuta aquí, sin cola, y se hace lo mismo al terminar. */
    public function test_en_local_se_ejecuta_directamente_sin_cola(): void
    {
        config(['contabilidad.ejecucion_local' => true]);
        $c = new class extends Procesos {
            public array $llamadas = [];
            public array $marcas = [];

            protected function ejecutarScript(array $args, int $timeout, string $etiqueta, ?string $cwd = null, array $env = []): array
            {
                $this->llamadas[] = $args;
                $this->salida .= "DATO iva=123.5\nlisto";
                $this->ultimoOk = true;

                return [];
            }

            protected function marcarChecklist(string $id, int $mes, string $estado = 'ok'): void
            {
                $this->marcas[] = [$id, $mes, $estado];
            }

            protected function cargarCashInStore(bool $abrir = false): void
            {
            }
        };
        $c->mes = 9;
        $c->pfMes = 9;
        $c->ejecutar('cashflow');
        $this->assertSame([['cashflow', 9, 'proc']], $c->marcas);   // con envío aparte: «procesado», no «hecho»
        $this->assertSame('python3', $c->llamadas[0][0]);
        $this->assertStringContainsString('===== Cash flow (mes 09, REAL) =====', $c->salida);

        $c->salida = '';
        $c->buscarImportesPagosFinMes();
        $this->assertSame('123,5', $c->pfIva);                       // el DATO se lee y no se enseña
        $this->assertStringNotContainsString('DATO iva', $c->salida);
        $this->assertSame(0, Schema::hasTable('tareas') ? DB::table('tareas')->count() : 0);
        $this->assertSame([], $c->pendientes);
    }

    public function test_la_pantalla_se_pinta_en_la_web(): void
    {
        [$h, $cap] = $this->pc();
        ColaTareas::guardarEstado('fiq.checklist_def', ['procesos' => [['id' => 'monthly_sales', 'nombre' => 'MS', 'auto' => 'tabla']]]);
        DB::table('users')->insert(['id' => 1]);
        $user = new \App\Models\User();
        $user->id = 1;
        $this->actingAs($user);
        // el submenú real consulta las tablas de permisos: se sustituye por uno vacío
        $dir = sys_get_temp_dir().'/vistas_test_'.getmypid();
        @mkdir($dir.'/livewire/contabilidad', 0777, true);
        file_put_contents($dir.'/livewire/contabilidad/_subnav.blade.php', '');
        view()->getFinder()->prependLocation($dir);
        \Livewire\Livewire::component('menu', MenuFalso::class);   // el menú real necesita las tablas de permisos
        \Livewire\Livewire::test(Procesos::class)
            ->assertSee('PCs de trabajo')
            ->assertSee('AlexMiniPC')
            ->call('ejecutar', 'monthly_sales')
            ->assertSee('pedido a los PCs')
            ->assertSee('en cola');
    }
}

class MenuFalso extends \Livewire\Component
{
    public $entidad;
    public $ruta;

    public function render()
    {
        return '<div></div>';
    }
}
