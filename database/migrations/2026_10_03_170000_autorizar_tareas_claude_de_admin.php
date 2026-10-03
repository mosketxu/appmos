<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las tareas que un Admin ya había asignado a Claude antes de existir la autorización salían como «sin autorizar»:
 * se dan por autorizadas por quien las creó. No se encolan (Claude no se pone solo con tareas antiguas; «Ejecutar ya» si se quiere).
 */
return new class extends Migration
{
    public function up(): void
    {
        $claude = DB::table('users')->where('name', 'Claude')->whereNull('email')->value('id');
        $admins = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'Admin')->where('model_has_roles.model_type', \App\Models\User::class)->pluck('model_id');
        if (! $claude || $admins->isEmpty()) {
            return;
        }
        DB::table('todo_tareas')->whereNull('claude_autorizada_at')->whereIn('creador_id', $admins)
            ->whereIn('id', DB::table('todo_tarea_user')->where('user_id', $claude)->select('tarea_id'))
            ->update(['claude_autorizada_at' => now(), 'claude_autorizada_por' => DB::raw('creador_id')]);
    }

    public function down(): void
    {
    }
};
