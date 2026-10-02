<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cola de tareas web -> PCs trabajadores. Solo se aceptan procesos de la lista cerrada
 * config('contabilidad.tareas_procesos'); el trabajador tampoco ejecuta nada que no conozca.
 */
class ColaTareas
{
    /** Segundos sin latido tras los cuales una tarea «en curso» se da por perdida y vuelve a la cola. */
    public const LATIDO_MAX = 120;

    public static function crear(string $proceso, array $parametros = [], ?string $destino = null, ?int $userId = null): int
    {
        abort_unless(array_key_exists($proceso, config('contabilidad.tareas_procesos', [])), 422, 'Proceso no permitido');

        return DB::table('tareas')->insertGetId([
            'proceso' => $proceso, 'parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE), 'destino' => $destino,
            'estado' => 'pendiente', 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Alta de un trabajador; devuelve el token en claro (solo se ve esta vez). */
    public static function crearTrabajador(string $nombre): string
    {
        $token = bin2hex(random_bytes(24));
        DB::table('trabajadores')->updateOrInsert(['nombre' => $nombre],
            ['token_hash' => hash('sha256', $token), 'activo' => true, 'updated_at' => now(), 'created_at' => now()]);

        return $token;
    }

    public static function autenticar(?string $token): ?object
    {
        if (! $token) {
            return null;
        }

        return DB::table('trabajadores')->where('token_hash', hash('sha256', $token))->where('activo', true)->first();
    }

    public static function latido(object $t, array $capacidades): void
    {
        DB::table('trabajadores')->where('id', $t->id)
            ->update(['ultimo_latido' => now(), 'capacidades' => json_encode(array_values($capacidades)), 'updated_at' => now()]);
    }

    /** Las tareas «en curso» de un trabajador que dejó de dar señales vuelven a la cola. */
    public static function recuperarPerdidas(): int
    {
        $limite = now()->subSeconds(self::LATIDO_MAX);
        $ids = DB::table('tareas')->join('trabajadores', 'trabajadores.id', '=', 'tareas.trabajador_id')
            ->where('tareas.estado', 'en_curso')
            ->where(fn ($q) => $q->whereNull('trabajadores.ultimo_latido')->orWhere('trabajadores.ultimo_latido', '<', $limite))
            ->pluck('tareas.id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::table('tareas')->whereIn('id', $ids)->where('estado', 'en_curso')->update([
            'estado' => 'pendiente', 'trabajador_id' => null, 'iniciada_at' => null, 'updated_at' => now(),
        ]);
    }

    /** Reserva atómica de la tarea pendiente más antigua que este trabajador sepa hacer. */
    public static function reservar(object $t, array $capacidades): ?object
    {
        self::recuperarPerdidas();

        return DB::transaction(function () use ($t, $capacidades) {
            $q = DB::table('tareas')->where('estado', 'pendiente')
                ->whereIn('proceso', $capacidades)
                ->where(fn ($q) => $q->whereNull('destino')->orWhere('destino', $t->nombre))
                ->orderBy('id')->lockForUpdate();
            $tarea = $q->first();
            if (! $tarea) {
                return null;
            }
            $n = DB::table('tareas')->where('id', $tarea->id)->where('estado', 'pendiente')
                ->update(['estado' => 'en_curso', 'trabajador_id' => $t->id, 'iniciada_at' => now(), 'updated_at' => now()]);

            return $n ? DB::table('tareas')->find($tarea->id) : null;
        });
    }

    public static function anadirLog(int $id, int $trabajadorId, string $texto): bool
    {
        $tarea = DB::table('tareas')->where('id', $id)->where('trabajador_id', $trabajadorId)->where('estado', 'en_curso')->first();
        if (! $tarea) {
            return false;
        }
        $nuevo = mb_substr(($tarea->log ?? '').$texto, -60000);

        return (bool) DB::table('tareas')->where('id', $id)->update(['log' => $nuevo, 'updated_at' => now()]);
    }

    /** Cierra la tarea y ejecuta, si lo hay, el efecto del lado servidor (p. ej. guardar el escaneo de certificados). */
    public static function terminar(int $id, int $trabajadorId, bool $ok, ?array $resultado, string $log = ''): bool
    {
        $tarea = DB::table('tareas')->where('id', $id)->where('trabajador_id', $trabajadorId)->where('estado', 'en_curso')->first();
        if (! $tarea) {
            return false;
        }
        $extra = '';
        if ($ok && $tarea->proceso === 'certificados.escanear' && is_array($resultado)) {
            try {
                DB::table('certificados_escaneos')->updateOrInsert(['pc' => $resultado['pc']],
                    ['escaneado' => $resultado['escaneado'], 'certs' => json_encode($resultado['certs'], JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);
            } catch (\Throwable $e) {
                $ok = false;
                $extra = "\nNo se pudo guardar el escaneo: ".$e->getMessage();
            }
        }
        DB::table('tareas')->where('id', $id)->update([
            'estado' => $ok ? 'ok' : 'error', 'resultado' => $resultado === null ? null : json_encode($resultado, JSON_UNESCAPED_UNICODE),
            'log' => mb_substr(($tarea->log ?? '').$log.$extra, -60000), 'terminada_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }
}
