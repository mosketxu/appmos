<?php

namespace Tests\Feature;

use App\Http\Livewire\LibroIva;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Libro de IVA: una empresa que presenta el 303 mensual trabaja con meses (09 …), no con trimestres (3T). */
class LibroIvaPeriodicidadTest extends TestCase
{
    use RefreshDatabase;

    protected function empresa(string $nombre, string $periodicidad): Entidad
    {
        $e = Entidad::create(['entidad' => $nombre, 'nif' => 'B'.random_int(10000000, 99999999), 'estado' => 1]);
        $m = DB::table('impuesto_modelos')->where('codigo', '303')->value('id');
        DB::table('entidad_impuestos')->insert(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => $periodicidad, 'created_at' => now(), 'updated_at' => now()]);

        return $e;
    }

    public function test_la_empresa_mensual_pasa_del_trimestre_al_ultimo_mes_y_la_trimestral_vuelve_al_trimestre(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $m = $this->empresa('Mensual SL', 'M');
        $t = $this->empresa('Trimestral SL', 'T');
        $c = Livewire::test(LibroIva::class)->set('verTodos', true)->set('periodo', '3T')->set('entidadId', (string) $m->id);
        $this->assertMatchesRegularExpression('/^(0[1-9]|1[0-2])$/', $c->get('periodo'));
        $c->assertSee('303 mensual');
        $c->set('entidadId', (string) $t->id);
        $this->assertMatchesRegularExpression('/^[1-4]T$/', $c->get('periodo'));
    }

    public function test_al_volver_a_la_pantalla_sale_el_resultado_de_la_ultima_generacion(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $t = $this->empresa('Trimestral SL', 'T');
        $libro = ['periodo' => '2026-3T', 'tipo' => 'A PAGAR', 'resultado_final' => 10.0, 'avisos' => [], 'anterior' => null, 'm303' => ['devengado' => [], 'total_devengado' => 10.0, 'deducible' => [], 'total_deducible' => 0.0, 'exentas' => [], 'diferencia' => 10.0, 'compensar_anterior' => 0.0, 'resultado' => 10.0, 'tipo' => 'A PAGAR']];
        DB::table('tareas')->insert(['proceso' => 'pc.libro_iva', 'estado' => 'ok', 'created_at' => now(), 'updated_at' => now(), 'terminada_at' => now(),
            'parametros' => json_encode(['accion' => 'generar', 'carpeta' => 'x', 'periodo' => '2026-3T', 'cliente' => 'T', 'entidad_id' => $t->id, 'm216' => false]),
            'resultado' => json_encode(['pc' => 'MINI', 'libro' => $libro])]);
        session(['libro_iva' => ['entidad' => (string) $t->id, 'ejercicio' => '2026', 'periodo' => '3T', 'verTodos' => true]]);
        Livewire::test(LibroIva::class)->assertSee('RESULTADO DE LA AUTOLIQUIDACIÓN')->assertSee('hecho en MINI');
        Livewire::test(LibroIva::class)->set('periodo', '2T')->assertDontSee('RESULTADO DE LA AUTOLIQUIDACIÓN');
    }

    public function test_se_cambia_la_periodicidad_desde_el_libro_y_queda_en_la_entidad(): void
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $m = $this->empresa('Mal puesta SL', 'M');
        $c = Livewire::test(LibroIva::class)->set('verTodos', true)->set('entidadId', (string) $m->id);
        $this->assertMatchesRegularExpression('/^(0[1-9]|1[0-2])$/', $c->get('periodo'));
        $c->call('cambiarPeriodicidad', 'T');
        $this->assertMatchesRegularExpression('/^[1-4]T$/', $c->get('periodo'));
        $this->assertSame('T', DB::table('entidad_impuestos')->where('entidad_id', $m->id)->value('periodicidad'));
    }
}
