<?php

namespace App\Support;

/**
 * Comunicar impuestos al cliente (Impuestos → Seguimiento, 15-oct-2026): correo con el resultado de los impuestos de un periodo y el día
 * en que se cargarán (domiciliación = fin de plazo, App\Support\PlazosAeat), en el idioma del cliente (ES/EN). Solo se deja como
 * BORRADOR en Outlook: nunca se envía desde Appmos. Modelo del correo: «August Taxes» de Fashion IQ.
 */
class ComunicarImpuestos
{
    /** Modelos que llevan pago (los informativos 349, 390, 190… no se comunican). */
    public const CON_PAGO = ['303', '111', '115', '123', '130', '202', '200', '210', '216'];

    public const DESCR = [
        'ES' => ['303' => 'IVA', '111' => 'Retenciones trabajo y profesionales', '115' => 'Retenciones alquileres', '123' => 'Retenciones capital mobiliario',
            '130' => 'Pago fraccionado IRPF', '202' => 'Pago a cuenta del Impuesto sobre Sociedades', '200' => 'Impuesto sobre Sociedades',
            '210' => 'IRNR (no residentes)', '216' => 'Retenciones no residentes'],
        'EN' => ['303' => 'VAT', '111' => 'Withholding payrolls', '115' => 'Withholding rent', '123' => 'Withholding capital income',
            '130' => 'Personal income tax instalment', '202' => 'Corporate tax instalment', '200' => 'Corporate income tax',
            '210' => 'Non-resident income tax', '216' => 'Withholding non-residents'],
    ];

