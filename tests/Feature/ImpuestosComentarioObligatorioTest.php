<?php

namespace Tests\Feature;

use App\Http\Livewire\Impuestos;
use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Comentario de lectura obligatoria: la casilla no cambia de estado hasta que se marca «leído y validado». */
class ImpuestosComentarioObligatorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_clic_no_avanza_hasta_validar(): void
    {
        $u = $this->adminUser(['activo' => 1, 'email' => 'alex.arregui@sumaempresa.com']);
        $this->actingAs($u);
        $m = DB::table('impuesto_modelos')->where('codigo', '303')->value('id');
        $e = Entidad::create(['entidad' => 'Espiral SL', 'nif' => 'B12345678', 'estado' => 1, 'cliente' => 1]);
        $ob = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $e->id, 'modelo_id' => $m, 'periodicidad' => 'T', 'created_at' => now(), 'updated_at' => now()]);

        $c = Livewire::test(Impuestos::class)->set('verTodos', true)->set('vista', 'T1')->call('abrirComentarios', $ob, 'T1')
            ->set('comTexto', 'Ojo con el anexo')->set('comObligatorio', true)->call('comentar');
        $ej = $c->get('ejercicio');
        $id = DB::table('impuesto_comentarios')->value('id');
        $this->assertSame(1, (int) DB::table('impuesto_comentarios')->value('obligatorio'));

        $c->call('clic', $ob, 'T1');
        $this->assertDatabaseMissing('impuesto_estados', ['entidad_impuesto_id' => $ob, 'periodo' => 'T1', 'estado' => 'revision']);

        $c->call('validarComentario', $id)->call('clic', $ob, 'T1');
        $this->assertDatabaseHas('impuesto_estados', ['entidad_impuesto_id' => $ob, 'ejercicio' => $ej, 'periodo' => 'T1', 'estado' => 'revision']);
    }
}
