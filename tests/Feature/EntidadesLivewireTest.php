<?php

namespace Tests\Feature;

use App\Http\Livewire\ContactoEntidad;
use App\Http\Livewire\Ent;
use App\Http\Livewire\FacturacionConceptos\FacturacionConceptos;
use App\Http\Livewire\Pu;
use App\Models\Ciclo;
use App\Models\Entidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EntidadesLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_new_entity_can_be_created(): void
    {
        Livewire::test(Ent::class, ['entidad' => new Entidad, 'contacto' => new Entidad, 'ruta' => 'entidad.nueva'])
            ->set('entidad.entidad', 'Cliente Nuevo SL')
            ->set('entidad.tipoiva', 21)
            ->call('save');

        $this->assertDatabaseHas('entidades', ['entidad' => 'Cliente Nuevo SL']);
    }

    public function test_entity_name_is_required(): void
    {
        Livewire::test(Ent::class, ['entidad' => new Entidad, 'contacto' => new Entidad, 'ruta' => 'entidad.nueva'])
            ->set('entidad.entidad', '')
            ->set('entidad.tipoiva', 21)
            ->call('save')
            ->assertHasErrors(['entidad.entidad' => 'required']);

        $this->assertDatabaseCount('entidades', 0);
    }

    public function test_existing_entity_can_be_edited(): void
    {
        $entidad = Entidad::create(['entidad' => 'Nombre original', 'tipoiva' => 21]);

        Livewire::test(Ent::class, ['entidad' => $entidad, 'contacto' => new Entidad, 'ruta' => 'entidad.edit'])
            ->set('entidad.entidad', 'Nombre actualizado')
            ->set('entidad.tipoiva', 21)
            ->call('save');

        $this->assertDatabaseHas('entidades', ['id' => $entidad->id, 'entidad' => 'Nombre actualizado']);
    }

    public function test_contact_can_be_added_and_deleted(): void
    {
        $entidad = Entidad::create(['entidad' => 'Empresa principal', 'tipoiva' => 21]);
        $contacto = Entidad::create(['entidad' => 'Empresa contacto', 'tipoiva' => 21]);

        $component = Livewire::test(ContactoEntidad::class, ['entidad' => $entidad])
            ->set('contacto', $contacto->id)
            ->call('savecontacto');

        $this->assertDatabaseHas('contacto_entidades', [
            'entidad_id' => $entidad->id,
            'contacto_id' => $contacto->id,
        ]);

        $contactoRow = \App\Models\ContactoEntidad::where('entidad_id', $entidad->id)->firstOrFail();

        $component->call('delete', $contactoRow->id);

        $this->assertDatabaseMissing('contacto_entidades', ['id' => $contactoRow->id]);
    }

    public function test_pu_can_be_created_and_deleted(): void
    {
        $entidad = Entidad::create(['entidad' => 'Empresa PU', 'tipoiva' => 21]);

        Livewire::test(Pu::class, ['entidad' => $entidad])
            ->call('create')
            ->assertSet('showEditModal', true)
            ->set('editing.destino', 'Servidor de pruebas')
            ->call('save');

        $this->assertDatabaseHas('pus', [
            'entidad_id' => $entidad->id,
            'destino' => 'Servidor de pruebas',
        ]);

        $pu = \App\Models\Pu::where('entidad_id', $entidad->id)->firstOrFail();

        Livewire::test(Pu::class, ['entidad' => $entidad])
            ->call('delete', $pu->id);

        $this->assertDatabaseMissing('pus', ['id' => $pu->id]);
    }

    public function test_facturacion_concepto_can_be_created(): void
    {
        $entidad = Entidad::create(['entidad' => 'Empresa conceptos', 'tipoiva' => 21]);
        $ciclo = Ciclo::findOrFail(DB::table('ciclos')->insertGetId(['ciclo' => 'Mensual', 'ciclos' => 12]));

        Livewire::test(FacturacionConceptos::class, ['entidad' => $entidad])
            ->set('editing.concepto', 'Cuota de mantenimiento')
            ->set('editing.ciclo_id', $ciclo->id)
            ->set('editing.ciclocorrespondiente', 1)
            ->set('concepto', 'Mantenimiento mensual')
            ->set('importe', 100)
            ->set('orden', 1)
            ->call('save');

        $this->assertDatabaseHas('facturacion_conceptos', [
            'entidad_id' => $entidad->id,
            'concepto' => 'Cuota de mantenimiento',
        ]);
        $this->assertDatabaseHas('facturacion_conceptodetalles', [
            'concepto' => 'Mantenimiento mensual',
            'importe' => 100,
        ]);
    }
}
