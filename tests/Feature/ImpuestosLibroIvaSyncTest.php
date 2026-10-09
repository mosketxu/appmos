<?php

namespace Tests\Feature;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El libro de IVA generado se registra como Excel de la casilla 303 y se actualiza cuando se modifica a mano en OneDrive. */
class ImpuestosLibroIvaSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function cabeceras(): array
    {
        $token = ColaTareas::crearTrabajador('PC-test');

        return ['X-Token' => $token];
    }

    protected function xlsx(string $marca): string
    {
        return 'PK'.str_repeat($marca, 120);
    }

    public function test_generar_registra_en_la_casilla_y_la_modificacion_manual_lo_sustituye(): void
    {
        Storage::fake('local');
        $h = $this->cabeceras();
        $ruta = '_Clientes/2026/Alex/IVA/IVA T3 2026.xlsx';
        $q = '?ruta='.urlencode($ruta).'&mtime=1000&generado=1&entidad_id=5&ejercicio=2026&periodo=T3';
        $this->call('POST', '/api/trabajador/impuestos/libro-subir'.$q, [], [], [], $this->serverH($h), $this->xlsx('a'))->assertOk()->assertJson(['ok' => true]);

        $d = ImpuestoDocumento::where('ruta_origen', $ruta)->first();
        $this->assertSame('303', $d->modelo);
        $this->assertSame('T3', $d->periodo);
        $this->assertSame('libro_iva', $d->tipo);
        $this->assertSame('IVA T3 2026.xlsx', $d->nombre);
        Storage::disk('local')->assertExists($d->almacen);
        $viejo = $d->almacen;

        // el PC lo ve en la lista y no sube nada si el fichero no es más nuevo
        $this->getJson('/api/trabajador/impuestos/libros', $h)->assertOk()->assertJsonPath('libros.0.ruta_origen', $ruta);
        $this->call('POST', '/api/trabajador/impuestos/libro-subir?ruta='.urlencode($ruta).'&mtime=1000', [], [], [], $this->serverH($h), $this->xlsx('b'))
            ->assertJson(['sin_cambios' => true]);

        // modificado a mano (más nuevo): sustituye la copia y borra el fichero antiguo
        $this->call('POST', '/api/trabajador/impuestos/libro-subir?ruta='.urlencode($ruta).'&mtime=2000', [], [], [], $this->serverH($h), $this->xlsx('b'))
            ->assertJson(['actualizado' => true]);
        $d->refresh();
        $this->assertSame(2000, (int) $d->mtime);
        $this->assertNotSame($viejo, $d->almacen);
        Storage::disk('local')->assertMissing($viejo);
        $this->assertSame(1, ImpuestoDocumento::count());

        // quitado a mano de Appmos: la sincronización no lo vuelve a subir; regenerar sí
        $d->update(['quitado_at' => now()]);
        $this->call('POST', '/api/trabajador/impuestos/libro-subir?ruta='.urlencode($ruta).'&mtime=3000', [], [], [], $this->serverH($h), $this->xlsx('c'))
            ->assertJson(['sin_cambios' => true]);
        $this->call('POST', '/api/trabajador/impuestos/libro-subir?ruta='.urlencode($ruta).'&mtime=3000&generado=1', [], [], [], $this->serverH($h), $this->xlsx('c'))->assertOk();
        $this->assertNull($d->fresh()->quitado_at);
    }

    public function test_rechaza_lo_que_no_es_un_excel(): void
    {
        $h = $this->cabeceras();
        $this->call('POST', '/api/trabajador/impuestos/libro-subir?ruta='.urlencode('_Clientes/x/IVA.xlsx').'&mtime=1&generado=1&entidad_id=1&ejercicio=2026&periodo=T3', [], [], [], $this->serverH($h), 'no soy un excel '.str_repeat('x', 200))
            ->assertStatus(422);
    }

    protected function serverH(array $h): array
    {
        return ['HTTP_X-Token' => $h['X-Token'], 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/octet-stream'];
    }
}
