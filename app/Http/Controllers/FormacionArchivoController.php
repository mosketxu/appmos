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

    /** ZIP con todos los archivos de un tema (nombres repetidos: se les añade un número). */
    public function zip(int $tema)
    {
        $archivos = DB::table('formacion_archivos')->where('tema_id', $tema)->orderBy('id')->get();
        abort_if($archivos->isEmpty(), 404);
        $titulo = DB::table('formacion_temas')->where('id', $tema)->value('titulo') ?: 'formacion';
        $nombreZip = (trim(preg_replace('/[^\p{L}\p{N}\- _.]+/u', '', $titulo)) ?: 'formacion').'.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'form');
        $zip = new \ZipArchive();
        abort_unless($zip->open($tmp, \ZipArchive::OVERWRITE) === true, 500);
        $usados = [];
        foreach ($archivos as $a) {
            $ruta = Storage::disk('local')->path($a->ruta);
            if (! is_file($ruta)) {
                continue;
            }
            $n = basename(str_replace('\\', '/', $a->nombre));
            if (isset($usados[$n])) {
                $info = pathinfo($n);
                $n = $info['filename'].' ('.(++$usados[$n]).')'.(isset($info['extension']) ? '.'.$info['extension'] : '');
            } else {
                $usados[$n] = 1;
            }
            $zip->addFile($ruta, $n);
        }
        $zip->close();

        return response()->download($tmp, $nombreZip, ['X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }
}
