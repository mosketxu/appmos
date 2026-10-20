<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ColaTareas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DB::table('trabajadores')->insert(['nombre' => 'PCtest', 'token_hash' => hash('sha256', 'tok'), 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function api(string $ruta, array $datos = [], string $cuerpo = null)
    {
        return $cuerpo === null
            ? $this->postJson('/api/trabajador/whatsapp/pevima/'.$ruta, $datos, ['X-Token' => 'tok'])
            : $this->call('POST', '/api/trabajador/whatsapp/pevima/'.$ruta, [], [], [], ['HTTP_X-Token' => 'tok'] + $datos, $cuerpo);
    }

    public function test_importar_dos_veces_no_duplica_y_no_pierde_adjuntos(): void
    {
        $m = fn ($n, $adj = null, $texto = 'hola') => ['fecha' => '2026-10-19 10:00:00', 'autor' => 'Ana', 'texto' => $texto, 'adjunto' => $adj, 'n' => $n];
        $this->api('mensajes', ['mensajes' => [$m(0), $m(1, null, '<Multimedia omitido>')]])->assertOk()->assertJson(['nuevos' => 2]);
        $this->api('mensajes', ['mensajes' => [$m(0), $m(1, null, '<Multimedia omitido>')]])->assertOk()->assertJson(['nuevos' => 0, 'iguales' => 2]);
        // export con archivos: el «omitido» pasa a tener nombre de fichero
        $this->api('mensajes', ['mensajes' => [$m(1, 'IMG-1.jpg', '')]])->assertJson(['actualizados' => 1]);
        // y un export posterior sin archivos no lo pisa
        $this->api('mensajes', ['mensajes' => [$m(1, null, '<Multimedia omitido>')]])->assertJson(['iguales' => 1]);
        $this->assertSame(2, DB::table('whatsapp_mensajes')->count());
        $this->assertSame('IMG-1.jpg', DB::table('whatsapp_mensajes')->where('texto', '')->value('adjunto'));
    }

    public function test_adjuntos_manifest_y_subida(): void
    {
        $this->api('manifest', ['adjuntos' => [['nombre' => 'a.pdf', 'tam' => 5]]])->assertJson(['subir' => ['a.pdf']]);
        $this->call('POST', '/api/trabajador/whatsapp/pevima/adjunto?nombre='.rawurlencode('a.pdf'), [], [], [], ['HTTP_X-Token' => 'tok'], '%PDF-')->assertOk();
        $this->api('manifest', ['adjuntos' => [['nombre' => 'a.pdf', 'tam' => 5]]])->assertJson(['subir' => []]);
        $this->call('POST', '/api/trabajador/whatsapp/pevima/adjunto?nombre='.rawurlencode('../x.pdf'), [], [], [], ['HTTP_X-Token' => 'tok'], 'x')->assertStatus(422);
        Storage::disk('local')->assertExists('whatsapp/'.DB::table('whatsapp_chats')->value('id').'/a.pdf');
    }

    public function test_sin_token_no_entra(): void
    {
        $this->postJson('/api/trabajador/whatsapp/pevima/mensajes', ['mensajes' => []])->assertForbidden();
    }

    public function test_la_tarea_solo_acepta_chats_validos(): void
    {
        $this->assertGreaterThan(0, ColaTareas::crear('pc.whatsapp', ['clave' => 'pevima', 'carpeta' => 'Pevima']));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        ColaTareas::crear('pc.whatsapp', ['clave' => 'pevima', 'carpeta' => '../fuera']);
    }

    public function test_la_pantalla_pide_permiso(): void
    {
        $u = User::factory()->create(['activo' => true, 'debe_cambiar_password' => 0]);
        $this->actingAs($u)->get('/contabilidad/whatsapp')->assertStatus(403);
        Permission::findOrCreate('contabilidad.whatsapp', 'web');
        $u->givePermissionTo('contabilidad.whatsapp');
        $this->actingAs($u->fresh())->get('/contabilidad/whatsapp')->assertOk()->assertSee('WhatsApp');
    }

    public function test_el_componente_pinta_mensajes_y_busca(): void
    {
        Permission::findOrCreate('contabilidad.whatsapp', 'web');
        $u = User::factory()->create(['activo' => true, 'debe_cambiar_password' => 0]);
        $u->givePermissionTo('contabilidad.whatsapp');
        $id = DB::table('whatsapp_chats')->value('id');
        DB::table('whatsapp_mensajes')->insert([
            ['chat_id' => $id, 'huella' => sha1('a'), 'fecha' => '2026-10-19 10:00:00', 'autor' => 'Ana', 'texto' => 'Factura de Mengual <b>', 'adjunto' => null, 'created_at' => now(), 'updated_at' => now()],
            ['chat_id' => $id, 'huella' => sha1('b'), 'fecha' => '2026-10-19 10:01:00', 'autor' => 'Alexander Arregui', 'texto' => '', 'adjunto' => 'f.pdf', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('whatsapp_adjuntos')->insert(['chat_id' => $id, 'nombre' => 'f.pdf', 'tam' => 2048, 'almacen' => 'whatsapp/1/f.pdf', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u);
        \Livewire\Livewire::test(\App\Http\Livewire\Contabilidad\WhatsApp::class)
            ->assertSee('Factura de Mengual &lt;b&gt;', false)->assertSee('f.pdf')
            ->set('buscar', 'Mengual')->assertSee('1 mensajes')->assertDontSee('f.pdf');
    }
}
