<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidades con contabilidad analítica: Facturas OCR pone el código de canal
 * del proveedor en la plantilla de SAGE solo si está marcado (se marca desde
 * la propia pantalla de Facturas OCR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('contabilidad_analitica')->default(false)->after('cicloimpuesto');
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn('contabilidad_analitica');
        });
    }
};
