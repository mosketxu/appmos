<?php

namespace App\Http\Controllers;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad;

/** Imágenes anonimizadas de un documento, para la vista previa (solo su dueño con confidencialidad.ver; nunca el mapa ni originales). */
class ConfidencialidadArchivoController extends Controller
{
    public function ver(int $id, int $n)
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->whereIn('estado', ['borrador', 'listo'])->findOrFail($id);
        $a = ($d->archivos ?? [])[$n] ?? null;
        abort_unless($a && preg_match('#^f\d+/[\w.-]+\.(jpg|png)$#', $a), 404);
        $ruta = Confidencialidad::dir($d).'/'.$a;
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, no-store']);
    }

    /** El ORIGINAL (solo mientras el documento está en borrador) para verlo junto al anonimizado: solo PDF e imágenes; el resto se ve como texto en la pantalla. */
    public function original(int $id, int $n)
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->whereIn('estado', ['procesando', 'borrador'])->findOrFail($id);
        $ruta = Confidencialidad::rutaOriginal($d, $n);
        abort_unless($ruta, 404);
        $tipos = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'bmp' => 'image/bmp'];
        $mime = $tipos[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? null;
        abort_unless($mime, 404);

        return response()->file($ruta, ['Content-Type' => $mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store']);
    }
}
