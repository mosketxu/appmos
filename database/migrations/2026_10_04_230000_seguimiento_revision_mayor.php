<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Revisión del mayor: un check por empresa y mes en el Seguimiento mensual (se hace en Proc.Mensuales, en la web). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'revisionmayor')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'revisionmayor', 'nombre' => 'Revisión del mayor', 'ambito' => 'cliente', 'ejecucion' => 'web',
                'detalle' => 'Provisiones por aplicar, pagos sin cruzar, pagos en 410000 con factura del mismo importe… (Proc.Mensuales › Revisión del mayor)',
                'enlace' => 'contabilidad.procesos-mensuales', 'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'revisionmayor')->delete();
        }
    }
};
