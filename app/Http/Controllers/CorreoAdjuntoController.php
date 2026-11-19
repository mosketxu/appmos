<?php

namespace App\Http\Controllers;

use App\Support\GraphMail;
use Illuminate\Http\Request;

/** Vista previa de un PDF adjunto de ✉ Correo: solo del buzón de quien lo pide, solo PDF, nunca se guarda en disco. */
class CorreoAdjuntoController extends Controller
{
    public function pdf(Request $r)
    {
        $buzon = mb_strtolower((string) auth()->user()?->email);
        abort_if($buzon === '', 403);
        $m = (string) $r->query('m');
        $a = (string) $r->query('a');
        abort_if($m === '' || $a === '' || strlen($m) > 400 || strlen($a) > 400, 404);
        try {
            $x = GraphMail::adjuntoContenido($buzon, $m, $a);
        } catch (\Throwable $e) {
            abort(404);
        }
        $bytes = base64_decode($x['bytes'], true);
        abort_unless($bytes !== false && str_starts_with($bytes, '%PDF'), 404);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="adjunto.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
