<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asunto (concepto) de la petición de documentación: por defecto el de la
 * plantilla de su idioma; si se cambia en una entidad, se guarda en ella
 * (null = el de la plantilla). {periodo} se cambia al enviar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('mail_peticion_asunto')->nullable()->after('mail_peticion');
        });
        Schema::table('plantillas_mail', function (Blueprint $table) {
            $table->string('asunto')->nullable()->after('idioma');
        });
        DB::table('plantillas_mail')->where('proceso', 'petdocimpuestos')->where('idioma', 'ES')
            ->update(['asunto' => 'Solicitud de documentación para impuestos – {periodo}']);
        DB::table('plantillas_mail')->where('proceso', 'petdocimpuestos')->where('idioma', 'EN')
            ->update(['asunto' => 'Request for tax documentation – {periodo}']);
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn('mail_peticion_asunto');
        });
        Schema::table('plantillas_mail', function (Blueprint $table) {
            $table->dropColumn('asunto');
        });
    }
};
