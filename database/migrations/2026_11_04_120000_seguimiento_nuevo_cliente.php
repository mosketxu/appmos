<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Nuevo cliente (4-nov-2026): línea en Proc.Mensuales para dar de alta una entidad desde el correo «Alta cliente» de Marta. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'nuevo_cliente')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'nuevo_cliente', 'nombre' => 'Nuevo cliente', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'Da de alta una entidad con los datos del correo «Alta cliente» de Marta. Arrastra el correo desde Outlook (o escribe su asunto), revisa los datos y crea la entidad. Solo mira el correo cuando se pide.',
                'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'nuevo_cliente')->delete();
        }
    }
};
