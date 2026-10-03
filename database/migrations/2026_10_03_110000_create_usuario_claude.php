<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Claude como posible destinatario de tareas del TO-DO (3-oct-2026). Sin correo ni contraseña conocida:
 * no puede entrar en Appmos; las tareas que se le asignan las lee Claude directamente de la base de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (User::where('name', 'Claude')->whereNull('email')->exists()) {
            return;
        }
        $u = User::create(['name' => 'Claude', 'email' => null, 'password' => Hash::make(Str::random(40)), 'activo' => true]);
        if (\Spatie\Permission\Models\Role::where('name', 'Usuario')->exists()) {
            $u->assignRole('Usuario');
        }
    }

    public function down(): void
    {
        User::where('name', 'Claude')->whereNull('email')->delete();
    }
};
