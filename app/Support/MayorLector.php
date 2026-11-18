<?php

namespace App\Support;

/**
 * Lector en streaming del mayor de SAGE en columnas (Código cuenta, Fecha asiento, Debe, Haber…): el .xlsx de SAGE pesa 60-70 MB
 * de XML, así que no se carga entero (PhpSpreadsheet agotaría la memoria): se recorre la hoja con XMLReader y solo se guardan
 * las filas de las cuentas pedidas. Vale para el .xlsx de SAGE (prefijo x:, t="str") y para el guardado por Excel (textos compartidos).
 */
class MayorLector
{
    /** Columnas que interesan (cabecera de SAGE → clave). */
    protected const COLS = ['Fecha asiento' => 'fecha', 'Número de asiento' => 'asiento', 'Código cuenta' => 'cuenta', 'Contrapartida' => 'contra',
        'Comentario' => 'comentario', 'Debe' => 'debe', 'Haber' => 'haber', 'Cód. canal' => 'canal', 'Su factura nº.' => 'factura', 'Número de factura' => 'nfactura'];

    /**
     * @param  string[]  $cuentas  códigos de cuenta a quedarse
     * @return array{filas: array<int, array>, max_fecha: ?string, filas_leidas: int}
     */
    public static function leer(string $ruta, array $cuentas): array
    {
        $zip = new \ZipArchive;
        abort_unless($zip->open($ruta) === true, 422, 'No puedo abrir el mayor');
        $hoja = collect(['xl/worksheets/sheet.xml', 'xl/worksheets/sheet1.xml'])->first(fn ($n) => $zip->locateName($n) !== false);
        abort_unless($hoja, 422, 'El mayor no tiene hoja');
        $comp = self::textosCompartidos($zip);
        $zip->close();

        $r = new \XMLReader;
        $r->open('zip://'.$ruta.'#'.$hoja);
        $set = array_flip($cuentas);
        $idx = null;        // índice de columna → clave
        $filas = [];
        $max = null;
        $leidas = 0;
        while ($r->read()) {
            if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'row') {
                continue;
            }
            $celdas = self::fila($r, $comp);
            if ($idx === null) {
                if (in_array('Código cuenta', $celdas, true)) {
                    $idx = [];
                    foreach ($celdas as $i => $t) {
                        if (isset(self::COLS[$t]) && ! in_array(self::COLS[$t], $idx, true)) {
                            $idx[$i] = self::COLS[$t];
                        }
                    }
                }
                continue;
            }
            $leidas++;
            $d = [];
            foreach ($idx as $i => $k) {
                $d[$k] = $celdas[$i] ?? '';
            }
            $f = self::fecha($d['fecha'] ?? '');
            if ($f && ($max === null || $f > $max)) {
                $max = $f;
            }
            $c = trim((string) ($d['cuenta'] ?? ''));
            if (! isset($set[$c])) {
                continue;
            }
            $filas[] = ['fecha' => $f, 'asiento' => trim((string) $d['asiento']), 'cuenta' => $c, 'contra' => trim((string) $d['contra']),
                'comentario' => trim(preg_replace('/\s+/', ' ', (string) $d['comentario'])), 'debe' => round((float) $d['debe'], 2), 'haber' => round((float) $d['haber'], 2),
                'canal' => trim((string) $d['canal']) === '' ? null : (int) $d['canal'], 'factura' => trim((string) $d['factura']), 'nfactura' => trim((string) $d['nfactura'])];
        }
        $r->close();

        return ['filas' => $filas, 'max_fecha' => $max, 'filas_leidas' => $leidas];
    }

    /**
     * Las primeras $n filas con contenido de la primera hoja de un .xlsx, en streaming (sin cargar el libro: el mayor de SAGE pesa
     * ~68 MB de XML y PhpSpreadsheet agota los 128 MB del servidor). Cada fila: [índice de columna => texto].
     * @return array<int, array<int, string>>
     */
    public static function primerasFilas(string $ruta, int $n): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($ruta) !== true) {
            return [];
        }
        $hoja = collect(['xl/worksheets/sheet.xml', 'xl/worksheets/sheet1.xml'])->first(fn ($x) => $zip->locateName($x) !== false);
        $comp = $hoja ? self::textosCompartidos($zip) : [];
        $zip->close();
        if (! $hoja) {
            return [];
        }
        $r = new \XMLReader;
        $r->open('zip://'.$ruta.'#'.$hoja);
        $out = [];
        while (count($out) < $n && $r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'row') {
                $f = self::fila($r, $comp);
                if (array_filter($f, fn ($t) => trim((string) $t) !== '')) {
                    $out[] = $f;
                }
            }
        }
        $r->close();

        return $out;
    }

    /** Celdas de la fila actual: [índice de columna => texto]. */
    protected static function fila(\XMLReader $r, array $comp): array
    {
        $out = [];
        if ($r->isEmptyElement) {
            return $out;
        }
        $prof = $r->depth;
        while ($r->read() && ! ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $prof)) {
            if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'c') {
                continue;
            }
            $ref = (string) $r->getAttribute('r');
            $tipo = (string) $r->getAttribute('t');
            $i = self::columna($ref);
            $txt = '';
            if (! $r->isEmptyElement) {
                $pc = $r->depth;
                while ($r->read() && ! ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $pc)) {
                    if ($r->nodeType === \XMLReader::ELEMENT && in_array($r->localName, ['v', 't'], true) && ! $r->isEmptyElement) {
                        $txt .= $r->readString();
                    }
                }
            }
            $out[$i] = $tipo === 's' ? ($comp[(int) $txt] ?? '') : $txt;
        }

        return $out;
    }

    protected static function columna(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $m);
        $n = 0;
        foreach (str_split($m[0] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    protected static function textosCompartidos(\ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $r = new \XMLReader;
        $r->XML($zip->getFromName('xl/sharedStrings.xml'));
        $out = [];
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'si') {
                $out[] = $r->isEmptyElement ? '' : preg_replace('/<[^>]+>/', '', $r->readInnerXml());
            }
        }

        return array_map(fn ($t) => html_entity_decode($t, ENT_QUOTES | ENT_XML1), $out);
    }

    /** Número de serie de Excel (46023) o dd/mm/aaaa → aaaa-mm-dd. */
    protected static function fecha(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
        }
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
    }
}
