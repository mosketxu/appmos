<?php

namespace Tests\Feature;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Cola de tareas para PCs trabajadores. Solo crea sus propias tablas (sqlite en memoria). */
class ColaTareasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', fn ($t) => $t->id());
        (require base_path('database/migrations/2026_10_02_140000_create_trabajadores_tareas_tables.php'))->up();
        (require base_path('database/migrations/2026_10_03_210000_create_estado_procesos_table.php'))->up();
        Schema::create('certificados_escaneos', function ($t) {
            $t->id(); $t->string('pc')->unique(); $t->string('escaneado'); $t->longText('certs'); $t->timestamps();
        });
    }

    protected function cabecera(string $token): array
    {
        return ['X-Token' => $token];
    }

    public function test_ciclo_completo_y_efecto_en_servidor(): void
    {
        $token = ColaTareas::crearTrabajador('AlexMiniPC');
        $id = ColaTareas::crear('certificados.escanear', [], 'AlexMiniPC');
        $cap = ['capacidades' => ['certificados.escanear']];

        $r = $this->postJson('/api/trabajador/siguiente', $cap, $this->cabecera($token))->assertOk();
        $this->assertSame($id, $r->json('tarea.id'));
        $this->assertSame('en_curso', DB::table('tareas')->find($id)->estado);
        // ya no se la da a nadie más
        $this->assertNull($this->postJson('/api/trabajador/siguiente', $cap, $this->cabecera($token))->json('tarea'));

        $this->postJson("/api/trabajador/tareas/$id/log", ['texto' => 'hola'], $this->cabecera($token))->assertJson(['ok' => true]);
        $this->postJson("/api/trabajador/tareas/$id/fin", ['ok' => true, 'resultado' => ['pc' => 'ALEXMINIPC', 'escaneado' => '2026-10-02T10:00:00', 'certs' => [['clave' => 'x']]]], $this->cabecera($token))->assertJson(['ok' => true]);

        $this->assertSame('ok', DB::table('tareas')->find($id)->estado);
        $this->assertSame(1, DB::table('certificados_escaneos')->where('pc', 'ALEXMINIPC')->count());
    }

    public function test_token_malo_y_proceso_no_permitido(): void
    {
        $this->postJson('/api/trabajador/siguiente', [], ['X-Token' => 'nada'])->assertForbidden();
        $this->postJson('/api/trabajador/siguiente', [])->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        ColaTareas::crear('rm -rf /');
    }

    public function test_destino_y_capacidades(): void
    {
        $a = ColaTareas::crearTrabajador('AlexMiniPC');
        $b = ColaTareas::crearTrabajador('PortalExomen');
        $id = ColaTareas::crear('certificados.escanear', [], 'PortalExomen');

        $this->assertNull($this->postJson('/api/trabajador/siguiente', ['capacidades' => ['certificados.escanear']], $this->cabecera($a))->json('tarea'));
        // sin la capacidad tampoco la coge
        $this->assertNull($this->postJson('/api/trabajador/siguiente', ['capacidades' => []], $this->cabecera($b))->json('tarea'));
        $this->assertSame($id, $this->postJson('/api/trabajador/siguiente', ['capacidades' => ['certificados.escanear']], $this->cabecera($b))->json('tarea.id'));
    }

    public function test_tarea_perdida_vuelve_a_la_cola_y_la_coge_el_otro_pc(): void
    {
        $a = ColaTareas::crearTrabajador('AlexMiniPC');
        $b = ColaTareas::crearTrabajador('PortalExomen');
        $cap = ['capacidades' => ['certificados.escanear']];
        $id = ColaTareas::crear('certificados.escanear');

        $this->assertSame($id, $this->postJson('/api/trabajador/siguiente', $cap, $this->cabecera($a))->json('tarea.id'));
        // AlexMiniPC deja de dar señales
        DB::table('trabajadores')->where('nombre', 'AlexMiniPC')->update(['ultimo_latido' => now()->subMinutes(10)]);
        $this->assertSame($id, $this->postJson('/api/trabajador/siguiente', $cap, $this->cabecera($b))->json('tarea.id'));
        $this->assertSame('PortalExomen', DB::table('trabajadores')->find(DB::table('tareas')->find($id)->trabajador_id)->nombre);
        // y el primero ya no puede cerrarla
        $this->postJson("/api/trabajador/tareas/$id/fin", ['ok' => true], $this->cabecera($a))->assertJson(['ok' => false]);
    }
}
