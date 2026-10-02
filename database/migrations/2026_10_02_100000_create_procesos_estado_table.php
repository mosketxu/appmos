<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cada proceso mensual por entidad y periodo (pedido 2026-10-02): sin fila =
 * «no solicitado»; 'solicitado' (se pone solo al enviar el correo de petición) y
 * 'recibido' (lo marca el usuario cuando llega la documentación).
 * Las empresas a las que ya se les envió la petición quedan como «solicitado».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_estado', function (Blueprint $table) {
            $table->id();
            $table->string('proceso', 50);
            $table->foreignId('entidad_id')->constrained('entidades')->cascadeOnDelete();
            $table->string('periodo', 7)->comment('AAAA-MM');
            $table->string('estado', 20)->comment('solicitado | recibido');
            $table->timestamp('solicitado_at')->nullable();
            $table->timestamp('recibido_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['proceso', 'entidad_id', 'periodo']);
        });

        $enviados = DB::table('mails_enviados')->whereNotNull('enviado_at')->whereNull('error')
            ->selectRaw('proceso, entidad_id, periodo, min(enviado_at) as primero, max(user_id) as user_id')
            ->groupBy('proceso', 'entidad_id', 'periodo')->get();
        foreach ($enviados as $m) {
            DB::table('procesos_estado')->insert([
                'proceso' => $m->proceso, 'entidad_id' => $m->entidad_id, 'periodo' => $m->periodo,
                'estado' => 'solicitado', 'solicitado_at' => $m->primero, 'user_id' => $m->user_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos_estado');
    }
};
