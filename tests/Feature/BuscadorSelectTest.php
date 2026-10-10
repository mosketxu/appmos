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

    /** El explorador del Libro de IVA: Alex y Marta ven todo; Suma/Admin además _Suma; el resto solo _Clientes. */
    public function test_raices_de_carpetas_por_usuario(): void
    {
        config(['contabilidad.libro_iva_acceso_total' => ['alex.arregui@sumaempresa.com', 'marta.ruiz@sumaempresa.com']]);
        \Spatie\Permission\Models\Role::findOrCreate('Suma', 'web');
        $alex = \App\Models\User::factory()->create(['email' => 'alex.arregui@sumaempresa.com', 'activo' => true]);
        $suma = \App\Models\User::factory()->create(['activo' => true]);
        $suma->assignRole('Suma');
        $otro = \App\Models\User::factory()->create(['activo' => true]);

        $this->assertSame(['_Clientes', '_RUR_Marta_Alex', '_Suma'], \App\Http\Livewire\LibroIva::raicesPermitidas($alex));
        $this->assertSame(['_Clientes', '_Suma'], \App\Http\Livewire\LibroIva::raicesPermitidas($suma));
        $this->assertSame(['_Clientes'], \App\Http\Livewire\LibroIva::raicesPermitidas($otro));
        $this->assertTrue(\App\Http\Livewire\LibroIva::rutaPermitida('_RUR_Marta_Alex/2026 RMA/IVA', $alex));
        $this->assertFalse(\App\Http\Livewire\LibroIva::rutaPermitida('_RUR_Marta_Alex/2026 RMA/IVA', $suma));
        $this->assertFalse(\App\Http\Livewire\LibroIva::rutaPermitida('_Suma2/x', $suma));
        $this->assertTrue(\App\Http\Livewire\LibroIva::rutaPermitida('_Clientes/2026/X', $otro));
    }
}
