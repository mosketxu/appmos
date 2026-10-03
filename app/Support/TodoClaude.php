<?php

namespace App\Support;

use App\Models\TodoAviso;
use App\Models\TodoComentario;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Claude como destinatario de tareas del TO-DO (3-oct-2026).
 * - Si la tarea se la asigna un Admin, queda autorizada y se encola; si se la asigna otro usuario, se avisa a los
 *   Admin (campana) y espera su visto bueno.
 * - La cola (ColaTareas, proceso «claude.todo») la entrega a un trabajador (AlexMiniPC, o PortalExomen si no está) que
 *   lanza Claude Code; el resultado vuelve por la API (registrar) como respuesta de Claude y cambio de estado.
 * - Sin bucles: Claude solo se vuelve a encolar si ALGUIEN que no es Claude responde, autoriza o pulsa «Ejecutar ya».
 */
class TodoClaude
{
    public static function usuario(): ?User
    {
        return User::where('name', 'Claude')->whereNull('email')->first();
    }

    // ---- Pausas y uso -------------------------------------------------------------------------------

    public static function pausadoGlobal(): bool
    {
        return DB::table('todo_ajustes')->where('clave', 'claude_pausado')->value('valor') === '1';
    }

    public static function pausarGlobal(bool $pausar): void
    {
        DB::table('todo_ajustes')->updateOrInsert(['clave' => 'claude_pausado'], ['valor' => $pausar ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]);
        if (! $pausar) {   // al reanudar, las tareas autorizadas y abiertas que estaban esperando vuelven a la cola
            foreach (TodoTarea::whereNotNull('claude_autorizada_at')->where('claude_pausada', false)->get() as $t) {
                if (! DB::table('tareas')->where('proceso', 'claude.todo')->whereIn('estado', ['pendiente', 'en_curso'])->whereRaw("json_extract(parametros, '$.tarea_id') = ?", [$t->id])->exists()
                    && self::pendiente($t)) {
                    self::encolar($t);
                }
            }
        }
    }

    /** ¿Le falta algo a Claude por hacer en esta tarea? Pendiente/en curso sin que él haya contestado lo último. */
    protected static function pendiente(TodoTarea $t): bool
    {
        $c = self::usuario();
        $ultimo = $t->comentarios()->where('tipo', 'respuesta')->latest('id')->first();

        return $t->abierta() && in_array($t->estado, ['pendiente', 'en_curso'], true) && (! $ultimo || $ultimo->user_id !== $c?->id);
    }

    public static function pausarTarea(TodoTarea $t, bool $pausar): void
    {
        $t->update(['claude_pausada' => $pausar]);
        if ($pausar) {   // lo que esperaba en la cola se cancela; si ya está en curso, termina esta pasada y no se repite
            DB::table('tareas')->where('proceso', 'claude.todo')->where('estado', 'pendiente')->whereRaw("json_extract(parametros, '$.tarea_id') = ?", [$t->id])
                ->update(['estado' => 'cancelada', 'updated_at' => now()]);
        } elseif (self::pendiente($t)) {
            self::encolar($t);
        }
    }

    public static function limiteDia(): int
    {
        return max(1, (int) config('contabilidad.claude_todo_max_dia', 10));
    }

    public static function ejecucionesHoy(): int
    {
        return DB::table('claude_ejecuciones')->where('created_at', '>=', now()->startOfDay())->count();
    }

    public static function costeHoy(): float
    {
        return (float) DB::table('claude_ejecuciones')->where('created_at', '>=', now()->startOfDay())->sum('coste_usd');
    }

    /** Uso de hoy de las ejecuciones automáticas respecto al tope diario (0-100+). No es el uso del plan de Claude. */
    public static function porcentajeUso(): int
    {
        return (int) round(100 * self::ejecucionesHoy() / self::limiteDia());
    }

    /** Última lectura del uso real del plan (`/usage`) subida por un PC, con minutos desde que se leyó; null si no hay. */
    public static function usoPlan(): ?array
    {
        $r = DB::table('claude_uso')->orderByDesc('leido_at')->first();
        if (! $r) {
            return null;
        }

        return [
            'sesion' => $r->sesion_pct, 'sesion_reinicia' => self::formatoReinicio($r->sesion_reinicia),
            'semana' => $r->semana_pct, 'semana_reinicia' => self::formatoReinicio($r->semana_reinicia),
            'pc' => $r->pc, 'hace_min' => $r->leido_at ? (int) \Illuminate\Support\Carbon::parse($r->leido_at)->diffInMinutes(now()) : null,
        ];
    }

