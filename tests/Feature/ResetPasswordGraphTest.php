<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** «¿Olvidaste tu contraseña?»: el enlace sale por Microsoft Graph, no por SMTP. */
class ResetPasswordGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_enlace_de_reseteo_se_envia_por_graph(): void
    {
        config(['contabilidad.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'sender' => 'alex.arregui@sumaempresa.com', 'redirect' => null]]);
        Cache::put('graph_token', 'tok', 60);
        Http::fake(['graph.microsoft.com/*' => Http::response('', 202)]);
        $u = User::factory()->create(['email' => 'ana@sumaempresa.com']);

        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'users/alex.arregui%40sumaempresa.com/sendMail')
            && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'ana@sumaempresa.com'
            && str_contains($r['message']['body']['content'], '/reset-password/'));
    }

    public function test_si_graph_falla_no_se_rompe_la_pantalla(): void
    {
        config(['contabilidad.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'sender' => 'x@sumaempresa.com', 'redirect' => null]]);
        Cache::put('graph_token', 'tok', 60);
        Http::fake(['graph.microsoft.com/*' => Http::response('', 403)]);
        $u = User::factory()->create();

        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status');
    }
}
