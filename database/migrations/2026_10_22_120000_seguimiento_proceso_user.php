<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Proc.Mensuales (22-oct-2026): a qué usuarios pertenece cada proceso (etiquetas a la derecha de la fila). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seguimiento_proceso_user')) {
            Schema::create('seguimiento_proceso_user', function (Blueprint $t) {
                $t->unsignedBigInteger('proceso_id');
                $t->unsignedBigInteger('user_id');
                $t->primary(['proceso_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_proceso_user');
    }
};
