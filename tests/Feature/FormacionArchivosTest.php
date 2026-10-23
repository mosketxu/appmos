<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Formación: archivos adjuntos a cada tema (uno o varios, arrastrando) y visor a la derecha. */
class FormacionArchivosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(bool $editar): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U', 'email' => ($editar ? 'ed' : 'ver').'@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $u = User::find($id);
        Permission::findOrCreate('formacion.ver');
        Permission::findOrCreate('formacion.editar');
        $u->givePermissionTo($editar ? ['formacion.ver', 'formacion.editar'] : ['formacion.ver']);

        return $u;
    }

    private function tema(): int
    {
        return DB::table('formacion_temas')->insertGetId(['titulo' => 'Tema uno', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_sube_varios_archivos_a_un_tema_y_se_ven_a_la_derecha(): void
    {
        Storage::fake('local');
        $t = $this->tema();
        $c = Livewire::actingAs($this->usuario(true))->test(\App\Http\Livewire\Formacion::class)
            ->set('temaSubida', $t)
            ->set('subidas', [UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'), UploadedFile::fake()->image('foto.png')]);
        $this->assertSame(2, DB::table('formacion_archivos')->where('tema_id', $t)->count());
        $ultimo = DB::table('formacion_archivos')->orderByDesc('id')->first();
        $this->assertSame('foto.png', $ultimo->nombre);
        Storage::disk('local')->assertExists($ultimo->ruta);
        $c->assertSet('archivoSel', $ultimo->id)->assertSee('foto.png')->assertSee('formacion/archivo/'.$ultimo->id, false);
    }

    public function test_sin_permiso_de_editar_no_sube_y_con_ver_solo_mira(): void
    {
        Storage::fake('local');
        $t = $this->tema();
        $ver = $this->usuario(false);
        Livewire::actingAs($ver)->test(\App\Http\Livewire\Formacion::class)->set('temaSubida', $t)
            ->set('subidas', [UploadedFile::fake()->create('x.pdf', 10)])->assertForbidden();
        $this->assertSame(0, DB::table('formacion_archivos')->count());
    }

    public function test_sin_tema_no_guarda(): void
    {
        Storage::fake('local');
        Livewire::actingAs($this->usuario(true))->test(\App\Http\Livewire\Formacion::class)
            ->set('subidas', [UploadedFile::fake()->create('x.pdf', 10)])->assertSee('No sé a qué tema');
        $this->assertSame(0, DB::table('formacion_archivos')->count());
    }

    public function test_el_archivo_se_sirve_en_linea_si_es_seguro_y_descarga_si_no(): void
    {
        Storage::fake('local');
        $t = $this->tema();
        Storage::disk('local')->put("formacion/$t/a.pdf", '%PDF-1.4');
        Storage::disk('local')->put("formacion/$t/b.html", '<script>alert(1)</script>');
        $pdf = DB::table('formacion_archivos')->insertGetId(['tema_id' => $t, 'nombre' => 'a.pdf', 'ruta' => "formacion/$t/a.pdf", 'extension' => 'pdf', 'created_at' => now(), 'updated_at' => now()]);
        $html = DB::table('formacion_archivos')->insertGetId(['tema_id' => $t, 'nombre' => 'b.html', 'ruta' => "formacion/$t/b.html", 'extension' => 'html', 'created_at' => now(), 'updated_at' => now()]);
        $u = $this->usuario(false);
        $r = $this->actingAs($u)->get(route('formacion.archivo', $pdf))->assertOk();
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $r2 = $this->actingAs($u)->get(route('formacion.archivo', $html))->assertOk();
        $this->assertStringContainsString('attachment', $r2->headers->get('Content-Disposition'));
        $this->get(route('formacion.archivo', 9999))->assertNotFound();
    }

    public function test_sin_sesion_o_sin_permiso_no_se_ve_el_archivo(): void
    {
        $this->get('/formacion/archivo/1')->assertRedirect();
    }

    public function test_borrar_un_tema_borra_sus_archivos(): void
    {
        Storage::fake('local');
        $t = $this->tema();
        Storage::disk('local')->put("formacion/$t/a.pdf", 'x');
        DB::table('formacion_archivos')->insert(['tema_id' => $t, 'nombre' => 'a.pdf', 'ruta' => "formacion/$t/a.pdf", 'extension' => 'pdf', 'created_at' => now(), 'updated_at' => now()]);
        Livewire::actingAs($this->usuario(true))->test(\App\Http\Livewire\Formacion::class)->call('borrar', $t);
        $this->assertSame(0, DB::table('formacion_archivos')->count());
        Storage::disk('local')->assertMissing("formacion/$t/a.pdf");
    }
}
