<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Entidades activas del usuario como «clientes» de Facturas OCR y Bancos (8-oct-2026). Cada proceso tiene una carpeta por cliente
 * (Contabilidad/FacturasOcr/<Cliente>, Contabilidad/Bancos/<Cliente>) con un cliente.json que la enlaza a la entidad; si la entidad
 * aún no tiene carpeta, se crea al elegirla (el usuario confirma en un modal el nombre y la carpeta anual de OneDrive que se propone).
 */
class ClientesEntidad
{
    private const SUFIJOS = ['sl', 'slu', 'sa', 'sau', 'slp', 'sc', 'cb', 'ltd', 'gmbh', 'sociedad', 'limitada'];

    /** Entidades activas (estado 1) y clientes que puede ver el usuario: [id => {id, entidad, alias, nif}]. */
    public static function activas(): array
    {
        $q = DB::table('entidades')->where('estado', 1)->where('cliente', 1)->whereNull('deleted_at')
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'nif']);
        $permitidas = Accesos::entidadesPermitidas();
        $out = [];
        foreach ($q as $e) {
            if ($permitidas === null || in_array((int) $e->id, $permitidas, true)) {
                $out[(int) $e->id] = $e;
            }
        }

        return $out;
    }

    /** entidad_id => nombre de carpeta, de las carpetas de cliente que hay en $baseDir. */
    public static function carpetas(string $baseDir): array
    {
        $out = [];
        foreach (glob(rtrim($baseDir, '/').'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $cfg = json_decode((string) @file_get_contents($d.'/cliente.json'), true);
            if (! empty($cfg['entidad_id'])) {
                $out[(int) $cfg['entidad_id']] = basename($d);
            }
        }

        return $out;
    }

    /**
     * Opciones del desplegable: las carpetas que ya hay (valor = nombre de carpeta) y, al final, las entidades activas del usuario
     * sin carpeta (valor «e:<id>»). $existentes = carpetas visibles que ya calculó el componente.
     */
    public static function opciones(array $existentes, string $baseDir): array
    {
        $out = [];
        foreach ($existentes as $c) {
            $out[$c] = $c;
        }
        $conCarpeta = array_keys(self::carpetas($baseDir));
        foreach (self::activas() as $id => $e) {
            if (! in_array($id, $conCarpeta, true)) {
                $out['e:'.$id] = '➕ '.$e->entidad.' (crear carpeta)';
            }
        }

        return $out;
    }

    /** Palabras significativas del nombre de la entidad (sin S.L., S.A.…). */
    protected static function palabras(string $nombre): array
    {
        $limpio = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', str_replace('.', '', $nombre));
        $out = [];
        foreach (preg_split('/\s+/', trim($limpio)) as $p) {
            if ($p !== '' && ! in_array(mb_strtolower($p), self::SUFIJOS, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    protected static function titulo(string $p): string
    {
        return mb_strlen($p) <= 3 ? mb_strtoupper($p) : mb_convert_case($p, MB_CASE_TITLE);
    }

    protected static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s));
    }

    /** Árbol de carpetas de OneDrive/_Clientes que subió un PC (tarea pc.arbol_carpetas): ['fecha', 'pc', 'dirs' => ['_Clientes/2026/Fashion 2026', ...]] o null. */
    public static function arbol(): ?array
    {
        try {
            $a = ColaTareas::estado('onedrive.arbol');
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($a) && ! empty($a['dirs']) ? $a : null;
    }

    /** ¿Hay ya una tarea de leer las carpetas de OneDrive en marcha? */
    public static function arbolEnCurso(): bool
    {
        try {
            return DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Pide a un PC que lea las carpetas de OneDrive (el resultado queda en arbol()). */
    public static function pedirArbol(): void
    {
        if (! self::arbolEnCurso()) {
            ColaTareas::crear('pc.arbol_carpetas', [], ColaTareas::pcElegido() ?: null, auth()->id());
        }
    }

    /** Subcarpetas directas de $ruta (relativa a OneDrive) según el árbol. */
    public static function hijas(array $dirs, string $ruta): array
    {
        $pre = $ruta === '' ? '' : $ruta.'/';
        $out = [];
        foreach ($dirs as $d) {
            if ($pre === '' ? ! str_contains($d, '/') : (str_starts_with($d, $pre) && ! str_contains(substr($d, strlen($pre)), '/') && strlen($d) > strlen($pre))) {
                $out[] = basename($d);
            }
        }
        natcasesort($out);

        return array_values($out);
    }

    /** «_Clientes/2026/Fashion 2026/_Facturas» → «Fashion {AAAA}/_Facturas» (null si no cuelga de _Clientes/<año>/). */
    public static function aCarpeta(string $ruta): ?string
    {
        if (! preg_match('#^_Clientes/((?:19|20)\d\d)/(.+)$#u', $ruta, $m)) {
            return null;
        }

        return str_replace($m[1], '{AAAA}', $m[2]);
    }

    /** Ruta con el año en lugar de {AAAA}, con barras de Windows, para enseñársela al usuario. */
    public static function rutaWindows(string $carpeta): string
    {
        return 'OneDrive\\_Clientes\\'.date('Y').'\\'.str_replace('/', '\\', str_replace('{AAAA}', date('Y'), $carpeta));
    }

    /**
     * Propuesta para una entidad sin carpeta: nombre del cliente y carpeta donde están (o estarán) sus facturas recibidas dentro de _Clientes/{AAAA}/
     * (con subcarpetas por mes), y si se detectó en OneDrive. La detección usa el árbol que subió un PC y, si no lo hay, la «OneDrive» de esta máquina.
     */
    public static function proponer(int $entidadId, ?string $raizOneDrive): array
    {
        $e = self::activas()[$entidadId] ?? null;
        abort_unless($e, 403, 'Entidad no disponible');
        $pal = self::palabras($e->entidad);
        $pal = $pal ?: [$e->entidad];
        // Si el otro proceso (Bancos / Facturas OCR) ya la tiene con carpeta, se usa el mismo nombre; si no, el nombre sale de la entidad sin repetir los ya cogidos
        $ocr = self::carpetas((string) config('contabilidad.facturasocr_dir'));
        $ban = self::carpetas((string) config('contabilidad.bancos_dir'));
        $nombre = $ocr[$entidadId] ?? $ban[$entidadId] ?? null;
        if ($nombre === null) {
            $usados = array_map('mb_strtolower', array_merge(array_values($ocr), array_values($ban)));
            $nombre = self::titulo($pal[0]);
            for ($n = 2; in_array(mb_strtolower($nombre), $usados, true) && $n <= count($pal); $n++) {
                $nombre = implode(' ', array_map([self::class, 'titulo'], array_slice($pal, 0, $n)));
            }
        }
        $carpeta = $nombre.' {AAAA}/_Facturas';
        $detectada = false;
        $anio = date('Y');
        $arbol = self::arbol();
        if ($arbol) {
            $dirs = $arbol['dirs'];
        } else {
            $dirs = [];
            foreach (['_Clientes', 'Clientes'] as $c) {
                foreach ($raizOneDrive ? (glob(rtrim($raizOneDrive, '/')."/{$c}/{$anio}/*", GLOB_ONLYDIR) ?: []) : [] as $d) {
                    $dirs[] = "_Clientes/{$anio}/".basename($d);
                    foreach (glob($d.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
                        $dirs[] = "_Clientes/{$anio}/".basename($d).'/'.basename($sub);
                    }
                }
            }
        }
        $claves = array_filter([self::norm((string) $e->alias), self::norm($pal[0]), self::norm(implode('', $pal))]);
        foreach ($dirs as $d) {
            if (! preg_match('#^_Clientes/'.$anio.'/([^/]+)$#u', $d, $m) || str_starts_with($m[1], '_')) {
                continue;
            }
            $stem = self::norm(trim(preg_replace('/\b(19|20)\d\d\b/', '', $m[1])));
            if ($stem === '' || ! in_array($stem, $claves, true)) {
                continue;
            }
            $detectada = true;
            $raiz = str_replace($anio, '{AAAA}', $m[1]);
            $carpeta = $raiz.'/_Facturas';
            // Dentro, la carpeta de facturas recibidas si se la reconoce por el nombre
            foreach (self::hijas($dirs, $d) as $h) {
                if (preg_match('/(^_?_?Facturas$|fras?\W*recib|recibid|facturas\W*recib)/iu', $h)) {
                    $carpeta = $raiz.'/'.str_replace($anio, '{AAAA}', $h);
                    break;
                }
            }
            break;
        }

        return ['nombre' => $nombre, 'carpeta' => $carpeta, 'detectada' => $detectada, 'entidad' => $e->entidad, 'nif' => (string) $e->nif];
    }

    /** Nombre válido como carpeta (letras, números, espacio, punto, guion). */
    public static function nombreValido(string $n): bool
    {
        return (bool) preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._-]{0,38}$/u', $n) && ! str_contains($n, '..');
    }

    /** Carpeta de facturas recibidas bajo _Clientes/{AAAA}/ (puede llevar subcarpetas con /). */
    public static function carpetaValida(string $c): bool
    {
        if (str_contains($c, '..') || ! str_contains($c, '{AAAA}')) {
            return false;
        }
        foreach (explode('/', $c) as $seg) {
            if (! preg_match('/^[\p{L}\p{N}_][\p{L}\p{N} ._{}()-]{0,78}$/u', $seg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Crea el cliente en Facturas OCR y en Bancos (cada uno solo si su carpeta de código existe en esta máquina), enlazado a la entidad.
     * Devuelve los módulos creados.
     */
    public static function crear(int $entidadId, string $nombre, string $carpeta, ?string $raizOneDrive): array
    {
        $e = self::activas()[$entidadId] ?? null;
        abort_unless($e, 403, 'Entidad no disponible');
        abort_unless(self::nombreValido($nombre) && self::carpetaValida($carpeta), 422, 'Nombre no válido');
        $hechos = [];
        $ocr = rtrim((string) config('contabilidad.facturasocr_dir'), '/');
        if ($ocr !== '' && is_dir($ocr) && ! is_dir("$ocr/$nombre")) {
            @mkdir("$ocr/$nombre", 0775, true);
            file_put_contents("$ocr/$nombre/cliente.json", json_encode([
                'entidad_id' => $entidadId, 'entidad' => $e->entidad, 'nif' => (string) $e->nif,
                'carpeta_recibidas' => "{OneDrive}/_Clientes/{AAAA}/{$carpeta}/{MM}",
                'carpeta_emitidas' => "{OneDrive}/_Clientes/{AAAA}/{$carpeta}/Emitidas/{MM}",
                'serie_recibidas' => '',
                'datos' => '{OneDrive}/_ClaudeDesarrollo/FacturasOCR/'.$nombre,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $hechos[] = 'Facturas OCR';
            if ($raizOneDrive && is_dir($raizOneDrive)) {   // carpeta anual de este año y datos compartidos
                $a = str_replace('{AAAA}', date('Y'), $carpeta);
                @mkdir(rtrim($raizOneDrive, '/').'/_Clientes/'.date('Y')."/$a", 0775, true);
                @mkdir(rtrim($raizOneDrive, '/').'/_ClaudeDesarrollo/FacturasOCR/'.$nombre, 0775, true);
            }
        }
        $ban = rtrim((string) config('contabilidad.bancos_dir'), '/');
        if ($ban !== '' && is_dir($ban) && ! is_dir("$ban/$nombre")) {
            @mkdir("$ban/$nombre", 0775, true);
            file_put_contents("$ban/$nombre/cliente.json", json_encode([
                'entidad_id' => $entidadId, 'entidad' => $e->entidad,
                'nota' => 'Enlace con la entidad de Appmos: decide qué usuarios ven este cliente en Bancos',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $hechos[] = 'Bancos';
        }

        return $hechos;
    }
}
