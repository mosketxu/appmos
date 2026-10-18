<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Revisión del mayor y Vencimientos como «impuestos» del Seguimiento de impuestos (mensuales). */
return new class extends Migration
{
    public function up(): void
    {
        $orden = (int) DB::table('impuesto_modelos')->max('orden');
        foreach ([['MAYOR', 'Revisión del mayor'], ['VENC', 'Vencimientos']] as [$codigo, $nombre]) {
            if (! DB::table('impuesto_modelos')->where('codigo', $codigo)->exists()) {
                DB::table('impuesto_modelos')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'periodicidad' => 'M', 'desfase' => 0,
                    'automatico' => 0, 'orden' => $orden += 10, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('impuesto_modelos')->whereIn('codigo', ['MAYOR', 'VENC'])->delete();
    }
};
