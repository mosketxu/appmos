<?php

namespace App\Http\Controllers;

/**
 * PDF de una factura de Contabilidad → Facturas OCR, para verlo en la pantalla
 * de revisión (la ruta del fichero sale de <Cliente>/facturas.json).
 */
class FacturasOcrController extends Controller
{
    public function pdf(string $cliente, string $id)
    {
        $base = rtrim(config('contabilidad.facturasocr_dir'), '/');
        $dir = $base.'/'.basename($cliente);
        $cfg = json_decode((string) @file_get_contents($dir.'/cliente.json'), true);
        abort_unless(is_array($cfg), 404);
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        abort_if($permitidas !== null && ! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true), 403);

        $estado = json_decode((string) @file_get_contents($dir.'/facturas.json'), true) ?: [];
        foreach ($estado['facturas'] ?? [] as $f) {
            if ($f['id'] === $id && is_file($f['ruta'])) {
                return response()->file($f['ruta'], [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.addslashes(basename($f['ruta'])).'"',
                ]);
            }
        }
        abort(404);
    }
}
