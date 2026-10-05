<?php

namespace App\Http\Livewire\Concerns;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pantallas de Contabilidad que, en la web (VPS, sin `ejecucion_local`), no ejecutan nada ellas mismas: dejan una
 * tarea en la cola y un PC trabajador (Contabilidad/TrabajadorWeb/trabajador.py) la hace con sus ficheros de OneDrive.
 * Al terminar, la pantalla hace lo mismo que haría en local con el resultado (3-oct-2026).
 *
 * La pantalla que lo use debe tener `public string $salida`, `protected string $grupoPc` (clave de
 * config('contabilidad.pc_grupos')), `protected bool $ultimoOk` y el método `recargarEstado()` (relee lo que depende
 * del estado que ha subido un PC). Si hay ficheros de resultado, `$resultados` y el componente
 * `x-contabilidad.resultado-fichero`.
 */
trait EjecutaEnPcs
{
    /**
     * Tareas pedidas a los PCs desde la web y aún sin cerrar:
     * [tarea_id => ['tipo' => 'script'|'estado', 'etiquetas' => [...], 'post' => método|null, 'ctx' => [...], 'resultados' => clave|null]].
     */
    public array $pendientes = [];

    /** En el VPS (sin ejecucion_local) los scripts los hacen los PCs trabajadores vía cola de tareas. */
    protected function remoto(): bool
    {
        return ! config('contabilidad.ejecucion_local');
    }

    /** PC al que van las tareas del grupo si se ha fijado uno (p. ej. el que guarda una base que viaja por git); null = cualquiera. */
    protected function destinoPc(): ?string
    {
        return ColaTareas::pcElegido() ?: (config('contabilidad.pc_grupos.'.$this->grupoPc.'.pc') ?: null);
    }

    protected function colaLista(): bool
    {
        return Schema::hasTable('tareas') && Schema::hasTable('trabajadores') && Schema::hasTable('estado_procesos');
    }

    protected function pcsConectados(): int
    {
        return DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
    }

    /**
     * Deja uno o varios scripts como UNA tarea para los PCs. $pasos: [['script','args','timeout','etiqueta','solo_si_ok'?], ...]
     * ('solo_si_ok': solo se ejecuta si el paso anterior fue bien). En los args, {DIR} y {DIRWIN} = carpeta del grupo en el PC
     * y {E0}, {E1}... = ficheros de $opc['entradas'] ya dejados en el PC.
     * $opc: 'resultados' => clave de $this->resultados para los ficheros; 'post' => método con ($ctx, $desde, array $oks);
     * 'ctx' => datos para el post; 'entradas' => [['ruta' => fichero local de la web, 'nombre' => cómo se llamará, 'dir' => carpeta
     * relativa del grupo, 'sello' => bool (prefijo AAAAMMDD-HHMMSS), 'unico' => bool (con sello solo si ya existe)]].
     * Devuelve el id de la tarea (null si no se pudo pedir).
     */
    protected function lanzarEnCola(array $pasos, array $opc = []): ?int
    {
        $etiquetas = array_column($pasos, 'etiqueta');
        $titulo = implode(' + ', $etiquetas);
        if (! $this->colaLista()) {
            $this->salida .= "\n\n⚠️ {$titulo}: falta hacer la migración de la cola de tareas en este servidor.";
            return null;
        }
        $params = ['grupo' => $this->grupoPc, 'pasos' => array_map(fn ($p) => array_filter([
            'script' => $p['script'], 'args' => array_map('strval', $p['args'] ?? []), 'timeout' => $p['timeout'] ?? 180,
            'solo_si_ok' => ! empty($p['solo_si_ok']) ?: null,
        ], fn ($v) => $v !== null), $pasos)];
        $params += (array) ($opc['extra'] ?? []);   // datos que el PC necesita (p. ej. el estado que se esperaba de OneDrive)
        $entradas = array_values($opc['entradas'] ?? []);
        foreach ($entradas as $i => $e) {
            $params['entradas'][] = ['archivo' => 'e'.$i, 'nombre' => $e['nombre'], 'dir' => $e['dir'], 'sello' => ! empty($e['sello']), 'unico' => ! empty($e['unico'])];
        }
        // Mismo botón pulsado dos veces: no se duplica (sobre todo importante en los envíos de correo).
        if (! $entradas && DB::table('tareas')->where('proceso', 'pc.script')->whereIn('estado', ['pendiente', 'en_curso'])
            ->where('parametros', json_encode($params, JSON_UNESCAPED_UNICODE))->exists()) {
            $this->salida .= "\n\n⚠️ [".date('H:i:s')."] {$titulo}: ya está pedido y sin terminar (mira «Tareas en los PCs»).";
            return null;
        }
        $this->podarFicherosViejos();
        $tid = ColaTareas::crear('pc.script', $params, $this->destinoPc(), auth()->id(), null, ColaTareas::preferido($this->grupoPc), $entradas ? 'preparando' : 'pendiente');
        if ($entradas) {
            $dir = ColaTareas::carpetaEntradas($tid);
            @mkdir($dir, 0775, true);
            foreach ($entradas as $i => $e) {
                if (! @copy($e['ruta'], "{$dir}/e{$i}")) {
                    DB::table('tareas')->where('id', $tid)->update(['estado' => 'cancelada', 'updated_at' => now()]);
                    $this->salida .= "\n\n⚠️ {$titulo}: no he podido guardar {$e['nombre']} para enviarlo al PC.";
                    return null;
                }
            }
            ColaTareas::liberar($tid);
        }
        $this->pendientes[$tid] = ['tipo' => 'script', 'etiquetas' => $etiquetas, 'post' => $opc['post'] ?? null,
            'ctx' => $opc['ctx'] ?? [], 'resultados' => $opc['resultados'] ?? null];
        // Se guarda también en la tarea: si se recarga la página (o se cierra y se vuelve), retomarTareas() la recoge.
        DB::table('tareas')->where('id', $tid)->update(['web' => json_encode(['pantalla' => static::class] + $this->pendientes[$tid], JSON_UNESCAPED_UNICODE)]);
        $this->salida .= "\n\n⏳ [".date('H:i:s')."] {$titulo} · pedido a los PCs (tarea #{$tid}); el resultado saldrá aquí en cuanto lo terminen.";
        if ($this->pcsConectados() === 0) {
            $this->salida .= "\n⚠️ Ahora mismo no hay ningún PC conectado: esperará hasta que alguno arranque (puedes cancelarla en «Tareas en los PCs»).";
        }

        return $tid;
    }

