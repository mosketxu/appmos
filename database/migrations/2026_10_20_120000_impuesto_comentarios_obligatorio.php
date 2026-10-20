<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comentario de Impuestos de lectura obligatoria: salta una alarma en la casilla hasta que alguien lo marca como leído y validado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_comentarios', function (Blueprint $table) {
            $table->boolean('obligatorio')->default(false)->after('texto');
            $table->unsignedBigInteger('validado_user_id')->nullable()->after('obligatorio');
            $table->timestamp('validado_at')->nullable()->after('validado_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('impuesto_comentarios', fn (Blueprint $table) => $table->dropColumn(['obligatorio', 'validado_user_id', 'validado_at']));
    }
};
