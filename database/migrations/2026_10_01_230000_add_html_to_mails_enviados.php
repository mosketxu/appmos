<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El HTML exacto que salió (con el logo), para poder ver el correo enviado. Los anteriores fueron en texto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mails_enviados', function (Blueprint $table) {
            $table->longText('html')->nullable()->after('texto');
        });
    }

    public function down(): void
    {
        Schema::table('mails_enviados', function (Blueprint $table) {
            $table->dropColumn('html');
        });
    }
};
