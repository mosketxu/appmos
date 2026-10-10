<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('entidad_impuestos', 'user_id')) {
            Schema::table('entidad_impuestos', fn (Blueprint $t) => $t->dropColumn('user_id'));
        }
    }

    public function down(): void
    {
        Schema::table('entidad_impuestos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
    }
};
