<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Validación de la facturación Breuninger/OMC contra el mayor de SAGE (18-nov-2026): lo que hay en Appmos (compras de Plein y
 * facturas de venta emitidas) tiene que estar contabilizado igual en el mayor: nº, importe, tienda (Cód. canal) y cuentas.
 */
class FiqIcValidacion
{
    public const TOL = 0.011;     // igual
    public const TOL_TIENDA = 0.101;  // reparto por tienda: SAGE redondea línea a línea
    public const TOL_CENT = 0.5;  // diferencia de céntimos (ajustes a mano): se avisa, no es un error de fondo

    /** @param array<int,array> $filas filas del mayor (MayorLector::leer) */
    public static function validar(int $anio, int $mes, array $filas, ?string $maxFecha): array
    {
        $periodo = sprintf('%d-%02d', $anio, $mes);
        $res = ['periodo' => $periodo, 'max_fecha' => $maxFecha, 'aviso_mayor' => null, 'empresas' => [], 'problemas' => 0];
        $finMes = date('Y-m-t', strtotime($periodo.'-01'));
        if ($maxFecha && $maxFecha < $finMes) {
            $res['aviso_mayor'] = 'El mayor llega solo hasta el '.date('d/m/Y', strtotime($maxFecha)).': los asientos posteriores a esa fecha figurarán como «sin contabilizar». Sube un mayor más reciente en Ficheros base.';
        }
        $porAsiento = [];
        foreach ($filas as $f) {
            $porAsiento[$f['asiento'].'|'.$f['fecha']][] = $f;
        }
        $todasCompras = DB::table('fiq_ic_compras')->get();
        foreach (FiqIc::empresas() as $emp) {
            $r = self::empresa($emp, $periodo, $filas, $porAsiento, $todasCompras);
            $res['empresas'][$emp] = $r;
            $res['problemas'] += $r['problemas'];
        }

        return $res;
    }

