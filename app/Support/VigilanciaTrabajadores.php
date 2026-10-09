<?php

namespace App\Support;

use App\Models\TodoAviso;
use App\Models\TodoComentario;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avisa por la campana del TO-DO (4-oct-2026) cuando un trabajador (PC) lleva demasiado sin dar señales, y cuando vuelve.
 * Se comprueba cada vez que cualquier trabajador da un latido y cada vez que se pinta la campana (como mucho una vez por minuto),
 * así que avisa aunque el otro PC esté apagado en cuanto alguien abra Appmos. Una tarea del TO-DO por PC caído, solo para Alex
 * (no para Claude: no hay nada que arreglar en el código), que se cierra sola cuando el PC vuelve. Nunca rompe lo que lo llama.
 */
class VigilanciaTrabajadores
{
    /** Minutos sin latido a partir de los cuales se avisa (los PCs dan señales cada pocos segundos). */
    public const SIN_SENAL_MIN = 10;

    public static function revisar(): void
    {
        ErroresApp::cerrarResueltos();   // de paso: cierra las tareas de error que ya no se repiten
        try {
            if (! Schema::hasTable('trabajadores') || ! Schema::hasTable('todo_tareas') || ! Cache::add('vigilancia.trabajadores', 1, 60)) {
                return;
            }
            $alex = User::whereIn('email', config('contabilidad.claude_todo_gestores', []))->orderBy('id')->first();
            if (! $alex) {
                return;
            }
            $limite = now()->subMinutes(self::SIN_SENAL_MIN);
            foreach (DB::table('trabajadores')->where('activo', true)->whereNull('silenciado_at')->get() as $w) {   // los apagados a propósito no se vigilan
                $marca = '[trab-'.$w->nombre.']';
                $abierta = TodoTarea::where('descripcion', 'like', '%'.$marca.'%')->whereNotIn('estado', TodoTarea::CERRADOS)->first();
                $caido = ! $w->ultimo_latido || \Illuminate\Support\Carbon::parse($w->ultimo_latido)->lt($limite);
                if ($caido && ! $abierta) {
                    self::avisarCaida($alex, $w, $marca);
                } elseif (! $caido && $abierta) {
                    self::cerrarAviso($alex, $abierta, $w);
                }
            }
        } catch (\Throwable $e) {
            // nunca romper por vigilar
        }
    }

    /** «Es correcto, lo he apagado»: no se vigila ese PC hasta que vuelva a dar señales (entonces se reactiva solo y avisa). Cierra el aviso abierto. */
    public static function silenciar(string $nombre): bool
    {
        $w = DB::table('trabajadores')->where('nombre', $nombre)->first();
        if (! $w) {
            return false;
        }
        DB::table('trabajadores')->where('id', $w->id)->update(['silenciado_at' => now()]);
        $alex = User::whereIn('email', config('contabilidad.claude_todo_gestores', []))->orderBy('id')->first();
        $abierta = TodoTarea::where('descripcion', 'like', '%[trab-'.$nombre.']%')->whereNotIn('estado', TodoTarea::CERRADOS)->first();
        if ($abierta && $alex) {
            TodoComentario::create(['tarea_id' => $abierta->id, 'user_id' => auth()->id() ?? $alex->id, 'tipo' => 'evento', 'fecha' => now()->format('Y-m-d'),
                'texto' => 'el PC '.$nombre.' está apagado a propósito ('.now()->format('d/m H:i').'): no se vigila hasta que vuelva a encenderse']);
            $abierta->update(['estado' => 'hecha', 'cerrada_at' => now()]);
        }

        return true;
    }

    /** Un PC silenciado da señales otra vez: vuelve la vigilancia y se avisa por la campana. Lo llama el latido. */
    public static function reactivar(object $w): void
    {
        DB::table('trabajadores')->where('id', $w->id)->update(['silenciado_at' => null]);
        try {
            $alex = User::whereIn('email', config('contabilidad.claude_todo_gestores', []))->orderBy('id')->first();
            if ($alex) {
                $t = TodoTarea::create(['titulo' => 'El PC '.$w->nombre.' se ha encendido: vigilancia reactivada', 'descripcion' => 'Estaba apagado a propósito; ha vuelto a dar señales y Appmos vuelve a vigilarlo.',
                    'creador_id' => $alex->id, 'estado' => 'hecha', 'prioridad' => 'baja', 'cerrada_at' => now()]);
                $t->asignados()->attach($alex->id, ['orden' => TodoTarea::siguienteOrden($alex->id)]);
                TodoAviso::create(['user_id' => $alex->id, 'tarea_id' => $t->id, 'origen_id' => null, 'texto' => '✔ El PC '.$w->nombre.' se ha encendido: vigilancia reactivada']);
            }
        } catch (\Throwable $e) {
            // no romper por avisar
        }
    }

