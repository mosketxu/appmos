<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vencimientos: proveedores que son notarios (se dan por pagados y no salen en la propuesta). `todas` = para todas las empresas; si no, `entidades` = JSON con los ids. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vencimientos_notarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('todas')->default(true);
            $table->json('entidades')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vencimientos_notarios');
    }
};