    protected static function empresa(string $emp, string $periodo, array $filas, array $porAsiento, $todasCompras): array
    {
        $cuentas = FiqIc::cuentasDe($emp);
        $prov = array_map('strval', array_keys($cuentas));
        $problemas = 0;

        // ── Compras ───────────────────────────────────────────────────────────────
        $filasProv = array_values(array_filter($filas, fn ($f) => in_array($f['cuenta'], $prov, true)));
        $usadas = [];
        $compras = [];
        // Las líneas de un abono (reversión de una factura) o de una factura «hecha mal» repiten compras que se contabilizaron una sola vez: no se validan.
        $anuladas = DB::table('fiq_ic_emisiones')->where('empresa', $emp)->where(fn ($q) => $q->where('tipo', 'abono')->orWhere('nota', 'like', '%hizo mal%'))->pluck('id')->all();
        $lineas = DB::table('fiq_ic_compras')->where('empresa', $emp)->where('periodo', $periodo)->orderBy('cuenta')->orderBy('id')->get()
            ->reject(fn ($l) => in_array($l->emision_id, $anuladas, true))->values();
        $importesAsiento = [];   // asiento|fecha → suma de lo apuntado en Appmos (varias facturas de Plein pueden ir en un asiento)
        foreach ($lineas as $l) {
            $dig = self::digitos($l->numero);
            $cand = [];
            foreach ($filasProv as $i => $f) {
                if (isset($usadas[$i]) || $f['cuenta'] !== (string) $l->cuenta) {
                    continue;
                }
                if ($dig !== '' && self::digitos($f['factura'] !== '' ? $f['factura'] : $f['comentario']) === $dig) {
                    $cand[] = $i;
                }
            }
            $item = ['linea' => $l, 'estado' => 'ok', 'detalle' => [], 'mayor_asiento' => null, 'mayor_importe' => null];
            if (! $cand) {
                $item['estado'] = 'falta';
                $item['detalle'][] = 'No está en el mayor (cuenta '.$l->cuenta.').';
            } else {
                // el candidato con el mismo importe; si no, el primero
                $sel = collect($cand)->first(fn ($i) => abs(round($filasProv[$i]['haber'] - $filasProv[$i]['debe'], 2) - (float) $l->importe) <= self::TOL) ?? $cand[0];
                $usadas[$sel] = true;
                $f = $filasProv[$sel];
                $imp = round($f['haber'] - $f['debe'], 2);
                $item['mayor_asiento'] = $f['asiento'];
                $item['mayor_importe'] = $imp;
                $item['_k'] = $f['asiento'].'|'.$f['fecha'];
                $importesAsiento[$item['_k']] = round(($importesAsiento[$item['_k']] ?? 0) + (float) $l->importe, 2);
                $item['_imp'] = $imp;
                $item['_gasto'] = (string) $cuentas[(int) $l->cuenta]['gasto'];
                $item['_contra'] = $f['contra'];
                $d = abs($imp - (float) $l->importe);
                if ($d > self::TOL) {
                    $item['estado'] = $d <= self::TOL_CENT ? 'cent' : 'importe';
                    $item['detalle'][] = 'Importe en el mayor '.self::eur($imp).' (Appmos '.self::eur((float) $l->importe).').';
                }
                if ($f['canal'] !== null && $f['canal'] !== (int) $l->canal) {
                    $item['estado'] = $item['estado'] === 'ok' ? 'tienda' : $item['estado'];
                    $item['detalle'][] = 'Tienda en el mayor: '.(FiqIc::TIENDAS[$f['canal']] ?? $f['canal']).' (Appmos '.(FiqIc::TIENDAS[$l->canal] ?? $l->canal).').';
                }
            }
            $item['_contado'] = $item['estado'] !== 'ok';
            if ($item['_contado']) {
                $problemas++;
            }
            $compras[] = $item;
        }
        foreach ($compras as &$c) {   // gasto del asiento = suma de las facturas de Plein apuntadas en él
            if (empty($c['_k'])) {
                continue;
            }
            $filasAs = $porAsiento[$c['_k']] ?? [];
            $enGasto = array_filter($filasAs, fn ($x) => $x['cuenta'] === $c['_gasto']);
            if (! $enGasto) {
                $c['estado'] = in_array($c['estado'], ['ok', 'tienda'], true) ? 'gasto' : $c['estado'];
                $c['detalle'][] = 'La contrapartida es '.($c['_contra'] !== '' ? $c['_contra'] : 'otra cuenta').' en vez de '.$c['_gasto'].'.';
            } else {
                $g = round(array_sum(array_map(fn ($x) => $x['debe'] - $x['haber'], $enGasto)), 2);
                $esperado = round(array_sum(array_map(fn ($x) => $x['haber'] - $x['debe'], array_filter($filasAs, fn ($x) => $x['cuenta'] === (string) $c['linea']->cuenta))), 2);
                if (abs($g - $esperado) > self::TOL) {
                    $c['estado'] = in_array($c['estado'], ['ok', 'tienda'], true) ? 'gasto' : $c['estado'];
                    $c['detalle'][] = 'El gasto '.$c['_gasto'].' del asiento es '.self::eur($g).' y el proveedor '.self::eur($esperado).'.';
                }
            }
            if ($c['estado'] !== 'ok' && ! $c['_contado']) {
                $problemas++;
            }
        }
        unset($c);
        // En el mayor (proveedores de esta empresa, asientos del mes) sin línea en Appmos
        $conocidas = [];
        foreach ($todasCompras as $c) {
            $conocidas[$c->cuenta.'|'.self::digitos($c->numero)] = true;
        }
        $comprasExtra = [];
        foreach ($filasProv as $i => $f) {
            if (isset($usadas[$i]) || ! $f['fecha'] || substr($f['fecha'], 0, 7) !== $periodo) {
                continue;
            }
            if (preg_match('/\d{5,}[a-z]{1,2}\b/i', $f['factura'] !== '' ? $f['factura'] : $f['comentario'])) {
                continue;   // letra al final (b, r, rr…): es una corrección de otra factura
            }
            $dig = self::digitos($f['factura'] !== '' ? $f['factura'] : $f['comentario']);
            if ($dig === '' || isset($conocidas[$f['cuenta'].'|'.$dig])) {
                continue;
            }
            $comprasExtra[] = $f + ['importe' => round($f['haber'] - $f['debe'], 2)];
            $problemas++;
        }

        // ── Ventas ────────────────────────────────────────────────────────────────
        $clientes = array_values(array_unique(array_map(fn ($c) => (string) $c['cliente'], $cuentas)));
        $todasVentas = array_values(array_unique(array_map(fn ($c) => (string) $c['ventas'], FiqIc::CUENTAS)));
        $ventasCtas = array_values(array_unique(array_map(fn ($c) => (string) $c['ventas'], $cuentas)));
        $filasCli = array_values(array_filter($filas, fn ($f) => in_array($f['cuenta'], $clientes, true) && preg_match('/^Inv/i', $f['comentario'])));
        $emisiones = DB::table('fiq_ic_emisiones')->where('empresa', $emp)->where('periodo', $periodo)->orderBy('id')->get();
        $ventas = [];
        $usadasCli = [];
        foreach ($emisiones as $e) {
            $compEm = DB::table('fiq_ic_compras')->where('emision_id', $e->id)->get();
            $esp = FiqIc::resumen($compEm);
            $item = ['emision' => $e, 'etiqueta' => FiqIc::etiqueta($e->serie, $e->numero), 'estado' => 'ok', 'detalle' => [], 'tiendas' => [], 'mayor_total' => null, 'mayor_asiento' => null, 'cuenta_ventas' => []];
            if ($e->numero === null) {
                $item['estado'] = 'na';
                $item['detalle'][] = 'Abono sin número propio: no se valida.';
                $ventas[] = $item;
                continue;
            }
            $enc = null;
            foreach ($filasCli as $i => $f) {
                if (isset($usadasCli[$i])) {
                    continue;
                }
                [$pf, $n] = self::numeroVenta($f);
                if ($n === (int) $e->numero && ($pf === null || $pf === $emp)) {
                    $enc = $i;
                    break;
                }
            }
            if ($enc === null) {
                $item['estado'] = 'falta';
                $item['detalle'][] = 'No está contabilizada en el mayor (cuenta cliente '.implode('/', $clientes).').';
                $problemas++;
                $ventas[] = $item;
                continue;
            }
            $usadasCli[$enc] = true;
            $f = $filasCli[$enc];
            $item['mayor_asiento'] = $f['asiento'];
            $tot = round($f['debe'] - $f['haber'], 2);
            $item['mayor_total'] = $tot;
            $d = abs($tot - (float) $e->total);
            if ($d > self::TOL) {
                $item['estado'] = $d <= self::TOL_CENT ? 'cent' : 'importe';
                $item['detalle'][] = 'Cliente '.$f['cuenta'].': '.self::eur($tot).' en el mayor, factura '.self::eur((float) $e->total).'.';
            }
            // Ventas del mismo asiento, por tienda y cuenta
            $mayorTi = [];
            foreach ($porAsiento[$f['asiento'].'|'.$f['fecha']] ?? [] as $x) {
                if (in_array($x['cuenta'], $todasVentas, true)) {
                    $c = $x['canal'] ?? -1;
                    $mayorTi[$c] = round(($mayorTi[$c] ?? 0) + $x['haber'] - $x['debe'], 2);
                    $item['cuenta_ventas'][$x['cuenta']] = true;
                }
            }
            $malas = array_diff(array_keys($item['cuenta_ventas']), $ventasCtas);
            if ($malas) {
                $item['estado'] = $item['estado'] === 'ok' ? 'cuenta' : $item['estado'];
                $item['detalle'][] = 'Ventas contabilizadas en '.implode(', ', $malas).' en vez de '.implode('/', $ventasCtas).'.';
            }
            if (! $item['cuenta_ventas']) {
                $item['estado'] = $item['estado'] === 'ok' ? 'cuenta' : $item['estado'];
                $item['detalle'][] = 'El asiento no tiene líneas de ventas ('.implode('/', $ventasCtas).').';
            }
            $canales = array_unique(array_merge(array_keys($esp['tiendas']), array_keys($mayorTi)));
            sort($canales);
            foreach ($canales as $c) {
                $a = $esp['tiendas'][$c]['total'] ?? 0.0;
                $m = $mayorTi[$c] ?? 0.0;
                $dd = round($m - $a, 2);
                $est = abs($dd) <= self::TOL_TIENDA ? 'ok' : (abs($dd) <= self::TOL_CENT ? 'cent' : 'mal');   // ≤ 0,10 por tienda: redondeo de céntimos
                $item['tiendas'][$c] = ['tienda' => FiqIc::TIENDAS[$c] ?? ($c < 0 ? 'sin tienda' : "canal {$c}"), 'appmos' => $a, 'mayor' => $m, 'dif' => $dd, 'estado' => $est];
                if ($est !== 'ok' && $item['estado'] === 'ok') {
                    $item['estado'] = $est === 'cent' ? 'cent' : 'tienda';
                }
            }
            if ($item['estado'] !== 'ok') {
                $problemas++;
            }
            $ventas[] = $item;
        }
        // Facturas de venta del mes en el mayor que Appmos no ha emitido
        $ventasExtra = [];
        foreach ($filasCli as $i => $f) {
            if (isset($usadasCli[$i]) || ! $f['fecha'] || substr($f['fecha'], 0, 7) !== $periodo || $f['debe'] <= 0) {
                continue;
            }
            [$pf, $n] = self::numeroVenta($f);
            if ($pf !== null && $pf !== $emp) {
                continue;
            }
            $ya = DB::table('fiq_ic_emisiones')->where('empresa', $emp)->where('numero', $n)->where('serie', FiqIc::serie($emp, (int) substr($periodo, 0, 4)))->exists();
            if ($ya) {
                continue;
            }
            $ventasExtra[] = $f;
            $problemas++;
        }

        return ['compras' => $compras, 'compras_extra' => $comprasExtra, 'ventas' => $ventas, 'ventas_extra' => $ventasExtra, 'problemas' => $problemas,
            'sin_nada' => $lineas->isEmpty() && $emisiones->isEmpty() && ! $comprasExtra && ! $ventasExtra];
    }

    /** Racha de dígitos más larga (≥ 5) de un nº de factura o comentario: «_120526216», «220172528rr», «Inv 120527361 PPI» → 120526216… */
    public static function digitos(string $t): string
    {
        preg_match_all('/\d{5,}/', $t, $m);
        $mejor = '';
        foreach ($m[0] as $x) {
            if (strlen($x) > strlen($mejor)) {
                $mejor = $x;
            }
        }

        return $mejor;
    }

    /** «Inv  OMC26  8  Cream…» → ['OMC', 8]; «Inv BR26 11 …» → ['Breuninger', 11]; sin prefijo, el nº de «Su factura nº.». */
    public static function numeroVenta(array $f): array
    {
        if (preg_match('/\b(BR|OMC|OCM)\s*(?:\d{2})?\s+(\d+)\b/i', $f['comentario'], $m)) {   // «Inv OCM26 5…», «Inv OCM 1…» (la de dic-2025 va sin año)
            return [strtoupper($m[1]) === 'BR' ? 'Breuninger' : 'OMC', (int) $m[2]];
        }

        return [null, ctype_digit($f['factura']) ? (int) $f['factura'] : null];
    }

    public static function eur(float $v): string
    {
        return number_format($v, 2, ',', '.').' €';
    }
}
