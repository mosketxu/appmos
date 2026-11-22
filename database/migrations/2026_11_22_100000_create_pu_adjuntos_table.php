<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PU de entidades: imágenes y ficheros adjuntos a cada PU (22-nov-2026, Alex). Se guardan en storage/app/pus (volumen cifrado en el VPS). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pu_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pu_id')->constrained('pus')->cascadeOnDelete();
            $table->string('nombre', 255);
            $table->string('ruta', 255);
            $table->unsignedBigInteger('tamano')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pu_adjuntos');
    }
};
