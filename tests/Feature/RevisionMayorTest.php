<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\RevisionMayor;
use App\Models\User;
use App\Support\FicherosBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Revisión del mayor (Proc.Mensuales): sube a los ficheros base centrales, ejecuta el script (aquí uno falso) y permite dar cosas por revisadas. */
class RevisionMayorTest extends TestCase
{
    use RefreshDatabase;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/rm-'.uniqid();
        mkdir($this->dir, 0775, true);
        file_put_contents($this->dir.'/revisar_mayor.py', <<<'PY'
import json, sys
a = sys.argv
salida, js = a[2], a[a.index('--json') + 1]
open(salida, 'w').write('x')
json.dump({'p410000': [], 'pago_factura': [{'clave': 'pf|1|2|10.00', 'pago': {'cuenta': '410001', 'proveedor': 'ACME', 'importe': 10, 'fecha': '2026-01-02', 'asiento': '1', 'texto': 'pago', 'num': '', 'gasto': ''},
    'factura': {'cuenta': '410001', 'proveedor': 'ACME', 'importe': 10, 'fecha': '2026-01-01', 'asiento': '2', 'texto': 'fra', 'num': 'A-1', 'gasto': ''}}],
    'pago_suma': [], 'prov_aplicar': [], 'prov_puntear': [], 'prov_sin_factura': [], 'colgados': [], 'revisadas': {}, 'generado': 'hoy'}, open(js, 'w'))
print('ok')
PY);
        config(['contabilidad.ficheros_base_dir' => $this->dir.'/fb', 'contabilidad.facturasocr_dir' => $this->dir,
            'contabilidad.facturasocr_python' => trim((string) shell_exec('command -v python3'))]);
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
        $b(storage_path('app/revisionmayor/9001'));
        parent::tearDown();
    }

    protected function usuarioConEmpresa(): array
    {
        $u = User::factory()->create(['activo' => true]);
        $id = DB::table('entidades')->insertGetId(['entidad' => 'Empresa Test SL', 'estado' => 1, 'cliente' => 1]);
        DB::table('entidad_user')->insert(['user_id' => $u->id, 'entidad_id' => $id]);

        return [$u, $id];
    }

    public function test_sube_el_mayor_al_central_revisa_y_marca_revisado(): void
    {
        [$u, $id] = $this->usuarioConEmpresa();
        $this->actingAs($u);
        $c = Livewire::test(RevisionMayor::class)->assertSet('entidadId', $id);
        $c->call('revisar')->assertSet('error', 'Falta el mayor de esta empresa: súbelo arriba.');

        $c->set('subMayor', UploadedFile::fake()->create('Mayor 2026.xlsx', 5));
        $this->assertSame('Mayor 2026.xlsx', FicherosBase::ultimo($id, 'mayor')['nombre'], 'el mayor queda en los ficheros base centrales');

        $c->call('revisar')->assertSet('error', '')->assertSee('Pago y factura abiertos del mismo importe')->assertSee('ACME');
        $c->call('marcarRevisado', 'pf|1|2|10.00', 'es correcto')->assertDontSee('A-1');
        $c->set('verRevisadas', true)->assertSee('es correcto');
        $c->call('desmarcarRevisado', 'pf|1|2|10.00');
        $this->assertArrayNotHasKey('pf|1|2|10.00', json_decode(file_get_contents(storage_path('app/revisionmayor/'.$id.'/revisados.json')), true));
        @unlink(storage_path('app/revisionmayor/'.$id.'/revisados.json'));
        foreach (glob(storage_path('app/revisionmayor/'.$id.'/*')) as $f) {
            @unlink($f);
        }
        @rmdir(storage_path('app/revisionmayor/'.$id));
    }

    public function test_no_deja_usar_una_empresa_que_no_es_del_usuario(): void
    {
        [$u] = $this->usuarioConEmpresa();
        $otra = DB::table('entidades')->insertGetId(['entidad' => 'Ajena SL', 'estado' => 1, 'cliente' => 1]);
        $this->actingAs($u);
        Livewire::test(RevisionMayor::class)->set('entidadId', $otra)->assertForbidden();
    }
}
