<?php

namespace Tests\Feature;

use App\Http\Livewire\Todo;
use App\Models\TodoAviso;
use App\Models\TodoTarea;
use App\Models\User;
use App\Support\ColaTareas;
use App\Support\TodoClaude;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Claude como destinatario de tareas: autorización, cola de trabajadores, pausas, tope diario y API. */
class TodoClaudeTest extends TestCase
{
    use RefreshDatabase;

    protected User $claude;
    protected User $alex;
    protected User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        config(['contabilidad.claude_todo_gestores' => ['alex@sumaempresa.com']]);
        $this->claude = User::where('name', 'Claude')->whereNull('email')->first();   // lo crea la migración
        $this->alex = User::factory()->create(['name' => 'Alex', 'email' => 'alex@sumaempresa.com', 'activo' => true]);
        $this->alex->assignRole('Admin');
        $this->ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@sumaempresa.com', 'activo' => true]);
    }

    protected function crearPara(User $quien, string $titulo = 'Haz esto'): TodoTarea
    {
        $this->actingAs($quien);
        Livewire::test(Todo::class)->set('titulo', $titulo)->set('asignadosIds', [$this->claude->id])->call('crear');

        return TodoTarea::where('titulo', $titulo)->firstOrFail();
    }

    protected function cola()
    {
        return DB::table('tareas')->where('proceso', 'claude.todo');
    }

    public function test_si_la_asigna_un_admin_se_autoriza_y_se_encola_para_la_proxima_pasada(): void
    {
        $t = $this->crearPara($this->alex);
        $this->assertNotNull($t->fresh()->claude_autorizada_at);
        $this->assertSame(1, $this->cola()->count());
        $this->assertGreaterThan(now(), \Illuminate\Support\Carbon::parse($this->cola()->value('no_antes_de')));
    }

    public function test_si_la_asigna_otro_usuario_espera_el_visto_bueno_y_avisa_a_los_admin(): void
    {
        $t = $this->crearPara($this->ana);
        $this->assertNull($t->fresh()->claude_autorizada_at);
        $this->assertSame(0, $this->cola()->count());
        $this->assertSame(1, TodoAviso::where('user_id', $this->alex->id)->where('texto', 'like', '%visto bueno%')->count());   // el aviso va a Alex, el gestor
        $this->assertSame(0, TodoAviso::where('user_id', $this->claude->id)->count());   // Claude no recibe avisos

        $this->actingAs($this->ana);
        Livewire::test(Todo::class)->call('autorizarClaude', $t->id)->assertForbidden();
        $otroAdmin = User::factory()->create(['email' => 'otro.admin@sumaempresa.com', 'activo' => true]);
        $otroAdmin->assignRole('Admin');                                   // ser Admin no basta: solo Alex es gestor de Claude
        $this->actingAs($otroAdmin);
        Livewire::test(Todo::class)->call('autorizarClaude', $t->id)->assertForbidden();
        Livewire::test(Todo::class)->call('pausarClaudeTarea', $t->id, true)->assertForbidden();

        $this->actingAs($this->alex);
        Livewire::test(Todo::class)->call('autorizarClaude', $t->id);
        $this->assertNotNull($t->fresh()->claude_autorizada_at);
        $this->assertSame(1, $this->cola()->count());
    }

    public function test_ejecutar_ya_y_respuesta_urgente_adelantan_la_pasada_sin_duplicar(): void
    {
        $t = $this->crearPara($this->alex);
        Livewire::test(Todo::class)->call('ejecutarYa', $t->id);
        $this->assertSame(1, $this->cola()->count());
        $this->assertNull($this->cola()->value('no_antes_de'));

        $this->actingAs($this->ana);
        Livewire::test(Todo::class)->call('ejecutarYa', $t->id)->assertForbidden();   // Ana ni la creó ni es Admin
    }

    public function test_la_cola_respeta_pausa_general_pausa_de_tarea_y_tope_diario(): void
    {
        $t = $this->crearPara($this->alex);
        $this->cola()->update(['no_antes_de' => null]);
        $token = ColaTareas::crearTrabajador('AlexMiniPC');
        $w = ColaTareas::autenticar($token);
        $reservar = fn () => DB::transaction(fn () => ColaTareas::reservar($w, ['claude.todo']));

        TodoClaude::pausarGlobal(true);
        $this->assertNull($reservar());
        TodoClaude::pausarGlobal(false);

        TodoClaude::pausarTarea($t->fresh(), true);                 // cancela lo que esperaba y no se vuelve a encolar
        $this->assertNull($reservar());
        TodoClaude::pausarTarea($t->fresh(), false);
        $this->assertSame(1, $this->cola()->where('estado', 'pendiente')->count());   // reanudada: vuelve a la cola (para la próxima pasada)
        $this->cola()->where('estado', 'pendiente')->update(['no_antes_de' => null]);
        $this->assertNotNull($reservar());
    }

    public function test_el_tope_diario_bloquea(): void
    {
        config(['contabilidad.claude_todo_max_dia' => 1]);
        $t = $this->crearPara($this->alex);
        $this->cola()->update(['no_antes_de' => null]);
        DB::table('claude_ejecuciones')->insert(['tarea_id' => $t->id, 'pc' => 'X', 'ok' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(100, TodoClaude::porcentajeUso());
        $w = ColaTareas::autenticar(ColaTareas::crearTrabajador('AlexMiniPC'));
        $this->assertNull(DB::transaction(fn () => ColaTareas::reservar($w, ['claude.todo'])));
    }

    public function test_el_secundario_solo_coge_tareas_de_claude_si_el_principal_no_da_senales_o_hay_espera(): void
    {
        $t = $this->crearPara($this->alex);
        $this->cola()->update(['no_antes_de' => null]);
        ColaTareas::latido(ColaTareas::autenticar(ColaTareas::crearTrabajador('AlexMiniPC')), ['claude.todo']);   // principal vivo
        $sec = ColaTareas::autenticar(ColaTareas::crearTrabajador('PortalExomen'));
        $this->assertNull(DB::transaction(fn () => ColaTareas::reservar($sec, ['claude.todo'])));

        $this->cola()->update(['created_at' => now()->subMinutes(11)]);                                         // lleva esperando
        $this->assertNotNull(DB::transaction(fn () => ColaTareas::reservar($sec, ['claude.todo'])));
    }

    public function test_api_del_trabajador_detalle_y_resultado(): void
    {
        $t = $this->crearPara($this->alex, 'Investiga X');
        $this->cola()->update(['no_antes_de' => null]);
        $token = ColaTareas::crearTrabajador('AlexMiniPC');
        $cola = ColaTareas::reservar(ColaTareas::autenticar($token), ['claude.todo']);

        $this->postJson("/api/trabajador/tareas/{$cola->id}/todo", [], ['X-Token' => $token])
            ->assertOk()->assertJsonPath('titulo', 'Investiga X')->assertJsonPath('creador', 'Alex');

        $this->postJson("/api/trabajador/tareas/{$cola->id}/todo-resultado", [
            'estado' => 'bloqueada', 'respuesta' => '¿Con qué cuenta lo hago?',
            'uso' => ['coste_usd' => 0.42, 'turnos' => 7, 'tokens' => 1234, 'segundos' => 60, 'ok' => true, 'pc' => 'AlexMiniPC'],
        ], ['X-Token' => $token])->assertOk();

        $this->assertSame('bloqueada', $t->fresh()->estado);
        $this->assertDatabaseHas('todo_comentarios', ['tarea_id' => $t->id, 'user_id' => $this->claude->id, 'tipo' => 'respuesta', 'texto' => '¿Con qué cuenta lo hago?']);
        $this->assertSame(1, TodoAviso::where('user_id', $this->alex->id)->where('texto', 'tiene una duda y espera tu respuesta')->count());
        $this->assertSame(1, DB::table('claude_ejecuciones')->count());
        $this->assertSame(1, $this->cola()->count());   // Claude no se vuelve a encolar solo: sin bucle

        // Alex contesta: ahora sí vuelve a la cola
        $this->actingAs($this->alex);
        Livewire::test(Todo::class)->set('comentario', 'Con la de Suma')->call('comentar', $t->id);
        $this->cola()->where('estado', 'en_curso')->update(['estado' => 'ok']);
        Livewire::test(Todo::class)->set('comentario', 'Y rápido, por favor')->set('respUrgente', true)->call('comentar', $t->id);
        $this->assertSame(1, $this->cola()->where('estado', 'pendiente')->count());
    }

    public function test_otro_trabajador_o_tarea_no_autorizada_no_pueden_usar_la_api(): void
    {
        $t = $this->crearPara($this->alex);
        $this->cola()->update(['no_antes_de' => null]);
        $a = ColaTareas::crearTrabajador('AlexMiniPC');
        $b = ColaTareas::crearTrabajador('PortalExomen');
        $cola = ColaTareas::reservar(ColaTareas::autenticar($a), ['claude.todo']);
        $this->postJson("/api/trabajador/tareas/{$cola->id}/todo", [], ['X-Token' => $b])->assertNotFound();
        $this->postJson("/api/trabajador/tareas/{$cola->id}/todo", [], ['X-Token' => 'mal'])->assertForbidden();
    }

    public function test_ejecutar_ya_explica_que_ha_pasado(): void
    {
        $t = $this->crearPara($this->alex);
        $c = Livewire::test(Todo::class);
        $c->call('ejecutarYa', $t->id)->assertSet('mensajeTipo', 'aviso')->assertSee('ningún PC trabajador está en línea');   // sin trabajadores

        ColaTareas::latido(ColaTareas::autenticar(ColaTareas::crearTrabajador('AlexMiniPC')), ['claude.todo']);
        $c->call('ejecutarYa', $t->id)->assertSet('mensajeTipo', 'ok')->assertSee('la cogerá AlexMiniPC');

        TodoClaude::pausarGlobal(true);
        $c->call('ejecutarYa', $t->id)->assertSee('en pausa');
        TodoClaude::pausarGlobal(false);

        $sin = $this->crearPara($this->ana, 'Sin autorizar');
        $this->actingAs($this->alex);
        Livewire::test(Todo::class)->call('ejecutarYa', $sin->id)->assertSee('todavía no está autorizado');
    }

    public function test_alex_ordena_tambien_la_lista_de_claude_y_la_cola_respeta_ese_orden(): void
    {
        $uno = $this->crearPara($this->alex, 'Primera creada');
        $dos = $this->crearPara($this->alex, 'Segunda creada');
        $this->cola()->update(['no_antes_de' => null]);

        $c = Livewire::test(Todo::class);
        $c->assertSee('data-grupo="'.$this->claude->id.'"', false);               // ⠿ en las tareas de Claude (Alex es gestor)
        $c->call('reordenar', [$dos->id, $uno->id], $this->claude->id);

        $w = ColaTareas::autenticar(ColaTareas::crearTrabajador('AlexMiniPC'));
        $primera = DB::transaction(fn () => ColaTareas::reservar($w, ['claude.todo']));
        $this->assertSame($dos->id, (int) json_decode($primera->parametros, true)['tarea_id']);   // la que Alex puso primero

        $this->actingAs($this->ana);
        Livewire::test(Todo::class)->assertDontSee('data-handle', false);
    }

    public function test_uso_real_del_plan_se_sube_se_muestra_y_frena(): void
    {
        $token = ColaTareas::crearTrabajador('AlexMiniPC');
        $this->postJson('/api/trabajador/claude-uso', [
            'sesion_pct' => 6, 'sesion_reinicia' => 'Oct 4, 2:30am (Europe/Madrid)', 'semana_pct' => 19, 'semana_reinicia' => 'Oct 7, 7am (Europe/Madrid)',
        ], ['X-Token' => $token])->assertOk();

        $u = TodoClaude::usoPlan();
        $this->assertSame(6, (int) $u['sesion']);
        $this->assertSame('dom. 4/10 02:30', $u['sesion_reinicia']);
        $this->assertFalse(TodoClaude::planAgotado());

        $this->postJson('/api/trabajador/claude-uso', ['sesion_pct' => 85, 'semana_pct' => 19], ['X-Token' => $token])->assertOk();
        $this->assertTrue(TodoClaude::planAgotado());
        $this->assertFalse(TodoClaude::permitido());                              // con el plan al 85 % no empieza nada solo

        $this->actingAs($this->alex);
        Livewire::test(\App\Http\Livewire\TodoClaudeEstado::class)->assertSee('85 %')->assertSee('FRENO');
    }
}
