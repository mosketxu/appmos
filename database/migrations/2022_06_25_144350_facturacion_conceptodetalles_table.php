<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FacturacionConceptodetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturacion_conceptodetalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facturacionconcepto_id')->constrained('facturacion_conceptos')->onDelete('cascade');
            $table->string('concepto');
            $table->string('unidades')->default('1');
            $table->double('importe', 15, 2)->default(0.00);
            $table->integer('orden')->default(0);
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
        Schema::dropIfExists('facturacion_conceptodetalles');
    }
}