    /**
     * Web: «⬇» de un fichero que solo está en el PC (carpeta del grupo + ruta relativa). El PC lo sube a Appmos y,
     * cuando llega (revisarTareas), el navegador lo descarga por una URL firmada.
     */
    protected function pedirFichero(string $relativa): void
    {
        if (! $this->colaLista() || ! ColaTareas::rutaRelativaSegura($relativa)) {
            $this->salida .= "\n\n⚠️ No puedo pedir {$relativa} al PC.";
            return;
        }
        $tid = ColaTareas::crear('pc.fichero', ['grupo' => $this->grupoPc, 'relativa' => $relativa], $this->destinoPc(), auth()->id(), null, ColaTareas::preferido($this->grupoPc));
        $this->pendientes[$tid] = ['tipo' => 'fichero', 'etiquetas' => ['Descargar '.basename($relativa)], 'post' => null, 'ctx' => [], 'resultados' => null];
        $this->salida .= "\n\n⏳ [".date('H:i:s')."] Pidiendo ".basename($relativa).' al PC (tarea #'.$tid.'); en cuanto llegue se descargará.';
    }

    /** Ficheros que subieron los PCs de tareas con más de 30 días. */
    protected function podarFicherosViejos(): void
    {
        $base = storage_path('app/tareas');
        foreach (is_dir($base) ? (glob($base.'/*', GLOB_ONLYDIR) ?: []) : [] as $d) {
            if (filemtime($d) < time() - 30 * 86400) {
                foreach (glob($d.'/{,entrada/}*', GLOB_BRACE) ?: [] as $f) {
                    is_file($f) && @unlink($f);
                }
                @rmdir($d.'/entrada');
                @rmdir($d);
            }
        }
    }

    /**
     * Al abrir la pantalla en la web: recoge las tareas que este usuario pidió desde esta misma pantalla hace menos de
     * 12 h y cuyo resultado nadie ha recogido (cerró la pestaña, recargó...). Así no se pierden la salida ni los checks.
     */
    protected function retomarTareas(): void
    {
        if (! $this->remoto() || ! $this->colaLista() || ! auth()->id()) {
            return;
        }
        $filas = DB::table('tareas')->where('user_id', auth()->id())->where('proceso', 'pc.script')->whereNull('cerrada_at')
            ->whereNotNull('web')->where('created_at', '>=', now()->subHours(12))->orderBy('id')->get(['id', 'web']);
        foreach ($filas as $f) {
            $w = json_decode($f->web, true) ?: [];
            if (($w['pantalla'] ?? '') === static::class && ($w['tipo'] ?? '') === 'script') {
                unset($w['pantalla']);
                $this->pendientes[$f->id] = $w;
            }
        }
    }

