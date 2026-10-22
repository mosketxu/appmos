<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Proc.Mensuales (22-oct-2026): «Prefacturación» pasa a llamarse «Notas de cargo» (la clave interna sigue siendo prefacturacion). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'prefacturacion')->update([
                'nombre' => 'Notas de cargo',
                'detalle' => 'Cargos que se preparan cada mes para facturar después en SAGE: importe, suplido, periodo en que se factura, motivo y, si hay, el albarán o factura adjunto.',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'prefacturacion')->update(['nombre' => 'Prefacturación']);
        }
    }
};
