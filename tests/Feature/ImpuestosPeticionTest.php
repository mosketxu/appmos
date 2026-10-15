<?php

namespace Tests\Feature;

use App\Http\Livewire\Impuestos;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Seguimiento de impuestos: la petición de documentación (📨, correo de Proc.Mensuales) se ve con su estado y se puede cambiar. */
class ImpuestosPeticionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_sobre_de_peticion_sale_con_el_estado_y_se_cambia_igual_que_en_proc_mensuales(): void
    {
        Carbon::setTestNow('2026-10-15 10:00');
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'Mensual SL', 'nif' => 'B12345678', 'estado' => 1, 'cliente' => 1, 'mail_peticion_check' => 1, 'cicloimpuesto_id' => 1]);
        $t = Entidad::create(['entidad' => 'Trimestral SL', 'nif' => 'B87654321', 'estado' => 1, 'cliente' => 1, 'mail_peticion_check' => 1, 'cicloimpuesto_id' => 3]);
        $m = DB::table('impuesto_modelos')->where('codigo', '303')->value('id');
        foreach ([[$e, 'M'], [$t, 'T']] as [$ent, $p]) {
            DB::table('entidad_impuestos')->insert(['entidad_id' => $ent->id, 'modelo_id' => $m, 'periodicidad' => $p, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('mails_enviados')->insert(['proceso' => 'petdocimpuestos', 'entidad_id' => $e->id, 'periodo' => '2026-09', 'destinatarios' => 'cliente@x.com',
            'asunto' => 'Documentación septiembre', 'texto' => 'Hola, necesitamos…', 'enviado_at' => '2026-10-02 09:00', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('procesos_estado')->insert(['proceso' => 'petdocimpuestos', 'entidad_id' => $e->id, 'periodo' => '2026-09', 'estado' => 'solicitado',
            'solicitado_at' => '2026-10-02 09:00', 'created_at' => now(), 'updated_at' => now()]);

        $c = Livewire::test(Impuestos::class)->set('verTodos', true)->set('vista', 'T3');
        $c->assertSee('Petición de documentación')->assertSee('Documentación septiembre')->assertSee('Enviado el 02/10/2026');

        $bloque = $c->instance()->peticionesBloque($e->id, 'T3', $this->invokar($c->instance()));
        $this->assertSame(['2026-07', '2026-08', '2026-09'], array_column($bloque, 0));
        $this->assertSame([null, null, 'solicitado'], array_column($bloque, 2));
        $trim = $c->instance()->peticionesBloque($t->id, 'T3', $this->invokar($c->instance()));
        $this->assertSame([['2026-09', 'T3']], array_map(fn ($x) => [$x[0], $x[1]], $trim));
        $this->assertSame([], $c->instance()->peticionesBloque($t->id, 'T4', $this->invokar($c->instance())));   // futuro: no sale

        $c->call('fijarPeticion', $e->id, '2026-09', 'recibido');
        $this->assertDatabaseHas('procesos_estado', ['proceso' => 'petdocimpuestos', 'entidad_id' => $e->id, 'periodo' => '2026-09', 'estado' => 'recibido']);
        $c->call('fijarPeticion', $t->id, '2026-09', 'solicitado');
        $this->assertDatabaseHas('procesos_estado', ['entidad_id' => $t->id, 'periodo' => '2026-09', 'estado' => 'solicitado']);
        $c->call('fijarPeticion', $e->id, '2026-09', null);
        $this->assertDatabaseMissing('procesos_estado', ['entidad_id' => $e->id, 'periodo' => '2026-09']);
    }

    private function invokar(Impuestos $i): array
    {
        $r = new \ReflectionMethod($i, 'peticiones');

        return $r->invoke($i, DB::table('entidades')->pluck('id')->all());
    }
}
