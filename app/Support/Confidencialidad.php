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

    public const MENSAJE_CERRADO = 'El almacén cifrado de Confidencialidad está CERRADO (el VPS se ha reiniciado): no se guarda nada hasta abrirlo. Desde un PC: bash Confidencialidad/almacen_cifrado.sh abrir';

    /** El almacén cifrado (LUKS, ver Confidencialidad/almacen_cifrado.sh) está montado: lleva una marca dentro. Sin él no se guarda NADA (si no, iría al disco sin cifrar). */
    public static function almacenAbierto(): bool
    {
        return is_file(storage_path('app/confidencialidad/.cifrado'));
    }

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
        $o = ['--perfil', in_array($meta['perfil'] ?? '', ['estricto', 'contable', 'mercantil'], true) ? $meta['perfil'] : 'estricto'];
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
    public static function crear(User $u, array $archivos, array $nombres, array $empresas, string $perfil, ?string $titulo, ?string $auto = null): ConfidencialidadDoc
    {
        if (! self::almacenAbierto()) {
            throw new \RuntimeException(self::MENSAJE_CERRADO);
        }
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'titulo' => $titulo ?: null, 'estado' => 'procesando']);
        $dir = self::dir($d);
        mkdir($dir.'/_orig', 0700, true);
        $meta = ['nombres' => array_values($nombres), 'empresas' => array_values($empresas), 'perfil' => $perfil, 'originales' => []];
        if ($auto !== null && trim($auto) !== '') {     // «anonimizar y enviar»: al terminar la primera pasada se da por buena y se le pide esto a Claude (ver alTerminar)
            $meta['auto'] = trim($auto);
        }
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

        return self::lanzar($d);
    }

    /** (Re)hace la anonimización de los originales con las listas de nombres/empresas y el perfil de meta.json. Empieza de cero: el mapa se rehace. */
    public static function procesar(ConfidencialidadDoc $d): ConfidencialidadDoc
    {
        $dir = self::dir($d);
        $meta = self::leerMeta($d);
        $d->update(['estado' => 'procesando', 'error' => null]);
        @unlink($dir.'/progreso.json');
        foreach (glob($dir.'/f*', GLOB_ONLYDIR) ?: [] as $x) {
            self::borrarDir($x);
        }
        @unlink($dir.'/mapa.json');
        $informe = [];
        $lista = [];
        $candidatos = [];
        $error = null;
        foreach ($meta['originales'] as $k => $o) {
            $orig = $dir.'/_orig/'.$o['n'].'.'.$o['ext'];
            file_put_contents($dir.'/progreso_doc.json', json_encode(['doc' => $k + 1, 'de' => count($meta['originales'])]));
            $r = Process::timeout(1800)->env(['APPMOS_PROGRESO' => $dir.'/progreso.json', 'APPMOS_OCR_CACHE' => $dir.'/_orig/ocr', 'APPMOS_TIEMPOS' => $dir.'/tiempos.log', 'APPMOS_VISIBLES' => json_encode(array_values($meta['visibles'] ?? []), JSON_UNESCAPED_UNICODE), 'APPMOS_ETIQUETAS' => self::etiquetasPropiasJson($meta), 'APPMOS_SIN_CABECERA' => ! empty($meta['sin_cabecera']) ? '1' : '0'])->run(array_merge(
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

            return $d->fresh() ?? $d;      // si lo borraron mientras se procesaba, fresh() da null
        }
        arsort($candidatos);
        file_put_contents($dir.'/candidatos.json', json_encode(array_map(fn ($t, $n) => ['texto' => $t, 'veces' => $n], array_keys($candidatos), array_values($candidatos)), JSON_UNESCAPED_UNICODE));
        chmod($dir.'/candidatos.json', 0600);
        @unlink($dir.'/progreso.json');
        @unlink($dir.'/progreso_doc.json');
        $d->update(['estado' => 'borrador', 'error' => null, 'informe' => $informe, 'archivos' => $lista]);

        return $d->fresh() ?? $d;
    }

    /** Cambia lo que hay que ocultar y rehace el anonimizado. */
    /** Etiquetas con nombre propio elegido por quien revisa: meta['etiquetas'] = [['texto' => 'B659…', 'etiqueta' => 'NifFashion'], …] -> {"NifFashion": ["B659…"]} */
    public static function etiquetasPropiasJson(array $meta): string
    {
        $g = [];
        foreach ($meta['etiquetas'] ?? [] as $e) {
            $g[$e['etiqueta']][] = $e['texto'];
        }

        return json_encode((object) $g, JSON_UNESCAPED_UNICODE);
    }

    /** Nombre de etiqueta válido: solo letras (sin tildes ni espacios), 3-20, primera en mayúscula. '' si no vale. */
    public static function limpiarEtiqueta(string $e): string
    {
        $e = preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($e));

        return strlen($e) >= 3 && strlen($e) <= 20 ? ucfirst($e) : '';
    }

    public static function etiquetasPropias(ConfidencialidadDoc $d): array
    {
        return array_values(self::leerMeta($d)['etiquetas'] ?? []);
    }

    public static function ponerEtiquetasPropias(ConfidencialidadDoc $d, array $lista): void
    {
        $meta = self::leerMeta($d);
        $meta['etiquetas'] = array_values($lista);
        self::guardarMeta($d, $meta);
    }

    /** Datos que se dejan a la vista a propósito (también los detectados solos). */
    public static function visibles(ConfidencialidadDoc $d): array
    {
        return array_values(self::leerMeta($d)['visibles'] ?? []);
    }

    public static function ponerVisibles(ConfidencialidadDoc $d, array $lista): void
    {
        $meta = self::leerMeta($d);
        $meta['visibles'] = array_values(array_unique(array_filter(array_map('trim', $lista))));
        self::guardarMeta($d, $meta);
    }

    public static function actualizar(ConfidencialidadDoc $d, array $nombres, array $empresas, string $perfil, ?bool $sinCabecera = null): ConfidencialidadDoc
    {
        $meta = self::leerMeta($d);
        if ($sinCabecera !== null) {
            $meta['sin_cabecera'] = $sinCabecera;      // «no tapar la parte alta de la página» (para documentos que no son extractos)
        }
        $meta['nombres'] = array_values($nombres);
        $meta['empresas'] = array_values($empresas);
        $meta['perfil'] = in_array($perfil, ['estricto', 'contable', 'mercantil'], true) ? $perfil : 'estricto';
        self::guardarMeta($d, $meta);
        $d->update(['estado' => 'procesando', 'error' => null]);

        return self::lanzar($d);
    }

    /**
     * Anonimizar un escaneado tarda (OCR por página, 1 CPU): se hace en SEGUNDO PLANO con `php artisan confidencialidad:procesar <id>` y la pantalla enseña el progreso.
     * Así la petición web no se queda colgada ni caduca.
     */
    public static function lanzar(ConfidencialidadDoc $d): ConfidencialidadDoc
    {
        $php = (new \Symfony\Component\Process\PhpExecutableFinder)->find(false) ?: 'php';
        Process::path(base_path())->run('nohup '.escapeshellarg($php).' artisan confidencialidad:procesar '.(int) $d->id.' > /dev/null 2>&1 &');

        return $d->fresh();
    }

    /**
     * «Anonimizar y enviar» (19-nov-2026, Alex: no esperar a revisar): al acabar la primera pasada automática se da por bueno el anonimizado (se borra el original)
     * y se le pide a Claude lo que se escribió, ya anonimizado con el mismo mapa. Sin revisión humana: solo se hace si se pidió así al empezar.
     */
    public static function alTerminar(ConfidencialidadDoc $d): void
    {
        $meta = self::leerMeta($d);
        $auto = $meta['auto'] ?? null;
        if (! $auto || $d->estado !== 'borrador') {
            return;                                   // sin envío programado, o la anonimización falló: se queda como estaba
        }
        $u = User::find($d->user_id);
        if (! $u) {
            return;
        }
        unset($meta['auto']);
        self::guardarMeta($d, $meta);                 // antes de enviar: nunca se pide dos veces
        self::finalizar($d, false);
        self::pedirAClaude($d->fresh(), $u, $auto);
    }

    /** Progreso del procesado en curso: ['doc' => k, 'de' => M, 'pagina' => i, 'total' => N] (lo que se sepa). */
    public static function progreso(ConfidencialidadDoc $d): array
    {
        $dir = self::dir($d);
        $a = json_decode((string) @file_get_contents($dir.'/progreso_doc.json'), true) ?: [];
        $b = json_decode((string) @file_get_contents($dir.'/progreso.json'), true) ?: [];

        return $a + $b;
    }

    /** Se da por bueno el anonimizado: se borran los originales y las sugerencias (que salen del original). Ya no se pueden afinar las listas. */
    public static function finalizar(ConfidencialidadDoc $d, bool $conservar = false): void
    {
        $dir = self::dir($d);
        @unlink($dir.'/candidatos.json');
        if ($conservar) {          // decisión de Alex: el original se queda (solo el fichero; sin caché de OCR) hasta CONSERVA_DIAS días
            self::borrarDir($dir.'/_orig/ocr');
            $meta = self::leerMeta($d);
            $meta['conservado'] = now()->toDateString();
            self::guardarMeta($d, $meta);
        } else {
            self::borrarDir($dir.'/_orig');
        }
        $d->update(['estado' => 'listo']);
    }

    public const CONSERVA_DIAS = 30;

    public static function originalConservado(ConfidencialidadDoc $d): bool
    {
        return $d->estado === 'listo' && ! empty(self::leerMeta($d)['conservado']) && is_dir(self::dir($d).'/_orig');
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
        foreach (ConfidencialidadDoc::where('estado', 'listo')->where('updated_at', '<', now()->subDays(self::CONSERVA_DIAS))->get() as $d) {   // originales conservados a propósito: caducan
            if (is_dir(self::dir($d).'/_orig')) {
                self::borrarDir(self::dir($d).'/_orig');
            }
        }
        foreach (ConfidencialidadDoc::whereIn('estado', ['borrador', 'procesando'])->where('created_at', '<', now()->subHours(self::HORAS_ORIGINAL))->get() as $d) {
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
    /**
     * Texto que hay bajo un recuadro dibujado sobre la página entregada `$n` (fracciones 0-1 del ancho y alto de la imagen), leído de la caché local del OCR
     * (no vuelve a leer nada). Sirve para tapar a mano un nombre: se propone lo que hay bajo el recuadro y se añade a las listas.
     */
    public static function textoEnRecuadro(ConfidencialidadDoc $d, int $n, float $x1, float $y1, float $x2, float $y2): string
    {
        $a = ($d->archivos ?? [])[$n] ?? null;
        if (! $a || ! preg_match('#^(f\d+)/([\w.-]+\.jpg)$#', $a, $m)) {
            return '';
        }
        $dir = self::dir($d);
        $hash = trim((string) @file_get_contents($dir.'/_orig/ocr/'.$m[1].'_'.$m[2].'.hash'));
        $lineas = $hash !== '' && preg_match('/^[0-9a-f]{40}$/', $hash) ? json_decode((string) @file_get_contents($dir.'/_orig/ocr/'.$hash.'.json'), true) : null;
        $tam = @getimagesize($dir.'/'.$a);
        if (! is_array($lineas) || ! $tam) {
            return '';
        }
        [$W, $H] = $tam;
        [$l, $r] = [min($x1, $x2) * $W, max($x1, $x2) * $W];
        [$t, $b] = [min($y1, $y2) * $H, max($y1, $y2) * $H];
        $salida = [];
        foreach ($lineas as $pal) {
            $dentro = [];
            foreach ($pal as $w) {
                $cx = ($w['l'] + $w['r']) / 2;
                $cy = ($w['t'] + $w['b']) / 2;
                if ($cx >= $l && $cx <= $r && $cy >= $t && $cy <= $b) {
                    $dentro[] = $w['x'];
                }
            }
            if ($dentro) {
                $salida[] = implode(' ', $dentro);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $salida)), " \t.,;:");
    }

    /** Tabla «quién es quién» (etiqueta => dato real) para enseñársela a su dueño en la pantalla; ordenada por tipo y número. */
    public static function quienEsQuien(ConfidencialidadDoc $d): array
    {
        $mapa = (json_decode((string) @file_get_contents(self::dir($d).'/mapa.json'), true) ?: [])['mapa'] ?? [];
        uksort($mapa, 'strnatcasecmp');

        return $mapa;
    }

    public static function restaurar(ConfidencialidadDoc $d, ?string $texto): string
    {
        $mapa = (json_decode((string) @file_get_contents(self::dir($d).'/mapa.json'), true) ?: [])['mapa'] ?? [];
        $bajo = array_change_key_case($mapa, CASE_LOWER);

        if (! $bajo) {
            return (string) $texto;
        }
        $claves = array_map(fn ($k) => preg_quote($k, '/'), array_keys($bajo));
        usort($claves, fn ($a, $b) => strlen($b) <=> strlen($a));

        return preg_replace_callback('/\b(?:'.implode('|', $claves).')\b/iu', fn ($m) => $bajo[mb_strtolower($m[0])] ?? $m[0], (string) $texto);
    }

    /** Tipos de fichero que se pueden dar a Claude tal cual (sin pasar por el anonimizador). */
    public const EXTENSIONES_CLARO = ['txt', 'pdf', 'jpg', 'jpeg', 'png'];

    /**
     * Decisión de Alex (19-nov-2026): trabajar un correo con Claude SIN anonimizar (el correo y sus adjuntos salen en claro hacia Anthropic; la pantalla pide confirmación).
     * Crea un documento ya «listo» con los ficheros tal cual (cuerpo en .txt, adjuntos PDF/imagen/txt); no hay mapa ni original. Los demás adjuntos (Excel, Word…) no van.
     *
     * @param  ArchivoLocal[]  $archivos
     * @return array{0: ConfidencialidadDoc, 1: string[]}  el documento y los nombres de los adjuntos que NO se han incluido
     */
    public static function crearSinAnonimizar(User $u, array $archivos, ?string $titulo): array
    {
        $d = ConfidencialidadDoc::create(['user_id' => $u->id, 'titulo' => $titulo ?: null, 'estado' => 'listo']);
        $dir = self::dir($d);
        mkdir($dir.'/f1', 0700, true);
        $lista = [];
        $omitidos = [];
        foreach (array_values($archivos) as $i => $f) {
            $ext = strtolower($f->getClientOriginalExtension());
            if (! in_array($ext, self::EXTENSIONES_CLARO, true)) {
                $omitidos[] = $f->getClientOriginalName();

                continue;
            }
            $ext = $ext === 'jpeg' ? 'jpg' : $ext;
            $nombre = sprintf('%02d', $i + 1).'_'.substr(preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($f->getClientOriginalName(), PATHINFO_FILENAME)), 0, 40).'.'.$ext;
            copy($f->getRealPath(), $dir.'/f1/'.$nombre);
            chmod($dir.'/f1/'.$nombre, 0600);
            $lista[] = 'f1/'.$nombre;
        }
        foreach ($archivos as $f) {
            try {
                $f->delete();
            } catch (\Throwable $e) {
            }
        }
        self::guardarMeta($d, ['nombres' => [], 'empresas' => [], 'perfil' => 'estricto', 'originales' => [], 'sin_anonimizar' => true]);
        $d->update(['archivos' => $lista]);

        return [$d->fresh() ?? $d, $omitidos];
    }

    /** ¿Este documento va a Claude tal cual, sin anonimizar? */
    public static function esClaro(ConfidencialidadDoc $d): bool
    {
        return ! empty(self::meta($d)['sin_anonimizar']);
    }

    /**
     * Crea la tarea del TO-DO para Claude con la instrucción ANONIMIZADA y los documentos de este registro. Si ya hay tarea, añade la indicación al hilo.
     */
    public static function pedirAClaude(ConfidencialidadDoc $d, User $u, string $instruccion): TodoTarea
    {
        $claro = self::esClaro($d);
        $anon = $claro ? trim($instruccion) : trim(self::anonimizarTexto($d, $instruccion));
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
            'descripcion' => $anon."\n\n---\n".($claro
                ? 'Documento(s) de esta petición (documento #'.$d->id.', SIN anonimizar por decisión de quien lo pidió): llegan como ficheros en la carpeta que se indica al trabajar la tarea.'
                : 'Documento(s) ANONIMIZADO(S) de esta petición (documento #'.$d->id.'): llegan como ficheros en la carpeta que se indica al trabajar la tarea. '
                    .'Los datos personales están sustituidos por etiquetas (Persona1, Empresa1, Mail1…): usa esas mismas etiquetas en tu respuesta; Appmos las convierte de nuevo en los datos reales al mostrársela a quien lo pidió.'),
            'creador_id' => $u->id,
            'prioridad' => 'normal',
            'oculta' => true,      // el chat vive en esta pantalla, no en el TO-DO
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

    /**
     * Conversación completa con Claude, con los datos reales puestos (solo para mostrarla): [{quien: yo|claude, fecha, texto}].
     * Lo primero es la petición inicial (descripción de la tarea sin el pie técnico); después los comentarios de ambos.
     */
    public static function conversacion(ConfidencialidadDoc $d): array
    {
        $t = $d->tarea_id ? TodoTarea::find($d->tarea_id) : null;
        $claude = TodoClaude::usuario();
        if (! $t || ! $claude) {
            return [];
        }
        $out = [];
        $primera = trim(explode("\n\n---\n", (string) $t->descripcion)[0]);
        if (! str_starts_with($primera, 'REVISIÓN DE FUGAS')) {       // la revisión de fugas es un paso técnico, no parte de la conversación
            $out[] = ['quien' => 'yo', 'fecha' => $t->created_at?->format('d/m H:i') ?? '', 'texto' => self::restaurar($d, $primera)];
        }
        foreach (TodoComentario::where('tarea_id', $t->id)->where('tipo', 'respuesta')->orderBy('id')->get() as $c) {
            if (preg_match('/^\s*(REVISI[ÓO]N)/iu', $c->texto) || str_starts_with($c->texto, 'REVISIÓN DE FUGAS')) {
                continue;
            }
            $out[] = ['quien' => $c->user_id === $claude->id ? 'claude' : 'yo', 'fecha' => $c->created_at?->format('d/m H:i') ?? $c->fecha->format('d/m'), 'texto' => self::restaurar($d, $c->texto)];
        }

        return $out;
    }

    /**
     * Correo en .eml -> ficheros sueltos (cuerpo.txt + adjuntos) en una carpeta temporal. Python con la librería estándar; no hace falta nada instalado.
     *
     * @return ArchivoLocal[]
     */
    public static function expandirEml(string $ruta): array
    {
        $tmp = sys_get_temp_dir().'/conf_eml_'.bin2hex(random_bytes(6));
        mkdir($tmp, 0700, true);
        $py = <<<'PY'
import sys, os, email, re, html
from email import policy
m = email.message_from_binary_file(open(sys.argv[1], 'rb'), policy=policy.default)
out = sys.argv[2]
cab = "De: %s\nPara: %s\nAsunto: %s\nFecha: %s\n\n" % (m['from'], m['to'], m['subject'], m['date'])
cuerpo = m.get_body(preferencelist=('plain', 'html'))
t = cuerpo.get_content() if cuerpo else ''
if cuerpo is not None and cuerpo.get_content_type() == 'text/html':
    t = re.sub(r'(?is)<(script|style).*?</\1>', ' ', t); t = re.sub(r'(?i)<br\s*/?>|</p>|</div>|</tr>', '\n', t); t = html.unescape(re.sub(r'<[^>]+>', ' ', t))
open(os.path.join(out, '00_cuerpo.txt'), 'w', encoding='utf-8').write(cab + re.sub(r'\n\s*\n+', '\n\n', t))
i = 0
for p in m.iter_attachments():
    nombre = p.get_filename() or 'adjunto'
    datos = p.get_payload(decode=True) or b''
    if not datos: continue
    i += 1
    open(os.path.join(out, '%02d_%s' % (i, re.sub(r'[^A-Za-z0-9._-]', '_', nombre))), 'wb').write(datos)
PY;
        $r = Process::timeout(60)->run(['python3', '-I', '-c', $py, $ruta, $tmp]);
        abort_unless($r->successful(), 422, 'No se ha podido leer el correo (.eml).');
        $lista = [];
        foreach (glob($tmp.'/*') ?: [] as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, self::EXTENSIONES, true) && ! (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true) && filesize($f) < 6000)) {   // sin logos/iconos minúsculos
                $lista[] = new ArchivoLocal($f, preg_replace('/^\d+_/', '', basename($f)));
            } else {
                @unlink($f);
            }
        }

        return $lista;
    }

    /** Correo del buzón (Graph) -> ficheros: el cuerpo en texto y todos los adjuntos, también las imágenes incrustadas (Graph a veces dice que no hay adjuntos). @return ArchivoLocal[] */
    public static function archivosDeCorreo(string $buzon, string $id): array
    {
        $m = GraphMail::mensaje($buzon, $id);
        $tmp = sys_get_temp_dir().'/conf_msg_'.bin2hex(random_bytes(6));
        mkdir($tmp, 0700, true);
        $texto = $m['html'];
        if (($m['tipo'] ?? 'html') === 'html') {
            $texto = preg_replace('/(?is)<(script|style).*?<\/\1>/', ' ', $texto);
            $texto = preg_replace('/(?i)<br\s*\/?>|<\/p>|<\/div>|<\/tr>/', "\n", $texto);
            $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $f = $tmp.'/00_cuerpo.txt';
        file_put_contents($f, 'De: '.$m['de']."\nAsunto: ".$m['asunto']."\nFecha: ".substr($m['fecha'], 0, 16)."\n\n".preg_replace('/\n\s*\n+/', "\n\n", $texto));
        $lista = [new ArchivoLocal($f, 'correo.txt')];
        foreach (GraphMail::adjuntos($buzon, $id) as $i => $a) {
            $ext = strtolower(pathinfo($a['nombre'], PATHINFO_EXTENSION));
            $datos = base64_decode($a['bytes'], true) ?: '';
            if ($datos === '' || ! in_array($ext, self::EXTENSIONES, true) || (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true) && strlen($datos) < 6000)) {
                continue;
            }
            $ruta = $tmp.'/'.sprintf('%02d', $i + 1).'.'.$ext;
            file_put_contents($ruta, $datos);
            $lista[] = new ArchivoLocal($ruta, $a['nombre']);
        }

        return $lista;
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
