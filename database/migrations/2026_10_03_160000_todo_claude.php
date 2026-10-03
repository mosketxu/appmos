<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claude ejecuta las tareas del TO-DO que se le asignan (3-oct-2026), a través de la cola de trabajadores:
 * - todo_tareas.claude_autorizada_*: Alex ha dado el visto bueno (automático si la asigna un Admin).
 * - tareas.no_antes_de: la cola no la entrega al trabajador antes de esa hora (pasadas cada hora; «Ejecutar ya» la adelanta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->timestamp('claude_autorizada_at')->nullable()->after('cerrada_at');
            $table->foreignId('claude_autorizada_por')->nullable()->after('claude_autorizada_at')->constrained('users')->nullOnDelete();
            $table->boolean('claude_pausada')->default(false)->after('claude_autorizada_por');
        });
        // Interruptor general («pausar todos los desarrollos automáticos») y otros ajustes sueltos del TO-DO
        Schema::create('todo_ajustes', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->string('valor')->nullable();
            $table->timestamps();
        });
        // Una fila por pasada de Claude: para medir el uso de las ejecuciones automáticas
        Schema::create('claude_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->nullable()->constrained('todo_tareas')->nullOnDelete();
            $table->string('pc', 50)->nullable();
            $table->boolean('ok')->default(true);
            $table->decimal('coste_usd', 8, 4)->nullable();
            $table->unsignedInteger('turnos')->nullable();
            $table->unsignedBigInteger('tokens')->nullable();
            $table->unsignedInteger('segundos')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
        Schema::table('tareas', function (Blueprint $table) {
            $table->timestamp('no_antes_de')->nullable()->after('destino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_ejecuciones');
        Schema::dropIfExists('todo_ajustes');
        Schema::table('tareas', fn (Blueprint $table) => $table->dropColumn('no_antes_de'));
        Schema::table('todo_tareas', function (Blueprint $table) {
            $table->dropForeign(['claude_autorizada_por']);
            $table->dropColumn(['claude_autorizada_at', 'claude_autorizada_por', 'claude_pausada']);
        });
    }
};
