<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Pestaña WhatsApp: sirve un adjunto del chat (solo los registrados en whatsapp_adjuntos, nunca una ruta que venga de la URL). */
class WhatsappController extends Controller
{
    private const EN_LINEA = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'opus' => 'audio/ogg', 'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'video/mp4',
    ];

    public function adjunto(string $clave, string $nombre)
    {
        $chat = DB::table('whatsapp_chats')->where('clave', $clave)->first() ?? abort(404);
        $a = DB::table('whatsapp_adjuntos')->where('chat_id', $chat->id)->where('nombre', $nombre)->first() ?? abort(404);
        $ruta = Storage::disk('local')->path($a->almacen);
        abort_unless(is_file($ruta), 404);
        $tipo = self::EN_LINEA[strtolower(pathinfo($nombre, PATHINFO_EXTENSION))] ?? null;

        return response()->file($ruta, ['Content-Type' => $tipo ?? 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($tipo ? 'inline' : 'attachment').'; filename="'.str_replace(['"', '\\'], '_', $nombre).'"; filename*=UTF-8\'\''.rawurlencode($nombre)]);
    }
}
