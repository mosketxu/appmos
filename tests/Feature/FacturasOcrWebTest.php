<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\FacturasOcr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Facturas OCR en la web (VPS): las facturas se suben con huella y no se vuelven a subir las que ya se conocen. */
class FacturasOcrWebTest extends TestCase
{
    protected string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        \Tests\Support\TablasCola::crear();
        $this->raiz = sys_get_temp_dir().'/focr_test_'.getmypid();
        @mkdir($this->raiz.'/codigo/Durcal', 0777, true);
        @mkdir($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal', 0777, true);
        file_put_contents($this->raiz.'/codigo/Durcal/cliente.json', json_encode(['entidad_id' => 92, 'nif' => 'B1', 'datos' => '{OneDrive}/_Clientes/_FacturasOCR/Durcal']));
        config(['contabilidad.facturasocr_web' => true, 'contabilidad.facturasocr_dir' => $this->raiz.'/codigo',
            'contabilidad.facturasocr_onedrive' => $this->raiz.'/OneDrive', 'contabilidad.ejecucion_local' => false]);
    }

    protected function tearDown(): void
    {
        $b = function (string $d) use (&$b) {
            foreach (glob($d.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
                if (in_array(basename($f), ['.', '..'], true)) {
                    continue;
                }
                is_dir($f) ? $b($f) : unlink($f);
            }
            @rmdir($d);
        };
        $b($this->raiz);
        parent::tearDown();
    }

    protected function pdf(string $contenido): \Illuminate\Http\UploadedFile
    {
        $f = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($f, "%PDF-1.4\n".$contenido);

        return new \Illuminate\Http\UploadedFile($f, "factura {$contenido}.pdf", 'application/pdf', null, true);
    }

    protected function componente(): FacturasOcr
    {
        $c = new FacturasOcr();
        $c->cliente = 'Durcal';   // sin mount(): no hay tabla de entidades en esta prueba
        $c->ciclo = '';           // sin IVA elegido no se lanza la lectura (ya se probó el Python aparte)

        return $c;
    }

    public function test_el_datos_cuelga_de_la_copia_de_onedrive_del_servidor(): void
    {
        $this->assertSame($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal', FacturasOcr::rutaDatos($this->raiz.'/codigo/Durcal'));
    }

    public function test_solo_se_guardan_las_facturas_nuevas_y_se_avisa_de_las_repetidas(): void
    {
        $c = $this->componente();
        $a = $this->pdf('A');
        $idA = substr(sha1_file($a->getRealPath()), 0, 12);
        $c->pdfsSubidos = [$a, $this->pdf('B')];
        $r = $c->recibirPdfs();
        $this->assertCount(2, $r['guardadas']);
        $this->assertFileExists($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal/Entrada/factura A.pdf');

        // el navegador pregunta antes de subir: la A (en la entrada) ya se conoce, la C no
        $c2 = $this->componente();
        $c3 = $this->pdf('C');
        $q = $c2->huellasNuevas([[$idA, 'factura A.pdf'], [substr(sha1_file($c3->getRealPath()), 0, 12), 'factura C.pdf']]);
        $this->assertSame(['ya está en el servidor'], array_values($q['conocidas']));
        $this->assertArrayHasKey($idA, $q['conocidas']);

        // aunque llegue igualmente, no se duplica
        $c2->pdfsSubidos = [$this->pdf('A'), $c3];
        $r2 = $c2->recibirPdfs();
        $this->assertCount(1, $r2['repetidas']);
        $this->assertCount(1, $r2['guardadas']);
        $this->assertCount(3, glob($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal/Entrada/*.pdf'));
    }

    public function test_una_factura_ya_validada_no_se_vuelve_a_subir_y_lo_que_no_es_pdf_se_rechaza(): void
    {
        $c = $this->componente();
        $v = $this->pdf('V');
        $id = substr(sha1_file($v->getRealPath()), 0, 12);
        file_put_contents($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal/facturas.json', json_encode(['facturas' => [['id' => $id, 'estado' => 'validada', 'ruta' => '/x/v.pdf']]]));
        $this->assertSame('ya validada', $c->huellasNuevas([[$id, 'v.pdf']])['conocidas'][$id]);

        $falso = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($falso, 'no soy un pdf');
        $c->pdfsSubidos = [new \Illuminate\Http\UploadedFile($falso, 'falso.pdf', 'application/pdf', null, true), $v];
        $r = $c->recibirPdfs();
        $this->assertSame(['falso.pdf'], $r['rechazadas']);
        $this->assertCount(1, $r['repetidas']);
        $this->assertSame([], $r['guardadas']);
    }

    public function test_la_api_del_pc_solo_da_lo_que_toca_y_con_huella(): void
    {
        $token = \App\Support\ColaTareas::crearTrabajador('PC');
        $anio = date('Y');
        $datos = $this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal';
        $mes = $this->raiz."/OneDrive/_Clientes/$anio/Durcal $anio/_Facturas/09";
        @mkdir($mes, 0777, true);
        @mkdir($datos.'/_texto', 0777, true);
        file_put_contents($datos.'/facturas.json', '{"facturas":[]}');
        file_put_contents($datos.'/_texto/x.txt', 'regenerable');
        file_put_contents($mes.'/a.pdf', '%PDF a');
        file_put_contents($this->raiz.'/OneDrive/secreto.txt', 'no');
        file_put_contents($this->raiz.'/codigo/Durcal/cliente.json', json_encode(['entidad_id' => 92, 'datos' => '{OneDrive}/_Clientes/_FacturasOCR/Durcal',
            'carpeta_recibidas' => '{OneDrive}/_Clientes/{AAAA}/Durcal {AAAA}/_Facturas/{MM}']));

        $this->getJson('/api/trabajador/facturasocr/Durcal/manifest')->assertForbidden();
        $h = ['X-Token' => $token];
        $m = $this->getJson('/api/trabajador/facturasocr/Durcal/manifest', $h)->assertOk()->json('ficheros');
        $rutas = array_column($m, 'ruta');
        $this->assertContains('_Clientes/_FacturasOCR/Durcal/facturas.json', $rutas);
        $this->assertContains("_Clientes/$anio/Durcal $anio/_Facturas/09/a.pdf", $rutas);
        $this->assertSame([], array_values(array_filter($rutas, fn ($r) => str_contains($r, '_texto') || str_contains($r, 'secreto'))));
        $this->assertSame(hash('sha256', '{"facturas":[]}'), $m[array_search('_Clientes/_FacturasOCR/Durcal/facturas.json', $rutas)]['sha256']);

        $this->get('/api/trabajador/facturasocr/Durcal/archivo?ruta='.urlencode('_Clientes/_FacturasOCR/Durcal/facturas.json'), $h)->assertOk();
        $this->get('/api/trabajador/facturasocr/Durcal/archivo?ruta='.urlencode('secreto.txt'), $h)->assertNotFound();
        $this->get('/api/trabajador/facturasocr/Durcal/archivo?ruta='.urlencode('_Clientes/_FacturasOCR/Durcal/_texto/x.txt'), $h)->assertNotFound();
        $this->get('/api/trabajador/facturasocr/Durcal/archivo?ruta='.urlencode('_Clientes/_FacturasOCR/Durcal/../../secreto.txt'), $h)->assertNotFound();
    }

    /** PDF válido de una página en blanco: sin texto, o sea «escaneado» para ocr_previo.py. */
    protected function pdfEscaneado(string $marca): \Illuminate\Http\UploadedFile
    {
        $f = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($f, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n%$marca\ntrailer<</Root 1 0 R>>\n");

        return new \Illuminate\Http\UploadedFile($f, "escaneada {$marca}.pdf", 'application/pdf', null, true);
    }

    public function test_las_escaneadas_pasan_por_el_ocr_de_windows_de_un_pc_antes_de_leerse(): void
    {
        $py = getenv('HOME').'/appmos/storage/app/venv-facturasocr/bin/python';
        if (! is_executable($py)) {
            $this->markTestSkipped('No hay venv de Facturas OCR en este PC');
        }
        config(['contabilidad.facturasocr_python' => $py, 'contabilidad.facturasocr_dir' => '/mnt/f/Claude/Contabilidad/FacturasOcr', 'contabilidad.facturasocr_ocr_windows_auto' => true]);
        @mkdir('/mnt/f/Claude/Contabilidad/FacturasOcr', 0777, true);
        if (! is_file('/mnt/f/Claude/Contabilidad/FacturasOcr/ocr_previo.py')) {
            $this->markTestSkipped('No está ocr_previo.py');
        }
        // el cliente de la prueba vive en su propia carpeta de código; ocr_previo.py se llama desde la carpeta real
        $token = \App\Support\ColaTareas::crearTrabajador('PC');
        \Illuminate\Support\Facades\DB::table('trabajadores')->update(['ultimo_latido' => now()]);
        $c = new class extends FacturasOcr {
            public int $analisis = 0;
            protected function lanzarAnalisis(): void
            {
                $this->analisis++;
            }
            protected function baseDir(): string
            {
                return '/mnt/f/Claude/Contabilidad/FacturasOcr';
            }
            protected function dirCliente(): string
            {
                return config('contabilidad.facturasocr_dir_cliente');
            }
        };
        config(['contabilidad.facturasocr_dir_cliente' => $this->raiz.'/codigo/Durcal']);
        $c->cliente = 'Durcal';
        $c->ciclo = 'T';
        $c->periodo = '2026-3T';
        $esc = $this->pdfEscaneado('A');
        $id = substr(sha1_file($esc->getRealPath()), 0, 12);
        $c->pdfsSubidos = [$esc, $this->pdf('con texto')];
        $c->recibirPdfs();

        $this->assertSame(0, $c->analisis, 'espera al OCR del PC: '.$c->salida);
        $this->assertTrue($c->leyendo);
        $t = \Illuminate\Support\Facades\DB::table('tareas')->where('proceso', 'pc.script')->first();
        $p = json_decode($t->parametros, true);
        $this->assertSame('facturasocr', $p['grupo']);
        $this->assertSame('ocr_previo.py', $p['pasos'][0]['script']);
        $this->assertCount(1, $p['entradas']);   // solo la escaneada

        // el PC la coge, hace el OCR y sube el json de lo leído
        $h = ['X-Token' => $token];
        $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.script']], $h)->assertOk();
        $this->call('POST', "/api/trabajador/tareas/{$t->id}/fichero", [], [], [], ['HTTP_X-Token' => $token, 'HTTP_X-Nombre' => base64_encode("$id.json")], '{"p0|r0|d250|":"texto de windows"}')->assertOk();
        $this->postJson("/api/trabajador/tareas/{$t->id}/fin", ['ok' => true, 'resultado' => ['ok' => true, 'pasos' => [['script' => 'ocr_previo.py', 'ok' => true, 'codigo' => 0, 'salida' => '1 de 1', 'ficheros' => [['ruta' => "/x/$id.json", 'nombre' => "$id.json", 'subido' => true]]]]]], $h)->assertOk();
        $c->revisarLectura();

        $this->assertFileExists($this->raiz."/OneDrive/_Clientes/_FacturasOCR/Durcal/_ocr/$id.json");
        $this->assertSame(1, $c->analisis, 'con el OCR ya en la caché empieza la lectura');
    }

    public function test_sin_ningun_pc_conectado_se_lee_ya_con_tesseract(): void
    {
        $c = new class extends FacturasOcr {
            public int $analisis = 0;
            protected function lanzarAnalisis(): void
            {
                $this->analisis++;
            }
        };
        $c->cliente = 'Durcal';
        $c->ciclo = 'T';
        $c->pdfsSubidos = [$this->pdf('Z')];
        $c->recibirPdfs();
        $this->assertSame(1, $c->analisis);
    }

    public function test_por_defecto_se_lee_con_tesseract_sin_esperar_a_ningun_pc_y_el_escaneo_de_calidad_es_por_factura(): void
    {
        \App\Support\ColaTareas::crearTrabajador('PC');
        DB::table('trabajadores')->update(['ultimo_latido' => now()]);
        $c = new class extends FacturasOcr {
            public int $analisis = 0;
            protected function lanzarAnalisis(): void
            {
                $this->analisis++;
            }
        };
        $c->cliente = 'Durcal';
        $c->ciclo = 'T';
        $c->pdfsSubidos = [$this->pdfEscaneado('B')];
        $c->recibirPdfs();
        $this->assertSame(1, $c->analisis, 'con un PC conectado, por defecto no espera al OCR de Windows');
        $this->assertSame(0, DB::table('tareas')->where('proceso', 'pc.script')->count());

        // «Escaneo de calidad» de una factura concreta: tarea para un PC con --forzar y su PDF como entrada
        $pdf = glob($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal/Entrada/*.pdf')[0];
        $id = substr(sha1_file($pdf), 0, 12);
        file_put_contents($this->raiz.'/OneDrive/_Clientes/_FacturasOCR/Durcal/facturas.json', json_encode(['facturas' => [['id' => $id, 'estado' => 'pendiente', 'ruta' => $pdf]]]));
        $c->sel = $id;
        $c->escaneoDeCalidad();
        $t = DB::table('tareas')->where('proceso', 'pc.script')->first();
        $this->assertNotNull($t);
        $p = json_decode($t->parametros, true);
        $this->assertSame('facturasocr', $p['grupo']);
        $this->assertContains('--forzar', $p['pasos'][0]['args']);
        $this->assertSame($id.'.pdf', $p['entradas'][0]['nombre']);
    }

    public function test_al_cambiar_base_o_porcentaje_la_cuota_se_calcula_sola(): void
    {
        $c = $this->componente();
        $c->form = ['lineas' => [['base' => '100,50', 'pct' => '21', 'cuota' => '0.00'], ['base' => '', 'pct' => '10', 'cuota' => ''], ['base' => '10', 'pct' => '', 'cuota' => '5']]];
        $c->updatedForm('100,50', 'lineas.0.base');
        $this->assertSame('21.11', $c->form['lineas'][0]['cuota']);   // 100,50 × 21 % = 21,105 → 21,11
        $c->form['lineas'][0]['pct'] = '10';
        $c->updatedForm('10', 'lineas.0.pct');
        $this->assertSame('10.05', $c->form['lineas'][0]['cuota']);
        // sin base o sin % no se inventa nada, y editar la cuota a mano no la recalcula
        $c->updatedForm('', 'lineas.1.pct');
        $this->assertSame('', $c->form['lineas'][1]['cuota']);
        $c->updatedForm('5', 'lineas.2.cuota');
        $this->assertSame('5', $c->form['lineas'][2]['cuota']);
    }
}
