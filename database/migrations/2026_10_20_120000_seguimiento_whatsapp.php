<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WhatsApp (20-oct-2026): recordatorio mensual en Proc.Mensuales → Seguimiento de exportar los chats de clientes y pulsar «Importar ahora». */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento_procesos') && ! DB::table('seguimiento_procesos')->where('clave', 'whatsapp')->exists()) {
            DB::table('seguimiento_procesos')->insert([
                'clave' => 'whatsapp', 'nombre' => 'Importar WhatsApp de clientes', 'ambito' => 'general', 'ejecucion' => 'web',
                'detalle' => 'Exportar el chat de cada cliente desde el móvil (con archivos), dejar el .zip en OneDrive/_ClaudeDesarrollo/WhatsApp/<cliente> y pulsar «Importar ahora» (Contabilidad › WhatsApp). Después, liberar los adjuntos del móvil: WhatsApp › Ajustes › Almacenamiento y datos › Administrar almacenamiento.',
                'enlace' => 'contabilidad.whatsapp', 'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seguimiento_procesos')) {
            DB::table('seguimiento_procesos')->where('clave', 'whatsapp')->delete();
        }
    }
};
