<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pantalla «✉ Correo» (19-nov-2026): enlaza un documento anonimizado con el correo del buzón del que salió (id de Graph). Idempotente. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('confidencialidad_docs', 'correo_id')) {
            Schema::table('confidencialidad_docs', function (Blueprint $t) {
                $t->string('correo_id', 400)->nullable()->after('tarea_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('confidencialidad_docs', 'correo_id')) {
            Schema::table('confidencialidad_docs', fn (Blueprint $t) => $t->dropColumn('correo_id'));
        }
    }
};
