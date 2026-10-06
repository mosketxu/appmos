<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historial de cada entidad: cambios de estado (con fecha y motivo) y comentarios sueltos. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entidad_historico')) {
            return;
        }
        Schema::create('entidad_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_id')->index();
            $table->date('fecha');
            $table->string('tipo', 20)->default('comentario');   // 'estado' | 'comentario'
            $table->tinyInteger('estado_anterior')->nullable();
            $table->tinyInteger('estado_nuevo')->nullable();
            $table->text('comentario')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('entidad_id')->references('id')->on('entidades')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidad_historico');
    }
};
