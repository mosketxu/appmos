<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Facturación intercompañía de Fashion IQ (Breuninger y OMC → CdC Switzerland).
 * Reglas del Excel «_Facturacion Breuninger OMC.xlsx» (hoja Cuentas/Stores) que Appmos sustituye (18-nov-2026).
 */
class FiqIc
{
    public const RECARGO = 0.05;

    /** Proveedor (Plein) → empresa, nombre, gasto (compra), cliente y ventas (contrapartida del cliente). */
    public const CUENTAS = [
        4000003 => ['empresa' => 'Breuninger', 'nombre' => 'P.Plein Int AG (PL) Breuninger', 'origen' => 'PPI', 'gasto' => 6000005, 'cliente' => 4300007, 'cliente_nombre' => 'CdC Switzerland GmbH receivables Breuninger', 'ventas' => 7000005],
        4000004 => ['empresa' => 'Breuninger', 'nombre' => 'Plein Sport AG (PL) Breuninger', 'origen' => 'PS', 'gasto' => 6000005, 'cliente' => 4300007, 'cliente_nombre' => 'CdC Switzerland GmbH receivables Breuninger', 'ventas' => 7000005],
        4000005 => ['empresa' => 'OMC', 'nombre' => 'P.Plein Int AG (PL) OMC', 'origen' => 'PPI', 'gasto' => 6000006, 'cliente' => 4300008, 'cliente_nombre' => 'CdC Switzerland GmbH receivables OMC', 'ventas' => 7000006],
        4000006 => ['empresa' => 'OMC', 'nombre' => 'Plein Sport AG (PL) OMC', 'origen' => 'PS', 'gasto' => 6000006, 'cliente' => 4300008, 'cliente_nombre' => 'CdC Switzerland GmbH receivables OMC', 'ventas' => 7000006],
        4000007 => ['empresa' => 'OMC', 'nombre' => 'Billionaire Int AG (PL) OMC', 'origen' => 'BI', 'gasto' => 6000006, 'cliente' => 4300008, 'cliente_nombre' => 'Billionaire International AG receivables OMC', 'ventas' => 7000006],
    ];

    /** Canal → tienda (hoja Stores). */
    public const TIENDAS = [0 => 'BCN', 2 => 'LRV PPI', 3 => 'ROZ', 4 => 'ORT', 5 => 'MAR', 6 => 'MAL', 10 => 'LRV PS'];

    public static function empresas(): array
    {
        return ['Breuninger', 'OMC'];
    }

    public static function cuentasDe(string $empresa): array
    {
        return array_filter(self::CUENTAS, fn ($c) => $c['empresa'] === $empresa);
    }

    /** Serie del año: BR26 / OMC26. */
    public static function serie(string $empresa, int $anio): string
    {
        return ($empresa === 'OMC' ? 'OMC' : 'BR').substr((string) $anio, -2);
    }

    /** Siguiente nº de factura de la serie (el mayor ya emitido + 1). */
    public static function siguienteNumero(string $empresa, int $anio): int
    {
        return 1 + (int) DB::table('fiq_ic_emisiones')->where('empresa', $empresa)
            ->where('serie', self::serie($empresa, $anio))->where('tipo', 'factura')->max('numero');
    }

    /** «BR26/00012» (como en el PDF) a partir de serie y número. */
    public static function etiqueta(string $serie, ?int $numero): string
    {
        return $numero === null ? $serie.' (abono)' : $serie.'/'.str_pad((string) $numero, 5, '0', STR_PAD_LEFT);
    }

    /** Importe + 5 %, en céntimos para no arrastrar decimales. */
    public static function conRecargo(float $importe): float
    {
        return round($importe * (1 + self::RECARGO), 2);
    }

    /** Pasa «1.234,56», «1234.56» o «-12,5» a float; null si no es un número. */
    public static function numero(string $txt): ?float
    {
        $t = trim(str_replace([' ', '€'], '', $txt));
        if ($t === '' || ! preg_match('/^-?[\d.,]+$/', $t)) {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace(',', '.', str_replace('.', '', $t));
        } elseif (substr_count($t, '.') > 1) {
            $t = str_replace('.', '', $t);
        }

        return is_numeric($t) ? round((float) $t, 2) : null;
    }

    /**
     * Total de la factura y desglose por tienda. El total es el recargo sobre la SUMA (no línea a línea);
     * el reparto por tienda se redondea y el desfase de céntimos se carga a la tienda mayor, para que cuadre.
     * @param  iterable<object|array>  $lineas  con canal e importe
     * @return array{base: float, recargo: float, total: float, tiendas: array<int, array{canal:int,tienda:string,n:int,base:float,recargo:float,total:float}>}
     */
    public static function resumen(iterable $lineas): array
    {
        $por = [];
        foreach ($lineas as $l) {
            $l = (object) $l;
            $c = (int) $l->canal;
            $por[$c] ??= ['canal' => $c, 'tienda' => self::TIENDAS[$c] ?? "canal {$c}", 'n' => 0, 'base' => 0.0];
            $por[$c]['n']++;
            $por[$c]['base'] = round($por[$c]['base'] + (float) $l->importe, 2);
        }
        ksort($por);
        $base = round(array_sum(array_column($por, 'base')), 2);
        $total = self::conRecargo($base);
        $suma = 0.0;
        foreach ($por as $c => &$t) {
            $t['total'] = self::conRecargo($t['base']);
            $suma = round($suma + $t['total'], 2);
        }
        unset($t);
        $dif = round($total - $suma, 2);
        if ($por && abs($dif) > 0.0001) {
            $mayor = array_keys($por, collect($por)->sortByDesc(fn ($t) => abs($t['base']))->first())[0];
            $por[$mayor]['total'] = round($por[$mayor]['total'] + $dif, 2);
        }
        foreach ($por as &$t) {
            $t['recargo'] = round($t['total'] - $t['base'], 2);
        }
        unset($t);

        return ['base' => $base, 'recargo' => round($total - $base, 2), 'total' => $total, 'tiendas' => $por];
    }

    /** Todas las cuentas del mayor que toca la facturación intercompañía (proveedores, gasto, clientes y ventas). */
    public static function cuentasMayor(): array
    {
        $c = [];
        foreach (self::CUENTAS as $cod => $x) {
            array_push($c, (string) $cod, (string) $x['gasto'], (string) $x['cliente'], (string) $x['ventas']);
        }
        $c[] = '6000000';

        return array_values(array_unique($c));
    }
}
