<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Los tests nunca envían correo real por Graph (los usuarios de las factorías tienen correos @example.*;
        // las credenciales salen del .env o de config.json del PC). Quien lo pruebe, las pone con config() y Http::fake().
        config(['contabilidad.graph.client_secret' => null]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }
}
