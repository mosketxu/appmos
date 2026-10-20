<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pestaña WhatsApp de Contabilidad (20-oct-2026): chats exportados de WhatsApp (.zip/.txt) que un PC trabajador lee de
 * OneDrive/_ClaudeDesarrollo/WhatsApp/<carpeta> y sube aquí. Un mensaje se identifica por fecha+autor+nº de orden en ese minuto
 * (huella), así que volver a importar el mismo chat, entero, no duplica nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_chats')) {
            Schema::create('whatsapp_chats', function (Blueprint $t) {
                $t->id();
                $t->string('clave', 60)->unique();      // pevima
                $t->string('nombre', 120);               // Pevima
                $t->string('carpeta', 60);               // carpeta de OneDrive/_ClaudeDesarrollo/WhatsApp
                $t->string('yo', 120)->nullable();       // nombre con el que sale Alex en el chat (sus mensajes a la derecha)
                $t->timestamp('importado_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_mensajes')) {
            Schema::create('whatsapp_mensajes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chat_id');
                $t->char('huella', 40);
                $t->dateTime('fecha');
                $t->string('autor', 190)->nullable();   // null = aviso del sistema
                $t->longText('texto');
                $t->string('adjunto', 255)->nullable(); // nombre del fichero en whatsapp_adjuntos
                $t->timestamps();
                $t->unique(['chat_id', 'huella']);
                $t->index(['chat_id', 'fecha']);
            });
        }
        if (! Schema::hasTable('whatsapp_adjuntos')) {
            Schema::create('whatsapp_adjuntos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chat_id');
                $t->string('nombre', 190);
                $t->unsignedBigInteger('tam');
                $t->string('almacen', 400);             // ruta en storage/app
                $t->timestamps();
                $t->unique(['chat_id', 'nombre']);
            });
        }
        if (! DB::table('whatsapp_chats')->where('clave', 'pevima')->exists()) {
            DB::table('whatsapp_chats')->insert(['clave' => 'pevima', 'nombre' => 'Pevima', 'carpeta' => 'Pevima', 'yo' => 'Alexander Arregui',
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_adjuntos');
        Schema::dropIfExists('whatsapp_mensajes');
        Schema::dropIfExists('whatsapp_chats');
    }
};
