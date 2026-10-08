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

    /**
     * Propuesta para una entidad sin carpeta: nombre del cliente, carpeta anual dentro de _Clientes/{AAAA} y si se detectó en OneDrive.
     * $raizOneDrive = la carpeta que hace de «OneDrive» (VPS: la copia de trabajo; PC: la real); 
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
        $anual = $nombre.' {AAAA}';
        $detectada = false;
        // ¿Hay ya una carpeta de la entidad en _Clientes/<año>? (alias, primera palabra o nombre completo)
        $anio = date('Y');
        $dirs = $raizOneDrive ? (glob(rtrim($raizOneDrive, '/')."/_Clientes/{$anio}/*", GLOB_ONLYDIR) ?: glob(rtrim($raizOneDrive, '/')."/Clientes/{$anio}/*", GLOB_ONLYDIR) ?: []) : [];
        $claves = array_filter([self::norm((string) $e->alias), self::norm($pal[0]), self::norm(implode('', $pal))]);
        foreach ($dirs as $d) {
            $b = basename($d);
            if (str_starts_with($b, '_')) {
                continue;
            }
            $stem = self::norm(trim(preg_replace('/\b(19|20)\d\d\b/', '', $b)));
            if ($stem !== '' && in_array($stem, $claves, true)) {
                $anual = preg_replace('/\b'.$anio.'\b/', '{AAAA}', $b);
                $detectada = true;
                break;
            }
        }

        return ['nombre' => $nombre, 'anual' => $anual, 'detectada' => $detectada, 'entidad' => $e->entidad, 'nif' => (string) $e->nif];
    }

    /** Nombre válido como carpeta (letras, números, espacio, punto, guion). */
    public static function nombreValido(string $n): bool
    {
        return (bool) preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._-]{0,38}$/u', $n) && ! str_contains($n, '..');
    }

    public static function anualValida(string $a): bool
    {
        return (bool) preg_match('/^[\p{L}\p{N}_][\p{L}\p{N} ._{}-]{0,58}$/u', $a) && ! str_contains($a, '..');
    }

    /**
     * Crea el cliente en Facturas OCR y en Bancos (cada uno solo si su carpeta de código existe en esta máquina), enlazado a la entidad.
     * Devuelve los módulos creados.
     */
    public static function crear(int $entidadId, string $nombre, string $anual, ?string $raizOneDrive): array
    {
        $e = self::activas()[$entidadId] ?? null;
        abort_unless($e, 403, 'Entidad no disponible');
        abort_unless(self::nombreValido($nombre) && self::anualValida($anual), 422, 'Nombre no válido');
        $hechos = [];
        $ocr = rtrim((string) config('contabilidad.facturasocr_dir'), '/');
        if ($ocr !== '' && is_dir($ocr) && ! is_dir("$ocr/$nombre")) {
            $raiz = "{OneDrive}/_Clientes/{AAAA}/{$anual}";
            @mkdir("$ocr/$nombre", 0775, true);
            file_put_contents("$ocr/$nombre/cliente.json", json_encode([
                'entidad_id' => $entidadId, 'entidad' => $e->entidad, 'nif' => (string) $e->nif,
                'carpeta_recibidas' => "{OneDrive}/_Clientes/{AAAA}/{$anual}/_Facturas/{MM}",
                'carpeta_emitidas' => "{OneDrive}/_Clientes/{AAAA}/{$anual}/_Facturas/Emitidas/{MM}",
                'serie_recibidas' => '',
                'datos' => '{OneDrive}/_ClaudeDesarrollo/FacturasOCR/'.$nombre,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $hechos[] = 'Facturas OCR';
            if ($raizOneDrive && is_dir($raizOneDrive)) {   // carpeta anual de este año y datos compartidos
                $a = str_replace('{AAAA}', date('Y'), $anual);
                @mkdir(rtrim($raizOneDrive, '/')."/_Clientes/".date('Y')."/$a/_Facturas", 0775, true);
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
