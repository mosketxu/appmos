<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de Proc.Mensuales, uno por fila. Mientras no se envía, la fila es la
 * marca «enviar ahora» de esa entidad y periodo (enviado_at = null); al enviar
 * se guarda lo que salió (destinatarios, asunto, texto) y la fecha. Un reenvío
 * es otra fila, así queda el histórico de todos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mails_enviados', function (Blueprint $table) {
            $table->id();
            $table->string('proceso', 50);
            $table->foreignId('entidad_id')->constrained('entidades')->cascadeOnDelete();
            $table->string('periodo', 7)->comment('AAAA-MM');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enviar_ahora')->default(false);
            $table->string('idioma', 2)->nullable();
            $table->text('destinatarios')->nullable();
            $table->string('asunto')->nullable();
            $table->text('texto')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['proceso', 'periodo', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mails_enviados');
    }
};
