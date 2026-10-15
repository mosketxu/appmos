<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 15-oct-2026: la petición de documentación de impuestos se lleva desde Impuestos → Seguimiento (icono de documento de cada periodo).
 * Sale de Seguimiento mensual (proceso petdocimpuestos inactivo); sus datos (procesos_estado, mails_enviados) siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('seguimiento_procesos')->where('clave', 'petdocimpuestos')->update(['activo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('seguimiento_procesos')->where('clave', 'petdocimpuestos')->update(['activo' => true, 'updated_at' => now()]);
    }
};
