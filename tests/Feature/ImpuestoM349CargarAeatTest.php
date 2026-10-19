<?php

namespace Tests\Feature;

use App\Http\Livewire\ImpuestoM349;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** M349 paso 6: cargar el .349 en la AEAT dejando Edge abierto (validar y presentar lo hace el usuario). */
class ImpuestoM349CargarAeatTest extends TestCase
{
    use RefreshDatabase;

    protected function pantalla(?string $estado)
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'NAE Test', 'nif' => 'B63442701', 'estado' => 1]);
        $m = DB::table('impuesto_modelos')->where('codigo', '349')->value('id');
        $ob = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'M', 'created_at' => now(), 'updated_at' => now()]);
        if ($estado) {
            DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $ob, 'ejercicio' => 2026, 'periodo' => '09', 'estado' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('trabajadores')->insert(['nombre' => 'PCX', 'token_hash' => 'x', 'activo' => true, 'ultimo_latido' => now(), 'created_at' => now(), 'updated_at' => now()]);
        session(['m349' => ['entidad' => (string) $e->id, 'ejercicio' => '2026', 'periodo' => '09', 'verTodos' => true]]);
        $c = Livewire::withCookie('appmos_pc', 'PCX')->test(ImpuestoM349::class);
        $dir = storage_path('app/m349/'.$e->id.'/2026-09/salida');
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/09 B63442701.349', 'x');
        $c->set('carpeta', 'Clientes/NAE/IVA')->set('res', ['fichero' => '09 B63442701.349', 'errores' => []]);

        return $c;
    }

    public function test_carga_el_349_en_la_aeat_dejando_abierto_y_sin_presentar(): void
    {
        $c = $this->pantalla('revisado');
        $this->assertSame('revisado', $c->instance()->estadoCasilla());
        $c->call('cargarEnAeat')->assertSet('error', '');
        $t = DB::table('tareas')->where('proceso', 'pc.aeat_349')->first();
        $this->assertNotNull($t);
        $p = json_decode($t->parametros, true);
        $this->assertTrue($p['dejar_abierto']);
        $this->assertSame('_Clientes/2026/_Impuestos 2026/Impuestos NAE', $p['justificante_dir']);
        $this->assertSame('Nae M349 09 2026.pdf', $p['justificante_nombre']);
        $this->assertSame('PCX', $t->destino);
        $this->assertNotNull($c->get('tareaCarga'));
        $this->assertNull($c->get('tareaAeat'));
    }

    public function test_si_marta_no_ha_revisado_se_avisa_en_la_pantalla_pero_no_se_impide(): void
    {
        $c = $this->pantalla('revision');
        $c->assertSee('aún no está en «revisado»');
        $c->call('cargarEnAeat');
        $this->assertSame(1, DB::table('tareas')->where('proceso', 'pc.aeat_349')->count());
    }
}
