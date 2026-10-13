<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** «Falta comunicar algún impuesto» deja de ser un estado y pasa a ser una marca aparte (❗) que no tapa el estado del sobre. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_avisos', fn (Blueprint $t) => $t->boolean('falta')->default(false)->after('estado'));
        DB::table('impuesto_avisos')->where('estado', 'parcial')->update(['estado' => 'comunicado', 'falta' => true]);
    }

    public function down(): void
    {
        Schema::table('impuesto_avisos', fn (Blueprint $t) => $t->dropColumn('falta'));
    }
};
