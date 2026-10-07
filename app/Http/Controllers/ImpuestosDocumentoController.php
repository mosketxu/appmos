<?php

namespace App\Http\Controllers;

use App\Models\ImpuestoDocumento;
use App\Support\Impuestos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Abre un PDF de impuestos: solo quien ve ese impuesto de ese cliente (Admin y Suma, todos). */
class ImpuestosDocumentoController extends Controller
{
    public function ver(ImpuestoDocumento $documento)
    {
        $u = auth()->user();
        if (! Impuestos::esGestor($u)) {
            $q = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
                ->where('ei.entidad_id', $documento->entidad_id)->where('m.codigo', $documento->modelo)->where('ei.etiqueta', (string) $documento->etiqueta);
            abort_unless($documento->entidad_id && Impuestos::soloVisibles($q, $u)->exists(), 403);
        }
        $ruta = Storage::disk('local')->path($documento->almacen);
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.addslashes($documento->nombre).'"']);
    }
}
