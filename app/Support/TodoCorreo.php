<?php

namespace App\Support;

use App\Models\TodoTarea;
use App\Models\User;

/**
 * Aviso por correo al asignar una tarea del TO-DO (3-oct-2026, OK de Alex). Sale por Microsoft Graph desde GRAPH_SENDER (el buzón de Alex:
 * la app de Graph solo puede usar los de Alex y Marta). No se avisa a uno mismo ni a Claude; un fallo de Graph no rompe nada.
 * Apagar con TODO_CORREO_ASIGNACION=false.
 */
class TodoCorreo
{
    public static function avisarAsignacion(TodoTarea $t, array $userIds, ?User $actor = null): void
    {
        if (! config('contabilidad.todo_correo_asignacion', true) || ! GraphMail::configurado()) {
            return;
        }
        $actor ??= auth()->user();
        $claude = TodoClaude::usuario()?->id;
        $url = url('/todo?t='.$t->id);
        foreach (User::whereIn('id', array_unique(array_map('intval', $userIds)))->get() as $u) {
            if (! $u->email || $u->id === $actor?->id || $u->id === $claude) {
                continue;
            }
            $texto = "Hola {$u->name},\n\n".($actor?->name ?? 'Alguien')." te ha asignado una tarea en Appmos:\n\n«{$t->titulo}»"
                .($t->fecha_limite ? "\nFecha límite: ".$t->fecha_limite->format('d/m/Y') : '')
                ."\n\nÁbrela aquí: {$url}\n\nSuma Apoyo Empresarial SL";
            try {
                GraphMail::enviar(config('contabilidad.graph.sender'), [$u->email], [], 'Tarea nueva en Appmos: '.mb_substr($t->titulo, 0, 80), $texto);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
