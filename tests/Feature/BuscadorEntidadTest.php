<?php

namespace Tests\Feature;

use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuscadorEntidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_buscador_va_en_la_cabecera_y_lleva_a_la_misma_pantalla_de_otra_entidad(): void
    {
        $this->seed(\Database\Seeders\AccesosSeeder::class);
        $u = $this->adminUser(['activo' => 1, 'debe_cambiar_password' => 0]);
        Sanctum::actingAs($u, ['*']);
        $this->actingAs($u);
        $e = Entidad::create(['entidad' => 'Eric Jacob Hiensch', 'nif' => 'X7736099A', 'estado' => 1, 'cliente' => 1]);
        $otra = Entidad::create(['entidad' => 'GRUPO LEOYBRA, S.L.', 'estado' => 1, 'cliente' => 1]);

        $html = $this->get(route('entidad.edit', $e))->assertOk()->getContent();
        $this->assertStringContainsString('lista-buscador-entidad', $html);
        $this->assertStringNotContainsString('lista-entidades-menu', $html);   // ya no está en el menú
        $this->assertMatchesRegularExpression('#entidad\W+'.$otra->id.'\W+edit#', $html);   // URL de la otra entidad (escapada por @js)

        $html = $this->get(route('entidad.contacto', $e))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#entidad\W+contacto\W+'.$otra->id.'\W#', $html);
    }
}
