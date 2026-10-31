<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Iniciales de cada usuario (31-oct-2026): para «Otro Resp.» en Entidades sin el nombre entero. Se rellenan con las de su nombre. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'iniciales')) {
            Schema::table('users', fn ($t) => $t->string('iniciales', 5)->nullable()->after('name'));
        }
        foreach (DB::table('users')->whereNull('iniciales')->get(['id', 'name']) as $u) {
            $ini = '';
            foreach (preg_split('/\s+/', trim($u->name)) as $p) {
                $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }
            DB::table('users')->where('id', $u->id)->update(['iniciales' => mb_substr($ini, 0, 5)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn ($t) => $t->dropColumn('iniciales'));
    }
};
