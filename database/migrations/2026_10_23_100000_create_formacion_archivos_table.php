<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Formación (23-oct-2026): archivos (PDF, imágenes, vídeos, presentaciones…) adjuntos a cada tema, guardados en storage/app/formacion/<tema>/. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formacion_archivos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tema_id')->index();
            $t->string('nombre');           // nombre original, el que se ve y se descarga
            $t->string('ruta');             // relativa al disco local (privado)
            $t->string('extension', 12)->default('');
            $t->unsignedBigInteger('tamano')->default(0);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formacion_archivos');
    }
};
