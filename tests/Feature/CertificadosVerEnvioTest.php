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
        Schema::create('users', function ($t) {
            $t->id(); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_22_160000_create_certificados_vistos_table.php'))->up();
        $this->actingAs(\App\Models\User::forceCreate(['name' => 'Marta', 'email' => 'marta.ruiz@sumaempresa.com', 'password' => 'x']));   // ve la pantalla completa
        Schema::create('certificados_escaneos', function ($t) {
            $t->id(); $t->string('pc')->unique(); $t->string('escaneado'); $t->longText('certs'); $t->timestamps();
            $t->unsignedBigInteger('user_id')->nullable(); $t->timestamp('solicitado_at')->nullable(); $t->string('equipo')->nullable();
        });
        (require base_path('database/migrations/2026_10_02_140000_create_certificados_envios_table.php'))->up();
    }

    public function test_clic_en_un_mes_abre_una_ventana_con_el_envio_sin_tocar_la_pantalla(): void
    {
        $id = DB::table('certificados_envios')->insertGetId(['periodo' => '2026-09', 'enviado_at' => '2026-09-15 10:00:00', 'origen' => 'app',
            'para' => 'marta.ruiz@sumaempresa.com', 'asunto' => 'Asunto viejo', 'texto' => "Marta,\n\nIntro vieja\n\n• 01/12/2026 — Cert A\n\nUn saludo",
            'filas' => json_encode([['nombre' => 'Cert A', 'caduca' => '2026-12-01', 'pcs' => 'AlexMiniPC', 'incluir' => true, 'nota' => '']]),
            'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(Certificados::class, ['embebido' => true])
            ->call('verMes', '2026-09')
            ->assertSet('envioId', $id)
            ->assertSee('Correo de Septiembre 2026 a Marta')->assertSee('Mes que estás mirando')->assertSee('Asunto viejo')->assertSee('Intro vieja')
            ->assertSet('asunto', 'Certificados digitales que caducan - '.now()->locale('es')->translatedFormat('F Y'))   // el formulario de debajo no cambia
            ->call('cerrarEnvio')->assertSet('envioVentana', false)->assertSet('envioMes', '2026-09');   // la ventana se cierra y el mes sigue marcado
    }

    public function test_envio_a_mano_sin_texto_no_rompe(): void
    {
        DB::table('certificados_envios')->insert(['periodo' => '2026-10', 'enviado_at' => '2026-10-02 12:00:00', 'origen' => 'manual',
            'para' => null, 'asunto' => null, 'texto' => null, 'filas' => null, 'created_at' => now(), 'updated_at' => now()]);
        Livewire::test(Certificados::class, ['embebido' => true])->call('verMes', '2026-10')->assertSee('a mano, fuera de Appmos')->assertSee('(sin texto guardado)');
    }

    public function test_mes_sin_envio_lo_dice_y_varios_envios_se_eligen(): void
    {
        foreach (['2026-10-02 12:00:00' => 'Primero', '2026-10-20 09:00:00' => 'Segundo'] as $cuando => $asunto) {
            DB::table('certificados_envios')->insert(['periodo' => '2026-10', 'enviado_at' => $cuando, 'origen' => 'app', 'para' => 'm@x.com', 'asunto' => $asunto,
                'texto' => 'T', 'filas' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
        $t = Livewire::test(Certificados::class, ['embebido' => true])
            ->call('verMes', '2026-09')->assertSee('no se envió ningún correo')
            ->call('verMes', '2026-10')->assertSee('Segundo')->assertSee('Hubo 2 correos');   // abre el más reciente
        $primero = DB::table('certificados_envios')->where('asunto', 'Primero')->value('id');
        $t->call('elegirEnvio', $primero)->assertSee('Primero');
    }
}
