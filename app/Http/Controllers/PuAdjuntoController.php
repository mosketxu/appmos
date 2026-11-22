<?php

namespace App\Http\Controllers;

use App\Models\Pu;
use App\Models\PuAdjunto;
use Illuminate\Support\Facades\Storage;

/** Abre un adjunto de una PU: solo quien ve esa entidad (el scope de Pu filtra por entidades permitidas). */
class PuAdjuntoController extends Controller
{
    public function ver(PuAdjunto $adjunto)
    {
        abort_unless(Pu::whereKey($adjunto->pu_id)->exists(), 404);
        $ruta = Storage::disk('local')->path($adjunto->ruta);
        abort_unless(is_file($ruta), 404);
        $inline = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $ext = $adjunto->extension();
        $nombre = addslashes($adjunto->nombre);

        // Solo PDF e imágenes se muestran en el navegador; lo demás se descarga (nunca se ejecuta HTML/SVG subido por un usuario)
        return response()->file($ruta, [
            'Content-Type' => $inline[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => (isset($inline[$ext]) ? 'inline' : 'attachment').'; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
