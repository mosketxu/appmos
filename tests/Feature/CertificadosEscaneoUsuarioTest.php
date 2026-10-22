<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CertificadosLista;
use App\Support\CertificadosScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Escaneo de certificados desde el PC de cada usuario: código personal, «Escanear ahora» y quién ve qué. */
class CertificadosEscaneoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private const CODIGO = 'abcdefghijklmnopqrstuvwxyz0123456789ABCD';

    private function usuario(string $email = 'prueba@example.com'): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Prueba '.$email, 'email' => $email, 'password' => 'x', 'certificados_codigo' => $email === 'prueba@example.com' ? self::CODIGO : null, 'created_at' => now(), 'updated_at' => now()]);

        return User::find($id);
    }

    private function cert(string $clave = 'emp:B12345678'): array
    {
        return ['clave' => $clave, 'nombre' => 'EMPRESA PRUEBA', 'alias' => '', 'caduca' => now()->addDays(20)->format('Y-m-d'), 'huella' => 'AA11'];
    }

    public function test_sin_codigo_valido_es_403(): void
    {
        $this->usuario();
        $this->postJson('/api/certificados/usuario/escaneo', ['escaneado' => '2026-10-22T10:00', 'certs' => []])->assertStatus(403);
        $this->postJson('/api/certificados/usuario/escaneo', ['escaneado' => '2026-10-22T10:00', 'certs' => []], ['X-Codigo' => 'corto'])->assertStatus(403);
        $this->getJson('/api/certificados/usuario/pendiente', ['X-Codigo' => str_repeat('z', 40)])->assertStatus(403);
    }

    public function test_el_escaneo_se_guarda_con_el_usuario_y_limpia_la_peticion(): void
    {
        $u = $this->usuario();
        $h = ['X-Codigo' => self::CODIGO];
        $this->postJson('/api/certificados/usuario/escaneo', ['escaneado' => '2026-10-22T10:00', 'equipo' => 'PC1', 'certs' => [$this->cert()]], $h)->assertOk()->assertJson(['certs' => 1]);
        $this->getJson('/api/certificados/usuario/pendiente', $h)->assertOk()->assertJson(['escanear' => false]);

        DB::table('certificados_escaneos')->where('user_id', $u->id)->update(['solicitado_at' => now()]);
        $this->getJson('/api/certificados/usuario/pendiente', $h)->assertJson(['escanear' => true]);

        // un escaneo vacío (usuario sin certificados) también vale y atiende la petición
        $this->postJson('/api/certificados/usuario/escaneo', ['escaneado' => '2026-10-22T10:05', 'certs' => []], $h)->assertOk();
        $this->getJson('/api/certificados/usuario/pendiente', $h)->assertJson(['escanear' => false]);
        $this->assertSame(1, DB::table('certificados_escaneos')->where('pc', 'PC de '.$u->name)->count());
    }

    public function test_cada_usuario_solo_ve_lo_suyo_y_marta_todo(): void
    {
        $u = $this->usuario();
        $otro = $this->usuario('otro@example.com');
        $marta = $this->usuario('marta.ruiz@sumaempresa.com');
        foreach ([$u, $otro] as $x) {
            DB::table('certificados_escaneos')->insert(['pc' => 'PC de '.$x->name, 'escaneado' => '2026-10-22T10:00', 'certs' => json_encode([$this->cert()]), 'user_id' => $x->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame(['PC de '.$u->name], array_keys(CertificadosLista::escaneos($u->id)));
        $this->assertFalse(CertificadosLista::verTodos($u));
        $this->assertTrue(CertificadosLista::verTodos($marta));
        config(['contabilidad.ejecucion_local' => false]);
        $this->assertCount(2, CertificadosLista::escaneos());
    }

    public function test_el_script_lleva_codigo_y_url(): void
    {
        $ps = CertificadosScript::generar(self::CODIGO, 'https://appmos.example/api/certificados/usuario/');
        $this->assertStringStartsWith('@echo off', $ps);   // .cmd sin BOM
        $this->assertStringContainsString("'".self::CODIGO."'", $ps);
        $this->assertStringContainsString("'https://appmos.example/api/certificados/usuario'", $ps);
        $this->assertStringNotContainsString('__URL__', $ps);
        $this->assertStringContainsString('-Desinstalar', $ps);
        $this->assertStringContainsString('appmos-certificados', $ps);
        $this->assertStringNotContainsString('Register-ScheduledTask', $ps);   // nada en segundo plano
    }

    public function test_un_usuario_normal_solo_ve_el_boton_y_el_modal_por_pasos(): void
    {
        config(['contabilidad.ejecucion_local' => false]);
        $u = $this->usuario();
        \Livewire\Livewire::actingAs($u)->test(\App\Http\Livewire\Contabilidad\Certificados::class)
            ->assertSee('Escanear mi PC')->assertDontSee('Paso 2')->assertDontSee('Escanear y preparar la lista')
            ->call('abrirInstalar')->assertSee('Pulsar aquí para descargar')->assertSee('doble clic')
            ->call('cerrarInstalar')->assertDontSee('Pulsar aquí para descargar');
        DB::table('certificados_escaneos')->insert(['pc' => 'PC de x', 'escaneado' => '2026-10-22T10:00', 'certs' => json_encode([$this->cert()]), 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        \Livewire\Livewire::actingAs($u)->test(\App\Http\Livewire\Contabilidad\Certificados::class)
            ->assertSee('Certificados encontrados en tu PC: 1')->assertSee('EMPRESA PRUEBA')->assertSee('Descargar mi lista')->assertSee('Copiar')->assertSee('EMPRESA PRUEBA · B12345678 · caduca')->assertDontSee('JSON.parse', false)
            ->call('descargarMiLista')->assertFileDownloaded(filename: 'mis-certificados-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_marta_ve_la_pantalla_completa(): void
    {
        config(['contabilidad.ejecucion_local' => false]);
        \Livewire\Livewire::actingAs($this->usuario('marta.ruiz@sumaempresa.com'))->test(\App\Http\Livewire\Contabilidad\Certificados::class)
            ->assertSee('Escanear mi PC')->assertSee('Paso 2');
    }
}
