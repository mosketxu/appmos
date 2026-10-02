<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de tareas para los PCs «trabajadores» (2-oct-2026). La web (VPS) deja tareas; cada PC, con su
 * trabajador (Contabilidad/TrabajadorWeb/trabajador.py), pregunta por salida si hay alguna, la ejecuta
 * y devuelve log y resultado. Ver Contabilidad/TrabajadorWeb/DIAGNOSTICO.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique()->comment('AlexMiniPC, PortalExomen...');
            $table->string('token_hash', 64);
            $table->boolean('activo')->default(true);
            $table->json('capacidades')->nullable()->comment('procesos que sabe ejecutar');
            $table->timestamp('ultimo_latido')->nullable();
            $table->timestamps();
        });

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('proceso', 80)->comment('clave de la lista cerrada config(contabilidad.tareas_procesos)');
            $table->json('parametros')->nullable();
            $table->string('destino', 50)->nullable()->comment('nombre del trabajador; null = cualquiera');
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | en_curso | ok | error | cancelada');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('trabajador_id')->nullable()->constrained('trabajadores')->nullOnDelete();
            $table->longText('log')->nullable();
            $table->longText('resultado')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('terminada_at')->nullable();
            $table->timestamps();
            $table->index(['estado', 'destino']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
        Schema::dropIfExists('trabajadores');
    }
};
