<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «Alta desde»: una obligación que empieza a mitad de año (p. ej. 303 mensual REDEME desde octubre) no genera los periodos anteriores. Pareja de baja_ejercicio/baja_periodo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->unsignedSmallInteger('alta_ejercicio')->nullable()->after('baja_periodo');
            $table->string('alta_periodo', 3)->nullable()->after('alta_ejercicio');
        });
    }

    public function down(): void
    {
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->dropColumn(['alta_ejercicio', 'alta_periodo']);
        });
    }
};
