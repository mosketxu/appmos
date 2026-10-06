<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** entidades.cnae y entidades.epigrafe_iae: texto libre y opcional (puede haber varios códigos). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            if (! Schema::hasColumn('entidades', 'cnae')) {
                $table->string('cnae')->nullable()->after('codigo_cliente');
            }
            if (! Schema::hasColumn('entidades', 'epigrafe_iae')) {
                $table->string('epigrafe_iae')->nullable()->after('cnae');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->dropColumn(['cnae', 'epigrafe_iae']);
        });
    }
};
