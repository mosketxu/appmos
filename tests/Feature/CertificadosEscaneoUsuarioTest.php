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

    public function test_colores_por_meses_naturales(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22'));
        $this->assertSame('rojo', CertificadosLista::color('2026-09-01'));   // ya caducado
        $this->assertSame('rojo', CertificadosLista::color('2026-10-01'));   // este mes (aunque ya haya pasado el día)
        $this->assertSame('rojo', CertificadosLista::color('2026-10-31'));
        $this->assertSame('rojo', CertificadosLista::color('2026-11-30'));   // el mes próximo
        $this->assertSame('amarillo', CertificadosLista::color('2026-12-01')); // 3.er mes
        $this->assertSame('amarillo', CertificadosLista::color('2027-01-31')); // 4.º mes
        $this->assertSame('', CertificadosLista::color('2027-02-01'));
        $this->assertSame('rojo', CertificadosLista::color('2027-11-30') === '' ? 'rojo' : 'x');   // año siguiente: sin color
        $this->travelTo(\Carbon\Carbon::parse('2026-12-15'));   // cambio de año: dic → ene (+1) rojo, feb/mar amarillo
        $this->assertSame('rojo', CertificadosLista::color('2027-01-20'));
        $this->assertSame('amarillo', CertificadosLista::color('2027-02-10'));
        $this->assertSame('amarillo', CertificadosLista::color('2027-03-10'));
        $this->assertSame('', CertificadosLista::color('2027-04-01'));
    }

    public function test_todos_ordena_junta_pcs_y_marca_renovados(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22'));
        $c = fn ($clave, $nombre, $caduca, $huella) => ['clave' => $clave, 'nombre' => $nombre, 'alias' => '', 'caduca' => $caduca, 'huella' => $huella];
        $lista = CertificadosLista::todos([
            'ALEXMINIPC' => ['escaneado' => 'x', 'certs' => [$c('emp:B1', 'Uno', '2026-11-05', 'H1'), $c('emp:B2', 'Dos viejo', '2026-10-30', 'H2'), $c('emp:B3', 'Tres', '2028-01-01', 'H3')]],
            'PC de Marta' => ['escaneado' => 'x', 'certs' => [$c('emp:B1', 'Uno', '2026-11-05', 'H1'), $c('emp:B2', 'Dos nuevo', '2028-05-01', 'H4')]],
        ]);
        $this->assertSame(['Dos viejo', 'Uno', 'Tres', 'Dos nuevo'], array_column($lista, 'nombre'));   // por fecha
        $this->assertSame(['AlexMiniPC', 'PC de Marta'], $lista[1]['pcs']);                            // mismo certificado, dos PCs
        $this->assertSame(['', 'rojo', '', ''], array_column($lista, 'color'));                       // el renovado no se pinta
        $this->assertTrue($lista[0]['renovado']);
        $this->assertSame('B1', $lista[1]['cif']);
    }

    public function test_meses_a_la_izquierda_y_detalle_del_mes_elegido(): void
    {
        config(['contabilidad.ejecucion_local' => false]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22'));
        $u = $this->usuario();
        $c = fn ($clave, $nombre, $caduca, $huella) => ['clave' => $clave, 'nombre' => $nombre, 'alias' => '', 'caduca' => $caduca, 'huella' => $huella];
        DB::table('certificados_escaneos')->insert(['pc' => 'PC de x', 'escaneado' => '2026-10-22T10:00', 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now(),
            'certs' => json_encode([$c('emp:B1', 'CERT NOVIEMBRE', '2026-11-05', 'H1'), $c('emp:B2', 'CERT FEBRERO', '2027-02-10', 'H2'), $c('emp:B3', 'CERT VIEJO', '2026-08-01', 'H3'), $c('emp:B4', 'CERT LEJANO', '2028-03-01', 'H4')])]);
        $t = \Livewire\Livewire::actingAs($u)->test(\App\Http\Livewire\Contabilidad\Certificados::class)
            ->assertSee('Caducidad por mes')->assertSee('Octubre 2026')->assertSee('Caducados antes de este mes')
            ->assertSee('CERT NOVIEMBRE')->assertSee('CERT LEJANO')                    // «Todos» los enseña
            ->call('elegirMes', '2026-11')->assertSee('Caducan en noviembre de 2026');
        $this->assertSame(['CERT NOVIEMBRE'], array_column($t->viewData('detalle'), 'nombre'));   // (el botón Copiar lleva todos en un atributo: no sirve assertDontSee)
        $t->call('elegirMes', 'caducados');
        $this->assertSame(['CERT VIEJO'], array_column($t->viewData('detalle'), 'nombre'));
        $t->call('elegirMes', '2026-12')->assertSee('No hay certificados pendientes en este mes.')
            ->call('elegirMes', 'basura')->assertSet('mesSel', 'todos');
        $html = $t->html();
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), 'divs sin cerrar');
    }

    public function test_certificados_abre_pantalla_entera_desde_proc_mensuales(): void
    {
        $src = file_get_contents(base_path('app/Http/Livewire/Contabilidad/ProcesosMensuales.php'));
        $this->assertStringContainsString("route('contabilidad.certificados')", $src);
    }
}
