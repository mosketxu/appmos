<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Limpieza de OneDrive (20-oct-2026): proceso mensual en Proc.Mensuales con botón para lanzarla a mano (borra «_anterior …» y _copias_libro_iva). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'limpieza')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'limpieza', 'nombre' => 'Limpieza de OneDrive', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'Borra de OneDrive los ficheros de apoyo que guardan los procesos y no sirven (los «_anterior …» y las copias de _copias_libro_iva) de más de N días. Se hace sola el primer día de cada mes (miniServ); aquí se puede lanzar a mano: «Simular» cuenta, «Limpiar ahora» borra.',
                'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'limpieza')->delete();
        }
    }
};
