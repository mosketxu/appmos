<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «Apagado a propósito»: mientras tenga fecha, la vigilancia no avisa de ese PC; al volver a dar señales se borra sola. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trabajadores', 'silenciado_at')) {
            Schema::table('trabajadores', fn (Blueprint $t) => $t->timestamp('silenciado_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('trabajadores', fn (Blueprint $t) => $t->dropColumn('silenciado_at'));
    }
};
