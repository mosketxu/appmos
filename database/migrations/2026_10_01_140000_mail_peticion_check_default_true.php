<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pet. Documentación Impuestos: de inicio se le pide a todas (pedido 1-oct-2026); se desmarca a mano. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('mail_peticion_check')->default(true)->change();
        });
        DB::table('entidades')->update(['mail_peticion_check' => true]);
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('mail_peticion_check')->default(false)->change();
        });
    }
};
