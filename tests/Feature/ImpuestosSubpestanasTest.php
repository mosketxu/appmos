<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pestaña Impuestos (9-oct-2026): subpestañas Seguimiento, IVA M303, IS M200 y Pago Cuenta M202 en todas sus pantallas; el TO-DO ya no lleva impuestos. */
class ImpuestosSubpestanasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_pantalla_de_impuestos_muestra_las_subpestanas_y_marca_la_suya(): void
    {
        config(['contabilidad.is_ejecucion' => true, 'contabilidad.is_url' => null]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        foreach (['impuestos' => 'Seguimiento de impuestos', 'impuestos.libro-iva' => 'IVA M303', 'contabilidad.is' => 'IS M200', 'impuestos.pago-cuenta' => 'Pago Cuenta M202'] as $ruta => $activa) {
            $r = $this->get(route($ruta))->assertOk();
            foreach (['Seguimiento de impuestos', 'IVA M303', 'IS M200', 'Pago Cuenta M202'] as $t) {
                $r->assertSee($t);
            }
            $this->assertMatchesRegularExpression('/class="activa">\s*'.preg_quote($activa, '/').'\s*</', $r->getContent(), $ruta);
        }
    }

    public function test_el_todo_ya_no_tiene_subpestanas_de_impuestos_y_contabilidad_no_lleva_is(): void
    {
        config(['contabilidad.is_ejecucion' => true]);
        $this->actingAs($this->adminUser(['activo' => 1]));
        $this->get(route('todo'))->assertOk()->assertDontSee('Libros IVA')->assertDontSee('Seguimiento de impuestos');
        $this->get(route('contabilidad.procesos'))->assertOk()->assertDontSee('>IS<', false);
    }
}
