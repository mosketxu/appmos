<?php

namespace Tests\Feature;

use App\Http\Livewire\FicherosBaseSubida;
use App\Models\Entidad;
use App\Support\FicherosBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Recuadro «Ficheros base de SAGE» que se incrusta en cualquier pantalla (19-oct-2026). */
class FicherosBaseSubidaTest extends TestCase
{
    use RefreshDatabase;

    protected function xlsx(string $nombre, string $textos)
    {
        $ruta = tempnam(sys_get_temp_dir(), 'fb').'.xlsx';
        $z = new \ZipArchive;
        $z->open($ruta, \ZipArchive::CREATE);
        $z->addFromString('xl/sharedStrings.xml', '<sst><si><t>'.$textos.'</t></si></sst>');
        $z->addFromString('[Content_Types].xml', '<Types/>');
        $z->close();

        return UploadedFile::fake()->createWithContent($nombre, (string) file_get_contents($ruta));
    }

    public function test_sube_el_listado_de_proveedores_al_fichero_base_central_y_avisa(): void
    {
        config(['contabilidad.ficheros_base_dir' => sys_get_temp_dir().'/fb-test-'.uniqid()]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'Cliente SL', 'nif' => 'B12345674', 'estado' => 1]);
        Livewire::test(FicherosBaseSubida::class, ['entidadId' => $e->id, 'tipos' => ['proveedores', 'mayor']])
            ->set('subidas.proveedores', $this->xlsx('lisProveedores_X.xlsx', 'CodigoContable CifDni CodigoPostal'))
            ->assertSet('error', '')
            ->assertDispatched('ficheros-base-cambiados');
        $u = FicherosBase::ultimo($e->id, 'proveedores');
        $this->assertSame('lisProveedores_X.xlsx', $u['nombre']);
    }

    public function test_rechaza_un_excel_que_no_es_lo_que_se_dice(): void
    {
        config(['contabilidad.ficheros_base_dir' => sys_get_temp_dir().'/fb-test-'.uniqid()]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'Cliente SL', 'nif' => 'B12345674', 'estado' => 1]);
        Livewire::test(FicherosBaseSubida::class, ['entidadId' => $e->id, 'tipos' => ['proveedores']])
            ->set('subidas.proveedores', $this->xlsx('otra cosa.xlsx', 'Hola'))
            ->assertSee('no parece el listado de proveedores')
            ->assertNotDispatched('ficheros-base-cambiados');
        $this->assertNull(FicherosBase::ultimo($e->id, 'proveedores'));
    }
}
