<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El importe de facturación y su periodo van en el historial de la entidad (entidad_historico), no en la ficha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidad_historico', function (Blueprint $table) {
            if (! Schema::hasColumn('entidad_historico', 'importe_facturacion')) {
                $table->decimal('importe_facturacion', 10, 2)->nullable()->after('comentario');
            }
            if (! Schema::hasColumn('entidad_historico', 'periodo_facturacion')) {
                $table->unsignedSmallInteger('periodo_facturacion')->nullable()->after('importe_facturacion');
            }
        });
        foreach (['importe_facturacion', 'periodo_facturacion'] as $col) {
            if (Schema::hasColumn('entidades', $col)) {
                Schema::table('entidades', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->decimal('importe_facturacion', 10, 2)->nullable();
            $table->unsignedSmallInteger('periodo_facturacion')->nullable();
        });
        Schema::table('entidad_historico', fn (Blueprint $t) => $t->dropColumn(['importe_facturacion', 'periodo_facturacion']));
    }
};
