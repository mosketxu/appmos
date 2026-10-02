<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento mensual (2-oct-2026): checklist de TODOS los procesos mensuales, como el de Procesos FIQ.
 * - seguimiento_procesos: la lista de procesos. ambito 'general' (un check por mes) o 'cliente' (un check por
 *   empresa y mes; la celda del proceso resume n/N). ejecucion 'web' o 'local' (hay que lanzarlo desde un PC).
 *   auto = proceso cuyo estado ya vive en otra tabla (petdocimpuestos → procesos_estado).
 * - seguimiento_marcas: un check por proceso, empresa (0 = proceso general) y mes (AAAA-MM): ok | proc | na.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_procesos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50)->unique();
            $table->string('nombre');
            $table->text('detalle')->nullable();
            $table->string('ambito', 10)->default('general')->comment('general | cliente');
            $table->string('ejecucion', 10)->default('web')->comment('web | local');
            $table->string('enlace')->nullable()->comment('ruta o URL donde se hace el proceso');
            $table->string('auto', 50)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('seguimiento_marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('seguimiento_procesos')->cascadeOnDelete();
            $table->unsignedBigInteger('entidad_id')->default(0);
            $table->string('periodo', 7)->comment('AAAA-MM');
            $table->string('estado', 10)->comment('ok | proc | na');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['proceso_id', 'entidad_id', 'periodo']);
        });

        $ahora = now();
        DB::table('seguimiento_procesos')->insert([
            ['clave' => 'petdocimpuestos', 'nombre' => 'Pet. Documentación Impuestos', 'ambito' => 'cliente', 'ejecucion' => 'web',
                'enlace' => 'contabilidad.procesos-mensuales', 'auto' => 'petdocimpuestos', 'orden' => 10,
                'detalle' => 'Correo mensual a cada cliente pidiendo la documentación. Sale de Proc.Mensuales: solicitado (P) al enviar, recibido (✓) a mano. El mes es el periodo al que se refiere la petición.',
                'created_at' => $ahora, 'updated_at' => $ahora],
            ['clave' => 'certificados', 'nombre' => 'Certificados por caducar', 'ambito' => 'general', 'ejecucion' => 'local',
                'enlace' => 'certificados', 'auto' => null, 'orden' => 20,
                'detalle' => 'Lista de certificados digitales que caducan en los próximos 3 meses (AlexMiniPC + PortalExomen) y correo a Marta. Se ejecuta en LOCAL (los certificados están en los PCs). Se marca a mano al enviarlo.',
                'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_marcas');
        Schema::dropIfExists('seguimiento_procesos');
    }
};
