<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Prefacturación (21-oct-2026, tarea #74): costes que Marta prepara cada mes para facturar luego en SAGE. Proceso en Proc.Mensuales. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prefacturas')) {
            Schema::create('prefacturas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('entidad_id')->nullable()->index();
                $t->string('cliente')->nullable();
                $t->decimal('importe', 12, 2);
                $t->string('periodo', 7)->index(); // periodo en que se factura (YYYY-MM)
                $t->text('motivo');
                $t->string('estado', 12)->default('pendiente'); // pendiente | facturado
                $t->string('adjunto_ruta')->nullable();
                $t->string('adjunto_nombre')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'prefacturacion')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'prefacturacion', 'nombre' => 'Notas de cargo', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'Costes que se preparan cada mes para facturar después en SAGE: importe, periodo en que se factura, motivo y, si hay, el albarán o factura adjunto.',
                'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prefacturas');
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'prefacturacion')->delete();
        }
    }
};
