<?php

namespace App\Support;

/**
 * Ficheros base de cada cliente (mayor, plan de cuentas, proveedores y clientes de SAGE), UNA sola vez para todos los procesos (4-oct-2026):
 * lo que se sube en una pantalla lo ven las demás. Estructura: storage/app/ficheros-base/<entidad_id>/<tipo>/<AAAAmmdd-HHMMSS>_<nombre>.xlsx
 * (se conserva todo el historial; los procesos usan el último de cada tipo). Cada proceso tiene su adaptador: Facturas OCR (dos sentidos, ver
 * FacturasOcr::sincronizarCentral), Revisión del mayor (lee y escribe aquí). Pendiente: Bancos, Neteges, IS y LeoyBra.
 *
 * 16-oct-2026 ("si subo el SyS para un proceso, cualquier otro que lo necesite tire siempre del último que he subido"): también el
 * balance de sumas y saldos ('sys'), que va POR PERIODO (AAAA-MM = mes al que llega: el fichero de SAGE no lo dice, lo pone la pantalla
 * que lo sube) y el ejercicio de cada fichero («Ejercicio: AAAA» de la cabecera de SAGE), para que cada proceso coja el último del
 * periodo/ejercicio que necesita: Procesos FIQ (Monthly sales, Anaplan, Rentas Variables), 202 modalidad 40.3 e IS (M200).
 */
class FicherosBase
{
    public const TIPOS = [
        'plan' => ['titulo' => 'Plan de cuentas', 'icono' => '📘', 'ayuda' => 'Plan de cuentas de SAGE (Excel).'],
        'clientes' => ['titulo' => 'Clientes', 'icono' => '👥', 'ayuda' => 'Listado de clientes de SAGE (Excel).'],
        'proveedores' => ['titulo' => 'Proveedores', 'icono' => '🏭', 'ayuda' => 'Listado de proveedores de SAGE (Excel).'],
        'mayor' => ['titulo' => 'Mayor', 'icono' => '📒', 'ayuda' => 'Mayor de SAGE (Excel). Se puede subir uno nuevo en cualquier momento: los procesos usan el último.'],
        'sys' => ['titulo' => 'Sumas y saldos', 'icono' => '⚖️', 'ayuda' => 'Balance de sumas y saldos de SAGE (Excel), del mes al que llega.', 'por_periodo' => true],
    ];

    public static function raiz(): string
    {
        return rtrim((string) config('contabilidad.ficheros_base_dir') ?: storage_path('app/ficheros-base'), '/');
    }

    public static function dir(int $entidadId, string $tipo): string
    {
        abort_unless($entidadId > 0 && isset(self::TIPOS[$tipo]), 422, 'Fichero base no válido');

        return self::raiz().'/'.$entidadId.'/'.$tipo;
    }

    /** Guarda una copia nueva (con fecha y hora delante). Devuelve la ruta, o null si no se pudo. */
    public static function guardar(int $entidadId, string $tipo, string $ruta, string $nombre, string $origen = '', ?string $periodo = null): ?string
    {
        if (! empty(self::TIPOS[$tipo]['por_periodo']) && ! preg_match('/^\d{4}-\d{2}$/', (string) $periodo)) {
            return null;   // un sumas y saldos sin saber de qué mes es no sirve a nadie
        }
        $dir = self::dir($entidadId, $tipo);
        @mkdir($dir, 0775, true);
        $limpio = preg_replace('/^\d{8}-\d{6}_/', '', preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($nombre)));
        $destino = $dir.'/'.date('Ymd-His').'_'.$limpio;
        if (! @copy($ruta, $destino)) {
            return null;
        }
        if ($origen !== '') {
            @file_put_contents($destino.'.origen', $origen);
        }
        if ($periodo) {
            @file_put_contents($destino.'.periodo', $periodo);
        }
        self::sha($destino);
        self::ejercicio($destino);

