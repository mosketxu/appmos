<?php

namespace Tests\Feature;

use App\Http\Livewire\Impuestos;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Seguimiento de impuestos: filtro por varios estados (p. ej. todo menos lo presentado) y botones Míos / Todos. */
class ImpuestosFiltroEstadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_varios_estados_y_lo_recuerda(): void
    {
        $u = $this->adminUser(['activo' => 1, 'email' => 'alex.arregui@sumaempresa.com']);
        $this->actingAs($u);
        $m = DB::table('impuesto_modelos')->where('codigo', '303')->value('id');
        $ids = [];
        foreach (['Presentada SL' => 'presentado', 'Pendiente SL' => 'pendiente', 'Revisada SL' => 'revisado'] as $nombre => $estado) {
            $e = Entidad::create(['entidad' => $nombre, 'nif' => 'B'.random_int(10000000, 99999999), 'estado' => 1, 'cliente' => 1]);
            $ob = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'T', 'created_at' => now(), 'updated_at' => now()]);
            $ids[$estado] = $ob;
        }
        $c = Livewire::test(Impuestos::class)->set('verTodos', true)->set('vista', 'T1');
        foreach ($ids as $estado => $ob) {
            DB::table('impuesto_estados')->updateOrInsert(['entidad_impuesto_id' => $ob, 'ejercicio' => $c->get('ejercicio'), 'periodo' => 'T1'], ['estado' => $estado]);
        }
        $c->call('refrescar')->assertSee('Presentada SL')->assertSee('Pendiente SL');

        $c->set('filtroEstados', ['pendiente', 'revisado'])->assertDontSee('Presentada SL')->assertSee('Pendiente SL')->assertSee('Revisada SL');
        $this->assertSame(['pendiente', 'revisado'], $u->fresh()->impuestos_filtros['filtroEstados']);
        $this->actingAs($u->fresh());
        Livewire::test(Impuestos::class)->assertSet('filtroEstados', ['pendiente', 'revisado']);

        $c->set('filtroEstados', ['presentado', 'inventado'])->assertSet('filtroEstados', ['presentado'])->assertSee('Presentada SL')->assertDontSee('Pendiente SL');
        $c->set('filtroEstados', [])->assertSee('Presentada SL')->assertSee('Pendiente SL');

        $c->set('verTodos', false)->assertSee('Míos')->assertSee('Todos');
    }
}
