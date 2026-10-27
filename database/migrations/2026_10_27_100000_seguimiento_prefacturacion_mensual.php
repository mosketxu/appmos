<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Prefacturación mensual (27-oct-2026): proceso mensual de Proc.Mensuales; del Excel de SAGE al «Prevision facturacion» y su envío a Susana y Marta. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'prefacturacion_mensual')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'prefacturacion_mensual', 'nombre' => 'Prefacturación mensual', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'Se sube el Excel «CabeceraAlbaranCliente» exportado de SAGE, un PC lo deja en OneDrive (_Suma/Facturas/2026) con el formato del «Prevision facturacion» del mes anterior y, tras confirmarlo, se envía a Susana y Marta con la firma de Alex. Al enviarlo se marca el mes.',
                'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'prefacturacion_mensual')->delete();
        }
    }
};
