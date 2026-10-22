<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prefacturación (22-oct-2026): marca si el importe es un suplido. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prefacturas') && ! Schema::hasColumn('prefacturas', 'suplido')) {
            Schema::table('prefacturas', fn (Blueprint $t) => $t->boolean('suplido')->default(false)->after('importe'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prefacturas', 'suplido')) {
            Schema::table('prefacturas', fn (Blueprint $t) => $t->dropColumn('suplido'));
        }
    }
};
