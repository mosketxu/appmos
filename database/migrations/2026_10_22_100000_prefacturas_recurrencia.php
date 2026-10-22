<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prefacturación (22-oct-2026): cada cargo es puntual (por defecto), mensual o trimestral a partir del periodo indicado. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prefacturas') && ! Schema::hasColumn('prefacturas', 'recurrencia')) {
            Schema::table('prefacturas', function (Blueprint $t) {
                $t->string('recurrencia', 12)->default('puntual')->after('periodo'); // puntual | mensual | trimestral
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prefacturas', 'recurrencia')) {
            Schema::table('prefacturas', fn (Blueprint $t) => $t->dropColumn('recurrencia'));
        }
    }
};
