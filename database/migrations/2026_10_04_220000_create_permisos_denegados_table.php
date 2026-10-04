<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Accesos que da el rol pero que se le quitan a una persona concreta (panel de control, «Roles y permisos»). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_denegados', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('permiso', 191);
            $table->timestamps();
            $table->primary(['user_id', 'permiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_denegados');
    }
};