    /** «Oct 4, 2:30am (Europe/Madrid)» -> «sáb 04/10 02:30»; si no se entiende, tal cual. */
    protected static function formatoReinicio(?string $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            $limpio = trim(preg_replace('/\(.*?\)/', '', $t));
            if (! preg_match('/\d:\d\d/', $limpio)) {   // «Oct 7, 7am» -> «Oct 7, 7:00am»
                $limpio = preg_replace('/(\d)\s*(am|pm)/i', '$1:00$2', $limpio);
            }
            return \Illuminate\Support\Carbon::parse($limpio, 'Europe/Madrid')->locale('es')->isoFormat('ddd D/M HH:mm');
        } catch (\Throwable $e) {
            return $t;
        }
    }

    /** ¿Ha llegado el uso real del plan al freno (config claude_todo_max_uso, por defecto 80 %)? Con lectura vieja (>60 min) no se tiene en cuenta. */
    public static function planAgotado(): bool
    {
        $u = self::usoPlan();
        if (! $u || ($u['hace_min'] ?? 999) > 60) {
            return false;
        }
        $max = (int) config('contabilidad.claude_todo_max_uso', 80);

        return max((int) $u['sesion'], (int) $u['semana']) >= $max;
    }

    /** ¿Puede entregarse ahora una tarea de Claude a un trabajador? (ni pausa general, ni tope diario, ni plan casi agotado) */
    public static function permitido(): bool
    {
        return ! self::pausadoGlobal() && self::ejecucionesHoy() < self::limiteDia() && ! self::planAgotado();
    }

    /** Quien manda sobre Claude: pausar, autorizar lo que le asignan otros. Por defecto solo Alex (config claude_todo_gestores). */
    public static function esGestor(?User $u): bool
    {
        return $u && $u->email && in_array(mb_strtolower($u->email), array_map('mb_strtolower', config('contabilidad.claude_todo_gestores', [])), true);
    }

    public static function gestores(): array
    {
        return User::whereIn('email', config('contabilidad.claude_todo_gestores', []))->pluck('id')->all();
    }

    public static function asignada(TodoTarea $t): bool
    {
        $c = self::usuario();

        return $c && $t->asignados()->where('users.id', $c->id)->exists();
    }

    /** Se acaba de asignar a Claude: autorizar y encolar si lo hace un Admin; si no, pedir el visto bueno. */
    public static function alAsignar(TodoTarea $t, User $actor): void
    {
        if ($t->claude_autorizada_at) {
            self::encolar($t);
        } elseif (self::esGestor($actor)) {
            self::autorizar($t, $actor);
        } else {
            TodoAviso::para($t, self::gestores(), 'ha asignado una tarea a Claude: necesita tu visto bueno para que la haga', $actor->id);
        }
    }

    public static function autorizar(TodoTarea $t, User $admin): void
    {
        if (! $t->claude_autorizada_at) {
            $t->update(['claude_autorizada_at' => now(), 'claude_autorizada_por' => $admin->id]);
            TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $admin->id, 'tipo' => 'evento', 'fecha' => now()->format('Y-m-d'), 'texto' => 'autorizó a Claude para hacer la tarea']);
        }
        self::encolar($t);
    }

    /**
     * Deja la tarea en la cola de trabajadores (una sola vez). Sin $ya espera a la próxima pasada (cada hora);
     * con $ya se puede coger ya mismo, también si ya estaba esperando.
     */
    public static function encolar(TodoTarea $t, bool $ya = false): ?string
    {
        if (! $t->claude_autorizada_at || $t->claude_pausada || ! $t->abierta() || ! self::asignada($t)) {
            return null;
        }
        $cola = DB::table('tareas')->where('proceso', 'claude.todo')->whereIn('estado', ['pendiente', 'en_curso'])
            ->whereRaw("json_extract(parametros, '$.tarea_id') = ?", [$t->id])->first();
        if ($cola) {
            if ($ya && $cola->estado === 'pendiente') {
                DB::table('tareas')->where('id', $cola->id)->update(['no_antes_de' => null, 'updated_at' => now()]);
            }

            return $cola->estado;
        }
        $minutos = max(1, (int) config('contabilidad.claude_todo_cada_minutos', 60));
        // Próxima pasada: el siguiente múltiplo de $minutos desde la hora en punto
        $siguiente = now()->copy()->startOfHour()->addMinutes(intdiv(now()->minute, $minutos) * $minutos + $minutos);
        ColaTareas::crear('claude.todo', ['tarea_id' => $t->id], null, auth()->id(), $ya ? null : $siguiente);

        return 'pendiente';
    }

    /** Alguien (no Claude) ha respondido en una tarea de Claude. */
    public static function alResponder(TodoTarea $t, int $actorId, bool $urgente = false): void
    {
        if ($actorId !== self::usuario()?->id && self::asignada($t)) {
            self::encolar($t, $urgente);
        }
    }

    /** Lo que ve Claude: la tarea con su hilo. */
    public static function detalle(TodoTarea $t): array
    {
        $t->load(['creador:id,name', 'asignados:id,name', 'comentarios.user:id,name']);

        return [
            'id' => $t->id, 'titulo' => $t->titulo, 'descripcion' => $t->descripcion, 'estado' => $t->estado,
            'prioridad' => $t->prioridad, 'fecha_limite' => $t->fecha_limite?->format('Y-m-d'),
            'creador' => $t->creador->name, 'asignados' => $t->asignados->pluck('name')->all(),
            'autorizada_por' => $t->claude_autorizada_por ? User::find($t->claude_autorizada_por)?->name : null,
            'hilo' => $t->comentarios->map(fn ($c) => [
                'tipo' => $c->tipo, 'autor' => $c->user->name, 'fecha' => $c->fecha->format('Y-m-d'), 'texto' => $c->texto,
            ])->all(),
        ];
    }

    /**
     * Resultado de una pasada de Claude: respuesta (opcional) y estado (hecha | bloqueada | en_curso). Avisa por la campana
     * a quien creó la tarea y a los demás asignados. No encola nada: Claude no se llama a sí mismo.
     */
    public static function registrar(TodoTarea $t, ?string $estado, ?string $respuesta, ?array $uso = null): void
    {
        $claude = self::usuario();
        abort_unless($claude && self::asignada($t) && $t->claude_autorizada_at, 403);
        $hoy = now()->format('Y-m-d');
        $interesados = array_values(array_unique(array_merge([$t->creador_id], $t->asignados()->pluck('users.id')->all())));

        if ($respuesta !== null && trim($respuesta) !== '') {
            TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $claude->id, 'tipo' => 'respuesta', 'fecha' => $hoy, 'texto' => trim($respuesta)]);
        }
        if ($estado && isset(TodoTarea::ESTADOS[$estado]) && $estado !== $t->estado) {
            TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $claude->id, 'tipo' => 'evento', 'fecha' => $hoy,
                'texto' => 'cambió el estado de «'.TodoTarea::ESTADOS[$t->estado].'» a «'.TodoTarea::ESTADOS[$estado].'»']);
            $t->estado = $estado;
            $t->cerrada_at = in_array($estado, TodoTarea::CERRADOS, true) ? now() : null;
            $t->save();
        }
        $texto = match ($estado) {
            'bloqueada' => 'tiene una duda y espera tu respuesta',
            'hecha' => 'ha terminado la tarea',
            'en_curso' => 'ha empezado la tarea',
            default => 'ha respondido',
        };
        if ($estado !== 'en_curso' || $respuesta) {
            TodoAviso::para($t, $interesados, $texto, $claude->id);
        }
        if ($uso) {   // fin de una pasada: anotar lo que ha costado
            DB::table('claude_ejecuciones')->insert([
                'tarea_id' => $t->id, 'pc' => mb_substr((string) ($uso['pc'] ?? ''), 0, 50), 'ok' => (bool) ($uso['ok'] ?? true),
                'coste_usd' => $uso['coste_usd'] ?? null, 'turnos' => $uso['turnos'] ?? null, 'tokens' => $uso['tokens'] ?? null,
                'segundos' => $uso['segundos'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $t->touch();
    }
}
