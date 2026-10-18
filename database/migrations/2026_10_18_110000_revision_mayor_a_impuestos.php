<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** La Revisión del mayor sale de Proc.Mensuales / Seguimiento mensual: ahora es el «impuesto» MAYOR de Impuestos → Seguimiento. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'revisionmayor')->update(['activo' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'revisionmayor')->update(['activo' => true, 'updated_at' => now()]);
        }
    }
};
