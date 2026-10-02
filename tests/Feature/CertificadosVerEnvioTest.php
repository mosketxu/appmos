<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\Certificados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Al pulsar un mes con envío, la pantalla enseña la lista y el correo de aquel envío. */
class CertificadosVerEnvioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['contabilidad.ejecucion_local' => false]);
        Schema::create('users', fn ($t) => $t->id());
        Schema::create('certificados_escaneos', function ($t) {
            $t->id(); $t->string('pc')->unique(); $t->string('escaneado'); $t->longText('certs'); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_02_140000_create_certificados_envios_table.php'))->up();
    }

    public function test_ver_envio_carga_lista_y_correo_y_se_puede_volver(): void
    {
        $id = DB::table('certificados_envios')->insertGetId(['periodo' => '2026-09', 'enviado_at' => '2026-09-15 10:00:00', 'origen' => 'app',
            'para' => 'marta.ruiz@sumaempresa.com', 'asunto' => 'Asunto viejo', 'texto' => "Marta,\n\nIntro vieja\n\n• 01/12/2026 — Cert A\n\nUn saludo",
            'filas' => json_encode([['nombre' => 'Cert A', 'caduca' => '2026-12-01', 'pcs' => 'AlexMiniPC', 'incluir' => true, 'nota' => '']]),
            'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(Certificados::class, ['embebido' => true])
            ->call('ver', $id)
            ->assertSet('viendoEnvio', true)
            ->assertSet('asunto', 'Asunto viejo')
            ->assertSet('filas.0.nombre', 'Cert A')
            ->assertSee('Estás viendo un')
            ->call('volverALista')
            ->assertSet('viendoEnvio', false)
            ->assertSet('filas', []);
    }

    public function test_envio_a_mano_sin_texto_no_rompe(): void
    {
        $id = DB::table('certificados_envios')->insertGetId(['periodo' => '2026-10', 'enviado_at' => '2026-10-02 12:00:00', 'origen' => 'manual',
            'para' => null, 'asunto' => null, 'texto' => null, 'filas' => null, 'created_at' => now(), 'updated_at' => now()]);
        Livewire::test(Certificados::class, ['embebido' => true])->call('ver', $id)->assertSet('viendoEnvio', true)->assertSee('Estás viendo un');
    }
}
