<?php

namespace Tests\Feature;

use App\Http\Livewire\ImpuestoM111;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** M111: cargar el fichero en el formulario de la AEAT dejando Edge abierto (validar y presentar lo hace el usuario). */
class ImpuestoM111CargarAeatTest extends TestCase
{
    use RefreshDatabase;

    protected function pantalla(?string $estado)
    {
        $this->actingAs($this->adminUser(['activo' => 1]));
        $e = Entidad::create(['entidad' => 'NAE Comunicacions, S.L.', 'alias' => 'NAE', 'nif' => 'B63442701', 'estado' => 1]);
        $m = DB::table('impuesto_modelos')->where('codigo', '111')->value('id');
        $ob = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'M', 'created_at' => now(), 'updated_at' => now()]);
        if ($estado) {
            DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $ob, 'ejercicio' => 2026, 'periodo' => '09', 'estado' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('trabajadores')->insert(['nombre' => 'PCX', 'token_hash' => 'x', 'activo' => true, 'ultimo_latido' => now(), 'created_at' => now(), 'updated_at' => now()]);
        session(['m111' => ['entidad' => $e->id, 'ejercicio' => 2026, 'periodo' => '09', 'verTodos' => true]]);
        $c = Livewire::withCookie('appmos_pc', 'PCX')->test(ImpuestoM111::class);
        $dir = storage_path('app/m111/'.$e->id.'/2026-09/salida');
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/111_B63442701_202609.txt', 'x');
        $c->set('res', ['fichero' => '111_B63442701_202609.txt', 'errores' => [], 'periodo' => '09', 'ejercicio' => 2026]);

        return $c;
    }

    public function test_carga_el_111_en_la_aeat_y_pide_guardar_el_justificante_en_impuestos_del_cliente(): void
    {
        $c = $this->pantalla('revisado');
        $c->call('cargarEnAeat')->assertSet('error', '');
        $t = DB::table('tareas')->where('proceso', 'pc.aeat_111')->first();
        $this->assertNotNull($t);
        $p = json_decode($t->parametros, true);
        $this->assertSame('B63442701', $p['nif']);
        $this->assertSame('09', $p['periodo']);
        $this->assertSame('_Clientes/2026/_Impuestos 2026/Impuestos Clientes 2026', $p['justificante_dir']);
        $this->assertSame('Nae M111 09 2026.pdf', $p['justificante_nombre']);
        $this->assertSame('PCX', $t->destino);
        $this->assertNotNull($c->get('tareaCarga'));
    }

    public function test_si_ya_esta_presentado_se_avisa_pero_no_se_impide(): void
    {
        $c = $this->pantalla('presentado');
        $c->assertSee('ya figura como PRESENTADA');
        $c->call('cargarEnAeat');
        $this->assertSame(1, DB::table('tareas')->where('proceso', 'pc.aeat_111')->count());
    }
}
