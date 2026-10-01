<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emailadm admite varios correos separados por «;» (a ellos va la petición de
 * documentación de Proc.Mensuales). Con 100 caracteres ya iba justo (hay de 90).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('emailadm', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('emailadm', 100)->nullable()->change();
        });
    }
};
