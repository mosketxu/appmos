<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

/**
 * Cuentas de proveedor que aún no están en SAGE, compartidas entre Bancos (datos en el VPS) y
 * Facturas OCR (se ejecuta en los PCs): son las filas del plan de la base de Bancos con "Origen"
 * (bancos_maestro.py cuentas_nuevas / alta_cuentas). Facturas OCR las consulta para no repetir
 * número y reconocer proveedores creados en Bancos, y manda los que da de alta al validar.
 * Autenticación: cabecera X-Token = BANCOS_SYNC_TOKEN (solo en el VPS, con BANCOS_EJECUCION).
 */
class BancosCuentasController extends Controller
{
    protected function comprobar(Request $request, string $cliente): string
    {
        $token = (string) config('contabilidad.bancos_sync_token');
        abort_unless(config('contabilidad.bancos_ejecucion') && $token !== ''
            && hash_equals($token, (string) $request->header('X-Token')), 403);
        abort_unless(preg_match('/^[\w\- ]+$/u', $cliente), 404);
        $base = rtrim(config('contabilidad.bancos_dir'), '/');
        abort_unless(is_file("{$base}/{$cliente}/Base/Base {$cliente}.xlsx"), 404, "{$cliente} no tiene base de Bancos.");

        return $base;
    }

    protected function python(string $base, array $args)
    {
        $venv = $base.'/.venv/bin/python3';
        $r = Process::path($base)->timeout(60)->run(array_merge([is_file($venv) ? $venv : 'python3', 'bancos_maestro.py'], $args));
        $datos = json_decode($r->output(), true);

        return is_array($datos)
            ? response()->json($datos, isset($datos['error']) ? 500 : 200)
            : response()->json(['error' => trim($r->output()."\n".$r->errorOutput())], 500);
    }

    public function listar(Request $request, string $cliente)
    {
        return $this->python($this->comprobar($request, $cliente), [$cliente, 'cuentas_nuevas']);
    }

    /** Cuerpo: {"cuentas": [{"cuenta": "410561", "nombre": "...", "cif": "B12345678"}]} */
    public function alta(Request $request, string $cliente)
    {
        $base = $this->comprobar($request, $cliente);
        $cuentas = array_values(array_filter(array_map(fn ($c) => [
            'cuenta' => preg_replace('/\D/', '', (string) ($c['cuenta'] ?? '')),
            'nombre' => mb_substr(trim((string) ($c['nombre'] ?? '')), 0, 100),
            'cif' => mb_substr(trim((string) ($c['cif'] ?? '')), 0, 20),
        ], (array) $request->input('cuentas', [])), fn ($c) => strlen($c['cuenta']) >= 6 && $c['nombre'] !== ''));
        $json = tempnam(sys_get_temp_dir(), 'cuentas');
        file_put_contents($json, json_encode($cuentas, JSON_UNESCAPED_UNICODE));
        $resp = $this->python($base, [$cliente, 'alta_cuentas', $json]);
        @unlink($json);

        return $resp;
    }
}
