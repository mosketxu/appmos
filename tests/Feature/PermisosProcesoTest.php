<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Roles;
use App\Http\Livewire\Admin\Usuarios;
use App\Models\User;
use App\Support\Accesos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Permisos por pestaña y por proceso: mientras no exista el de un proceso vale el de su pestaña; al tocarlo se crea sin quitar nada a nadie. */
class PermisosProcesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // En las peticiones web de Appmos el guard por defecto acaba siendo «sanctum» (sin proveedor de usuarios): el panel no debe depender de él
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::findOrCreate('contabilidad.procesosmensuales', 'web');
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Suma', 'web')->givePermissionTo('contabilidad.procesosmensuales');
        Role::findOrCreate('Gestoria', 'web');
    }

    protected function suma(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('Suma');

        return $u;
    }

    public function test_sin_permiso_propio_el_proceso_vale_lo_que_su_pestana(): void
    {
        $u = $this->suma();
        $this->assertFalse(Accesos::existe('proceso.pm.seguimiento'));
        $this->assertTrue($u->can('proceso.pm.seguimiento'));
        $otro = User::factory()->create();
        $this->assertFalse($otro->can('proceso.pm.seguimiento'));
    }

    public function test_quitar_un_proceso_a_un_rol_no_quita_los_demas_ni_la_pestana(): void
    {
        $this->actingAs($this->adminUser());
        Livewire::test(Roles::class)->call('alternar', 'Suma', 'proceso.pm.seguimiento');
        $s = $this->suma();
        $this->assertFalse($s->can('proceso.pm.seguimiento'));
        $this->assertTrue($s->can('proceso.pm.certificados'));
        $this->assertTrue($s->can('proceso.pm.petdocimpuestos'));
        $this->assertTrue($s->can('contabilidad.procesosmensuales'));
    }

    public function test_marcar_una_pestana_marca_sus_procesos_y_desmarcarla_los_quita(): void
    {
        $this->actingAs($this->adminUser());
        $c = Livewire::test(Roles::class);
        $c->call('alternar', 'Gestoria', 'contabilidad.procesosmensuales');
        $g = Role::findByName('Gestoria', 'web');
        $this->assertTrue($g->hasPermissionTo('contabilidad.procesosmensuales'));
        $this->assertTrue($g->hasPermissionTo('proceso.pm.seguimiento'));
        $c->call('alternar', 'Gestoria', 'contabilidad.procesosmensuales');
        $g->refresh();
        $this->assertFalse($g->hasPermissionTo('contabilidad.procesosmensuales'));
        $this->assertFalse($g->hasPermissionTo('proceso.pm.seguimiento'));
    }

    public function test_acceso_usuario_por_usuario_es_un_permiso_directo_y_no_toca_lo_del_rol(): void
    {
        $this->actingAs($this->adminUser());
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('Gestoria');
        $c = Livewire::test(Roles::class);
        $c->call('alternarUsuario', $u->id, 'proceso.pm.certificados');
        $u->refresh();
        $this->assertTrue($u->hasDirectPermission('proceso.pm.certificados'));
        $c->call('alternarUsuario', $u->id, 'proceso.pm.certificados');
        $this->assertFalse($u->fresh()->hasDirectPermission('proceso.pm.certificados'));
        // lo que da su rol no se puede quitar desde aquí
        $s = $this->suma();
        $c->call('alternarUsuario', $s->id, 'contabilidad.procesosmensuales');
        $this->assertTrue($s->fresh()->can('contabilidad.procesosmensuales'));
    }

    public function test_la_ficha_del_usuario_enseña_pestanas_y_procesos_y_los_guarda(): void
    {
        $this->actingAs($this->adminUser());
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('Gestoria');
        $c = Livewire::test(Usuarios::class)->call('editar', $u->id)
            ->assertSee('Acceso a pestañas y procesos')->assertSee('Certificados por caducar')->assertSee('Acceso a entidades');
        $c->call('marcarPestana', 'contabilidad.procesosmensuales', true)->set('permisosExtra', array_values(array_diff($c->get('permisosExtra'), ['proceso.pm.seguimiento'])))
            ->call('guardar');
        $u->refresh();
        $this->assertTrue($u->hasDirectPermission('contabilidad.procesosmensuales'));
        $this->assertTrue($u->hasDirectPermission('proceso.pm.certificados'));
        $this->assertFalse($u->hasDirectPermission('proceso.pm.seguimiento'));
    }

    public function test_la_pantalla_de_roles_ordena_por_bloques_con_roles_y_usuarios_juntos(): void
    {
        $this->actingAs($this->adminUser());
        // roles y usuarios en la misma tabla, con una barra entre ambos
        $h = Livewire::test(Roles::class)->assertSee('Contabilidad')->assertSee('Seguimiento (checklist mensual)')->html();
        $this->assertStringContainsString('border-left:2px solid #9ca3af', $h);
        $this->assertStringContainsString('Gestoria', $h);
    }

    public function test_cabecera_de_usuarios_con_inicial_y_apellido_y_mas_letras_si_coinciden(): void
    {
        $this->actingAs($this->adminUser(['name' => 'Zoe Quintana']));
        User::factory()->create(['name' => 'Nuria Lopez', 'activo' => true]);
        User::factory()->create(['name' => 'Nuno Lopez', 'activo' => true]);
        User::factory()->create(['name' => 'Susana Gómez Pérez', 'activo' => true]);
        $h = Livewire::test(Roles::class)->html();
        $this->assertStringContainsString('Z. Quintana', $h);
        $this->assertStringContainsString('Nur. Lopez', $h);    // coinciden N. y Nu. Lopez: se van añadiendo letras al nombre
        $this->assertStringContainsString('Nun. Lopez', $h);
        $this->assertStringContainsString('S. Gómez Pérez', $h);
        $this->assertStringNotContainsString('>Nuria Lopez<', $h);
    }

    public function test_los_usuarios_inactivos_salen_al_final_con_su_historial_y_no_se_tocan(): void
    {
        $this->actingAs($this->adminUser());
        $baja = User::factory()->create(['name' => 'Ines Baja', 'activo' => false]);
        $baja->assignRole('Suma');
        $c = Livewire::test(Roles::class);
        $h = $c->html();
        $this->assertStringContainsString('inactivo', $h);
        $this->assertStringContainsString('Usuario inactivo: tenía este acceso', $h);
        $c->call('alternarUsuario', $baja->id, 'proceso.pm.certificados');
        $this->assertCount(0, $baja->fresh()->getDirectPermissions());
        // sigue sin poder entrar
        $this->assertFalse((bool) $baja->fresh()->activo);
    }
}
