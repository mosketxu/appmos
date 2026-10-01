<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Petición de documentación de impuestos (Proc.Mensuales): si se le pide por
 * correo a la entidad y el mensaje, parecido para todas pero personalizable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('mail_peticion_check')->default(false)->after('observaciones');
            $table->text('mail_peticion')->nullable()->after('mail_peticion_check');
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn(['mail_peticion_check', 'mail_peticion']);
        });
    }
};
