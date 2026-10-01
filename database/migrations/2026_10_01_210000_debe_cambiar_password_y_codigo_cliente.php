<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.debe_cambiar_password: al entrar se le obliga a poner una contraseña nueva
 *   (contraseña inicial o puesta por el Admin en el panel).
 * - entidades.codigo_cliente: código de cliente (430xxx del Excel ToDO Alex). No es la
 *   cuenta contable, aunque muchas veces coincida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('password');
        });
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('codigo_cliente', 20)->nullable()->after('cuentacontable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn('codigo_cliente');
        });
    }
};
