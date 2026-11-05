<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** `entidades.retencion` guarda ya el CÓDIGO de SAGE (7 = 7 %, 18 = 15 %, 19 = 19 %), no el porcentaje: 15 → 18. Idempotente. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('entidades')->where('retencion', 15)->update(['retencion' => 18]);
    }

    public function down(): void
    {
    }
};
