<?php

namespace App\Support;

use App\Http\Livewire\Contabilidad\FacturasOcr;

/**
 * Deja PDF en la carpeta «Entrada» de un cliente de Facturas OCR (VPS) sin pasar por la pantalla de Facturas OCR: lo usan el Genérico
 * («Pasar a Facturas OCR», ya renombradas y agrupadas) y «Traer de Z:». Misma regla de duplicados que la subida de la web: la huella es
 * el SHA-1 (12 primeros caracteres), que es también el id de la factura; lo ya conocido (en Entrada o en la lista de facturas) no se repite.
 * Las facturas se leen cuando se abre Facturas OCR de ese cliente y se elige IVA/periodo.
 */
class FacturasOcrEntrada
{
    public static function baseDir(): string
    {
        return rtrim((string) config('contabilidad.facturasocr_dir'), '/');
    }

    /** Clientes de Facturas OCR que el usuario puede ver: [nombre => entidad_id]. */
    public static function clientes(): array
    {
        $permitidas = Accesos::entidadesPermitidas();
        $out = [];
        foreach (glob(self::baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $n = basename($d);
            if ($n === 'Doc_y_Config' || $n[0] === '.' || $n[0] === '_' || ! is_file($d.'/cliente.json')) {
                continue;
            }
            $eid = (int) ((json_decode((string) @file_get_contents($d.'/cliente.json'), true)['entidad_id'] ?? 0));
            if ($permitidas === null || in_array($eid, $permitidas, true)) {
                $out[$n] = $eid;
            }
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    public static function dirEntrada(string $cliente): string
    {
        return FacturasOcr::rutaDatos(self::baseDir().'/'.basename($cliente)).'/Entrada';
    }

    /** id (SHA-1[:12]) de las facturas que el cliente ya tiene en la lista y no están quitadas sin contabilizar. */
    protected static function idsConocidos(string $cliente): array
    {
        $estado = json_decode((string) @file_get_contents(FacturasOcr::rutaDatos(self::baseDir().'/'.basename($cliente)).'/facturas.json'), true) ?: [];
        $ids = [];
        foreach ($estado['facturas'] ?? [] as $f) {
            if (! (! empty($f['oculta']) && in_array($f['estado'] ?? '', ['pendiente', 'rechazada', 'ilegible'], true))) {
                $ids[$f['id']] = true;
            }
        }

        return $ids;
    }

    /**
     * @param  array  $archivos  [['ruta' => absoluta, 'nombre' => nombre con el que se guarda], …]
     * @return array{guardadas: string[], repetidas: string[], rechazadas: string[]}
     */
    public static function importar(string $cliente, array $archivos): array
    {
        $res = ['guardadas' => [], 'repetidas' => [], 'rechazadas' => []];
        abort_unless(array_key_exists($cliente, self::clientes()), 403);
        $entrada = self::dirEntrada($cliente);
        @mkdir($entrada, 0775, true);
        $conocidos = self::idsConocidos($cliente);
        foreach (glob($entrada.'/*') ?: [] as $f) {
            if (is_file($f) && preg_match('/\.pdf$/i', $f)) {
                $conocidos[substr(sha1_file($f), 0, 12)] = true;
            }
        }
        foreach ($archivos as $a) {
            $nombre = preg_replace('/[\\\\\/:*?"<>|]+/', '_', (string) $a['nombre']);
            $ruta = (string) $a['ruta'];
            if (! is_file($ruta) || ! preg_match('/\.pdf$/i', $nombre) || ! str_starts_with((string) @file_get_contents($ruta, false, null, 0, 5), '%PDF')) {
                $res['rechazadas'][] = $nombre;

                continue;
            }
            $id = substr(sha1_file($ruta), 0, 12);
            if (isset($conocidos[$id])) {
                $res['repetidas'][] = $nombre;

                continue;
            }
            $destino = $entrada.'/'.$nombre;
            if (file_exists($destino)) {
                $destino = $entrada.'/'.pathinfo($nombre, PATHINFO_FILENAME).'_'.$id.'.pdf';
            }
            copy($ruta, $destino);
            $conocidos[$id] = true;
            $res['guardadas'][] = basename($destino);
        }

        return $res;
    }

    public static function resumen(array $r): string
    {
        return count($r['guardadas']).' guardada(s) en Entrada'
            .($r['repetidas'] ? ', '.count($r['repetidas']).' ya estaban' : '')
            .($r['rechazadas'] ? ', '.count($r['rechazadas']).' no válidas ('.implode(', ', array_slice($r['rechazadas'], 0, 3)).')' : '').'.';
    }
}
