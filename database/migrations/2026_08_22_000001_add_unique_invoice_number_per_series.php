<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueInvoiceNumberPerSeries extends Migration
{
    public function up()
    {
        DB::table('facturacion')->where('numfactura', '')->update(['numfactura' => null]);

        Schema::table('facturacion', function (Blueprint $table) {
            $table->unique(['serie', 'numfactura'], 'facturacion_serie_numfactura_unique');
        });
    }

    public function down()
    {
        Schema::table('facturacion', function (Blueprint $table) {
            $table->dropUnique('facturacion_serie_numfactura_unique');
        });
    }
}
