<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Casillas mensuales «para el cliente» (11-nov-2026): lo que se entrega al cliente cada mes aunque la presentación a la AEAT sea trimestral
 * (303 y 111 del Grupo) y Durcal. Son modelos aparte: el 303/111 real (con su periodicidad y PDF) no se toca. Pendiente ↔ revisado, sin PDF.
 * Se asignan a cada entidad en Entidades → Impuestos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orden = (int) DB::table('impuesto_modelos')->where('codigo', 'MAYOR')->value('orden');
        foreach ([['303C', '303 · informe al cliente (mensual)', 1], ['111C', '111 · informe al cliente (mensual)', 2], ['DURCAL', 'Durcal (mensual)', 3]] as [$cod, $nom, $i]) {
            if (! DB::table('impuesto_modelos')->where('codigo', $cod)->exists()) {
                DB::table('impuesto_modelos')->insert(['codigo' => $cod, 'nombre' => $nom, 'periodicidad' => 'M', 'desfase' => 0, 'automatico' => 0,
                    'orden' => $orden + $i * 2, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('impuesto_modelos')->whereIn('codigo', ['303C', '111C', 'DURCAL'])->delete();
    }
};
