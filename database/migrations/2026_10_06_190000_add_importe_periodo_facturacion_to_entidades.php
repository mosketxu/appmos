<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** entidades.importe_facturacion (importe que se factura) y periodo_facturacion (a qué periodo corresponde: mensual, trimestral...). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            if (! Schema::hasColumn('entidades', 'importe_facturacion')) {
                $table->decimal('importe_facturacion', 10, 2)->nullable()->after('ciclofacturacion_id');
            }
            if (! Schema::hasColumn('entidades', 'periodo_facturacion')) {
                $table->unsignedSmallInteger('periodo_facturacion')->nullable()->after('importe_facturacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn(['importe_facturacion', 'periodo_facturacion']);
        });
    }
};
