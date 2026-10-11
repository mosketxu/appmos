<?php

namespace Tests\Feature;

use App\Support\FacturasOcrEntrada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Importar PDF a la Entrada de un cliente de Facturas OCR (Genérico → Facturas OCR, Traer de Z:). */
class FacturasOcrEntradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_lo_nuevo_y_no_repite_lo_conocido(): void
    {
        $base = sys_get_temp_dir().'/focr-'.uniqid();
        mkdir($base.'/Cli/datos', 0775, true);
        file_put_contents($base.'/Cli/cliente.json', json_encode(['entidad_id' => 1, 'datos' => $base.'/Cli/datos']));
        config(['contabilidad.facturasocr_dir' => $base, 'contabilidad.facturasocr_onedrive' => null]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        $pdf = fn ($t) => tap(tempnam(sys_get_temp_dir(), 'p'), fn ($f) => file_put_contents($f, '%PDF-1.4 '.$t));
        $a = $pdf('uno'); $b = $pdf('dos'); $malo = tempnam(sys_get_temp_dir(), 'm');
        file_put_contents($malo, 'no es pdf');
        $r = FacturasOcrEntrada::importar('Cli', [['ruta' => $a, 'nombre' => 'Xate Fra 1.pdf'], ['ruta' => $malo, 'nombre' => 'x.pdf'], ['ruta' => $a, 'nombre' => 'otra.pdf']]);
        $this->assertSame(['Xate Fra 1.pdf'], $r['guardadas']);
        $this->assertSame(['otra.pdf'], $r['repetidas']);
        $this->assertSame(['x.pdf'], $r['rechazadas']);
        // lo ya en la lista de facturas (otra ruta, mismo contenido) tampoco se repite
        file_put_contents($base.'/Cli/datos/facturas.json', json_encode(['facturas' => [['id' => substr(sha1_file($b), 0, 12), 'estado' => 'validada']]]));
        $r2 = FacturasOcrEntrada::importar('Cli', [['ruta' => $b, 'nombre' => 'Dos.pdf']]);
        $this->assertSame(['Dos.pdf'], $r2['repetidas']);
        $this->assertFileExists($base.'/Cli/datos/Entrada/Xate Fra 1.pdf');
    }
}
