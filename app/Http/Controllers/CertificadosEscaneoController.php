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

    /** Envío hecho desde un PC: se guarda también aquí y se marca el mes en el Seguimiento de la web. */
    public function envio(Request $request)
    {
        $token = (string) config('contabilidad.certificados_sync_token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('X-Token')), 403);
        $d = $request->validate(['periodo' => 'required|regex:/^\d{4}-\d{2}$/', 'enviado_at' => 'required|date', 'origen' => 'nullable|string|max:10',
            'para' => 'nullable|string', 'cc' => 'nullable|string', 'asunto' => 'nullable|string', 'texto' => 'nullable|string', 'filas' => 'nullable|string', 'de' => 'nullable|string']);
        \App\Http\Livewire\Contabilidad\Certificados::guardarEnvio($d, \App\Models\User::where('email', $d['de'] ?? '')->value('id'));

        return response()->json(['ok' => true]);
    }
}
