<?php

namespace Tests\Feature;

use App\Support\Ayuda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Ayuda de pantalla (19-nov-2026): cada pantalla del menú tiene sus instrucciones y el icono sale junto al título. */
class AyudaPantallaTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_pantallas_del_menu_tienen_ayuda(): void
    {
        foreach (['entidades', 'todo', 'todo.mejoras', 'formacion', 'correo', 'confidencialidad', 'impuestos', 'impuestos.libro-iva', 'impuestos.m111', 'impuestos.m349', 'impuestos.m216',
            'impuestos.intrastat', 'impuestos.pago-cuenta', 'contabilidad.is', 'contabilidad.procesos', 'contabilidad.facturacion-pdf', 'contabilidad.durcal', 'contabilidad.bancos',
            'contabilidad.facturas-ocr', 'contabilidad.neteges', 'contabilidad.leoybra', 'contabilidad.whatsapp', 'contabilidad.procesos-mensuales', 'contabilidad.certificados',
            'admin.usuarios', 'admin.roles', 'password.cambiar.form', 'contabilidad.seguimiento-mensual'] as $ruta) {
            $a = Ayuda::para($ruta);
            $this->assertNotNull($a, "Falta la ayuda de la pantalla {$ruta} en config/ayuda.php");
            $this->assertNotSame('', $a['titulo']);
            $this->assertGreaterThan(40, strlen(strip_tags($a['html'])), "La ayuda de {$ruta} es demasiado corta");
        }
    }

    public function test_una_ruta_sin_ayuda_no_pinta_nada(): void
    {
        $this->assertNull(Ayuda::para('ruta.que.no.existe'));
        $this->assertNull(Ayuda::para(null));
        $this->assertStringNotContainsString('ayuda-btn', Blade::render('<x-ayuda-pantalla ruta="ruta.que.no.existe" />'));
    }

    public function test_el_componente_pinta_el_boton_y_el_modal_con_la_ayuda(): void
    {
        $h = Blade::render('<x-ayuda-pantalla ruta="correo" />');
        $this->assertStringContainsString('id="ayuda-btn"', $h);
        $this->assertStringContainsString('<dialog id="ayuda-dialog"', $h);
        $this->assertStringContainsString('📖 Correo', $h);
        $this->assertStringContainsString('Cada uno ve solo su buzón', $h);
    }

    public function test_la_pagina_de_correo_lleva_la_ayuda_en_el_layout(): void
    {
        config(['contabilidad.graph.client_secret' => null]);
        $this->actingAs($this->adminUser(['activo' => true]));
        $this->get('/correo')->assertOk()->assertSee('id="ayuda-btn"', false)->assertSee('📖 Correo', false);
    }
}
