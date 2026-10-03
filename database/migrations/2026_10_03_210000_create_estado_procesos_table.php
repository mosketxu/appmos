<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procesos FIQ desde la web (3-oct-2026): el VPS no ve OneDrive, así que guarda una COPIA del estado que los
 * scripts dejan en los PCs (pagosFinMes.json, resultado de Cash in store, checklist...). La verdad sigue en
 * OneDrive; el trabajador la sube tras cada tarea. Además, las tareas pueden tener un PC «preferido» (el que
 * hizo el paso anterior, por el retraso de OneDrive entre PCs) sin quedar atadas a él si se apaga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_procesos', function (Blueprint $table) {
            $table->string('clave', 120)->primary();
            $table->longText('valor')->nullable()->comment('JSON');
            $table->string('origen', 50)->nullable()->comment('PC que lo subió');
            $table->timestamps();
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->string('preferido', 50)->nullable()->after('destino')->comment('PC que debería cogerla; si está apagado, cualquiera');
            $table->json('web')->nullable()->comment('qué hace la pantalla al terminar (post, ctx, etiquetas): permite cerrarla aunque se recargue la página');
            $table->timestamp('cerrada_at')->nullable()->comment('la pantalla ya ha recogido el resultado');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', fn (Blueprint $table) => $table->dropColumn(['preferido', 'web', 'cerrada_at']));
        Schema::dropIfExists('estado_procesos');
    }
};
