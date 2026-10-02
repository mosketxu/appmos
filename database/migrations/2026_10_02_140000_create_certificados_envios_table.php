<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Correos de «Certificados por caducar» enviados (para revisarlos): uno por envío, con el texto y la lista tal como salieron. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_envios', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7)->comment('AAAA-MM del envío');
            $table->timestamp('enviado_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 10)->default('app')->comment('app | manual');
            $table->string('para')->nullable();
            $table->string('cc')->nullable();
            $table->string('asunto')->nullable();
            $table->longText('texto')->nullable();
            $table->longText('filas')->nullable()->comment('JSON de la lista enviada');
            $table->timestamps();
            $table->index('periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_envios');
    }
};
