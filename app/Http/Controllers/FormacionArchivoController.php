<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Sirve los archivos de Formación (privados, solo con formacion.ver). Solo se muestran en línea los tipos seguros; el resto se descarga. */
class FormacionArchivoController extends Controller
{
    /** Extensión => tipo, solo lo que el navegador puede enseñar sin ejecutar nada (nada de html ni svg). */
    public const VISIBLES = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'txt' => 'text/plain; charset=UTF-8',
    ];

    public function ver(int $id)
    {
        $a = DB::table('formacion_archivos')->find($id);
        abort_unless($a && Storage::disk('local')->exists($a->ruta), 404);
        $ext = strtolower($a->extension);
        $visible = isset(self::VISIBLES[$ext]) && ! request()->boolean('descargar');
        $cab = ['X-Content-Type-Options' => 'nosniff'];
        if ($visible) {
            return Storage::disk('local')->response($a->ruta, $a->nombre, $cab + ['Content-Type' => self::VISIBLES[$ext]], 'inline');
        }

        return Storage::disk('local')->download($a->ruta, $a->nombre, $cab);
    }
}
