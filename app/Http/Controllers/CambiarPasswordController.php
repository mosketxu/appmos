<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Contraseña nueva obligatoria cuando users.debe_cambiar_password está a true (ver UsuarioActivo). */
class CambiarPasswordController extends Controller
{
    public function show()
    {
        return view('auth.cambiar-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ]);
        $user = $request->user();
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Tiene que ser distinta de la actual.']);
        }
        $user->forceFill(['password' => Hash::make($request->password), 'debe_cambiar_password' => false])->save();

        return redirect()->route('entidades')->with('message', 'Contraseña cambiada.');
    }
}
