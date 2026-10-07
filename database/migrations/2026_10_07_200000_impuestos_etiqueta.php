<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos declaraciones del mismo impuesto para una misma entidad (7-oct-2026): p. ej. el 303 del cliente y el de su pareja. Cada obligación lleva una
 * «etiqueta» (vacía = la normal); la etiqueta viaja también en los PDF y en los alias de nombres de fichero para que cada PDF caiga en la suya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->string('etiqueta', 60)->default('')->after('modelo_id');
        });
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->dropForeign(['entidad_id']);   // el índice único (entidad, modelo) sostenía esta clave: se recrea con la etiqueta
            $table->dropUnique(['entidad_id', 'modelo_id']);
            $table->unique(['entidad_id', 'modelo_id', 'etiqueta']);
            $table->foreign('entidad_id')->references('id')->on('entidades')->cascadeOnDelete();
        });
        Schema::table('impuesto_documentos', function (Blueprint $table) {
            $table->string('etiqueta', 60)->default('')->after('modelo');
        });
        Schema::table('impuesto_alias', function (Blueprint $table) {
            $table->string('etiqueta', 60)->default('')->after('entidad_id');
        });
    }

    public function down(): void
    {
        Schema::table('impuesto_alias', fn (Blueprint $t) => $t->dropColumn('etiqueta'));
        Schema::table('impuesto_documentos', fn (Blueprint $t) => $t->dropColumn('etiqueta'));
        Schema::table('entidad_impuestos', function (Blueprint $table) {
            $table->dropForeign(['entidad_id']);
            $table->dropUnique(['entidad_id', 'modelo_id', 'etiqueta']);
            $table->dropColumn('etiqueta');
            $table->unique(['entidad_id', 'modelo_id']);
            $table->foreign('entidad_id')->references('id')->on('entidades')->cascadeOnDelete();
        });
    }
};