        return $destino;
    }

    /** Todos los de ese tipo, del más reciente al más antiguo: [ruta, nombre, fecha (timestamp), origen]. */
    public static function historial(int $entidadId, string $tipo): array
    {
        $out = [];
        foreach (glob(self::dir($entidadId, $tipo).'/*.xlsx') ?: [] as $f) {
            $out[] = ['ruta' => $f, 'nombre' => preg_replace('/^\d{8}-\d{6}_/', '', basename($f)), 'fecha' => filemtime($f),
                'origen' => trim((string) @file_get_contents($f.'.origen')),
                'periodo' => trim((string) @file_get_contents($f.'.periodo')) ?: null, 'ejercicio' => self::ejercicio($f)];
        }
        usort($out, fn ($a, $b) => strcmp(basename($b['ruta']), basename($a['ruta'])));

        return $out;
    }

    /**
     * El último subido de ese tipo. $periodo (AAAA-MM): solo los de ese mes (sumas y saldos); $ejercicio: solo los de ese ejercicio
     * (los de ejercicio desconocido también valen, por si SAGE no lo pusiera en la cabecera).
     */
    public static function ultimo(int $entidadId, string $tipo, ?string $periodo = null, ?int $ejercicio = null): ?array
    {
        foreach (self::historial($entidadId, $tipo) as $h) {
            if (($periodo === null || $h['periodo'] === $periodo) && ($ejercicio === null || $h['ejercicio'] === null || $h['ejercicio'] === $ejercicio)) {
                return $h;
            }
        }

        return null;
    }

    /** Ejercicio del listado de SAGE («Ejercicio: 2026» en la cabecera), o null. Se apunta en un «.ejercicio» al lado. */
    public static function ejercicio(string $ruta): ?int
    {
        $c = $ruta.'.ejercicio';
        if (is_file($c)) {
            $v = trim((string) file_get_contents($c));

            return $v === '' ? null : (int) $v;
        }
        $ej = null;
        $txt = self::principio($ruta);
        if (preg_match('/Ejercicio:\s*(\d{4})/u', $txt, $m)) {
            $ej = (int) $m[1];
        }
        if (str_starts_with($ruta, self::raiz().'/')) {
            @file_put_contents($c, (string) $ej);
        }

        return $ej;
    }

    /** ¿Aparecen todos esos textos al principio del Excel? (para comprobar a grandes rasgos que es el listado que se dice). */
    public static function contiene(string $ruta, string ...$textos): bool
    {
        $t = self::principio($ruta);
        foreach ($textos as $x) {
            if (! str_contains($t, $x)) {
                return false;
            }
        }

        return true;
    }

    /** ¿Es un balance de sumas y saldos de SAGE? (para no guardar como tal, p. ej., una «Cuenta de explotación»). */
    public static function esSumasSaldos(string $ruta): bool
    {
        return (bool) preg_match('/sumas\s+y\s+saldos/iu', self::principio($ruta));
    }

    /** ¿Es el mayor de SAGE en columnas (Código cuenta, Fecha asiento...: el que leen Bancos, Facturas OCR, Neteges...), no en «fichas»? */
    public static function esMayorLineal(string $ruta): bool
    {
        $t = self::principio($ruta);

        return str_contains($t, 'Código cuenta') && str_contains($t, 'Fecha asiento');
    }

    /**
     * Trae a la carpeta de un proceso el último fichero base que le sirve ($periodo/$ejercicio como en ultimo()) si es NUEVO para ese
     * proceso: lo deja como $dir/$clave.xlsx (el que hubiera pasa a $dir/anteriores con fecha) y apunta su huella en
     * $dir/central_visto.json para no volver a traerlo. Si lo de la carpeta es más reciente que el del central, se queda lo de la carpeta.
     * Devuelve el fichero base traído, o null si no había nada nuevo.
     */
    public static function traer(int $entidadId, string $tipo, ?string $periodo, ?int $ejercicio, string $dir, string $clave): ?array
    {
        $c = $entidadId ? self::ultimo($entidadId, $tipo, $periodo, $ejercicio) : null;
        if (! $c) {
            return null;
        }
        $sha = self::sha($c['ruta']);
        $marca = $dir.'/central_visto.json';
        $visto = is_file($marca) ? (json_decode((string) file_get_contents($marca), true) ?: []) : [];
        if (($visto[$clave] ?? null) === $sha) {
            return null;
        }
        $actuales = array_values(array_filter(glob($dir.'/'.$clave.'.*') ?: [], fn ($p) => ! str_ends_with($p, '.origen.txt')));
        $apuntar = function () use ($marca, $visto, $clave, $sha, $dir) {
            @mkdir($dir, 0777, true);
            @file_put_contents($marca, json_encode([$clave => $sha] + $visto, JSON_PRETTY_PRINT));
        };
        if ($actuales && (hash_file('sha256', $actuales[0]) === $sha || filemtime($actuales[0]) > $c['fecha'])) {
            $apuntar();   // ya es ese, o lo de la carpeta es más nuevo

            return null;
        }
        if (! is_dir($dir.'/anteriores') && ! @mkdir($dir.'/anteriores', 0777, true)) {
            return null;
        }
        foreach ($actuales as $viejo) {
            @rename($viejo, $dir.'/anteriores/'.date('Ymd-His').' '.basename($viejo));
        }
        if (! @copy($c['ruta'], $dir.'/'.$clave.'.xlsx')) {
            return null;
        }
        @file_put_contents($dir.'/'.$clave.'.origen.txt', $c['nombre']."\n".date('d/m/Y H:i', $c['fecha']).' · de los ficheros base'.($c['origen'] ? ' ('.$c['origen'].')' : '')."\n");
        $apuntar();

        return $c;
    }

    /** Los primeros KB de texto del fichero (la primera hoja y los textos compartidos de un .xlsx; un .csv tal cual). */
    protected static function principio(string $ruta, int $bytes = 30000): string
    {
        $zip = new \ZipArchive;
        if (@$zip->open($ruta) !== true) {
            return (string) @file_get_contents($ruta, false, null, 0, $bytes);
        }
        $txt = '';
        foreach (['xl/sharedStrings.xml', 'xl/worksheets/sheet.xml', 'xl/worksheets/sheet1.xml'] as $n) {
            $st = $zip->getStream($n);
            if ($st) {
                $txt .= (string) fread($st, $bytes);
                fclose($st);
            }
        }
        $zip->close();

        return $txt;
    }

    /** Huella SHA-256 del fichero; para los guardados aquí se apunta en un «.sha256» al lado (no se recalcula en cada pantalla). */
    public static function sha(string $ruta): string
    {
        $c = $ruta.'.sha256';
        if (is_file($c) && filemtime($c) >= filemtime($ruta)) {
            return trim((string) file_get_contents($c));
        }
        $h = hash_file('sha256', $ruta);
        if (str_starts_with($ruta, self::raiz().'/')) {
            @file_put_contents($c, $h);
        }

        return $h;
    }

    /** ¿Ya hay en el central un fichero con ese contenido? (para no duplicar al sincronizar con un proceso). */
    public static function existeContenido(int $entidadId, string $tipo, string $ruta): bool
    {
        $sha = hash_file('sha256', $ruta);
        foreach (self::historial($entidadId, $tipo) as $h) {
            if (self::sha($h['ruta']) === $sha) {
                return true;
            }
        }

        return false;
    }
}
