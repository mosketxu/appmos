<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Libro de IVA (303): carpeta de OneDrive (relativa a la raíz de OneDrive) donde cada empresa tiene sus Excel de IVA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libro_iva_carpetas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entidad_id')->unique();
            $t->string('carpeta', 500);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        // Primera prueba (9-oct-2026): Alexander Arregui
        $id = DB::table('entidades')->where('entidad', 'Alexander Arregui')->where('estado', 1)->value('id');
        if ($id) {
            DB::table('libro_iva_carpetas')->insert(['entidad_id' => $id, 'carpeta' => '_RUR_Marta_Alex/2026 RMA/_Alex 2026/IVA', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('libro_iva_carpetas');
    }
};
