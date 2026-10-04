<?php

namespace Tests\Feature;

use App\Support\FicherosBase;
use Tests\TestCase;

/** Ficheros base centrales por empresa: historial, último, y detección de contenido repetido. */
class FicherosBaseTest extends TestCase
{
    protected string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->raiz = sys_get_temp_dir().'/fb-'.uniqid();
        config(['contabilidad.ficheros_base_dir' => $this->raiz]);
    }

    protected function tearDown(): void
    {
        $b = function (string $d) use (&$b) {
            foreach (glob($d.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) {
                is_dir($f) ? $b($f) : unlink($f);
            }
            @rmdir($d);
        };
        $b($this->raiz);
        parent::tearDown();
    }

    protected function xlsx(string $contenido): string
    {
        $f = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($f, $contenido);

        return $f;
    }

    public function test_guarda_con_historial_y_el_ultimo_es_el_mas_reciente(): void
    {
        $a = FicherosBase::guardar(92, 'mayor', $this->xlsx('uno'), 'Mayor Durcal.xlsx', 'Facturas OCR');
        $this->assertFileExists($a);
        sleep(1);
        $b = FicherosBase::guardar(92, 'mayor', $this->xlsx('dos'), 'Mayor Durcal V2.xlsx', 'Revisión del mayor');
        $h = FicherosBase::historial(92, 'mayor');
        $this->assertCount(2, $h);
        $this->assertSame($b, FicherosBase::ultimo(92, 'mayor')['ruta']);
        $this->assertSame('Mayor Durcal V2.xlsx', FicherosBase::ultimo(92, 'mayor')['nombre']);
        $this->assertSame('Revisión del mayor', FicherosBase::ultimo(92, 'mayor')['origen']);
        $this->assertNull(FicherosBase::ultimo(92, 'plan'));
        $this->assertNull(FicherosBase::ultimo(93, 'mayor'), 'cada empresa tiene los suyos');
    }

    public function test_reconoce_el_mismo_contenido_aunque_cambie_el_nombre(): void
    {
        FicherosBase::guardar(5, 'plan', $this->xlsx('plan A'), 'Plan.xlsx');
        $this->assertTrue(FicherosBase::existeContenido(5, 'plan', $this->xlsx('plan A')));
        $this->assertFalse(FicherosBase::existeContenido(5, 'plan', $this->xlsx('plan B')));
    }

    public function test_tipo_no_valido_se_rechaza(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        FicherosBase::dir(1, '../etc');
    }
}
