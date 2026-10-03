<?php

namespace App\Notifications;

use App\Support\GraphMail;
use Illuminate\Auth\Notifications\ResetPassword;

/**
 * «¿Olvidaste tu contraseña?»: el mismo aviso de Laravel, pero el correo sale por Microsoft Graph (desde GRAPH_SENDER),
 * como el resto de correos de Appmos. Si Graph falla se anota en el log y el usuario ve el aviso de siempre
 * (no se revela si el correo existe).
 */
class ResetPasswordGraph extends ResetPassword
{
    public function via($notifiable): array
    {
        return ['graph'];
    }

    public function toGraph($notifiable): void
    {
        $enlace = $this->resetUrl($notifiable);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $texto = "Hola {$notifiable->name},\n\n"
            ."Has pedido restablecer tu contraseña de Appmos. Pulsa aquí para elegir una nueva:\n{$enlace}\n\n"
            ."El enlace caduca en {$minutos} minutos. Si no lo has pedido tú, ignora este correo.\n\n"
            ."Suma Apoyo Empresarial SL";
        try {
            GraphMail::enviar(config('contabilidad.graph.sender'), [$notifiable->email], [], 'Restablecer tu contraseña de Appmos', $texto);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
