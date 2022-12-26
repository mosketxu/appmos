<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFacturacionConceptosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturacion_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entidad_id')->constrained('entidades');
            $table->foreignId('ciclo_id')->constrained('ciclos');
            // $table->string('concepto');
            // $table->double('importe', 15, 2)->default(0.00);
            $table->integer('ciclocorrespondiente')->default(0);
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
        Schema::dropIfExists('facturacion_conceptos');
    }
}
