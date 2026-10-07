<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un comentario de Impuestos puede llevar un fichero adjunto (uno por comentario). Se guarda en storage/app/impuestos/adjuntos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_comentarios', function (Blueprint $table) {
            $table->string('adjunto_nombre', 255)->nullable()->after('texto');
            $table->string('adjunto_almacen', 255)->nullable()->after('adjunto_nombre');
            $table->unsignedBigInteger('adjunto_tam')->nullable()->after('adjunto_almacen');
        });
    }

    public function down(): void
    {
        Schema::table('impuesto_comentarios', fn (Blueprint $table) => $table->dropColumn(['adjunto_nombre', 'adjunto_almacen', 'adjunto_tam']));
    }
};
