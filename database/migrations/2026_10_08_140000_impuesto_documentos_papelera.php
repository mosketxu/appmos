<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Papelera de PDF de Impuestos: «quitar» solo marca el documento (no se borra el fichero ni vuelve a subirse desde OneDrive). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_documentos', function (Blueprint $t) {
            $t->timestamp('quitado_at')->nullable()->index();
            $t->unsignedBigInteger('quitado_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('impuesto_documentos', fn (Blueprint $t) => $t->dropColumn(['quitado_at', 'quitado_por']));
    }
};
