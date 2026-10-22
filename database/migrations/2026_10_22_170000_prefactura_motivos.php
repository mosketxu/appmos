<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prefacturación (22-oct-2026): motivos habituales que ayudan a rellenar el motivo de cada cargo (se van añadiendo al usarlos). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prefactura_motivos')) {
            Schema::create('prefactura_motivos', function (Blueprint $t) {
                $t->id();
                $t->string('texto', 255)->unique();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prefactura_motivos');
    }
};
