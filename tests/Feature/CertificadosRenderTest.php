<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Certificados;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sin las tablas de la cola, pedir el escaneo avisa en vez de romper; en local no se pide a la cola. */
class CertificadosRenderTest extends TestCase
{
    public function test_render_sin_tablas_de_cola(): void
    {
        $this->assertFalse(Schema::hasTable('tareas'));
        config(['contabilidad.ejecucion_local' => false]);   // el .env de este PC lo tiene a true
        $c = new Certificados();
        $c->pedirEscaneoPCs();
        $this->assertStringContainsString('migración', $c->salida);
        $c->actualizarCola();
    }

    public function test_en_local_no_se_pide_a_la_cola(): void
    {
        config(['contabilidad.ejecucion_local' => true]);
        $c = new Certificados();
        $c->pedirEscaneoPCs();
        $this->assertStringContainsString('vive en la web', $c->salida);
    }
}
