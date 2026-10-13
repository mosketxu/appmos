<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** «sin_comunicar» (13 letras) no cabía en string(12) y se guardaba truncado: se amplía la columna y se arregla lo ya guardado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_avisos', fn (Blueprint $t) => $t->string('estado', 20)->default('sin_comunicar')->change());
        DB::table('impuesto_avisos')->where('estado', 'sin_comunica')->update(['estado' => 'sin_comunicar']);
    }

    public function down(): void
    {
    }
};
