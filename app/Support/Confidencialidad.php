<?php

namespace App\Support;

use App\Models\ConfidencialidadDoc;
use App\Models\TodoAviso;
use App\Models\TodoComentario;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/**
 * Anonimizar documentos en el VPS y pedir a Claude que trabaje con ellos YA anonimizados (17-nov-2026, orden de Alex).
 *
 * El documento original se procesa con Contabilidad/_comun/anonimizador.py (copia en confidencialidad_dir), se BORRA al terminar y solo quedan:
 *   storage/app/confidencialidad/<id>/f<n>/...   ficheros anonimizados (texto.txt o p001.jpg…)
 *   storage/app/confidencialidad/<id>/mapa.json  etiqueta -> dato real (600; no sale del VPS: ni a la BD, ni al trabajador, ni a Anthropic)
 *   storage/app/confidencialidad/<id>/meta.json  nombres/empresas a ocultar y perfil (600)
 * A Claude (tarea del TO-DO) solo llegan los anonimizados y la instrucción también anonimizada; sus respuestas se restauran aquí, al mostrarlas.
 */
class Confidencialidad
{
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'txt', 'csv', 'md', 'eml', 'json', 'html', 'htm', 'xml', 'log', 'xlsx', 'xlsm', 'docx'];

    private const ETIQUETAS = 'Persona|Empresa|Mail|Telefono|Iban|Cuenta|Tarjeta|Nif|Cif|Direccion|Poblacion|Ref';

    public static function dir(ConfidencialidadDoc $d): string
    {
        return storage_path('app/confidencialidad/'.$d->id);
    }

    protected static function script(): string
    {
        return rtrim((string) config('contabilidad.confidencialidad_dir'), '/').'/anonimizador.py';
    }

    protected static function python(): string
    {
        return (string) config('contabilidad.confidencialidad_python', 'python3');
    }

    protected static function meta(ConfidencialidadDoc $d): array
    {
        return json_decode((string) @file_get_contents(self::dir($d).'/meta.json'), true) ?: ['nombres' => [], 'empresas' => [], 'perfil' => 'estricto'];
    }

    /** Opciones comunes de la línea de comandos: nombres, empresas y perfil. */
    protected static function opciones(array $meta): array
    {
        $o = ['--perfil', in_array($meta['perfil'] ?? '', ['estricto', 'contable'], true) ? $meta['perfil'] : 'estricto'];
        foreach ($meta['nombres'] ?? [] as $n) {
            array_push($o, '--nombre', $n);
        }
        foreach ($meta['empresas'] ?? [] as $e) {
            array_push($o, '--empresa', $e);
        }

        return $o;
    }

    /** Horas que un original puede quedarse en el VPS mientras se revisa lo que hay que ocultar. Pasado ese tiempo se borra todo el documento. */
    public const HORAS_ORIGINAL = 12;

    protected static function guardarMeta(ConfidencialidadDoc $d, array $meta): void
    {
        $f = self::dir($d).'/meta.json';
        file_put_contents($f, json_encode($meta, JSON_UNESCAPED_UNICODE));
        chmod($f, 0600);
    }

    public static function leerMeta(ConfidencialidadDoc $d): array
    {
        return self::meta($d) + ['nombres' => [], 'empresas' => [], 'perfil' => 'estricto', 'originales' => []];
    }

    /**
     * Sube los documentos y hace una primera anonimización AUTOMÁTICA. Los originales se quedan en el VPS (carpeta 700, ficheros 600, máx. HORAS_ORIGINAL horas)
     * para poder verlos al lado del anonimizado y afinar qué ocultar; el estado es «borrador» hasta que se dan por buenos (finalizar), que los borra.
     *
     * @param  \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[]|array  $archivos
     */
    public static function crear(User $u, array $archivos, array $nombres, array $empresas, string $perfil, ?string $titulo): ConfidencialidadDoc
    {
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'titulo' => $titulo ?: null, 'estado' => 'borrador']);
        $dir = self::dir($d);
        mkdir($dir.'/_orig', 0700, true);
        $meta = ['nombres' => array_values($nombres), 'empresas' => array_values($empresas), 'perfil' => $perfil, 'originales' => []];
        try {
            foreach (array_values($archivos) as $i => $f) {
                $ext = strtolower($f->getClientOriginalExtension());
                $n = $i + 1;
                copy($f->getRealPath(), $dir.'/_orig/'.$n.'.'.$ext);
                chmod($dir.'/_orig/'.$n.'.'.$ext, 0600);
                $meta['originales'][] = ['n' => $n, 'ext' => $ext, 'nombre' => $f->getClientOriginalName()];
                if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'], true)) {   // para ver el original en pantalla: su texto
                    $r = Process::timeout(120)->run([self::python(), self::script(), '--ver-texto', $dir.'/_orig/'.$n.'.'.$ext]);
                    if ($r->successful()) {
                        file_put_contents($dir.'/_orig/'.$n.'.vista.txt', $r->output());
                        chmod($dir.'/_orig/'.$n.'.vista.txt', 0600);
                    }
                }
            }
        } finally {
            foreach ($archivos as $f) {
                try {
                    $f->delete();                      // la copia temporal de la subida no se queda
                } catch (\Throwable $e) {
                }
            }
        }
        self::guardarMeta($d, $meta);

        return self::procesar($d);
    }

    /** (Re)hace la anonimización de los originales con las listas de nombres/empresas y el perfil de meta.json. Empieza de cero: el mapa se rehace. */
    public static function procesar(ConfidencialidadDoc $d): ConfidencialidadDoc
    {
        $dir = self::dir($d);
        $meta = self::leerMeta($d);
        foreach (glob($dir.'/f*', GLOB_ONLYDIR) ?: [] as $x) {
            self::borrarDir($x);
        }
        @unlink($dir.'/mapa.json');
        $informe = [];
        $lista = [];
        $candidatos = [];
        $error = null;
        foreach ($meta['originales'] as $o) {
            $orig = $dir.'/_orig/'.$o['n'].'.'.$o['ext'];
            $r = Process::timeout(600)->run(array_merge(
                [self::python(), self::script(), '--carpeta', $orig, $dir.'/f'.$o['n'], '--mapa', $dir.'/mapa.json'],
                self::opciones($meta)
            ));
            $j = json_decode($r->output(), true);
            if (! is_array($j) || empty($j['ok'])) {
                $error = ($j['error'] ?? null) ?: trim($r->errorOutput() ?: $r->output() ?: 'No se ha podido anonimizar.');
                break;
            }
            foreach ($j['archivos'] as $a) {
                $lista[] = 'f'.$o['n'].'/'.$a;
            }
            foreach ($j['etiquetado'] ?? [] as $k => $n) {
                $informe[$k] = ($informe[$k] ?? 0) + (int) $n;
            }
            foreach ($j['avisos'] ?? [] as $av) {
                $informe['avisos'][] = $av;
            }
            foreach ($j['candidatos'] ?? [] as $c) {
                $candidatos[$c['texto']] = ($candidatos[$c['texto']] ?? 0) + (int) $c['veces'];
            }
        }
        if (is_file($dir.'/mapa.json')) {
            chmod($dir.'/mapa.json', 0600);
        }
        if ($error !== null) {
            self::borrarDir($dir.'/_orig');          // si falla, no se guarda nada del original
            $d->update(['estado' => 'error', 'error' => $error]);

            return $d->fresh();
        }
        arsort($candidatos);
        file_put_contents($dir.'/candidatos.json', json_encode(array_map(fn ($t, $n) => ['texto' => $t, 'veces' => $n], array_keys($candidatos), array_values($candidatos)), JSON_UNESCAPED_UNICODE));
        chmod($dir.'/candidatos.json', 0600);
        $d->update(['estado' => $d->estado === 'listo' ? 'listo' : 'borrador', 'error' => null, 'informe' => $informe, 'archivos' => $lista]);

        return $d->fresh();
    }

    /** Cambia lo que hay que ocultar y rehace el anonimizado. */
    public static function actualizar(ConfidencialidadDoc $d, array $nombres, array $empresas, string $perfil): ConfidencialidadDoc
    {
        $meta = self::leerMeta($d);
        $meta['nombres'] = array_values($nombres);
        $meta['empresas'] = array_values($empresas);
        $meta['perfil'] = in_array($perfil, ['estricto', 'contable'], true) ? $perfil : 'estricto';
        self::guardarMeta($d, $meta);

        return self::procesar($d);
    }

    /** Se da por bueno el anonimizado: se borran los originales y las sugerencias (que salen del original). Ya no se pueden afinar las listas. */
    public static function finalizar(ConfidencialidadDoc $d): void
    {
        $dir = self::dir($d);
        self::borrarDir($dir.'/_orig');
        @unlink($dir.'/candidatos.json');
        $d->update(['estado' => 'listo']);
    }

    /** Palabras que quedan sin ocultar en el original (sugerencias locales), [{texto, veces}]. */
    public static function candidatos(ConfidencialidadDoc $d): array
    {
        return json_decode((string) @file_get_contents(self::dir($d).'/candidatos.json'), true) ?: [];
    }

    /** Ruta de un original para verlo en pantalla (solo en borrador). */
    public static function rutaOriginal(ConfidencialidadDoc $d, int $n): ?string
    {
        foreach (self::leerMeta($d)['originales'] as $o) {
            if ($o['n'] === $n) {
                $r = self::dir($d).'/_orig/'.$n.'.'.$o['ext'];

                return is_file($r) ? $r : null;
            }
        }

        return null;
    }

    /** Borra los documentos que llevan más de HORAS_ORIGINAL horas en borrador (el original no debe quedarse esperando). */
    public static function limpiarAbandonados(): int
    {
        $n = 0;
        foreach (ConfidencialidadDoc::where('estado', 'borrador')->where('created_at', '<', now()->subHours(self::HORAS_ORIGINAL))->get() as $d) {
            self::borrar($d);
            $n++;
        }

        return $n;
    }

    /** Anonimiza un texto suelto con el mismo mapa (y los mismos nombres) que los documentos. */
    public static function anonimizarTexto(ConfidencialidadDoc $d, string $texto): string
    {
        $r = Process::timeout(120)->input($texto)->run(array_merge(
            [self::python(), self::script(), '--texto-stdin', '--mapa', self::dir($d).'/mapa.json'],
            self::opciones(self::meta($d))
        ));
        abort_unless($r->successful(), 500, 'No se ha podido anonimizar el texto.');

        return $r->output();
    }

    /** Pone los datos reales en un texto con etiquetas (solo para mostrarlo; no se guarda). */
    public static function restaurar(ConfidencialidadDoc $d, ?string $texto): string
    {
        $mapa = (json_decode((string) @file_get_contents(self::dir($d).'/mapa.json'), true) ?: [])['mapa'] ?? [];
        $bajo = array_change_key_case($mapa, CASE_LOWER);

        return preg_replace_callback('/\b(?:'.self::ETIQUETAS.')\d+\b/iu', fn ($m) => $bajo[mb_strtolower($m[0])] ?? $m[0], (string) $texto);
    }

    /**
     * Crea la tarea del TO-DO para Claude con la instrucción ANONIMIZADA y los documentos de este registro. Si ya hay tarea, añade la indicación al hilo.
     */
    public static function pedirAClaude(ConfidencialidadDoc $d, User $u, string $instruccion): TodoTarea
    {
        $anon = trim(self::anonimizarTexto($d, $instruccion));
        $hoy = now()->format('Y-m-d');
        $claude = TodoClaude::usuario();
        abort_unless($claude, 500, 'No existe el usuario Claude en el TO-DO.');

        if ($d->tarea_id && ($t = TodoTarea::find($d->tarea_id))) {
            TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $u->id, 'tipo' => 'respuesta', 'fecha' => $hoy, 'texto' => $anon]);
            if (! $t->abierta()) {
                $t->update(['estado' => 'pendiente', 'cerrada_at' => null]);
            }
            TodoClaude::alResponder($t, $u->id, true);

            return $t;
        }
        $t = TodoTarea::create([
            'titulo' => 'Documento anonimizado #'.$d->id,
            'descripcion' => $anon."\n\n---\nDocumento(s) ANONIMIZADO(S) de esta petición (documento #".$d->id.'): llegan como ficheros en la carpeta que se indica al trabajar la tarea. '
                .'Los datos personales están sustituidos por etiquetas (Persona1, Empresa1, Mail1…): usa esas mismas etiquetas en tu respuesta; Appmos las convierte de nuevo en los datos reales al mostrársela a quien lo pidió.',
            'creador_id' => $u->id,
            'prioridad' => 'normal',
        ]);
        $t->asignados()->attach($claude->id, ['orden' => TodoTarea::siguienteOrden($claude->id)]);
        TodoComentario::create(['tarea_id' => $t->id, 'user_id' => $u->id, 'tipo' => 'evento', 'fecha' => $hoy, 'texto' => 'creó la tarea desde Confidencialidad y la asignó a Claude']);
        $d->update(['tarea_id' => $t->id]);
        TodoClaude::alAsignar($t, $u);
        TodoClaude::encolar($t, true);   // sin esperar a la pasada de la hora

        return $t;
    }

    /** Respuestas de Claude en la tarea, con los datos reales puestos. [{fecha, texto, estado}] */
    public static function respuestas(ConfidencialidadDoc $d): array
    {
        if (! $d->tarea_id) {
            return [];
        }
        $claude = TodoClaude::usuario();
        $t = TodoTarea::find($d->tarea_id);
        if (! $t || ! $claude) {
            return [];
        }

        return TodoComentario::where('tarea_id', $t->id)->where('user_id', $claude->id)->where('tipo', 'respuesta')->orderBy('id')->get()
            ->map(fn ($c) => ['fecha' => $c->created_at?->format('d/m H:i') ?? $c->fecha->format('d/m'), 'texto' => self::restaurar($d, $c->texto)])->all();
    }

    public static function borrar(ConfidencialidadDoc $d): void
    {
        self::borrarDir(self::dir($d));
        $d->delete();
    }

    protected static function borrarDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