    protected static function avisarCaida(User $alex, object $w, string $marca): void
    {
        $desde = $w->ultimo_latido ? \Illuminate\Support\Carbon::parse($w->ultimo_latido)->format('d/m H:i') : 'nunca';
        $pend = DB::table('tareas')->where('estado', 'pendiente')->where(fn ($q) => $q->where('destino', $w->nombre)->orWhereNull('destino'))->pluck('proceso')->countBy()->all();
        $esperan = $pend ? implode(', ', array_map(fn ($p, $n) => "{$p} ×{$n}", array_keys($pend), $pend)) : 'ninguna';
        $t = TodoTarea::create([
            'titulo' => 'El PC '.$w->nombre.' no da señales desde las '.$desde,
            'descripcion' => $marca."\nAppmos no recibe latidos del trabajador de ".$w->nombre." desde ".$desde." (avisa a partir de ".self::SIN_SENAL_MIN." minutos).\n\n"
                ."Tareas pendientes que podrían estar esperándolo: {$esperan}.\n\n"
                ."Qué mirar: que el PC esté encendido y con la sesión de Windows abierta (el trabajador necesita sesión: Outlook, Excel y OneDrive). Si es el escritorio remoto, "
                ."cierra la ventana (desconecta) en vez de «Cerrar sesión». Esta tarea se cierra sola cuando el PC vuelva a dar señales.",
            'creador_id' => $alex->id, 'estado' => 'pendiente', 'prioridad' => 'alta',
        ]);
        $t->asignados()->attach($alex->id, ['orden' => TodoTarea::siguienteOrden($alex->id)]);
        TodoAviso::create(['user_id' => $alex->id, 'tarea_id' => $t->id, 'origen_id' => null, 'texto' => '⚠ El PC '.$w->nombre.' no da señales desde las '.$desde]);
        self::correo($alex, $w, $desde, $esperan);
    }

    /** Correo a Alex (solo al caerse un PC; una vez por caída). Si Graph no está configurado o falla, no pasa nada: queda la campana y el banner. */
    protected static function correo(User $alex, object $w, string $desde, string $esperan): void
    {
        try {
            if (! config('contabilidad.vigilancia_correo', true) || ! GraphMail::configurado() || ! $alex->email) {
                return;
            }
            $texto = "Hola Alex,\n\nEl PC {$w->nombre} no da señales a Appmos desde las {$desde}. Hasta que vuelva, no se ejecutan en él los procesos de Appmos "
                ."(tareas esperando: {$esperan}).\n\nQué mirar: que el PC esté encendido y con la sesión de Windows abierta. Si entras por escritorio remoto, cierra la ventana "
                ."(desconecta) en vez de «Cerrar sesión». El aviso se cierra solo cuando el PC vuelva.\n\nAppmos";
            GraphMail::enviar(config('contabilidad.graph.sender'), [$alex->email], [], '⚠ El PC '.$w->nombre.' no da señales (Appmos)', $texto);
        } catch (\Throwable $e) {
            // no romper por avisar
        }
    }

    /** Para el banner grande: los PCs caídos con aviso abierto [nombre, titulo, tarea_id, pendientes]. */
    public static function caidos(): array
    {
        try {
            if (! Schema::hasTable('todo_tareas')) {
                return [];
            }
            $out = [];
            foreach (TodoTarea::where('descripcion', 'like', '[trab-%')->whereNotIn('estado', TodoTarea::CERRADOS)->get() as $t) {
                if (preg_match('/^\[trab-([^\]]+)\]/', $t->descripcion, $m)) {
                    $pend = DB::table('tareas')->where('estado', 'pendiente')->where(fn ($q) => $q->where('destino', $m[1])->orWhereNull('destino'))->count();
                    $out[] = ['nombre' => $m[1], 'titulo' => $t->titulo, 'tarea_id' => $t->id, 'pendientes' => $pend];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function cerrarAviso(User $alex, TodoTarea $t, object $w): void
    {
        TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $alex->id, 'tipo' => 'evento', 'fecha' => now()->format('Y-m-d'),
            'texto' => 'el PC '.$w->nombre.' vuelve a dar señales ('.now()->format('d/m H:i').'): cierro el aviso']);
        $t->update(['estado' => 'hecha', 'cerrada_at' => now()]);
        TodoAviso::create(['user_id' => $alex->id, 'tarea_id' => $t->id, 'origen_id' => null, 'texto' => '✔ El PC '.$w->nombre.' vuelve a estar activo']);
    }
}
