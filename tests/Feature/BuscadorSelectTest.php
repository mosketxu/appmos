<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** El desplegable con buscador (modelo por defecto con muchas opciones) se pinta con sus opciones y la actual. */
class BuscadorSelectTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_pinta_las_opciones_y_el_valor_actual(): void
    {
        $html = Blade::render('<x-buscador-select model="entidadId" :valor="$v" :opciones="$o" />', ['v' => '2', 'o' => [1 => 'Alex Arregui', 2 => 'ALDRIBO INVESTMENTS SL']]);
        $this->assertStringContainsString('ALDRIBO INVESTMENTS SL', $html);
        $this->assertStringContainsString("sel: '2'", html_entity_decode($html));
        $this->assertStringContainsString('Escribe para buscar', $html);
    }

    public function test_la_pantalla_del_libro_de_iva_lleva_la_cabecera_y_el_buscador(): void
    {
        \Illuminate\Support\Facades\Config::set('contabilidad.todo_url', null);
        $perm = \Spatie\Permission\Models\Permission::findOrCreate('impuestos.ver', 'web');
        $u = \App\Models\User::factory()->create(['activo' => true]);
        $u->givePermissionTo($perm);
        $html = $this->actingAs($u)->get('/impuestos/libro-iva');
        $html->assertOk()->assertSee('Escribe para buscar', false)->assertSee('Impuestos');
        $this->assertStringContainsString('menu', strtolower($html->getContent()));
    }

    /** Un comentario como primera línea de un @script de Livewire lo rompe (Alpine lo trata como expresión): así dejó sin función «subir» de Impuestos. */
    public function test_los_script_de_livewire_empiezan_por_codigo(): void
    {
        foreach (glob(resource_path('views/livewire/*.blade.php')) as $f) {
            if (preg_match_all('/@script\s*<script>(.*?)<\/script>\s*@endscript/s', file_get_contents($f), $m)) {
                foreach ($m[1] as $js) {
                    $this->assertFalse(str_starts_with(ltrim($js), '//') || str_starts_with(ltrim($js), '/*'), basename($f).': el @script empieza por un comentario');
                }
            }
        }
    }
}
