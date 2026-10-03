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

    /** Tarea de la cola «claude.todo» que este trabajador tiene en curso, con su tarea del TO-DO. */
    protected function tareaClaude(Request $r, int $id): \App\Models\TodoTarea
    {
        $t = $this->trabajador($r);
        $cola = \Illuminate\Support\Facades\DB::table('tareas')->where('id', $id)->where('proceso', 'claude.todo')
            ->where('trabajador_id', $t->id)->where('estado', 'en_curso')->first();
        abort_unless($cola, 404);

        return \App\Models\TodoTarea::findOrFail((int) (json_decode($cola->parametros ?? '{}', true)['tarea_id'] ?? 0));
    }

    /** Lo que Claude tiene que hacer: la tarea del TO-DO con su hilo de respuestas. */
    public function todo(Request $r, int $id)
    {
        return response()->json(\App\Support\TodoClaude::detalle($this->tareaClaude($r, $id)));
    }

    /** Resultado de una pasada de Claude: estado (hecha | bloqueada | en_curso), respuesta y uso. */
    public function todoResultado(Request $r, int $id)
    {
        $t = $this->tareaClaude($r, $id);
        $d = $r->validate([
            'estado' => 'nullable|in:hecha,bloqueada,en_curso', 'respuesta' => 'nullable|string|max:20000',
            'uso' => 'nullable|array', 'uso.coste_usd' => 'nullable|numeric', 'uso.turnos' => 'nullable|integer',
            'uso.tokens' => 'nullable|integer', 'uso.segundos' => 'nullable|integer', 'uso.ok' => 'nullable|boolean', 'uso.pc' => 'nullable|string',
        ]);
        \App\Support\TodoClaude::registrar($t, $d['estado'] ?? null, $d['respuesta'] ?? null, $d['uso'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** Uso real del plan de Claude leído en un PC (`claude -p "/usage"`). */
    public function claudeUso(Request $r)
    {
        $t = $this->trabajador($r);
        $d = $r->validate([
            'sesion_pct' => 'nullable|integer|min:0|max:100', 'sesion_reinicia' => 'nullable|string|max:60',
            'semana_pct' => 'nullable|integer|min:0|max:100', 'semana_reinicia' => 'nullable|string|max:60',
        ]);
        \Illuminate\Support\Facades\DB::table('claude_uso')->updateOrInsert(['pc' => $t->nombre], $d + ['leido_at' => now(), 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
