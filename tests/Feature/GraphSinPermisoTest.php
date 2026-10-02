<?php

namespace Tests\Feature;

use App\Support\GraphMail;
use App\Support\GraphSinPermiso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Un 403 de Graph (buzón fuera del grupo Appmos-Buzones) da un mensaje que dice qué pedirle a Alex. */
class GraphSinPermisoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['contabilidad.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'sender' => 'x@sumaempresa.com']]);
        Cache::put('graph_token', 'token-falso', 60);
    }

    public function test_envio_con_403_explica_que_pedir(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403)]);
        try {
            GraphMail::enviar('carme.tarres@sumaempresa.com', ['a@b.com'], [], 'Asunto', 'Texto');
            $this->fail('Debía lanzar GraphSinPermiso');
        } catch (GraphSinPermiso $e) {
            $this->assertStringContainsString('carme.tarres@sumaempresa.com', $e->getMessage());
            $this->assertStringContainsString('Contacta con Alex', $e->getMessage());
            $this->assertStringContainsString('Appmos-Buzones', $e->getMessage());
            $this->assertStringContainsString('ErrorAccessDenied', $e->getMessage());
        }
    }

    public function test_otros_errores_siguen_igual(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response('mal', 400)]);
        $this->expectExceptionMessage('Microsoft no ha aceptado el correo (HTTP 400)');
        GraphMail::enviar('alex.arregui@sumaempresa.com', ['a@b.com'], [], 'Asunto', 'Texto');
    }
}
