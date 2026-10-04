<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\LeoyBra;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** LeoyBra: sube ficheros a la carpeta del cliente y lanza el motor (aquí, uno falso que imita sus dos órdenes). */
class LeoyBraTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/leoybra-test-'.uniqid();
        mkdir($this->dir, 0775, true);
        file_put_contents($this->dir.'/leoybra.py', <<<'PY'
import json, os, sys
d = os.environ['LEOYBRA_DATOS']
if sys.argv[1] == 'estado':
    per = {}
    for p in (os.listdir(f'{d}/Output') if os.path.isdir(f'{d}/Output') else []):
        per[p] = {'resumen': json.load(open(f'{d}/Output/{p}/resumen.json'))}
    print(json.dumps({'base': {}, 'periodos': per}))
else:
    p = sys.argv[2]
    os.makedirs(f'{d}/Output/{p}', exist_ok=True)
    open(f'{d}/Output/{p}/PluginBancos.xlsx', 'w').write('x')
    json.dump({'periodo': p, 'generado': 'hoy', 'datos': 'd.xlsx', 'emitidas': 1, 'recibidas': 2, 'sin_iva': 0, 'movimientos': 3,
               'iva': {'repercutido': 1, 'soportado': 1, 'resultado_trimestre': 0, 'compensacion_anterior': 5, 'a_compensar_final': 5, 'a_ingresar': 0},
               'archivos': [f'Output/{p}/PluginBancos.xlsx'], 'avisos': [{'tipo': 'bancos', 'texto': 'mira esto'}]},
              open(f'{d}/Output/{p}/resumen.json', 'w'))
    print('ok', ' '.join(sys.argv[3:]))
PY);
        Livewire::component('menu', new class extends \Livewire\Component {   // el menú real consulta entidades (BD)
            public $entidad;
            public $ruta;

            public function render()
            {
                return '<div></div>';
            }
        });
        config(['contabilidad.leoybra_dir' => $this->dir, 'contabilidad.leoybra_python' => trim((string) shell_exec('command -v python3'))]);
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

    public function test_sube_base_datos_y_pdfs_genera_y_deja_descargar(): void
    {
        $c = Livewire::test(LeoyBra::class)->set('periodo', '2026-3T');
        $c->set('subidas', [UploadedFile::fake()->create('Mayor 2026.xlsx', 5)])->call('procesarSubidas', 'mayor');
        $c->set('subidas', [UploadedFile::fake()->create('prov.xlsx', 5)])->call('procesarSubidas', 'proveedores');
        $c->set('subidas', [UploadedFile::fake()->create('prov2.xlsx', 5)])->call('procesarSubidas', 'proveedores');
        $this->assertCount(1, glob($this->dir.'/Base/mayor_*'));
        $this->assertCount(1, glob($this->dir.'/Base/proveedores_*'), 'de los demás solo vale el último');
        $this->assertCount(1, glob($this->dir.'/Base/OLD/proveedores_*'));

        $c->call('generar')->assertSet('error', 'Falta el fichero de datos del trimestre (ventas, compras, banco y tarjeta).');
        $c->set('subidas', [UploadedFile::fake()->create('trim.xlsx', 5)])->call('procesarSubidas', 'datos');
        $c->set('subidas', [UploadedFile::fake()->create('f1.pdf', 5), UploadedFile::fake()->create('x.exe', 5)])->call('procesarSubidas', 'pdfs');
        $this->assertCount(1, glob($this->dir.'/Datos/2026-3T/PDF/*'));

        $c->set('numeroInicial', '300')->call('generar')->assertSet('error', '');
        $this->assertFileExists($this->dir.'/Output/2026-3T/PluginBancos.xlsx');
        $c->assertSee('mira esto')->assertSee('PluginBancos.xlsx');
        $c->call('descargar', 'Output/2026-3T/PluginBancos.xlsx')->assertFileDownloaded('PluginBancos.xlsx');
        $c->call('descargar', '../etc/passwd')->assertSet('error', 'No se encuentra ../etc/passwd.');
    }

    public function test_rechaza_ficheros_que_no_son_excel_como_base(): void
    {
        Livewire::test(LeoyBra::class)->set('subidas', [UploadedFile::fake()->create('mayor.pdf', 5)])->call('procesarSubidas', 'mayor')
            ->assertSet('error', '«mayor.pdf» no es un Excel (.xlsx o .xls).');
        $this->assertEmpty(glob($this->dir.'/Base/*'));
    }
}
