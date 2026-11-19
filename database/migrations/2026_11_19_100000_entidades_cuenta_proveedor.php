<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 19-nov-2026 (Alex): «cuenta contable» pasa a «cuenta contable cliente» y se añaden código y cuenta contable de PROVEEDOR
 * (la ficha muestra los de cliente si es cliente y los de proveedor si es proveedor; los 4 si es las dos cosas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $t) {
            $t->renameColumn('cuentacontable', 'cuentacontable_cliente');
        });
        Schema::table('entidades', function (Blueprint $t) {
            $t->string('codigo_proveedor', 20)->nullable()->after('codigo_cliente');
            $t->string('cuentacontable_proveedor', 10)->nullable()->after('codigo_proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $t) {
            $t->dropColumn(['codigo_proveedor', 'cuentacontable_proveedor']);
        });
        Schema::table('entidades', function (Blueprint $t) {
            $t->renameColumn('cuentacontable_cliente', 'cuentacontable');
        });
    }
};
