<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFacturacionDetalleConceptosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturacion_detalle_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facturaciondetalle_id')->constrained('facturacion_detalles')->onDelete('cascade');
            $table->integer('orden')->default(0);
            $table->string('concepto');
            $table->integer('unidades')->default(1);
            $table->double('importe', 15, 2)->default(0.00);
            $table->double('iva', 15, 2)->default(0.00);
            $table->double('totaliva', 15, 2)->default(0.00);
            $table->double('base', 15, 2)->default(0.00);
            $table->double('exenta', 15, 2)->default(0.00);
            $table->double('total', 15, 2)->default(0.00);
            $table->integer('tipo')->default('1');
            $table->integer('subcuenta')->default('705000');
            $table->integer('bloqueado')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('facturacion_detalle_conceptos');
    }
}
