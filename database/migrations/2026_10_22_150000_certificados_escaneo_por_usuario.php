<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificados por caducar: escaneo desde el PC de cualquier usuario (22-oct-2026). Cada usuario tiene un código personal
 * (users.certificados_codigo) que lleva el .ps1 que se instala en su PC; el escaneo se guarda con su user_id y,
 * si desde la web pulsa «Escanear ahora», queda solicitado_at hasta que su PC lo recoge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('certificados_codigo', 64)->nullable()->unique();
        });
        Schema::table('certificados_escaneos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('solicitado_at')->nullable();
            $table->string('equipo', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('certificados_escaneos', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'solicitado_at', 'equipo']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('certificados_codigo');
        });
    }
};
