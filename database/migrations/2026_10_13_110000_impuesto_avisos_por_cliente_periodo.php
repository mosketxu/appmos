<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El aviso al cliente es uno por cliente y periodo (bloque T1..T4 / anual), no por casilla: se mueve a su propia tabla. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuesto_avisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedSmallInteger('ejercicio');
            $table->string('bloque', 12);                        // T1..T4, D<modelo>, A
            $table->string('estado', 12)->default('sin_avisar'); // sin_avisar | avisado | respondido
            $table->timestamp('avisado_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['entidad_id', 'ejercicio', 'bloque'], 'impuesto_avisos_unico');
        });
        Schema::table('impuesto_estados', fn (Blueprint $table) => $table->dropColumn(['aviso_estado', 'aviso_at', 'aviso_user_id']));
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_avisos');
        Schema::table('impuesto_estados', function (Blueprint $table) {
            $table->string('aviso_estado', 12)->default('sin_avisar');
            $table->timestamp('aviso_at')->nullable();
            $table->unsignedBigInteger('aviso_user_id')->nullable();
        });
    }
};
