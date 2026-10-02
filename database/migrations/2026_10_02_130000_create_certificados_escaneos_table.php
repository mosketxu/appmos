<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificados por caducar: el escaneo de cada PC (certificados.py --escanear) se sube aquí para que la
 * pantalla también funcione desde la web. Y el proceso pasa a ejecutarse «en la web y en local» (ejecucion = 'ambos').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_escaneos', function (Blueprint $table) {
            $table->id();
            $table->string('pc', 50)->unique();
            $table->string('escaneado', 25);
            $table->longText('certs');
            $table->timestamps();
        });
        DB::table('seguimiento_procesos')->where('clave', 'certificados')->update([
            'ejecucion' => 'ambos', 'enlace' => 'contabilidad.certificados',
            'detalle' => 'Lista de certificados digitales que caducan en los próximos 3 meses (AlexMiniPC + PortalExomen) y correo a Marta. Se puede lanzar en la web o en local; el escaneo de certificados lo hace cada PC (botón «Escanear este PC») y se sube a la web. Se marca a mano al enviarlo.',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_escaneos');
    }
};
