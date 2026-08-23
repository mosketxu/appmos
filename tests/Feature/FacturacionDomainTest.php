<?php

namespace Tests\Feature;

use App\Actions\FacturaCreateAction;
use App\Actions\FacturaReplicarAction;
use App\Actions\PrefacturaCreateAction;
use App\Http\Livewire\Facturacion\Prefacturas;
use App\Models\Ciclo;
use App\Models\Entidad;
use App\Models\Facturacion;
use App\Models\FacturacionConcepto;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FacturacionDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefactura_generation_reuses_loaded_entity_across_cycles(): void
    {
        $entity = Entidad::create([
            'entidad' => 'Entidad plan generacion',
            'diafactura' => 1,
            'diavencimiento' => 10,
            'enviar' => false,
            'tipoiva' => 0.21,
        ]);
        $cycle = Ciclo::findOrFail(DB::table('ciclos')->insertGetId(['ciclo' => 'Mensual', 'ciclos' => 3]));
        $concepto = FacturacionConcepto::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => $cycle->id,
            'ciclocorrespondiente' => '0',
            'concepto' => 'Cuota mensual',
            'importe' => 100,
        ]);

        $entidadQueries = 0;
        DB::listen(function ($query) use (&$entidadQueries) {
            if (str_contains($query->sql, '"entidades"') || str_contains($query->sql, '`entidades`')) {
                $entidadQueries++;
            }
        });

        (new PrefacturaCreateAction)->execute($concepto, $entity, 2026);

        $this->assertSame(3, Facturacion::where('entidad_id', $entity->id)->count());
        $this->assertSame(0, $entidadQueries);
    }

    public function test_prefactura_without_concepts_still_appears_in_the_listing(): void
    {
        $this->actingAs(User::factory()->create());

        $entity = Entidad::create(['entidad' => 'Entidad sin conceptos']);
        $cycle = Ciclo::findOrFail(DB::table('ciclos')->insertGetId(['ciclo' => 'Mensual', 'ciclos' => 12]));
        $prefactura = Facturacion::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => $cycle->id,
            'fechafactura' => now()->format('Y-m-d'),
            'facturable' => '1',
        ]);

        $rows = Livewire::test(Prefacturas::class, ['entidad' => new Entidad, 'ruta' => 'facturacion.prefacturas'])
            ->instance()
            ->rowsQuery
            ->get();

        $this->assertTrue($rows->pluck('id')->contains($prefactura->id));
    }

    public function test_replication_copies_details_and_concepts_without_number(): void
    {
        $entity = Entidad::create(['entidad' => 'Entidad de prueba']);
        $cycle = Ciclo::findOrFail(DB::table('ciclos')->insertGetId([
            'ciclo' => 'Mensual',
            'ciclos' => 12,
        ]));
        $invoice = Facturacion::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => $cycle->id,
            'serie' => '26',
            'numfactura' => 100001,
            'fechafactura' => '2026-01-01',
            'fechavencimiento' => '2026-01-10',
        ]);
        $detail = FacturacionDetalle::create([
            'facturacion_id' => $invoice->id,
            'concepto' => 'Servicio',
        ]);
        FacturacionDetalleConcepto::create([
            'facturaciondetalle_id' => $detail->id,
            'concepto' => 'Detalle',
            'unidades' => 2,
            'importe' => 10,
            'total' => 24.2,
        ]);

        $clone = (new FacturaReplicarAction)->execute($invoice);

        $this->assertNull($clone->numfactura);
        $this->assertDatabaseHas('facturacion_detalles', [
            'facturacion_id' => $clone->id,
            'concepto' => 'Servicio',
        ]);
        $cloneDetail = FacturacionDetalle::where('facturacion_id', $clone->id)->firstOrFail();
        $this->assertDatabaseHas('facturacion_detalle_conceptos', [
            'facturaciondetalle_id' => $cloneDetail->id,
            'concepto' => 'Detalle',
            'total' => 24.2,
        ]);
    }

    public function test_invoice_number_is_generated_per_series(): void
    {
        $entity = Entidad::create(['entidad' => 'Entidad numerada']);
        $cycle = Ciclo::findOrFail(DB::table('ciclos')->insertGetId([
            'ciclo' => 'Mensual',
            'ciclos' => 12,
        ]));
        Facturacion::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => $cycle->id,
            'serie' => '26',
            'numfactura' => 26000001,
            'fechafactura' => '2026-01-01',
            'fechavencimiento' => '2026-01-10',
        ]);
        $invoice = Facturacion::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => $cycle->id,
            'fechafactura' => '2026-01-02',
            'fechavencimiento' => '2026-01-10',
        ]);

        (new FacturaCreateAction)->execute($invoice);

        $this->assertSame('26', $invoice->fresh()->serie);
        $this->assertSame(26000002, $invoice->fresh()->numfactura);
    }

    public function test_invoice_total_accessors_reuse_loaded_concepts(): void
    {
        $entity = Entidad::create(['entidad' => 'Entidad de totales']);
        $invoice = Facturacion::create([
            'entidad_id' => $entity->id,
            'ciclo_id' => 1,
            'fechafactura' => '2026-01-01',
            'fechavencimiento' => '2026-01-10',
        ]);
        $detail = FacturacionDetalle::create(['facturacion_id' => $invoice->id]);
        FacturacionDetalleConcepto::create([
            'facturaciondetalle_id' => $detail->id,
            'concepto' => 'Detalle',
            'total' => 10,
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $invoice->getDiezAttribute();
        $invoice->getTotalesAttribute();

        $this->assertSame(1, $queries);
    }
}
