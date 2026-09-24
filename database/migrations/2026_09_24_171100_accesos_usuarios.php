<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de acceso (24-sep-2026):
 *  - users.email opcional (se crean usuarios con solo el nombre; hasta tener
 *    correo no pueden entrar) y users.activo para desactivar sin borrar.
 *  - sumas.user_id: el "Responsable Suma" de una entidad es un usuario, que ve
 *    esa entidad.
 *  - entidad_user: además del responsable, otros usuarios con acceso a la entidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->boolean('activo')->default(true)->after('email');
        });

        Schema::table('sumas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
        });

        Schema::create('entidad_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entidad_id')->constrained('entidades')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['entidad_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidad_user');
        Schema::table('sumas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
            $table->string('email')->nullable(false)->change();
        });
    }
};