    public const MESES = [
        'ES' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'EN' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    public static function idioma(?string $i): string
    {
        return strtoupper((string) $i) === 'EN' ? 'EN' : 'ES';
    }

    /** Texto de un periodo: '09' → «septiembre de 2026» / «September 2026»; 'T3' → «3.er trimestre de 2026» / «Q3 2026»; 'P2' → «2.º pago fraccionado de 2026»; 'A' → «ejercicio 2025». */
    public static function periodo(string $per, int $anio, string $idioma): string
    {
        $en = $idioma === 'EN';
        if (ctype_digit($per)) {
            $m = self::MESES[$idioma][(int) $per - 1];
            return $en ? "$m $anio" : "$m de $anio";
        }
        $n = (int) substr($per, 1);
        return match ($per[0]) {
            'T' => $en ? "Q$n $anio" : ['1.er', '2.º', '3.er', '4.º'][$n - 1]." trimestre de $anio",
            'P' => $en ? ['1st', '2nd', '3rd'][$n - 1]." instalment $anio" : ['1.er', '2.º', '3.er'][$n - 1]." pago fraccionado de $anio",
            default => $en ? "fiscal year $anio" : "ejercicio $anio",
        };
    }

    public static function importe(string $raw): ?float
    {
        $s = str_replace([' ', '€'], '', trim($raw));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        return is_numeric($s) ? round((float) $s, 2) : null;
    }

    /** «a@b.com; c@d.com» → [a@b.com, c@d.com]. */
    public static function direcciones(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', $raw))));
    }

    public static function eur(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    /** Tabla del correo (como la de «August Taxes»). $filas = [[texto, importe, 'dd/mm/aaaa']]. */
    public static function tabla(array $filas, string $titulo, string $idioma, string $banco = ''): string
    {
        $td = 'padding:0 4.7px;';
        $tdb = $td.'border:1px solid #000;';
        $en = $idioma === 'EN';
        $h = '<table style="border-collapse:collapse;font-family:Aptos Narrow,Calibri,Arial;font-size:11pt">';
        if ($banco !== '') {
            $h .= '<tr><td style="'.$td.'"></td><td style="'.$td.'"></td><td style="'.$tdb.'"><b>'.e($banco).'</b></td></tr>';
        }
        $h .= '<tr><td style="'.$tdb.'"><b>'.e($titulo).'</b></td><td style="'.$tdb.'"><b>'.($en ? 'Amount' : 'Importe').'</b></td>'
            .'<td style="'.$tdb.'"><b>'.($en ? 'Payment day' : 'Día de cargo').'</b></td></tr>';
        foreach ($filas as [$texto, $imp, $fecha]) {
            $dia = $imp > 0 ? $fecha : ($en ? 'No charge' : 'Sin cargo');
            $h .= '<tr><td style="'.$tdb.'">'.e($texto).'</td><td style="'.$tdb.'text-align:right">'.self::eur($imp).'</td>'
                .'<td style="'.$tdb.'text-align:right">'.e($dia).'</td></tr>';
        }
        return $h.'</table>';
    }

    /** Cuerpo HTML: el texto línea a línea; {tabla} es la tabla y {logo} el logo de Suma (cid:logo_suma). */
    public static function html(string $texto, string $tabla): string
    {
        $p = '<p style="margin:0">';
        $logo = '<img src="cid:logo_suma" alt="Suma Apoyo Empresarial S.L." width="118" height="71" style="display:block;margin-top:6px;border:0;">';
        $out = '';
        foreach (preg_split('/\r?\n/', $texto) as $l) {
            if (trim($l) === '{tabla}') {
                $out .= $tabla;
            } elseif (trim($l) === '{logo}') {
                $out .= $logo;
            } else {
                $l = e($l);
                $l = preg_replace('/www\.sumaempresa\.com/', '<a href="https://www.sumaempresa.com">www.sumaempresa.com</a>', $l);
                $out .= $p.($l === '' ? '&nbsp;' : $l).'</p>';
            }
        }
        return '<div style="font-family:Calibri,Arial,sans-serif;font-size:11pt">'.$out.'</div>';
    }

    /**
     * Importe del 303 de una casilla: del PDF (borrador o presentado, casilla 71, con leer_303.py) o del libro de IVA (Excel 📒:
     * celda a la derecha de «RESULTADO FINAL» / «IVA Pagar»). Devuelve [importe, de dónde] o null.
     */
    public static function buscar303(int $entidadId, int $ejercicio, string $periodo): ?array
    {
        $docs = \App\Models\ImpuestoDocumento::where('entidad_id', $entidadId)->where('modelo', '303')->where('ejercicio', $ejercicio)
            ->where('periodo', $periodo)->whereNull('quitado_at')->orderByDesc('id')->get();
        $script = rtrim((string) config('contabilidad.facturasocr_dir'), '/').'/leer_303.py';
        $py = storage_path('app/venv-facturasocr/bin/python');
        foreach ($docs->sortBy(fn ($d) => $d->tipo === 'presentado' ? 0 : 1) as $d) {
            $ruta = storage_path('app/'.$d->almacen);
            if (! is_file($ruta)) {
                continue;
            }
            if (preg_match('/\.pdf$/i', $d->nombre) && is_file($script)) {
                $r = \Illuminate\Support\Facades\Process::timeout(60)->run([is_executable($py) ? $py : 'python3', $script, $ruta]);
                $j = json_decode(trim($r->output()), true);
                if (is_array($j) && isset($j['resultado'])) {
                    return [round((float) $j['resultado'], 2), 'PDF «'.$d->nombre.'» (casilla 71)'];
                }
            } elseif (preg_match('/\.xlsx?$/i', $d->nombre)) {
                try {
                    $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
                    foreach ($libro->getAllSheets() as $hoja) {
                        foreach ($hoja->getRowIterator() as $fila) {
                            $cels = iterator_to_array($fila->getCellIterator());
                            $vals = array_values($cels);
                            foreach ($vals as $i => $c) {
                                $v = trim((string) $c->getValue());
                                if (preg_match('/^(RESULTADO FINAL|IVA Pagar)$/i', $v)) {
                                    for ($k = $i + 1; $k < count($vals); $k++) {
                                        $n = $vals[$k]->getCalculatedValue();
                                        if (is_numeric($n)) {
                                            return [round((float) $n, 2), 'Excel «'.$d->nombre.'» («'.$v.'»)'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        return null;
    }
}
