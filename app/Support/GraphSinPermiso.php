<?php

namespace App\Support;

use RuntimeException;

/**
 * Microsoft Graph ha dado 403: la app de Appmos solo puede usar los buzones del grupo de Exchange
 * «Appmos-Buzones» (política de acceso a aplicaciones, 2-oct-2026) y este buzón no está.
 * El mensaje se muestra tal cual en pantalla: dice qué hacer y trae el texto para pegárselo a Claude.
 */
class GraphSinPermiso extends RuntimeException
{
    public static function para(string $buzon, string $accion): self
    {
        $usuario = auth()->check() ? auth()->user()->email : '(sin sesión)';
        $ruta = request()->path();
        $cuando = now()->format('d/m/Y H:i');

        return new self(
            "🚫 Appmos todavía no tiene permiso para usar el buzón {$buzon}.\n"
            ."Contacta con Alex y envíale este texto tal cual (él se lo pasa a Claude y lo resuelve en un minuto):\n"
            ."────────────────────\n"
            ."Hay que dar permiso a Appmos para el buzón {$buzon}: Microsoft Graph responde 403 (ErrorAccessDenied) al intentar «{$accion}». "
            ."Lo pidió {$usuario} desde la pantalla /{$ruta} el {$cuando}. "
            ."Solución: añadir {$buzon} al grupo de Exchange «Appmos-Buzones» "
            ."(Add-DistributionGroupMember -Identity \"Appmos-Buzones\" -Member {$buzon}); "
            ."la política de acceso de la app 358d115a-3889-4b7f-82e1-b6da7f7b8a45 está en Contabilidad/TrabajadorWeb/LIMITAR_GRAPH.md.\n"
            .'────────────────────'
        );
    }
}
