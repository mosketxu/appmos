<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Datos que pide la importación «Gestión de clientes y proveedores» de SAGE (5-nov-2026). Idempotente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function ($t) {
            if (! Schema::hasColumn('entidades', 'codigo_empresa_sage')) {
                $t->string('codigo_empresa_sage', 10)->nullable();
            }
            if (! Schema::hasColumn('entidades', 'retencion')) {
                $t->decimal('retencion', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('entidades', 'persona_fj')) {
                $t->string('persona_fj', 1)->default('J');
            }
        });
        DB::table('entidades')->where('nif', 'B66260076')->whereNull('codigo_empresa_sage')->update(['codigo_empresa_sage' => '1017']);
    }

    public function down(): void
    {
    }
};
