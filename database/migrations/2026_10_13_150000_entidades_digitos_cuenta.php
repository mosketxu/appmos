<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cifras de las cuentas de SAGE de cada empresa (Fashion 7, Neteges 9, el resto de clientes activos 6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $t) {
            $t->unsignedTinyInteger('digitos_cuenta')->nullable()->after('cuentacontable');
        });
        DB::table('entidades')->where('estado', 1)->where('cliente', 1)->update(['digitos_cuenta' => 6]);
        DB::table('entidades')->where('id', 2229)->update(['digitos_cuenta' => 7]);        // FASHION IQ BCN, S.L
        DB::table('entidades')->where('entidad', 'like', 'Neteges%')->where('cliente', 1)->update(['digitos_cuenta' => 9]);
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $t) {
            $t->dropColumn('digitos_cuenta');
        });
    }
};
