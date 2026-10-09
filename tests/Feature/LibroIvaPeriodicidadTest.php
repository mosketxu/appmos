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
        $c->assertSee('presenta el 303 mensual');
        $c->set('entidadId', (string) $t->id);
        $this->assertMatchesRegularExpression('/^[1-4]T$/', $c->get('periodo'));
    }
}
