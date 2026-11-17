<?php

namespace App\Http\Controllers;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad;

/** Imágenes anonimizadas de un documento, para la vista previa (solo su dueño con confidencialidad.ver; nunca el mapa ni originales). */
class ConfidencialidadArchivoController extends Controller
{
    public function ver(int $id, int $n)
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->where('estado', 'listo')->findOrFail($id);
        $a = ($d->archivos ?? [])[$n] ?? null;
        abort_unless($a && preg_match('#^f\d+/[\w.-]+\.(jpg|png)$#', $a), 404);
        $ruta = Confidencialidad::dir($d).'/'.$a;
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, no-store']);
    }
}
