<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Contrapartida de SAGE (importación de clientes/proveedores). Suma: 705000 para sus clientes. Idempotente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function ($t) {
            if (! Schema::hasColumn('entidades', 'contrapartida')) {
                $t->string('contrapartida', 12)->nullable();
            }
        });
        DB::table('entidades')->where('nif', 'B66260076')->whereNull('contrapartida')->update(['contrapartida' => '705000']);
    }

    public function down(): void
    {
    }
};
