<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comunicar impuestos al cliente: además del borrador, envío real en dos pasos (15-oct-2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_comunicaciones', fn (Blueprint $t) => $t->timestamp('enviado_at')->nullable()->after('borrador_at'));
    }

    public function down(): void
    {
        Schema::table('impuesto_comunicaciones', fn (Blueprint $t) => $t->dropColumn('enviado_at'));
    }
};
