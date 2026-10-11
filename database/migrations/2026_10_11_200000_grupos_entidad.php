<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidad_grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->timestamps();
        });
        Schema::table('entidades', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_id')->nullable()->after('alias');
            $table->index('grupo_id');
        });

        // Carga inicial (11-oct-2026): las demás entidades quedan sin grupo.
        $grupos = [
            'Grupo' => ['ALDRIBO%', 'Angir%', 'ARLESA%', 'BUXDEL%', 'Espiral Trade%', 'Gava%', 'Girona%', 'MONTRAS 1%', 'MONTRAS 2%',
                'Nueva Icaria Residencial%', 'Sa_c Residencial%', 'VULPELLAC%', 'YINMAL%'],
            'Gr.Neteges' => ['Chanclis%', 'Neteges Sic%', 'Nova Xanclis%'],
        ];
        foreach ($grupos as $nombre => $patrones) {
            $id = DB::table('entidad_grupos')->insertGetId(['nombre' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($patrones as $p) {
                DB::table('entidades')->where('entidad', 'like', $p)->update(['grupo_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropIndex(['grupo_id']);
            $table->dropColumn('grupo_id');
        });
        Schema::dropIfExists('entidad_grupos');
    }
};
