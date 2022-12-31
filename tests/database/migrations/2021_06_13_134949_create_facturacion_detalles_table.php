<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFacturacionDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facturacion_id');
            $table->integer('orden')->default(0);
            $table->integer('tipo')->default(0);
            $table->string('concepto')->nullable();
            $table->integer('unidades')->default(1);
            $table->double('importe', 15, 2)->default(0.00);
            $table->decimal('iva', 15, 2)->default(0.00);
            $table->integer('subcuenta')->default(705000);
            $table->integer('pagadopor')->default(0);
            $table->timestamps();
            $table->foreign('facturacion_id', 'facturacion_detalles_facturacion_id_foreign')->references('id')->on('facturacion')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('facturacion_detalles');
    }
}
