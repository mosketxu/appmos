<?php

namespace Tests\Feature;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Neteges y Durcal van a AlexMiniPC; si no da señales y la tarea espera, las coge PortalExomen (relevo). */
class RelevoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Tests\Support\TablasCola::crear();
        config(['contabilidad.pc_grupos.neteges.relevo' => ['PortalExomen'], 'contabilidad.pc_grupos.durcal.relevo' => ['PortalExomen']]);
        $this->a = ColaTareas::crearTrabajador('AlexMiniPC');
        $this->p = ColaTareas::crearTrabajador('PortalExomen');
    }

    protected string $a;
    protected string $p;

    protected function pideP(): ?int
    {
        return $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.script', 'pc.estado']], ['X-Token' => $this->p])->json('tarea.id');
    }

    public function test_el_relevo_solo_entra_si_el_principal_no_da_senales_y_la_tarea_espera(): void
    {
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()]);
        DB::table('trabajadores')->where('nombre', 'PortalExomen')->update(['ultimo_latido' => now()]);
        $id = ColaTareas::crear('pc.script', ['grupo' => 'neteges', 'pasos' => [['script' => 'neteges_base.py', 'args' => []]]], 'AlexMiniPC');
        $this->assertNull($this->pideP(), 'recién pedida: espera a AlexMiniPC');

        DB::table('tareas')->where('id', $id)->update(['created_at' => now()->subMinutes(10)]);
        $this->assertNull($this->pideP(), 'esperando mucho, pero AlexMiniPC está en línea: no hay relevo');

        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(10)]);
        DB::table('trabajadores')->where('nombre', 'PortalExomen')->update(['ultimo_latido' => now()]);
        $this->assertSame($id, $this->pideP(), 'AlexMiniPC sin señales y tarea esperando: la coge PortalExomen');
    }

    public function test_el_traspaso_de_facturas_ocr_al_onedrive_tambien_tiene_relevo(): void
    {
        config(['contabilidad.facturasocr_relevo' => ['PortalExomen']]);
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(30)]);
        $id = ColaTareas::crear('pc.facturasocr', ['cliente' => 'Durcal'], 'AlexMiniPC');
        $pide = fn () => $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.facturasocr']], ['X-Token' => $this->p])->json('tarea.id');
        $this->assertNull($pide(), 'recién creada: espera unos minutos a AlexMiniPC');
        DB::table('tareas')->where('id', $id)->update(['created_at' => now()->subMinutes(5)]);
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()]);
        $this->assertNull($pide(), 'AlexMiniPC está en línea: no hay relevo');
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(30)]);
        $this->assertSame($id, $pide(), 'AlexMiniPC sin señales y la tarea esperando: la coge PortalExomen');
    }

    public function test_un_grupo_sin_relevo_no_se_coge(): void
    {
        $id = ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'monthlyFIQ.js', 'args' => ['09']]]], 'AlexMiniPC');
        DB::table('tareas')->where('id', $id)->update(['created_at' => now()->subMinutes(10)]);
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(10)]);
        $this->assertNull($this->pideP());
    }

    public function test_durcal_tambien_y_el_estado_pedido_al_principal_igual(): void
    {
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(10)]);
        $id = ColaTareas::crear('pc.estado', ['grupo' => 'durcal'], 'AlexMiniPC');
        DB::table('tareas')->where('id', $id)->update(['created_at' => now()->subMinutes(5)]);
        $this->assertSame($id, $this->pideP());
    }

    public function test_nunca_dos_tareas_de_neteges_a_la_vez_en_dos_pcs(): void
    {
        config(['contabilidad.pc_grupos.neteges.exclusivo' => true]);
        DB::table('trabajadores')->update(['ultimo_latido' => now()]);
        $t1 = ColaTareas::crear('pc.script', ['grupo' => 'neteges', 'pasos' => [['script' => 'neteges_base.py', 'args' => []]]]);
        $t2 = ColaTareas::crear('pc.script', ['grupo' => 'neteges', 'pasos' => [['script' => 'neteges_ventas.py', 'args' => []]]]);
        $otra = ColaTareas::crear('pc.script', ['grupo' => 'fiq', 'pasos' => [['script' => 'monthlyFIQ.js', 'args' => ['09']]]]);
        $this->assertSame($t1, $this->pideP());                      // Portal empieza la primera de Neteges
        $a = fn () => $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.script']], ['X-Token' => $this->a])->json('tarea.id');
        $this->assertSame($otra, $a(), 'lo de otro grupo sí puede ir al otro PC');
        $this->assertNull($a(), 'la 2.ª de Neteges espera mientras la 1.ª siga en curso en el otro PC');
        $this->postJson("/api/trabajador/tareas/$t1/fin", ['ok' => true, 'resultado' => ['ok' => true]], ['X-Token' => $this->p])->assertOk();
        $this->assertSame($t2, $a(), 'terminada la 1.ª, ya puede ir la 2.ª');
    }

    public function test_el_pc_elegido_en_el_selector_manda_si_esta_en_linea_y_si_cae_lo_coge_el_otro(): void
    {
        DB::table('trabajadores')->where('nombre', 'PortalExomen')->update(['ultimo_latido' => now()]);
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()]);
        $this->app['request']->cookies->set('appmos_pc', 'PortalExomen');
        $this->assertSame('PortalExomen', ColaTareas::pcElegido());
        $this->app['request']->cookies->set('appmos_pc', 'AlexMiniPC');
        $this->assertSame('AlexMiniPC', ColaTareas::pcElegido());
        $this->app['request']->cookies->set('appmos_pc', 'Otro');
        $this->assertNull(ColaTareas::pcElegido(), 'un nombre que no es un PC dado de alta se ignora');
        $this->app['request']->cookies->set('appmos_pc', 'PortalExomen');
        DB::table('trabajadores')->where('nombre', 'PortalExomen')->update(['ultimo_latido' => now()->subMinutes(10)]);
        $this->assertNull(ColaTareas::pcElegido(), 'el elegido no da señales: vuelve a automático');

        // relevo general: una tarea fijada a un PC que no responde la coge cualquier otro tras 3 min (aunque no esté en ninguna lista de relevo)
        config(['contabilidad.facturasocr_relevo' => []]);
        $id = ColaTareas::crear('pc.facturasocr', ['cliente' => 'Durcal'], 'PortalExomen');
        $pide = fn () => $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['pc.facturasocr']], ['X-Token' => $this->a])->json('tarea.id');
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()]);
        $this->assertNull($pide(), 'recién creada: espera');
        DB::table('tareas')->where('id', $id)->update(['created_at' => now()->subMinutes(5)]);
        $this->assertSame($id, $pide());
    }
}
