<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación intercompañía de Fashion IQ a CdC Switzerland (Breuninger y OMC): sustituye al Excel
 * «_Facturacion Breuninger OMC.xlsx». Compras de Plein (facturas recibidas) + 5 % = venta emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiq_ic_emisiones')) {
            Schema::create('fiq_ic_emisiones', function (Blueprint $t) {
                $t->id();
                $t->string('empresa', 20);              // Breuninger | OMC
                $t->string('periodo', 7);               // AAAA-MM del mes de trabajo
                $t->string('serie', 10);                // BR26 | OMC26
                $t->unsignedInteger('numero')->nullable(); // null = abono sin número propio
                $t->string('tipo', 10)->default('factura'); // factura | abono
                $t->date('fecha');
                $t->decimal('total', 12, 2);
                $t->decimal('total_calculado', 12, 2)->nullable();
                $t->string('nota', 200)->nullable();
                $t->string('origen', 10)->default('web'); // web | excel (histórico importado)
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
                $t->index(['empresa', 'serie', 'numero']);
                $t->index(['empresa', 'periodo']);
            });
        }
        if (! Schema::hasTable('fiq_ic_compras')) {
            Schema::create('fiq_ic_compras', function (Blueprint $t) {
                $t->id();
                $t->string('empresa', 20);
                $t->string('periodo', 7);               // mes en que se emite la venta
                $t->unsignedInteger('cuenta');          // proveedor 4000003…4000007
                $t->string('numero', 40);               // nº de la factura de Plein
                $t->date('fecha')->nullable();
                $t->unsignedSmallInteger('canal');      // tienda (0 BCN, 2 LRV PPI, 3 ROZ…)
                $t->decimal('importe', 12, 2);          // sin IVA; abonos en negativo
                $t->unsignedBigInteger('emision_id')->nullable();
                $t->string('origen', 10)->default('web');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
                $t->index(['empresa', 'periodo']);
                $t->index('emision_id');
                $t->index(['cuenta', 'numero']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiq_ic_compras');
        Schema::dropIfExists('fiq_ic_emisiones');
    }
};
