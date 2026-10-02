<?php

namespace App\Http\Controllers;

use App\Support\ColaTareas;
use Illuminate\Http\Request;

/**
 * API que usan los PCs trabajadores (Contabilidad/TrabajadorWeb/trabajador.py). Solo conexiones de salida
 * desde el PC. Cabecera X-Token = token del trabajador (se guarda hasheado en la tabla trabajadores).
 */
class TrabajadorApiController extends Controller
{
    protected function trabajador(Request $r): object
    {
        $t = ColaTareas::autenticar($r->header('X-Token'));
        abort_unless($t, 403);

        return $t;
    }

    /** Capacidades que el trabajador dice tener, recortadas a la lista cerrada del servidor. */
    protected function capacidades(Request $r): array
    {
        return array_values(array_intersect((array) $r->input('capacidades', []), array_keys(config('contabilidad.tareas_procesos', []))));
    }

    /** Latido + reserva: devuelve la siguiente tarea o null. */
    public function siguiente(Request $r)
    {
        $t = $this->trabajador($r);
        $cap = $this->capacidades($r);
        ColaTareas::latido($t, $cap);
        $tarea = ColaTareas::reservar($t, $cap);

        return response()->json(['tarea' => $tarea ? [
            'id' => $tarea->id, 'proceso' => $tarea->proceso, 'parametros' => json_decode($tarea->parametros ?? '[]', true),
        ] : null]);
    }

    public function latido(Request $r)
    {
        $t = $this->trabajador($r);
        ColaTareas::latido($t, $this->capacidades($r));

        return response()->json(['ok' => true]);
    }

    public function log(Request $r, int $id)
    {
        $t = $this->trabajador($r);
        ColaTareas::latido($t, $this->capacidades($r) ?: (json_decode($t->capacidades ?? '[]', true) ?: []));

        return response()->json(['ok' => ColaTareas::anadirLog($id, $t->id, (string) $r->input('texto', ''))]);
    }

    public function fin(Request $r, int $id)
    {
        $t = $this->trabajador($r);
        $d = $r->validate(['ok' => 'required|boolean', 'resultado' => 'nullable|array', 'log' => 'nullable|string']);

        return response()->json(['ok' => ColaTareas::terminar($id, $t->id, (bool) $d['ok'], $d['resultado'] ?? null, (string) ($d['log'] ?? ''))]);
    }
}
