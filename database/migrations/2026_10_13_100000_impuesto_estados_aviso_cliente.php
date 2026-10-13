<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «Aviso al cliente» por casilla de Impuestos (13-oct-2026): sin_avisar → avisado (con fecha) → respondido. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_estados', function (Blueprint $table) {
            $table->string('aviso_estado', 12)->default('sin_avisar')->after('estado');
            $table->timestamp('aviso_at')->nullable()->after('aviso_estado');
            $table->unsignedBigInteger('aviso_user_id')->nullable()->after('aviso_at');
        });
    }

    public function down(): void
    {
        Schema::table('impuesto_estados', fn (Blueprint $table) => $table->dropColumn(['aviso_estado', 'aviso_at', 'aviso_user_id']));
    }
};
