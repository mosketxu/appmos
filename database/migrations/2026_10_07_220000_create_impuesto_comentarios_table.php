<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comentarios de una casilla de Impuestos (obligación + ejercicio + periodo): puede haber varios, con autor y fecha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuesto_comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_impuesto_id');
            $table->unsignedSmallInteger('ejercicio');
            $table->string('periodo', 3);
            $table->text('texto');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['entidad_impuesto_id', 'ejercicio', 'periodo'], 'impuesto_coment_celda');
            $table->foreign('entidad_impuesto_id')->references('id')->on('entidad_impuestos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_comentarios');
    }
};
