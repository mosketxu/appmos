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

    /** Usuario dueño del código personal (cabecera X-Codigo) del .ps1 instalado en su PC. */
    protected function usuario(Request $request): object
    {
        $codigo = (string) $request->header('X-Codigo');
        $u = strlen($codigo) >= 20 ? DB::table('users')->where('certificados_codigo', $codigo)->first() : null;
        abort_unless($u, 403);

        return $u;
    }

    /** Escaneo del PC de un usuario (lo sube su tarea de Windows). Atiende también el «Escanear ahora» pedido desde la web. */
    public function usuarioEscaneo(Request $request)
    {
        $u = $this->usuario($request);
        $d = $request->validate(['escaneado' => 'required|string|max:25', 'equipo' => 'nullable|string|max:50', 'certs' => 'present|array']);
        $pc = 'PC de '.$u->name;
        $ya = DB::table('certificados_escaneos')->where('pc', $pc)->exists();
        DB::table('certificados_escaneos')->updateOrInsert(['pc' => $pc], [
            'escaneado' => $d['escaneado'], 'certs' => json_encode($d['certs'], JSON_UNESCAPED_UNICODE), 'user_id' => $u->id,
            'equipo' => $d['equipo'] ?? null, 'solicitado_at' => null, 'updated_at' => now()] + ($ya ? [] : ['created_at' => now()]));

        return response()->json(['ok' => true, 'certs' => count($d['certs'])]);
    }

    /** La tarea del PC pregunta cada pocos minutos si desde la web se ha pulsado «Escanear ahora». */
    public function usuarioPendiente(Request $request)
    {
        $u = $this->usuario($request);

        return response()->json(['escanear' => DB::table('certificados_escaneos')->where('user_id', $u->id)->whereNotNull('solicitado_at')->exists()]);
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
