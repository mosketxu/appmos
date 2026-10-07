<?php

namespace App\Http\Controllers;

use App\Support\Impuestos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Abre el fichero adjunto de un comentario de Impuestos: solo quien ve esa casilla (Admin y Suma, todas). */
class ImpuestosAdjuntoController extends Controller
{
    public function ver(int $comentario)
    {
        $c = DB::table('impuesto_comentarios')->find($comentario);
        abort_unless($c && $c->adjunto_almacen, 404);
        abort_unless(Impuestos::esGestor() || Impuestos::puedeVer((int) $c->entidad_impuesto_id), 403);
        $ruta = Storage::disk('local')->path($c->adjunto_almacen);
        abort_unless(is_file($ruta), 404);
        $ext = strtolower(pathinfo($c->adjunto_nombre, PATHINFO_EXTENSION));
        $inline = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
        $nombre = addslashes($c->adjunto_nombre);

        // Solo PDF e imágenes se muestran en el navegador; lo demás se descarga (nunca se ejecuta HTML/SVG subido por un usuario)
        return response()->file($ruta, [
            'Content-Type' => $inline[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => (isset($inline[$ext]) ? 'inline' : 'attachment').'; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
