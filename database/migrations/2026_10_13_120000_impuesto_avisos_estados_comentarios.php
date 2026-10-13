<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Aviso al cliente: estados sin_comunicar | comunicado | parcial (falta comunicar algún impuesto) | respondido, y comentarios con fecha y autor. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('impuesto_avisos')->where('estado', 'sin_avisar')->update(['estado' => 'sin_comunicar']);
        DB::table('impuesto_avisos')->where('estado', 'avisado')->update(['estado' => 'comunicado']);
        Schema::create('impuesto_aviso_comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedSmallInteger('ejercicio');
            $table->string('bloque', 12);
            $table->text('texto');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['entidad_id', 'ejercicio', 'bloque'], 'impuesto_aviso_com_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_aviso_comentarios');
        DB::table('impuesto_avisos')->where('estado', 'sin_comunicar')->update(['estado' => 'sin_avisar']);
        DB::table('impuesto_avisos')->where('estado', 'comunicado')->update(['estado' => 'avisado']);
    }
};
