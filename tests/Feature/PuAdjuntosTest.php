<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Pu;
use App\Models\PuAdjunto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** PU de entidades: imágenes y ficheros adjuntos (22-nov-2026). */
class PuAdjuntosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(bool $editar): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U', 'email' => ($editar ? 'ed' : 'ver').'@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $u = User::find($id);
        foreach (['entidades.ver', 'entidades.editar', 'entidades.todas'] as $p) {
            Permission::findOrCreate($p);
        }
        $u->givePermissionTo($editar ? ['entidades.ver', 'entidades.editar', 'entidades.todas'] : ['entidades.ver', 'entidades.todas']);

        return $u;
    }

    private function entidad(): Entidad
    {
        return Entidad::withoutGlobalScopes()->forceCreate(['entidad' => 'Cliente PU', 'nif' => 'B00000000']);
    }

    public function test_sube_imagen_y_fichero_al_guardar_y_se_abren(): void
    {
        Storage::fake('local');
        $ent = $this->entidad();
        $u = $this->usuario(true);
        Livewire::actingAs($u)->test(\App\Http\Livewire\Pu::class, ['entidad' => $ent])
            ->call('create')
            ->set('editing.destino', 'Banco')
            ->set('nuevas', [UploadedFile::fake()->image('captura.png')])
            ->set('nuevas', [UploadedFile::fake()->create('contrato.pdf', 50, 'application/pdf')])
            ->assertCount('subidas', 2)
            ->call('save')
            ->assertHasNoErrors();
        $pu = Pu::where('destino', 'Banco')->firstOrFail();
        $this->assertSame(['captura.png', 'contrato.pdf'], $pu->adjuntos->pluck('nombre')->all());
        foreach ($pu->adjuntos as $a) {
            Storage::disk('local')->assertExists($a->ruta);
        }
        $img = $pu->adjuntos->first();
        $this->actingAs($u)->get(route('entidad.pu.adjunto', $img->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($u)->get(route('entidad.pu', $ent->id))->assertOk()->assertSee(route('entidad.pu.adjunto', $img->id), false);
    }

    public function test_borrar_adjunto_y_pu_borra_ficheros(): void
    {
        Storage::fake('local');
        $ent = $this->entidad();
        $c = Livewire::actingAs($this->usuario(true))->test(\App\Http\Livewire\Pu::class, ['entidad' => $ent])
            ->call('create')->set('editing.destino', 'Web')
            ->set('nuevas', [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')])
            ->call('save');
        $pu = Pu::where('destino', 'Web')->firstOrFail();
        [$a, $b] = $pu->adjuntos->all();
        $c->call('quitarAdjunto', $a->id);
        Storage::disk('local')->assertMissing($a->ruta);
        $this->assertSame(1, PuAdjunto::count());
        $c->call('delete', $pu->id);
        Storage::disk('local')->assertMissing($b->ruta);
        $this->assertSame(0, PuAdjunto::count());
    }

    public function test_sin_permiso_de_editar_no_sube(): void
    {
        Storage::fake('local');
        $ent = $this->entidad();
        Livewire::actingAs($this->usuario(false))->test(\App\Http\Livewire\Pu::class, ['entidad' => $ent])
            ->set('nuevas', [UploadedFile::fake()->image('x.png')])->assertForbidden();
        $this->assertSame(0, PuAdjunto::count());
    }
}
