<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** «Bancos» (pasados y validados en el mes) como casilla mensual a la izquierda de Revisión del mayor: pendiente ↔ validado (estado revisado). */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('impuesto_modelos')->where('codigo', 'BANC')->exists()) {
            DB::table('impuesto_modelos')->insert(['codigo' => 'BANC', 'nombre' => 'Bancos (pasados y validados)', 'periodicidad' => 'M', 'desfase' => 0,
                'automatico' => 0, 'orden' => (int) DB::table('impuesto_modelos')->where('codigo', 'MAYOR')->value('orden') - 5, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('impuesto_modelos')->where('codigo', 'BANC')->delete();
    }
};
