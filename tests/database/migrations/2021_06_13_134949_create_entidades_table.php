<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEntidadesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entidades', function (Blueprint $table) {
            $table->id();
            $table->string('entidad')->unique('entidad_UNIQUE');
            $table->string('alias')->nullable();
            $table->boolean('favorito')->nullable()->default(0);
            $table->integer('entidadtipo_id')->nullable();
            $table->string('direccion', 100)->nullable();
            $table->string('codpostal', 10)->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('provincia_id', 2)->nullable();
            $table->string('pais_id', 2)->nullable()->default('ES');
            $table->string('nif', 12)->nullable()->unique('nif_UNIQUE');
            $table->string('tfno', 50)->nullable();
            $table->string('emailgral', 100)->nullable();
            $table->string('emailadm', 100)->nullable();
            $table->string('web', 100)->nullable();
            $table->string('idioma', 2)->nullable()->default('ES');
            $table->string('banco1')->nullable();
            $table->string('iban1')->nullable();
            $table->string('banco2')->nullable();
            $table->string('iban2')->nullable();
            $table->string('banco3')->nullable();
            $table->string('iban3')->nullable();
            $table->integer('cicloimpuesto_id')->nullable()->default(0);
            $table->string('cicloimpuesto', 45)->nullable();
            $table->integer('metodopago_id')->nullable()->default(1);
            $table->integer('ciclofacturacion_id')->nullable()->default(2);
            $table->string('ciclofacturacion', 45)->nullable();
            $table->integer('diafactura')->nullable()->default(1);
            $table->integer('diavencimiento')->nullable()->default(10);
            $table->string('referenciacliente', 30)->nullable();
            $table->double('tipoiva', 8, 2)->nullable()->default(0.00);
            $table->double('porcentajemarta', 8, 2)->nullable()->default(0.00);
            $table->double('porcentajesusana', 8, 2)->nullable()->default(0.00);
            $table->string('cuentacontable_cliente', 10)->nullable();
            $table->string('observaciones')->nullable();
            $table->bigInteger('suma_id')->nullable()->index('entidades_suma_id_foreign');
            $table->string('cliente')->nullable();
            $table->boolean('estado')->nullable()->default(0);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('entidades');
    }
}
