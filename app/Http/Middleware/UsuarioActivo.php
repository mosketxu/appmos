<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Si el administrador desactiva a un usuario, su sesión abierta se cierra en la siguiente petición.
 * Si tiene debe_cambiar_password, no puede hacer nada hasta poner una contraseña nueva.
 */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Tu usuario está desactivado.']);
        }
        if ($user && $user->debe_cambiar_password && ! $request->routeIs('password.cambiar', 'password.cambiar.form', 'logout')) {
            return $request->expectsJson() ? abort(403, 'Tienes que cambiar la contraseña.') : redirect()->route('password.cambiar.form');
        }
        return $next($request);
    }
}
