<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CC de la petición de documentación (pedido 1-oct-2026): por defecto Marta, y se
 * puede quitar o añadir más por entidad (varios separados por «;»). En
 * mails_enviados se guarda el CC con el que salió cada correo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('mail_peticion_cc', 500)->nullable()->default('marta.ruiz@sumaempresa.com')->after('mail_peticion');
        });
        DB::table('entidades')->update(['mail_peticion_cc' => 'marta.ruiz@sumaempresa.com']);

        Schema::table('mails_enviados', function (Blueprint $table) {
            $table->text('cc')->nullable()->after('destinatarios');
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn('mail_peticion_cc');
        });
        Schema::table('mails_enviados', function (Blueprint $table) {
            $table->dropColumn('cc');
        });
    }
};
