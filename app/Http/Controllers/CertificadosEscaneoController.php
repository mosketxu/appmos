<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Recibe el escaneo de certificados de un PC (cabecera X-Token = CERTIFICADOS_SYNC_TOKEN; solo en el VPS). */
class CertificadosEscaneoController extends Controller
{
    public function guardar(Request $request)
    {
        $token = (string) config('contabilidad.certificados_sync_token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('X-Token')), 403);
        $d = $request->validate(['pc' => 'required|string|max:50', 'escaneado' => 'required|string|max:25', 'certs' => 'required|array']);
        DB::table('certificados_escaneos')->updateOrInsert(['pc' => $d['pc']],
            ['escaneado' => $d['escaneado'], 'certs' => json_encode($d['certs'], JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['ok' => true, 'certs' => count($d['certs'])]);
    }
}
