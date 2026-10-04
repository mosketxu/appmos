<?php

namespace App\Support;

/**
 * Ficheros base de cada cliente (mayor, plan de cuentas, proveedores y clientes de SAGE), UNA sola vez para todos los procesos (4-oct-2026):
 * lo que se sube en una pantalla lo ven las demás. Estructura: storage/app/ficheros-base/<entidad_id>/<tipo>/<AAAAmmdd-HHMMSS>_<nombre>.xlsx
 * (se conserva todo el historial; los procesos usan el último de cada tipo). Cada proceso tiene su adaptador: Facturas OCR (dos sentidos, ver
 * FacturasOcr::sincronizarCentral), Revisión del mayor (lee y escribe aquí). Pendiente: Bancos, Neteges, IS y LeoyBra.
 */
class FicherosBase
{
    public const TIPOS = [
        'mayor' => ['titulo' => 'Mayor', 'icono' => '📒', 'ayuda' => 'Mayor de SAGE (Excel). Se puede subir uno nuevo en cualquier momento: los procesos usan el último.'],
        'plan' => ['titulo' => 'Plan de cuentas', 'icono' => '📘', 'ayuda' => 'Plan de cuentas de SAGE (Excel).'],
        'proveedores' => ['titulo' => 'Proveedores', 'icono' => '🏭', 'ayuda' => 'Listado de proveedores de SAGE (Excel).'],
        'clientes' => ['titulo' => 'Clientes', 'icono' => '👥', 'ayuda' => 'Listado de clientes de SAGE (Excel).'],
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
    public static function guardar(int $entidadId, string $tipo, string $ruta, string $nombre, string $origen = ''): ?string
    {
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
        self::sha($destino);

        return $destino;
    }

    /** Todos los de ese tipo, del más reciente al más antiguo: [ruta, nombre, fecha (timestamp), origen]. */
    public static function historial(int $entidadId, string $tipo): array
    {
        $out = [];
        foreach (glob(self::dir($entidadId, $tipo).'/*.xlsx') ?: [] as $f) {
            $out[] = ['ruta' => $f, 'nombre' => preg_replace('/^\d{8}-\d{6}_/', '', basename($f)), 'fecha' => filemtime($f),
                'origen' => trim((string) @file_get_contents($f.'.origen'))];
        }
        usort($out, fn ($a, $b) => strcmp(basename($b['ruta']), basename($a['ruta'])));

        return $out;
    }

    public static function ultimo(int $entidadId, string $tipo): ?array
    {
        return self::historial($entidadId, $tipo)[0] ?? null;
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
