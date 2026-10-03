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

    /** Usuario con rol Admin (desde el 24-sep-2026 guardar/borrar entidades y facturas exige permisos: el rol Admin los tiene todos). */
    protected function adminUser(array $atributos = []): \App\Models\User
    {
        \Spatie\Permission\Models\Role::findOrCreate('Admin', 'web');
        $u = \App\Models\User::factory()->create($atributos);
        $u->assignRole('Admin');

        return $u;
    }
}
