<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un cliente que deja de presentar un impuesto no pierde su historial (8-oct-2026): la obligación queda «dada de baja desde» un periodo
 * (baja_ejercicio + baja_periodo): desde ahí no se generan más casillas y las pendientes se quitan; lo anterior se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->unsignedSmallInteger('baja_ejercicio')->nullable()->after('periodicidad');
            $table->string('baja_periodo', 3)->nullable()->after('baja_ejercicio');
        });
    }

    public function down(): void
    {
        Schema::table('entidad_impuestos', fn (Blueprint $table) => $table->dropColumn(['baja_ejercicio', 'baja_periodo']));
    }
};
