<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uso REAL del plan de Claude (lo que dice «/usage»: % de la sesión de 5 h y % de la semana, con su hora de reinicio).
 * Lo lee cada PC trabajador (`claude -p "/usage"`) cada pocos minutos y lo sube aquí; se enseña en la barra de menú
 * y sirve de freno: con mucho uso, Claude no empieza tareas automáticas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claude_uso', function (Blueprint $table) {
            $table->id();
            $table->string('pc', 50)->unique();
            $table->unsignedSmallInteger('sesion_pct')->nullable();
            $table->string('sesion_reinicia', 60)->nullable();
            $table->unsignedSmallInteger('semana_pct')->nullable();
            $table->string('semana_reinicia', 60)->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_uso');
    }
};
