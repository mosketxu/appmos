<?php

namespace Tests\Feature;

use App\Models\TodoAviso;
use App\Models\TodoTarea;
use App\Models\User;
use App\Support\ColaTareas;
use App\Support\VigilanciaTrabajadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** La campana avisa cuando un PC trabajador lleva rato sin dar señales y se cierra sola cuando vuelve. */
class VigilanciaTrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_avisa_una_vez_cuando_un_pc_se_cae_y_cierra_el_aviso_cuando_vuelve(): void
    {
        $alex = User::factory()->create(['email' => 'alex.arregui@sumaempresa.com']);
        config(['contabilidad.claude_todo_gestores' => ['alex.arregui@sumaempresa.com']]);
        ColaTareas::crearTrabajador('AlexMiniPC');
        ColaTareas::crearTrabajador('PortalExomen');
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(30)]);
        DB::table('trabajadores')->where('nombre', 'PortalExomen')->update(['ultimo_latido' => now()]);
        ColaTareas::crear('pc.facturasocr', ['cliente' => 'Durcal'], 'AlexMiniPC');

        VigilanciaTrabajadores::revisar();
        $t = TodoTarea::where('descripcion', 'like', '%[trab-AlexMiniPC]%')->first();
        $this->assertNotNull($t);
        $this->assertStringContainsString('pc.facturasocr', $t->descripcion);
        $this->assertSame(1, TodoAviso::where('user_id', $alex->id)->sinLeer()->count());
        $this->assertCount(0, TodoTarea::where('descripcion', 'like', '%[trab-PortalExomen]%')->get(), 'el que sigue vivo no genera aviso');

        Cache::forget('vigilancia.trabajadores');
        VigilanciaTrabajadores::revisar();   // sigue caído: no repite
        $this->assertSame(1, TodoTarea::where('descripcion', 'like', '%[trab-AlexMiniPC]%')->count());
        $this->assertSame(1, TodoAviso::where('user_id', $alex->id)->count());

        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()]);
        Cache::forget('vigilancia.trabajadores');
        VigilanciaTrabajadores::revisar();
        $this->assertSame('hecha', $t->fresh()->estado);
        $this->assertSame(2, TodoAviso::where('user_id', $alex->id)->count(), 'aviso de que vuelve');
    }

    public function test_un_pc_que_acaba_de_dar_senales_no_avisa(): void
    {
        User::factory()->create(['email' => 'alex.arregui@sumaempresa.com']);
        config(['contabilidad.claude_todo_gestores' => ['alex.arregui@sumaempresa.com']]);
        ColaTareas::crearTrabajador('AlexMiniPC');
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(5)]);
        VigilanciaTrabajadores::revisar();
        $this->assertSame(0, TodoTarea::count());
    }
}
