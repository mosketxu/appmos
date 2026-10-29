<?php

namespace App\Support;

use App\Models\TodoAviso;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Copia diaria de la BD de producción de mmcontrol (29-oct-2026). La hacen los trabajadores (miniServ primero, luego AlexMiniPC y
 * PortalExomen si el anterior no la ha conseguido: ver mmcontrol_bd_tick en trabajador.py) y cuentan aquí cómo les fue. Si pasadas las
 * ALARMA_HORA no hay ninguna copia buena de hoy, se abre una tarea en el TO-DO de Alex con campana; se borra sola al llegar una copia buena.
 * Estado en storage/app/mmcontrol_bd_estado.json (no hace falta migración).
 */
class BackupMmcontrol
{
    public const ALARMA_HORA = 6;

    protected const MARCA = '[bk-mmcontrol]';

    protected static function fichero(): string
    {
        return storage_path('app/mmcontrol_bd_estado.json');
    }

    public static function estado(): array
    {
        $d = is_file(self::fichero()) ? json_decode((string) file_get_contents(self::fichero()), true) : null;

        return is_array($d) ? $d : [];
    }

    /** Un trabajador cuenta el resultado de su intento. */
    public static function registrar(string $pc, bool $ok, string $mensaje): void
    {
        $e = self::estado();
        $e['intentos'][$pc] = ['ok' => $ok, 'mensaje' => mb_substr($mensaje, 0, 500), 'at' => now()->toDateTimeString()];
        if ($ok) {
            $e['ultimo_ok'] = ['pc' => $pc, 'fecha' => now()->toDateString(), 'at' => now()->toDateTimeString(), 'mensaje' => mb_substr($mensaje, 0, 200)];
        }
        file_put_contents(self::fichero(), json_encode($e, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($ok) {
            self::cerrarAlarma();
        }
    }

    public static function hechaHoy(): bool
    {
        return (self::estado()['ultimo_ok']['fecha'] ?? null) === now()->toDateString();
    }

    /** Se llama desde la vigilancia (latidos de los PCs): abre la alarma si ya es tarde y no hay copia de hoy. */
    public static function vigilar(): void
    {
        try {
            if (now()->hour < self::ALARMA_HORA || self::hechaHoy() || ! Schema::hasTable('todo_tareas')) {
                return;
            }
            if (TodoTarea::where('descripcion', 'like', '%'.self::MARCA.'%')->whereNotIn('estado', TodoTarea::CERRADOS)->exists()) {
                return;
            }
            $alex = User::whereIn('email', config('contabilidad.claude_todo_gestores', []))->orderBy('id')->first();
            if (! $alex) {
                return;
            }
            $e = self::estado();
            $ult = $e['ultimo_ok']['at'] ?? 'nunca';
            $intentos = collect($e['intentos'] ?? [])->map(fn ($i, $pc) => "- {$pc}: ".($i['ok'] ? 'OK' : 'ERROR').' ('.$i['at'].') '.$i['mensaje'])->implode("\n") ?: '- ningún PC lo ha intentado';
            $t = TodoTarea::create([
                'titulo' => '⚠ No hay copia de hoy de la BD de mmcontrol (producción)',
                'descripcion' => self::MARCA."\nLa copia diaria de la base de datos de producción de mmcontrol (sistema.milimetrica.es) no se ha podido hacer en ningún PC "
                    ."(miniServ, AlexMiniPC, PortalExomen). Última copia buena: {$ult}.\n\nÚltimos intentos:\n{$intentos}\n\n"
                    ."Qué mirar: que miniServ esté encendido con el trabajador activo; `E:\\Claude\\Backup\\milimetrica\\backup.log` del PC que lo intentó; si producción "
                    ."(194.36.123.21:22322) responde y no ha cambiado la contraseña o bloqueado la IP (en ese caso, hablar con sistemas de mmcontrol). "
                    .'Esta tarea se borra sola cuando llegue una copia buena.',
                'creador_id' => $alex->id, 'estado' => 'pendiente', 'prioridad' => 'alta',
            ]);
            $t->asignados()->attach($alex->id, ['orden' => TodoTarea::siguienteOrden($alex->id)]);
            TodoAviso::create(['user_id' => $alex->id, 'tarea_id' => $t->id, 'origen_id' => null, 'texto' => '⚠ Sin copia de hoy de la BD de mmcontrol']);
        } catch (\Throwable $e) {
            // nunca romper por vigilar
        }
    }

    protected static function cerrarAlarma(): void
    {
        try {
            TodoTarea::where('descripcion', 'like', '%'.self::MARCA.'%')->whereNotIn('estado', TodoTarea::CERRADOS)->get()->each->delete();
        } catch (\Throwable $e) {
        }
    }
}
