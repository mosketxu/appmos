<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('redeme')->default(false)->after('cicloimpuesto_id');       // inscrita en devolución mensual (REDEME): el 303 negativo puede pedirse a devolver
            $table->boolean('gran_empresa')->default(false)->after('redeme');           // gran empresa: impuestos mensuales
        });
        DB::table('entidades')->where('entidad', 'like', 'Nueva Icaria Residencial%')->update(['redeme' => true]);   // 303 de agosto 2026: «Registro de devolución mensual»
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn(['redeme', 'gran_empresa']);
        });
    }
};
