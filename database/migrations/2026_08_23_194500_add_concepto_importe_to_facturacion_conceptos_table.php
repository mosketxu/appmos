<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConceptoImporteToFacturacionConceptosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('facturacion_conceptos', function (Blueprint $table) {
            if (! Schema::hasColumn('facturacion_conceptos', 'concepto')) {
                $table->string('concepto')->nullable()->after('ciclo_id');
            }
            if (! Schema::hasColumn('facturacion_conceptos', 'importe')) {
                $table->double('importe', 15, 2)->default(0.00)->after('concepto');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('facturacion_conceptos', function (Blueprint $table) {
            $table->dropColumn(['concepto', 'importe']);
        });
    }
}
