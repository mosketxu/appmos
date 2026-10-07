<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Un impuesto anual puede pintarse justo después de un trimestre de la vista del año (D2: entre T1 y el 04), en vez de en las «Anuales» del final. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuesto_modelos', function (Blueprint $table) {
            $table->string('despues_de', 3)->nullable()->after('mes_anual');   // T1..T4
        });
        DB::table('impuesto_modelos')->where('codigo', 'D2')->update(['despues_de' => 'T1']);
    }

    public function down(): void
    {
        Schema::table('impuesto_modelos', fn (Blueprint $table) => $table->dropColumn('despues_de'));
    }
};
