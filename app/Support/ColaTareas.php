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

    /** Minutos durante los que el PC que hizo la última tarea FIQ sigue siendo el «preferido» para la siguiente. */
    public const PREFERIDO_MIN = 15;

    /**
     * $estado 'preparando': la tarea aún no la coge nadie (la web está dejando los ficheros de entrada); se libera con liberar().
     */
    public static function crear(string $proceso, array $parametros = [], ?string $destino = null, ?int $userId = null, ?string $preferido = null, string $estado = 'pendiente'): int
    {
        abort_unless(array_key_exists($proceso, config('contabilidad.tareas_procesos', [])), 422, 'Proceso no permitido');
        if (in_array($proceso, ['pc.script', 'pc.estado', 'pc.fichero'], true)) {
            $grupo = config('contabilidad.pc_grupos.'.($parametros['grupo'] ?? ''));
            abort_unless(is_array($grupo), 422, 'Grupo no permitido');
            foreach ((array) ($parametros['pasos'] ?? []) as $paso) {
                abort_unless(in_array($paso['script'] ?? null, $grupo['scripts'], true), 422, 'Script no permitido');
                abort_unless(collect($paso['args'] ?? [])->every(fn ($a) => is_scalar($a)), 422, 'Argumentos no válidos');
            }
            foreach ((array) ($parametros['entradas'] ?? []) as $e) {
                abort_unless(self::rutaRelativaSegura((string) ($e['dir'] ?? '')) && basename((string) ($e['nombre'] ?? '')) !== '', 422, 'Entrada no válida');
            }
            if ($proceso === 'pc.fichero') {
                abort_unless(self::rutaRelativaSegura((string) ($parametros['relativa'] ?? '')), 422, 'Ruta no válida');
            }
        }

        return DB::table('tareas')->insertGetId([
            'proceso' => $proceso, 'parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE), 'destino' => $destino,
            'preferido' => $preferido, 'estado' => $estado, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Ruta relativa a la carpeta de un grupo: sin «..», sin rutas absolutas ni unidades de Windows. */
    public static function rutaRelativaSegura(string $r): bool
    {
        return $r !== '' && ! str_starts_with($r, '/') && ! str_starts_with($r, '\\') && ! preg_match('/^[A-Za-z]:/', $r)
            && ! in_array('..', preg_split('#[/\\\\]#', $r), true);
    }

    /** La tarea estaba «preparando» (la web subía los ficheros de entrada): ya la pueden coger los PCs. */
    public static function liberar(int $id): void
    {
        DB::table('tareas')->where('id', $id)->where('estado', 'preparando')->update(['estado' => 'pendiente', 'updated_at' => now()]);
    }

    /**
     * PC que hizo la última tarea FIQ hace poco y sigue conectado: los pasos encadenados de un proceso van mejor
     * al mismo PC (OneDrive tarda en sincronizar el fichero que acaba de dejar el otro).
     */
    public static function preferido(string $grupo = 'fiq'): ?string
    {
        $t = DB::table('tareas')->join('trabajadores', 'trabajadores.id', '=', 'tareas.trabajador_id')
            ->where('tareas.proceso', 'pc.script')->where('tareas.estado', 'ok')
            ->where('tareas.parametros', 'like', '%"grupo":"'.$grupo.'"%')
            ->where('tareas.terminada_at', '>=', now()->subMinutes(self::PREFERIDO_MIN))
            ->where('trabajadores.ultimo_latido', '>=', now()->subSeconds(self::LATIDO_MAX))
            ->orderByDesc('tareas.id')->first(['trabajadores.nombre']);

        return $t->nombre ?? null;
    }

    public static function estado(string $clave): mixed
    {
        $v = DB::table('estado_procesos')->where('clave', $clave)->value('valor');

        return $v === null ? null : json_decode($v, true);
    }

    public static function guardarEstado(string $clave, mixed $valor, ?string $origen = null): void
    {
        DB::table('estado_procesos')->updateOrInsert(['clave' => $clave],
            ['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'origen' => $origen, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Carpeta donde se guardan los ficheros que subió el PC al terminar una tarea. */
    public static function carpetaFicheros(int $tareaId): string
    {
        return storage_path('app/tareas/'.$tareaId);
    }

    /** Ficheros que la web deja para el PC antes de la tarea (los que ha subido el usuario). */
    public static function carpetaEntradas(int $tareaId): string
    {
        return storage_path('app/tareas/'.$tareaId.'/entrada');
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
                ->where(fn ($q) => $q->whereNull('preferido')->orWhere('preferido', $t->nombre)
                    ->orWhereNotIn('preferido', DB::table('trabajadores')->where('ultimo_latido', '>=', now()->subSeconds(self::LATIDO_MAX))->select('nombre')))
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
        // Procesos FIQ: el PC sube una copia del estado que han dejado los scripts (OneDrive sigue siendo la verdad).
        if ((str_starts_with($tarea->proceso, 'pc.') || str_starts_with($tarea->proceso, 'fiq.')) && is_array($resultado['estado'] ?? null)) {
            $nombre = DB::table('trabajadores')->where('id', $trabajadorId)->value('nombre');
            foreach ($resultado['estado'] as $clave => $valor) {
                self::guardarEstado((string) $clave, $valor, $nombre);
            }
            unset($resultado['estado']);
        }
        DB::table('tareas')->where('id', $id)->update([
            'estado' => $ok ? 'ok' : 'error', 'resultado' => $resultado === null ? null : json_encode($resultado, JSON_UNESCAPED_UNICODE),
            'log' => mb_substr(($tarea->log ?? '').$log.$extra, -60000), 'terminada_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }
}
