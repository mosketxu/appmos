<?php

namespace Tests\Feature;

use App\Http\Livewire\CorreoClaude;
use App\Models\ConfidencialidadDoc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** «✉ Correo»: bandeja a la izquierda, correo en el centro (aislado) y a la derecha lo que se hace con él. Solo Admin; Graph siempre simulado. */
class CorreoClaudeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['contabilidad.graph.tenant_id' => 't', 'contabilidad.graph.client_id' => 'c', 'contabilidad.graph.client_secret' => 's', 'contabilidad.graph.sender' => 'buzon@example.test']);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'x']),
            '*mailFolders/inbox/messages*' => Http::response(['value' => [
                ['id' => 'AAA', 'subject' => 'Factura de prueba', 'from' => ['emailAddress' => ['name' => 'Proveedor Uno', 'address' => 'p@example.test']],
                    'receivedDateTime' => '2026-11-19T10:00:00Z', 'hasAttachments' => true, 'isRead' => false, 'bodyPreview' => "Hola   Alex,\n adjunto"],
                ['id' => 'BBB', 'subject' => 'Otro', 'from' => ['emailAddress' => ['name' => 'Ana', 'address' => 'a@example.test']],
                    'receivedDateTime' => '2026-11-19T11:00:00Z', 'hasAttachments' => false, 'isRead' => true, 'bodyPreview' => 'x'],
            ]]),
            '*messages/AAA/attachments*' => Http::response(['value' => [
                ['name' => 'f.pdf', 'size' => 2048, 'contentType' => 'application/pdf', 'isInline' => false],
                ['name' => 'logo.png', 'size' => 900, 'contentType' => 'image/png', 'isInline' => true],
            ]]),
            '*messages/AAA?*' => Http::response(['id' => 'AAA', 'subject' => 'Factura de prueba', 'from' => ['emailAddress' => ['name' => 'Proveedor Uno', 'address' => 'p@example.test']],
                'toRecipients' => [['emailAddress' => ['address' => 'buzon@example.test']]], 'ccRecipients' => [], 'receivedDateTime' => '2026-11-19T10:00:00Z',
                'body' => ['contentType' => 'html', 'content' => '<p>Hola</p><script>alert(1)</script><img src="https://track.example/p.gif">']]),
        ]);
    }

    public function test_solo_admin_entra(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['activo' => true]));
        $this->get('/correo')->assertForbidden();
    }

    public function test_lista_y_correo_abierto_con_adjuntos_sin_los_incrustados(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)
            ->assertSee('Proveedor Uno')->assertSee('Factura de prueba')->assertSee('Otro')
            ->call('seleccionar', 'AAA')
            ->assertSee('p@example.test')->assertSee('f.pdf')->assertDontSee('logo.png')
            ->assertSee('Anonimizar y preparar');
    }

    public function test_el_iframe_va_aislado_y_sin_scripts_ni_imagenes_remotas(): void
    {
        $h = CorreoClaude::documentoSeguro(['tipo' => 'html', 'html' => '<p>Hola</p><script>alert(1)</script><iframe src="x"></iframe>']);
        $this->assertStringContainsString("default-src 'none'", $h);
        $this->assertStringContainsString("img-src data:", $h);
        $this->assertStringNotContainsString('alert(1)', $h);
        $this->assertStringNotContainsString('<iframe', $h);
        $this->assertStringContainsString('<p>Hola</p>', $h);
        $this->actingAs($this->adminUser());
        $html = Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->html();
        $this->assertStringContainsString('sandbox=""', $html);
    }

    public function test_el_documento_es_de_quien_lo_prepara(): void
    {
        $a = $this->adminUser();
        ConfidencialidadDoc::create(['user_id' => $a->id, 'estado' => 'listo', 'correo_id' => 'AAA']);
        $this->actingAs($this->adminUser(['email' => 'otro@example.test']));
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Anonimizar y preparar');   // el de otro Admin no se ve
        $this->actingAs($a);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Conversación con Claude')->assertDontSee('Anonimizar y preparar');
    }

    public function test_pedir_sin_documento_no_hace_nada(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('instruccion', 'Resume')->call('pedir')->assertStatus(422);
    }
}
