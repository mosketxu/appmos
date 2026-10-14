<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * Motor de Intrastat (Contabilidad/Impuestos/Intrastat/intrastat.py), común a la pestaña Impuestos → Intrastat y a la fila
 * Intrastat de Procesos FIQ (14-oct-2026). Se ejecuta en el servidor, sin PC trabajador.
 * Datos: <intrastat_datos>/<cliente>/<AAAA-MM>/{entrada,salida}.
 */
class IntrastatMotor
{
    public static function motorDir(): string
    {
        return rtrim(config('contabilidad.intrastat_dir'), '/');
    }

    public static function datosDir(): string
    {
        return rtrim(config('contabilidad.intrastat_datos') ?: storage_path('app/intrastat'), '/');
    }

    public static function python(): string
    {
        foreach ([config('contabilidad.leoybra_python'), storage_path('app/venv-facturasocr/bin/python')] as $p) {
            if ($p && is_file($p)) {
                return $p;
            }
        }
        return 'python3';
    }

    public static function ejecutar(array $args, int $timeout = 300)
    {
        return Process::path(self::motorDir())->timeout($timeout)->env(['PYTHONIOENCODING' => 'utf-8'])
            ->run(array_merge([self::python(), '-I', self::motorDir().'/intrastat.py'], $args));
    }

    public static function carpeta(string $cliente, string $periodo, string $sub): string
    {
        $d = self::datosDir().'/'.$cliente.'/'.$periodo.'/'.$sub;
        @mkdir($d, 0775, true);
        return $d;
    }

    /** Nombre de fichero seguro para dejarlo en entrada/. */
    public static function nombreEntrada(string $original): string
    {
        return preg_replace('/[^A-Za-z0-9._ &()-]+/', '_', $original);
    }

    /** resumen.json de la última generación (con 'generado' = fecha), o null. */
    public static function resumen(string $cliente, string $periodo): ?array
    {
        $f = self::datosDir().'/'.$cliente.'/'.$periodo.'/salida/resumen.json';
        if (! is_file($f)) {
            return null;
        }
        $r = json_decode((string) file_get_contents($f), true) ?: null;
        if ($r) {
            $r['generado'] = date('d/m/Y H:i', filemtime($f));
        }
        return $r;
    }

    /** Genera con lo que haya en entrada/. Devuelve '' si fue bien o el texto del error. */
    public static function generar(string $cliente, string $periodo): string
    {
        $entradas = glob(self::carpeta($cliente, $periodo, 'entrada').'/*') ?: [];
        if (! $entradas) {
            return 'Sube primero los ficheros del cliente para este mes.';
        }
        $salida = self::carpeta($cliente, $periodo, 'salida');
        foreach (glob($salida.'/*') ?: [] as $viejo) {
            @unlink($viejo);
        }
        try {
            $r = self::ejecutar(array_merge(['generar', $cliente, $periodo], $entradas, ['--salida', $salida]));
        } catch (\Throwable $e) {
            return 'No se pudo generar: '.$e->getMessage();
        }
        return $r->successful() ? '' : 'No se pudo generar: '.trim($r->errorOutput() ?: $r->output());
    }
}