    /** wire:poll mientras haya tareas pedidas: cierra las que ya han terminado (haciendo lo que haría el modo local). */
    public function revisarTareas(): void
    {
        if (! $this->pendientes) {
            return;
        }
        foreach ($this->pendientes as $tid => $p) {
            $t = DB::table('tareas')->find($tid);
            if ($t && in_array($t->estado, ['preparando', 'pendiente', 'en_curso'], true)) {
                continue;
            }
            unset($this->pendientes[$tid]);
            // Quien la cierra es quien consigue marcarla: con dos pestañas abiertas no se hace dos veces
            if ($t && ! DB::table('tareas')->where('id', $tid)->whereNull('cerrada_at')->update(['cerrada_at' => now()])) {
                continue;
            }
            if (! $t || $t->estado === 'cancelada') {
                $this->salida .= "\n\n🚫 ".implode(' + ', $p['etiquetas'] ?? ['Tarea']).' · cancelada.';
                continue;
            }
            $this->cerrarTarea($t, $p);
        }
    }

    protected function cerrarTarea(object $t, array $p): void
    {
        $res = json_decode((string) $t->resultado, true) ?: [];
        if (($p['tipo'] ?? 'script') === 'estado') {
            $this->recargarEstado();
            return;
        }
        if (($p['tipo'] ?? '') === 'fichero') {
            if ($t->estado === 'ok' && ! empty($res['nombre'])) {
                $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('contabilidad.tarea-fichero', now()->addMinutes(10), ['id' => $t->id, 'nombre' => $res['nombre']]);
                $this->js('window.location.href = '.json_encode($url));
            } else {
                $this->salida .= "\n\n⚠️ El PC no ha podido subir el fichero: ".trim((string) $t->log);
            }
            return;
        }
        $etiquetas = $p['etiquetas'] ?? [];
        $pasos = $res['pasos'] ?? [];
        $oks = [];
        $desde = strlen($this->salida);
        if (! $pasos) {
            // el trabajador falló antes de ejecutar nada (script no permitido, falta playwright...)
            $this->salida .= "\n\n===== ".date('H:i:s').' '.implode(' + ', $etiquetas)." =====\n⚠️ ".trim((string) $t->log);
            $this->dispatch('proceso-terminado', mensaje: '⚠️ '.implode(' + ', $etiquetas)."\nNo se pudo ejecutar en el PC. Mira la caja de Salida.");
            $this->ultimoOk = false;
            return;
        }
        $pc = $res['pc'] ?? ($t->trabajador_id ? DB::table('trabajadores')->where('id', $t->trabajador_id)->value('nombre') : '');
        foreach ($pasos as $i => $paso) {
            if (! empty($paso['omitido'])) {   // un paso anterior falló y este era «solo si ok»
                $oks[] = false;
                continue;
            }
            $etiqueta = $etiquetas[$i] ?? ($paso['script'] ?? 'Proceso');
            $this->salida .= "\n\n===== ".date('H:i:s')." {$etiqueta}".($pc ? " · en {$pc}" : '')." =====\n";
            $desde = strlen($this->salida);
            $this->salida .= (string) ($paso['salida'] ?? '');
            $ok = ! empty($paso['ok']);
            $oks[] = $ok;
            if ($ok) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida ".($paso['codigo'] ?? '?').'.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código ".($paso['codigo'] ?? '?')."). Mira la caja de Salida: cada ⚠️ dice qué hacer (👉) si el proceso lo sabe.");
            }
            if (! empty($p['resultados'])) {
                foreach ($paso['ficheros'] ?? [] as $f) {
                    $this->anexarFicheroRemoto($p['resultados'], $f, (int) $t->id, (string) $pc);
                }
            }
        }
        $this->ultimoOk = ! in_array(false, $oks, true);
        if (! empty($p['post'])) {
            $this->{$p['post']}(($p['ctx'] ?? []) + ['tarea' => (int) $t->id], $desde, $oks);
        }
    }

    /** Fichero resultado de una tarea de un PC: ruta Windows en ese PC y, si se subió, botón de descarga. */
    protected function anexarFicheroRemoto(string $key, array $f, int $tid, string $pc): void
    {
        $win = $this->rutaWindows((string) $f['ruta']);
        foreach ($this->resultados[$key] ?? [] as $r) {
            if (str_starts_with($r['ruta'] ?? '', $win)) {
                return;
            }
        }
        $this->resultados[$key][] = ['ruta' => $win.($pc ? " ({$pc})" : ''), 'url' => null, 'local' => null,
            'tarea' => ! empty($f['subido']) ? $tid : null, 'nombre' => (string) ($f['nombre'] ?? basename($win))];
    }

    /** «⬇ Descargar» de un fichero que subió un PC al terminar una tarea (web). */
    public function descargarDeTarea(int $tid, string $nombre)
    {
        $nombre = basename($nombre);
        $ruta = ColaTareas::carpetaFicheros($tid).'/'.$nombre;
        if ($nombre === '' || ! is_file($ruta)) {
            $this->salida .= "\n\n⚠️ No puedo descargar ese fichero (ya no está en el servidor; se borran a los 30 días).";
            return null;
        }

        return response()->download($ruta, $nombre);
    }

    /** Anula una tarea que aún no ha cogido ningún PC (p. ej. un envío pedido con todos los PCs apagados). */
    public function cancelarTarea(int $tid): void
    {
        $n = DB::table('tareas')->where('id', $tid)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'terminada_at' => now(), 'updated_at' => now()]);
        $this->salida .= $n ? "\n\n🚫 Tarea #{$tid} cancelada." : "\n\n⚠️ La tarea #{$tid} ya la ha cogido un PC (o ya terminó): no se puede cancelar.";
        $this->revisarTareas();
    }

    /** PCs trabajadores y últimas tareas del grupo, para el panel «Tareas en los PCs» (solo en la web). */
    public function getPcsProperty(): array
    {
        if (! $this->remoto() || ! Schema::hasTable('trabajadores') || ! Schema::hasTable('tareas')) {
            return ['pcs' => [], 'tareas' => []];
        }
        $limite = now()->subSeconds(ColaTareas::LATIDO_MAX)->toDateTimeString();

        return [
            'pcs' => DB::table('trabajadores')->where('activo', true)->orderBy('nombre')->get()
                ->map(fn ($t) => ['nombre' => $t->nombre, 'conectado' => (bool) ($t->ultimo_latido && $t->ultimo_latido >= $limite), 'latido' => $t->ultimo_latido])->all(),
            'tareas' => DB::table('tareas')->leftJoin('trabajadores', 'trabajadores.id', '=', 'tareas.trabajador_id')
                ->whereIn('tareas.proceso', ['pc.script', 'pc.estado', 'pc.fichero'])
                ->where('tareas.parametros', 'like', '%"grupo":"'.$this->grupoPc.'"%')
                ->orderByDesc('tareas.id')->limit(6)
                ->get(['tareas.id', 'tareas.proceso', 'tareas.parametros', 'tareas.estado', 'tareas.created_at', 'trabajadores.nombre as pc'])->all(),
        ];
    }

    /** Estado que subió un PC (copia en BD de lo que dejan los scripts). */
    protected function estadoRemoto(string $clave): ?array
    {
        try {
            $d = Schema::hasTable('estado_procesos') ? ColaTareas::estado($clave) : null;
        } catch (\Throwable $e) {
            $d = null;
        }

        return is_array($d) ? $d : null;
    }

    /**
     * Web: pide a un PC que suba el estado del grupo si no hay copia o es vieja. Sin esperar: se recarga al llegar
     * (recargarEstado). $claveRef = una de las claves que sube; su updated_at dice cómo de reciente es.
     */
    protected function sincronizarEstado(string $claveRef, bool $forzar = false, int $minutos = 30): void
    {
        if (! $this->remoto() || ! $this->colaLista()) {
            return;
        }
        $ultima = DB::table('estado_procesos')->where('clave', $claveRef)->value('updated_at');
        if (! $forzar && $ultima && \Carbon\Carbon::parse($ultima)->gt(now()->subMinutes($minutos))) {
            return;
        }
        if (DB::table('tareas')->where('proceso', 'pc.estado')->where('parametros', 'like', '%"grupo":"'.$this->grupoPc.'"%')
            ->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            return;
        }
        $tid = ColaTareas::crear('pc.estado', ['grupo' => $this->grupoPc], $this->destinoPc(), auth()->id(), null, ColaTareas::preferido($this->grupoPc));
        $this->pendientes[$tid] = ['tipo' => 'estado', 'etiquetas' => ['Estado de los procesos'], 'post' => null, 'ctx' => [], 'resultados' => null];
    }
}
