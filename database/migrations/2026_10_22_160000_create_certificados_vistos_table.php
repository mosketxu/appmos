<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Certificados por caducar: «visto, no me interesa seguir viéndolo» (por usuario) para los ya caducados. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_vistos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('huella', 64);
            $table->timestamps();
            $table->unique(['user_id', 'huella']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_vistos');
    }
};
