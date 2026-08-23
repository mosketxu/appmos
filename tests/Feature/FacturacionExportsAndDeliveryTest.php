<?php

namespace Tests\Feature;

use App\Exports\RemesaExport;
use App\Http\Livewire\Facturacion\Facturaciones;
use App\Mail\MailFactura;
use App\Models\Ciclo;
use App\Models\Entidad;
use App\Models\Facturacion;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;
use App\Models\MetodoPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class FacturacionExportsAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCycle(): Ciclo
    {
        return Ciclo::findOrFail(DB::table('ciclos')->insertGetId(['ciclo' => 'Mensual', 'ciclos' => 12]));
    }

    private function makeFacturaConDetalle(Entidad $entidad, Ciclo $cycle, array $overrides = []): Facturacion
    {
        $factura = Facturacion::create(array_merge([
            'entidad_id' => $entidad->id,
            'ciclo_id' => $cycle->id,
            'serie' => '26',
            'numfactura' => 26000001,
            'fechafactura' => '2026-01-01',
            'fechavencimiento' => '2026-01-10',
            'metodopago_id' => 2,
        ], $overrides));

        $detalle = FacturacionDetalle::create([
            'facturacion_id' => $factura->id,
            'concepto' => 'Servicio',
        ]);
        FacturacionDetalleConcepto::create([
            'facturaciondetalle_id' => $detalle->id,
            'concepto' => 'Detalle',
            'tipo' => '0',
            'iva' => '0.21',
            'unidades' => 1,
            'importe' => 100,
            'base' => 100,
            'totaliva' => 21,
            'total' => 121,
        ]);

        return $factura;
    }

    public function test_remesa_export_does_not_mix_other_entities_invoices(): void
    {
        $this->actingAs(User::factory()->create());
        $cycle = $this->makeCycle();

        $entidadObjetivo = Entidad::create(['entidad' => 'Entidad objetivo']);
        $entidadAjena = Entidad::create(['entidad' => 'Entidad ajena']);

        $this->makeFacturaConDetalle($entidadObjetivo, $cycle, ['numfactura' => 26000001]);
        $this->makeFacturaConDetalle($entidadAjena, $cycle, ['numfactura' => 26000002]);

        Excel::fake();

        Livewire::test(Facturaciones::class, ['entidad' => $entidadObjetivo, 'ruta' => 'facturacion.show'])
            ->set('filtroremesa', '2026-01-10')
            ->call('exportRemesa');

        Excel::assertDownloaded('remesa.xlsx', function (RemesaExport $export) use ($entidadObjetivo) {
            return $export->remesa->isNotEmpty()
                && $export->remesa->pluck('empresacli')->every(fn ($nombre) => $nombre === $entidadObjetivo->entidad);
        });
    }

    public function test_mail_selected_only_sends_when_pdf_and_email_exist_and_marks_invoice_as_sent(): void
    {
        $this->actingAs(User::factory()->create());
        Mail::fake();
        Storage::fake('public');

        $cycle = $this->makeCycle();
        $entidad = Entidad::create(['entidad' => 'Entidad con mail']);

        $conPdfYMail = $this->makeFacturaConDetalle($entidad, $cycle, [
            'numfactura' => 26000001,
            'mail' => 'cliente@example.com',
            'ruta' => 'facturas',
            'fichero' => 'con-pdf.pdf',
        ]);
        Storage::disk('public')->put('facturas/con-pdf.pdf', 'contenido-pdf');

        $sinPdf = $this->makeFacturaConDetalle($entidad, $cycle, [
            'numfactura' => 26000002,
            'mail' => 'otro@example.com',
            'ruta' => 'facturas',
            'fichero' => 'no-existe.pdf',
        ]);

        Livewire::test(Facturaciones::class, ['entidad' => $entidad, 'ruta' => 'facturacion.show'])
            ->set('filtroanyo', '2026')
            ->set('filtromes', '1')
            ->set('selectAll', true)
            ->call('mailSelected');

        Mail::assertSent(MailFactura::class, function (MailFactura $mail) use ($conPdfYMail) {
            return $mail->factura->id === $conPdfYMail->id;
        });
        Mail::assertNotSent(MailFactura::class, function (MailFactura $mail) use ($sinPdf) {
            return $mail->factura->id === $sinPdf->id;
        });

        $this->assertTrue((bool) $conPdfYMail->fresh()->enviada);
        $this->assertFalse((bool) $sinPdf->fresh()->enviada);
    }

    public function test_pdf_generation_writes_a_pdf_file_to_storage(): void
    {
        Storage::fake('local');
        DB::table('metodo_pagos')->insert(['id' => 2, 'metodopago' => 'Transferencia', 'metodopagocorto' => 'Transf.']);
        $cycle = $this->makeCycle();
        $entidad = Entidad::create(['entidad' => 'Entidad PDF', 'tipoiva' => 0.21]);
        $factura = $this->makeFacturaConDetalle($entidad, $cycle, [
            'numfactura' => 26000001,
            'ruta' => 'facturas',
            'fichero' => 'factura-generada.pdf',
        ]);

        Facturacion::pdffactura($factura);

        Storage::disk('local')->assertExists('public/facturas/factura-generada.pdf');
        $this->assertGreaterThan(0, Storage::disk('local')->size('public/facturas/factura-generada.pdf'));
    }
}
