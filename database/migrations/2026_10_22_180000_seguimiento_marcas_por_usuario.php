<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proc.Mensuales (22-oct-2026): procesos «por usuario» (Certificados por caducar): cada usuario tiene sus propios checks.
 * seguimiento_procesos.por_usuario + seguimiento_marcas.propietario_id (0 = marca de todos; id de usuario = solo suya).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seguimiento_procesos', 'por_usuario')) {
            Schema::table('seguimiento_procesos', fn (Blueprint $t) => $t->boolean('por_usuario')->default(false));
        }
        if (! Schema::hasColumn('seguimiento_marcas', 'propietario_id')) {
            Schema::table('seguimiento_marcas', function (Blueprint $t) {
                $t->unsignedBigInteger('propietario_id')->default(0);
                $t->unique(['proceso_id', 'entidad_id', 'periodo', 'propietario_id'], 'seg_marcas_propietario_unique');
            });
            Schema::table('seguimiento_marcas', fn (Blueprint $t) => $t->dropUnique(['proceso_id', 'entidad_id', 'periodo']));
        }
        DB::table('seguimiento_procesos')->where('clave', 'certificados')->update(['por_usuario' => true]);
        // lo ya marcado en Certificados pasa a ser de quien lo marcó
        $ids = DB::table('seguimiento_procesos')->where('por_usuario', true)->pluck('id');
        DB::table('seguimiento_marcas')->whereIn('proceso_id', $ids)->where('propietario_id', 0)->whereNotNull('user_id')->update(['propietario_id' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        // no se deshace: volver a la clave única antigua podría chocar con marcas de varios usuarios
    }
};
