<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\FacturasOcr;
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
}
