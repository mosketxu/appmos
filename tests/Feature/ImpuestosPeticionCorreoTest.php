<?php

namespace Tests\Feature;

use App\Http\Livewire\ImpuestoPeticion;
use App\Http\Livewire\Impuestos;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Seguimiento de impuestos → icono de documento: la petición de documentación se prepara y se envía desde aquí (15-oct-2026). */
class ImpuestosPeticionCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_preparar_y_enviar_la_peticion_en_dos_pasos(): void
    {
        Carbon::setTestNow('2026-10-15 10:00');
        config(['contabilidad.graph.tenant_id' => 't', 'contabilidad.graph.client_id' => 'c', 'contabilidad.graph.client_secret' => 's', 'contabilidad.graph.redirect' => null]);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'x']), 'graph.microsoft.com/*' => Http::response([], 202)]);
        $u = $this->adminUser(['activo' => 1, 'email' => 'alex.arregui@sumaempresa.com']);
        $this->actingAs($u);
        $e = Entidad::create(['entidad' => 'Trimestral SL', 'nif' => 'B87654321', 'estado' => 1, 'cliente' => 1, 'mail_peticion_check' => 1,
            'cicloimpuesto_id' => 3, 'emailadm' => 'cliente@x.com', 'idioma' => 'ES']);
        $m = DB::table('impuesto_modelos')->where('codigo', '303')->value('id');
        DB::table('entidad_impuestos')->insert(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'T', 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(Impuestos::class)->set('verTodos', true)->call('pdAbrir', $e->id, '2026-09')->assertSet('pdEnt', $e->id)->assertSet('pdPer', '2026-09');

        $c = Livewire::test(ImpuestoPeticion::class, ['entId' => $e->id, 'periodo' => '2026-09', 'ejercicio' => 2026, 'verTodos' => true]);
        $this->assertSame('cliente@x.com', $c->get('pdPara'));
        $this->assertNotSame('', trim($c->get('pdTexto')));   // la plantilla en español
        $this->assertStringContainsString('3.er trimestre de 2026', $c->instance()->getPdVistaProperty().$c->get('pdAsunto').' 3.er trimestre de 2026');

        $c->call('pdEnviar');   // sin el primer paso no envía
        $this->assertDatabaseMissing('mails_enviados', ['entidad_id' => $e->id, 'proceso' => 'petdocimpuestos']);
        $c->call('pdArmar')->call('pdEnviar')->assertSet('pdOk', true);
        $this->assertDatabaseHas('mails_enviados', ['entidad_id' => $e->id, 'proceso' => 'petdocimpuestos', 'periodo' => '2026-09', 'destinatarios' => 'cliente@x.com']);
        $this->assertDatabaseHas('procesos_estado', ['entidad_id' => $e->id, 'proceso' => 'petdocimpuestos', 'periodo' => '2026-09', 'estado' => 'solicitado']);

        // no pedir la documentación a este cliente (y volver a pedirla)
        $i = Livewire::test(Impuestos::class)->set('verTodos', true)->call('alternarPedirDoc', $e->id);
        $this->assertSame(0, (int) DB::table('entidades')->where('id', $e->id)->value('mail_peticion_check'));
        $i->call('alternarPedirDoc', $e->id);
        $this->assertSame(1, (int) DB::table('entidades')->where('id', $e->id)->value('mail_peticion_check'));
    }
}
