<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carpeta de Outlook de cada cliente (pedido 2026-10-02): los correos de petición enviados se
 * mueven de Enviados a «<año>\___Suma <año>\<carpeta> <año>». Se guarda sin el año.
 * mails_enviados.archivado_at: cuándo se movió (o el aviso si no se pudo, en archivo_error).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('carpeta_outlook', 150)->nullable()->after('mail_peticion_cc');
        });
        Schema::table('mails_enviados', function (Blueprint $table) {
            $table->timestamp('archivado_at')->nullable()->after('enviado_at');
            $table->string('archivo_error', 500)->nullable()->after('archivado_at');
        });
        // Las de los primeros envíos (septiembre 2026), vistas en Outlook de Alex
        foreach ([27 => 'Eric', 183 => 'U Invest', 187 => 'Eva Font - Apoyo', 2232 => 'Vadim', 2228 => 'Jar',
                  2484 => 'Grupo Leoybra', 2545 => 'Kervoern'] as $id => $carpeta) {
            DB::table('entidades')->where('id', $id)->whereNull('carpeta_outlook')->update(['carpeta_outlook' => $carpeta]);
        }
    }

    public function down(): void
    {
        Schema::table('entidades', fn (Blueprint $t) => $t->dropColumn('carpeta_outlook'));
        Schema::table('mails_enviados', fn (Blueprint $t) => $t->dropColumn(['archivado_at', 'archivo_error']));
    }
};
