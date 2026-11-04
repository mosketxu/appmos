<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Informes de Balerga (4-nov-2026): línea en Proc.Mensuales con los ficheros de SAGE que hay que subir cada mes para el informe de Albena. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'informes_balerga')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'informes_balerga', 'nombre' => 'Informes de Balerga', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'A principios de mes, para el mes anterior: sube el mayor y el sumas y saldos de Balerga Digital (y en el cierre de trimestre también pérdidas y ganancias; el balance es opcional). Con eso se preparan el resumen mensual de alquileres facturados/cobrados/pendientes y gastos, y cada trimestre el P&L detallado para Albena.',
                'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'informes_balerga')->delete();
        }
    }
};
