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
            '*mailFolders/*/childFolders*' => Http::response(['value' => [['id' => 'F3', 'displayName' => 'Eric 2026', 'childFolderCount' => 0, 'unreadItemCount' => 0, 'totalItemCount' => 5]]]),
            '*mailFolders?*' => Http::response(['value' => [
                ['id' => 'F2', 'displayName' => 'Clientes', 'childFolderCount' => 1, 'unreadItemCount' => 0, 'totalItemCount' => 9],
                ['id' => 'F1', 'displayName' => 'Bandeja de entrada', 'childFolderCount' => 0, 'unreadItemCount' => 3, 'totalItemCount' => 50]]]),
            '*mailFolders/inbox?*' => Http::response(['id' => 'F1']),
            '*mailFolders/drafts?*' => Http::response(['id' => 'FD']),
            '*mailFolders/sentitems?*' => Http::response(['id' => 'FS']),
            '*mailFolders/deleteditems?*' => Http::response(['id' => 'FX']),
            '*mailFolders/junkemail?*' => Http::response(['id' => 'FJ']),
            '*mailFolders/archive?*' => Http::response([], 404),
            '*messages/AAA/createReply' => Http::response(['id' => 'D1', 'body' => ['content' => '<html><body><p>cita original</p></body></html>']]),
            '*messages/D1/attachments' => Http::response(['id' => 'LOGO']),
            '*messages/D2/attachments' => Http::response(['id' => 'LOGO']),
            '*messages/D1' => Http::response(['id' => 'D1']),
            '*users/*/messages' => Http::response(['id' => 'D2']),
            '*messages/AAA' => Http::response(['id' => 'AAA', 'isRead' => true]),
            '*messages/AAA/move' => Http::response(['id' => 'NUEVO']),
            '*mailFolders/inbox/messages*' => Http::response(['value' => [
                ['id' => 'AAA', 'subject' => 'Factura de prueba', 'from' => ['emailAddress' => ['name' => 'Proveedor Uno', 'address' => 'p@example.test']],
                    'receivedDateTime' => '2026-11-19T10:00:00Z', 'hasAttachments' => true, 'isRead' => false, 'bodyPreview' => "Hola   Alex,\n adjunto"],
                ['id' => 'BBB', 'subject' => 'Otro', 'from' => ['emailAddress' => ['name' => 'Ana', 'address' => 'a@example.test']],
                    'receivedDateTime' => '2026-11-19T11:00:00Z', 'hasAttachments' => false, 'isRead' => true, 'bodyPreview' => 'x'],
            ]]),
            '*messages/AAA/attachments/ATT1*' => Http::response(['id' => 'ATT1', 'name' => 'f.pdf', 'contentType' => 'application/pdf', 'contentBytes' => base64_encode('PDFDATA')]),
            '*messages/AAA/attachments/ATT2*' => Http::response(['id' => 'ATT2', 'name' => 'foto.png', 'contentType' => 'image/png', 'contentBytes' => 'iVBORw0KGgo=']),
            '*messages/AAA/attachments*' => Http::response(['value' => [
                ['id' => 'ATT1', 'name' => 'f.pdf', 'size' => 2048, 'contentType' => 'application/pdf', 'isInline' => false],
                ['id' => 'ATT2', 'name' => 'foto.png', 'size' => 2000, 'contentType' => 'image/png', 'isInline' => false],
                ['id' => 'ATT3', 'name' => 'logo.png', 'size' => 900, 'contentType' => 'image/png', 'isInline' => true],
            ]]),
            '*messages/AAA?*' => Http::response(['id' => 'AAA', 'subject' => 'Factura de prueba', 'from' => ['emailAddress' => ['name' => 'Proveedor Uno', 'address' => 'p@example.test']],
                'toRecipients' => [['emailAddress' => ['address' => 'buzon@example.test']]], 'ccRecipients' => [], 'receivedDateTime' => '2026-11-19T10:00:00Z',
                'body' => ['contentType' => 'html', 'content' => '<p>Hola</p><script>alert(1)</script><img src="https://track.example/p.gif">']]),
        ]);
    }

    protected function usuarioConCorreo(string $email = 'ana@example.test'): \App\Models\User
    {
        \Spatie\Permission\Models\Permission::findOrCreate('correo.ver', 'web');
        $u = \App\Models\User::factory()->create(['activo' => true, 'email' => $email]);
        $u->givePermissionTo('correo.ver');

        return $u;
    }

    public function test_sin_el_permiso_no_entra(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['activo' => true]));
        $this->get('/correo')->assertForbidden();
    }

    public function test_cualquier_usuario_con_el_permiso_entra_y_lee_solo_su_buzon(): void
    {
        $this->actingAs($this->usuarioConCorreo());
        $this->get('/correo')->assertOk();
        Livewire::test(CorreoClaude::class)->assertSee('Proveedor Uno')->assertSee('ana@example.test');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'users/ana%40example.test/mailFolders'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'buzon%40example.test'));
    }

    public function test_el_permiso_correo_ver_lo_tienen_los_roles_de_siempre(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('Usuario', 'web');
        (include base_path('database/migrations/2026_11_19_150000_permiso_correo_ver.php'))->up();
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('Usuario', 'web')->hasPermissionTo('correo.ver'));
    }

    public function test_buzon_sin_permiso_en_exchange_explica_que_hacer(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'x']), '*' => Http::response(['error' => 'x'], 403)]);
        $this->actingAs($this->usuarioConCorreo('nuevo@example.test'));
        Livewire::test(CorreoClaude::class)->assertSee('Appmos-Buzones')->assertSee('nuevo@example.test');
    }

    public function test_la_peticion_de_un_usuario_normal_se_autoriza_sola(): void
    {
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result('x')]);
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        \App\Models\User::factory()->create(['email' => 'jefe@example.test', 'activo' => true]);
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'listo', 'correo_id' => 'AAA']);
        @mkdir(\App\Support\Confidencialidad::dir($d), 0700, true);
        file_put_contents(\App\Support\Confidencialidad::dir($d).'/meta.json', json_encode(['sin_anonimizar' => true]));
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('instruccion', 'Resume')->call('pedir');
        $t = \App\Models\TodoTarea::find($d->fresh()->tarea_id);
        $this->assertNotNull($t->claude_autorizada_at);
        $this->assertSame([], (array) $t->claude_permisos);          // sin permisos extra
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('tareas')->where('proceso', 'claude.todo')->count());   // ya está en la cola
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_lista_y_correo_abierto_con_adjuntos_sin_los_incrustados(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)
            ->assertSee('Proveedor Uno')->assertSee('Factura de prueba')->assertSee('Otro')
            ->call('seleccionar', 'AAA')
            ->assertSee('p@example.test')->assertSee('f.pdf')->assertDontSee('logo.png')
            ->assertSee('Anonimizar y enviar a Claude');
    }

    public function test_el_clic_de_cada_fila_es_un_atributo_bien_formado(): void
    {
        $this->actingAs($this->adminUser());
        $this->assertStringContainsString('wire:click="seleccionar(\'AAA\')"', Livewire::test(CorreoClaude::class)->html());
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
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Anonimizar y enviar a Claude');   // el de otro Admin no se ve
        $this->actingAs($a);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Conversación con Claude')->assertDontSee('Anonimizar y enviar a Claude');
    }

    public function test_pedir_sin_documento_no_hace_nada(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('instruccion', 'Resume')->call('pedir')->assertStatus(422);
    }

    public function test_sin_anonimizar_es_voluntario_y_deja_el_correo_tal_cual(): void
    {
        $u = $this->adminUser();
        $this->actingAs($u);
        $tmp = sys_get_temp_dir().'/orig_'.bin2hex(random_bytes(4));
        mkdir($tmp);
        file_put_contents($tmp.'/00_cuerpo.txt', "De: Pepe Ejemplo\nHola");
        file_put_contents($tmp.'/01.xlsx', 'x');
        [$d, $omitidos] = \App\Support\Confidencialidad::crearSinAnonimizar($u, [
            new \App\Support\ArchivoLocal($tmp.'/00_cuerpo.txt', 'correo.txt'), new \App\Support\ArchivoLocal($tmp.'/01.xlsx', 'hoja.xlsx')], 'Correo (sin anonimizar): x');
        $this->assertSame('listo', $d->estado);
        $this->assertTrue(\App\Support\Confidencialidad::esClaro($d));
        $this->assertSame(['hoja.xlsx'], $omitidos);
        $this->assertCount(1, $d->archivos);
        $this->assertStringContainsString('Pepe Ejemplo', file_get_contents(\App\Support\Confidencialidad::dir($d).'/'.$d->archivos[0]));
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_pedir_con_un_documento_sin_anonimizar_manda_la_instruccion_tal_cual(): void
    {
        $u = $this->adminUser();
        $this->actingAs($u);
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'listo', 'correo_id' => 'AAA']);
        @mkdir(\App\Support\Confidencialidad::dir($d), 0700, true);
        file_put_contents(\App\Support\Confidencialidad::dir($d).'/meta.json', json_encode(['sin_anonimizar' => true]));
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('SIN ANONIMIZAR')
            ->set('instruccion', 'Contesta a Pepe Ejemplo')->call('pedir');
        $t = \App\Models\TodoTarea::find($d->fresh()->tarea_id);
        $this->assertStringContainsString('Contesta a Pepe Ejemplo', $t->descripcion);
        $this->assertStringContainsString('SIN anonimizar', $t->descripcion);
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_las_imagenes_son_voluntarias_y_las_incrustadas_van_como_datos(): void
    {
        $m = ['tipo' => 'html', 'html' => '<img src="cid:Img1@x"><img src="https://r.example/p.gif">'];
        $sin = CorreoClaude::documentoSeguro($m, ['img1@x' => 'data:image/png;base64,AAAA'], false);
        $this->assertStringContainsString("img-src data:\"", $sin);
        $this->assertStringNotContainsString('base64,AAAA', $sin);
        $con = CorreoClaude::documentoSeguro($m, ['img1@x' => 'data:image/png;base64,AAAA'], true);
        $this->assertStringContainsString('img-src data: https:', $con);
        $this->assertStringContainsString('src="data:image/png;base64,AAAA"', $con);
    }

    public function test_columna_de_carpetas_con_la_bandeja_primero_y_subcarpetas(): void
    {
        $this->actingAs($this->adminUser());
        $h = Livewire::test(CorreoClaude::class)->assertSeeInOrder(['Bandeja de entrada', 'Clientes'])->assertDontSee('Eric 2026');
        $h->call('alternar', 'F2')->assertSee('Eric 2026');
    }

    public function test_mover_el_correo_abierto_o_uno_arrastrado(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->call('mover', 'F3')->assertSet('sel', 'NUEVO')->assertSee('Correo movido');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/AAA/move') && $r['destinationId'] === 'F3');
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'messages/AAA') && $r['isRead'] === true);   // queda leído
        Livewire::test(CorreoClaude::class)->call('moverId', 'inbox', 'AAA');       // soltarlo en la carpeta donde ya está: no hace nada
        Http::assertSentCount(Http::recorded()->count());
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/move')));
    }

    public function test_sin_correo_abierto_mover_no_hace_nada(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('mover', 'F3')->assertStatus(422);
    }

    public function test_el_cuadro_de_texto_se_ve_desde_el_principio_y_se_conserva_al_anonimizar(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')
            ->assertSee('Escribe o dicta aquí lo que quieres hacer con este correo')
            ->set('instruccion', 'Resume')->assertSet('instruccion', 'Resume');
    }

    public function test_anonimizar_y_enviar_pide_a_claude_solo_al_terminar_y_una_vez(): void
    {
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result('Contesta a Persona1')]);
        $u = $this->adminUser();
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'procesando', 'correo_id' => 'AAA']);
        $dir = \App\Support\Confidencialidad::dir($d);
        mkdir($dir.'/_orig', 0700, true);
        file_put_contents($dir.'/meta.json', json_encode(['perfil' => 'estricto', 'auto' => 'Contesta a Pepe', 'originales' => []]));
        \App\Support\Confidencialidad::alTerminar($d->fresh());          // aún procesando: no hace nada
        $this->assertNull($d->fresh()->tarea_id);
        $d->update(['estado' => 'borrador']);
        \App\Support\Confidencialidad::alTerminar($d->fresh());
        $d = $d->fresh();
        $this->assertSame('listo', $d->estado);
        $t = \App\Models\TodoTarea::find($d->tarea_id);
        $this->assertStringContainsString('Contesta a Persona1', $t->descripcion);
        $this->assertStringNotContainsString('Pepe', $t->descripcion);
        $this->assertArrayNotHasKey('auto', \App\Support\Confidencialidad::leerMeta($d));
        \App\Support\Confidencialidad::alTerminar($d);                    // otra vez: no pide nada más
        $this->assertSame($t->id, $d->fresh()->tarea_id);
        $this->assertSame(1, \App\Models\TodoComentario::where('tarea_id', $t->id)->where('tipo', 'respuesta')->count() + 1);
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_las_columnas_tienen_tiradores_para_cambiar_el_ancho(): void
    {
        $this->actingAs($this->adminUser());
        $h = Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->html();
        $this->assertSame(3, substr_count($h, 'cursor:col-resize'));
        $this->assertStringContainsString('id="correo-grid"', $h);
        $this->assertStringNotContainsString('x-data', $h);      // el reparto de columnas no depende de Alpine: sin JavaScript sigue saliendo bien
    }

    public function test_eliminar_manda_el_correo_a_elementos_eliminados_y_lo_deja_leido(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Eliminar')->call('eliminar')
            ->assertSet('sel', null)->assertSee('Elementos eliminados');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/AAA/move') && $r['destinationId'] === 'FX');
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['isRead'] === true);
    }

    public function test_el_boton_de_dictar_acompana_a_los_cuadros_de_texto(): void
    {
        $this->actingAs($this->adminUser());
        $h = Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->html();
        $this->assertStringContainsString('data-dictar="instr-nuevo"', $h);
    }

    protected function docListo(\App\Models\User $u): ConfidencialidadDoc
    {
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'listo', 'correo_id' => 'AAA']);
        @mkdir(\App\Support\Confidencialidad::dir($d), 0700, true);
        file_put_contents(\App\Support\Confidencialidad::dir($d).'/meta.json', json_encode(['sin_anonimizar' => true]));

        return $d;
    }

    public function test_crear_borrador_de_respuesta_con_la_cita_y_la_firma_del_usuario(): void
    {
        $this->actingAs($u = $this->usuarioConCorreo());
        $d = $this->docListo($u);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('bTexto', 'Gracias por tu correo.')->call('crearBorrador')->assertSee('Borrador creado');
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'messages/D1')
            && str_contains($r['body']['content'], 'Gracias por tu correo.') && str_contains($r['body']['content'], 'cita original')
            && str_contains($r['body']['content'], 'Suma Apoyo Empresarial SL') && str_contains($r['body']['content'], 'cid:logo_suma')
            && str_contains($r['body']['content'], 'In compliance with the provisions of the RGPD'));      // pie legal
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/send'));                      // nunca se envía
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/D1/attachments'));      // el logo en línea
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_crear_borrador_de_correo_nuevo_con_destinatarios_validos(): void
    {
        $this->actingAs($u = $this->usuarioConCorreo());
        $d = $this->docListo($u);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('bModo', 'nuevo')->set('bPara', 'susan@example.test, mal-escrito; otra@example.test')
            ->set('bAsunto', 'Invoice')->set('bTexto', 'Dear Susanne')->call('crearBorrador')->assertSee('Borrador creado');
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/messages') && $r['subject'] === 'Invoice'
            && count($r['toRecipients']) === 2 && $r['toRecipients'][0]['emailAddress']['address'] === 'susan@example.test');
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_el_bloque_borrador_de_la_respuesta_de_claude_se_carga_para_editarlo(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $claude = \App\Support\TodoClaude::usuario();
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'listo', 'correo_id' => 'AAA']);
        @mkdir(\App\Support\Confidencialidad::dir($d), 0700, true);
        file_put_contents(\App\Support\Confidencialidad::dir($d).'/meta.json', json_encode(['sin_anonimizar' => true]));
        $t = \App\Models\TodoTarea::create(['titulo' => 't', 'descripcion' => 'redacta', 'creador_id' => $u->id, 'prioridad' => 'normal', 'oculta' => true]);
        $d->update(['tarea_id' => $t->id]);
        \App\Models\TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $claude->id, 'tipo' => 'respuesta', 'fecha' => now()->format('Y-m-d'),
            'texto' => "Aquí tienes.\n---BORRADOR---\nDear Susanne,\nPlease find the ledger.\n---FIN---\nNota breve."]);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSet('bTexto', "Dear Susanne,\nPlease find the ledger.")->assertSee('Crear borrador en Outlook');
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_las_peticiones_del_gestor_llevan_todos_los_permisos_y_las_del_resto_ninguno(): void
    {
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result('x')]);
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $jefe = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($jefe);
        $d = $this->docListo($jefe);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('instruccion', 'Haz esto')->call('pedir');
        $t = \App\Models\TodoTarea::find($d->fresh()->tarea_id);
        $this->assertEqualsCanonicalizing(array_keys(\App\Models\TodoTarea::PERMISOS_CLAUDE), (array) $t->claude_permisos);
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_si_claude_solo_nombra_las_marcas_en_una_frase_no_se_carga_basura(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $claude = \App\Support\TodoClaude::usuario();
        $d = $this->docListo($u);
        $t = \App\Models\TodoTarea::create(['titulo' => 't', 'descripcion' => 'redacta', 'creador_id' => $u->id, 'prioridad' => 'normal', 'oculta' => true]);
        $d->update(['tarea_id' => $t->id]);
        \App\Models\TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $claude->id, 'tipo' => 'respuesta', 'fecha' => now()->format('Y-m-d'),
            'texto' => 'Correo redactado entre ---BORRADOR--- y ---FIN---. Le dice que sí.']);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSet('bTexto', '')->assertSee('Usar la respuesta de Claude como borrador');
        \App\Support\Confidencialidad::borrar($d);
    }

    protected function tareaConDoc(\App\Models\User $u): array
    {
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result('x')]);
        $d = $this->docListo($u);
        \App\Support\Confidencialidad::pedirAClaude($d->fresh(), $u, 'Redacta el correo');

        return [$d->fresh(), \App\Models\TodoTarea::find($d->fresh()->tarea_id)];
    }

    public function test_el_borrador_se_crea_solo_cuando_claude_responde_y_una_sola_vez(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $u = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($u);
        [$d, $t] = $this->tareaConDoc($u);
        $resp = "Hecho.\n---BORRADOR---\nHello Susanne,\nPlease find the ledger attached.\n---FIN---\nNota.";
        \App\Support\TodoClaude::registrar($t, 'hecha', $resp);
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'messages/D1') && str_contains($r['body']['content'], 'Please find the ledger')
            && str_contains($r['body']['content'], 'Suma Apoyo Empresarial SL') && str_contains($r['body']['content'], 'In compliance with'));
        $this->assertSame('respuesta', \App\Support\Confidencialidad::leerMeta($d)['borrador_auto']['modo']);
        \App\Support\TodoClaude::registrar($t->fresh(), 'hecha', $resp);                       // misma respuesta: no se repite
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), 'messages/AAA/createReply')));
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->assertSee('Borrador creado en tu Outlook')->assertSet('bTexto', '');
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_el_borrador_automatico_nuevo_usa_para_y_asunto_de_la_cabecera(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $u = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($u);
        [$d, $t] = $this->tareaConDoc($u);
        \App\Support\TodoClaude::registrar($t, 'hecha', "---BORRADOR---\nMODO: nuevo\nPARA: susanne@example.test\nASUNTO: Pending invoice\n\nDear Susanne,\nWe attach the ledger.\n---FIN---");
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/messages') && $r['subject'] === 'Pending invoice'
            && $r['toRecipients'][0]['emailAddress']['address'] === 'susanne@example.test' && str_contains($r['body']['content'], 'We attach the ledger'));
        $this->assertStringNotContainsString('MODO', json_encode(Http::recorded()->map(fn ($p) => $p[0]->body())->all()));
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_una_respuesta_sin_bloque_no_crea_borrador(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $u = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($u);
        [$d, $t] = $this->tareaConDoc($u);
        \App\Support\TodoClaude::registrar($t, 'hecha', 'Correo redactado entre ---BORRADOR--- y ---FIN---. Le dice que sí.');
        $this->assertCount(0, Http::recorded(fn ($r) => str_ends_with($r->url(), 'createReply')));
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_al_mover_un_correo_su_conversacion_con_claude_se_va_con_el(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $d = $this->docListo($u);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->call('mover', 'F3');
        $this->assertSame('NUEVO', $d->fresh()->correo_id);          // el id nuevo que devuelve Graph al mover
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_vista_previa_de_un_adjunto_de_imagen_voluntaria(): void
    {
        $this->actingAs($this->adminUser());
        $c = Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA');
        $this->assertStringNotContainsString('data:image/png;base64,iVBOR', $c->html());                  // por defecto no se carga
        $c->call('verAdjunto', 'ATT2');
        $this->assertStringContainsString('data:image/png;base64,iVBORw0KGgo=', $c->html());
        $this->assertStringNotContainsString('data:application/pdf', $c->html());
        $c->call('verAdjunto', null);
        $this->assertStringNotContainsString('data:image/png;base64,iVBOR', $c->html());
    }

    public function test_los_adjuntos_del_borrador_son_voluntarios_y_se_suben_al_crearlo(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $d = $this->docListo($u);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('bTexto', 'Hola')
            ->set('adjOriginales', ['ATT1'])->set('subidasAdj', [\Illuminate\Http\UploadedFile::fake()->create('mayor.pdf', 50)])->call('crearBorrador')->assertSee('con 2 adjunto');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/D1/attachments') && ($r['name'] ?? '') === 'f.pdf');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/D1/attachments') && ($r['name'] ?? '') === 'mayor.pdf');
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_sin_elegir_adjuntos_no_se_adjunta_nada_salvo_el_logo(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $d = $this->docListo($u);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('bTexto', 'Hola')->call('crearBorrador')->assertSee('Borrador creado');
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), 'messages/D1/attachments')));      // solo el logo
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_adjuntar_a_un_borrador_ya_creado_automaticamente(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $u = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($u);
        [$d, $t] = $this->tareaConDoc($u);
        \App\Support\TodoClaude::registrar($t, 'hecha', "---BORRADOR---\nHello,\nSee attached.\n---FIN---");
        $this->assertSame('D1', \App\Support\Confidencialidad::leerMeta($d)['borrador_auto']['id']);
        Livewire::test(CorreoClaude::class)->call('seleccionar', 'AAA')->set('adjOriginales', ['ATT2'])->call('adjuntarAlBorrador')->assertSee('Se han añadido 1 adjunto');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'messages/D1/attachments') && ($r['name'] ?? '') === 'foto.png');
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_filtro_solo_con_conversacion_muestra_esos_correos_de_cualquier_carpeta(): void
    {
        $u = $this->usuarioConCorreo();
        $this->actingAs($u);
        $d = $this->docListo($u);                                   // AAA: con petición hecha
        $t = \App\Models\TodoTarea::create(['titulo' => 't', 'descripcion' => 'x', 'creador_id' => $u->id, 'prioridad' => 'normal', 'oculta' => true]);
        $d->update(['tarea_id' => $t->id]);
        $sin = ConfidencialidadDoc::create(['user_id' => $u->id, 'estado' => 'borrador', 'correo_id' => 'BBB']);      // BBB: preparado pero sin conversación
        $c = Livewire::test(CorreoClaude::class)->assertSee('Solo con conversación con Claude (1)')->assertSee('Otro');
        $c->call('alternarConv')->assertSee('Factura de prueba')->assertDontSee('Otro');
        $c->call('alternarConv')->assertSee('Otro');
        \App\Support\Confidencialidad::borrar($d);
        $sin->delete();
    }

    public function test_los_ids_de_graph_son_inmutables_para_que_la_conversacion_sobreviva_a_mover_el_correo(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(CorreoClaude::class);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.microsoft.com') && str_contains($r->header('Prefer')[0] ?? '', 'ImmutableId'));
    }

    public function test_adjuntos_pedidos_por_claude_van_solos_al_borrador(): void
    {
        config(['contabilidad.claude_todo_gestores' => ['jefe@example.test']]);
        $u = $this->usuarioConCorreo('jefe@example.test');
        $this->actingAs($u);
        [$d, $t] = $this->tareaConDoc($u);
        \App\Support\TodoClaude::registrar($t, 'hecha', "---BORRADOR---\nADJUNTOS: f.pdf\nHello,\nSee attached.\n---FIN---");
        $this->assertSame(['f.pdf'], \App\Support\Confidencialidad::leerMeta($d)['borrador_auto']['adjuntados']);
        $this->assertCount(2, Http::recorded(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), 'messages/D1/attachments')));   // logo + f.pdf
        \App\Support\Confidencialidad::borrar($d);
    }

    public function test_vista_previa_de_pdf_solo_pdf_y_solo_con_permiso(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*messages/AAA/attachments/PDF1*' => Http::response(['id' => 'PDF1', 'name' => 'a.pdf', 'contentType' => 'application/pdf', 'contentBytes' => base64_encode("%PDF-1.4 x")]),
            '*messages/AAA/attachments/TXT1*' => Http::response(['id' => 'TXT1', 'name' => 'a.pdf', 'contentType' => 'application/pdf', 'contentBytes' => base64_encode('no soy pdf')]),
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'x'])]);
        $this->get('/correo/adjunto?m=AAA&a=PDF1')->assertRedirect();
        $this->actingAs($this->usuarioConCorreo());
        $this->get('/correo/adjunto?m=AAA&a=PDF1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/correo/adjunto?m=AAA&a=TXT1')->assertNotFound();
    }
}
