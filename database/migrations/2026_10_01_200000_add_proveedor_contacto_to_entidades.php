<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relación con la entidad, independiente entre sí (una entidad puede ser varias
 * cosas a la vez; p.ej. cliente por un lado y contacto de otra empresa por otro):
 * cliente (ya existía), proveedor y contacto. Contacto parte de las que tienen tipo
 * «Contacto» (entidadtipo_id 3) o son contacto de otra entidad (contacto_entidades).
 * Las de cliente vacío pasan a 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->boolean('proveedor')->default(false)->after('cliente');
            $table->boolean('contacto')->default(false)->after('proveedor');
        });
        DB::table('entidades')->where('entidadtipo_id', 3)
            ->orWhereIn('id', DB::table('contacto_entidades')->select('contacto_id'))
            ->update(['contacto' => true]);
        DB::table('entidades')->whereNull('cliente')->update(['cliente' => 0]);
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn(['proveedor', 'contacto']);
        });
    }
};
