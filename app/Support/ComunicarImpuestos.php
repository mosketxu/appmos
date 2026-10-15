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

    /** Copia fija de los correos de impuestos (decisión de Alex, 15-oct-2026). */
    public const CC_DEFECTO = 'marta.ruiz@sumaempresa.com';

    /**
     * Para y CC por defecto de un cliente: Para = Email Adm de la ficha (si no hay, Email Gral) sin las direcciones de Suma;
     * CC = marta.ruiz@ y las de Suma que hubiera en la ficha. Devuelve [para, cc] como texto «a; b».
     */
    public static function destinatariosDefecto(object $ent): array
    {
        $dirs = self::direcciones((string) ($ent->emailadm ?? '')) ?: self::direcciones((string) ($ent->emailgral ?? ''));
        $suma = fn ($d) => str_ends_with(mb_strtolower($d), '@sumaempresa.com');
        $para = array_values(array_unique(array_filter($dirs, fn ($d) => ! $suma($d))));
        $cc = array_values(array_unique(array_merge([self::CC_DEFECTO], array_filter($dirs, $suma))));
        return [implode('; ', $para), implode('; ', $cc)];
    }

    /**
     * Nombre de pila del contacto del cliente para el saludo: el contacto de la ficha (Contactos) cuyo correo está en $para; si no,
     * el de departamento Contabilidad; si no, el único que tenga. Si no hay contactos y es persona física (NIF de persona), su
     * propio nombre. '' si no se sabe (el saludo queda «Hola:» / «Dear all,»).
     */
    public static function contacto(object $ent, array $para): string
    {
        $norm = fn ($t) => mb_strtolower(\Illuminate\Support\Str::ascii(trim((string) $t)));
        // los de Suma (usuarios de Appmos) no son contacto del cliente aunque estén en su ficha
        $deSuma = \Illuminate\Support\Facades\DB::table('users')->pluck('name')->map($norm)->filter(fn ($n) => str_contains($n, ' '))->all();
        $cs = \Illuminate\Support\Facades\DB::table('contacto_entidades as ce')->join('entidades as c', 'c.id', '=', 'ce.contacto_id')
            ->where('ce.entidad_id', $ent->id)->whereNull('c.deleted_at')->get(['c.entidad', 'c.nif', 'c.emailgral', 'c.emailadm', 'ce.departamento'])
            // solo personas: fuera sociedades (CIF o S.L., S.A., banco…)
            ->reject(fn ($c) => preg_match('/^[A-HJNPQRSUVW]\d/i', (string) $c->nif)
                || preg_match('/\b(S\.?\s?L\.?\s?U?\.?|S\.?\s?A\.?\s?U?\.?|A\.S\.|z\.?o\.?o\.?|banco|bank|caixa)$|\bbanco\b|\bbank\b/iu', trim((string) $c->entidad)))
            ->reject(fn ($c) => collect($deSuma)->contains(fn ($n) => str_starts_with($norm($c->entidad), $n))
                || collect(self::direcciones($c->emailgral.' '.$c->emailadm))->contains(fn ($d) => str_ends_with(mb_strtolower($d), '@sumaempresa.com')))
            ->values();
        $para = array_map('mb_strtolower', $para);
        $mails = fn ($c) => array_map('mb_strtolower', self::direcciones($c->emailgral.' '.$c->emailadm));
        $c = $cs->first(fn ($c) => array_intersect($para, $mails($c)))
            ?? $cs->first(fn ($c) => $norm($c->departamento) === 'contabilidad')
            // el único contacto, salvo que el correo vaya a otra dirección que no es la suya
            ?? ($cs->count() === 1 && (! $para || ! $mails($cs->first())) ? $cs->first() : null);
        $nombre = $c->entidad ?? (! $cs->count() && preg_match('/^[0-9XYZKLM]/i', (string) $ent->nif) ? $ent->entidad : '');
        if (str_contains($nombre, ',')) {   // «APELLIDO, Nombre»
            $nombre = trim(explode(',', $nombre, 2)[1]);
        }
        $pila = preg_split('/\s+/', trim((string) $nombre))[0] ?? '';
        return $pila !== '' && mb_strtoupper($pila) === $pila ? mb_convert_case($pila, MB_CASE_TITLE) : $pila;
    }

    /** Pone el contacto en el saludo; sin contacto: «Hola:» / «Dear all,». */
    public static function saludo(string $texto, string $contacto, string $idioma): string
    {
        $t = str_replace('{contacto}', $contacto !== '' ? $contacto : ($idioma === 'EN' ? 'all' : ''), $texto);
        return preg_replace('/\b(Hola|Hello|Hi) +([:,])/u', '$1$2', $t);
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

    public const TIPOS = ['pagar' => 'A pagar', 'compensar' => 'A compensar', 'devolver' => 'A devolver'];
    public const TIPOS_EN = ['pagar' => 'To pay', 'compensar' => 'To be offset', 'devolver' => 'To be refunded'];

    /**
     * Tabla del correo (como la de «August Taxes»). $filas = [[texto, importe, 'dd/mm/aaaa', tipo]]; tipo pagar | compensar | devolver.
     * Columna «A pagar / A compensar…» (15-oct-2026); solo las de pagar llevan día de cargo.
     */
    public static function tabla(array $filas, string $titulo, string $idioma, string $banco = ''): string
    {
        $td = 'padding:0 4.7px;';
        $tdb = $td.'border:1px solid #000;';
        $en = $idioma === 'EN';
        $h = '<table style="border-collapse:collapse;font-family:Aptos Narrow,Calibri,Arial;font-size:11pt">';
        if ($banco !== '') {
            $h .= '<tr><td style="'.$td.'"></td><td style="'.$td.'"></td><td style="'.$td.'"></td><td style="'.$tdb.'"><b>'.e($banco).'</b></td></tr>';
        }
        $h .= '<tr><td style="'.$tdb.'"><b>'.e($titulo).'</b></td><td style="'.$tdb.'"><b>'.($en ? 'Amount' : 'Importe').'</b></td>'
            .'<td style="'.$tdb.'"><b>'.($en ? 'Result' : 'Resultado').'</b></td>'
            .'<td style="'.$tdb.'"><b>'.($en ? 'Payment day' : 'Día de cargo').'</b></td></tr>';
        foreach ($filas as [$texto, $imp, $fecha, $tipo]) {
            $tipo = isset(self::TIPOS[$tipo]) ? $tipo : 'pagar';
            $h .= '<tr><td style="'.$tdb.'">'.e($texto).'</td><td style="'.$tdb.'text-align:right">'.self::eur(abs($imp)).'</td>'
                .'<td style="'.$tdb.'">'.e(($en ? self::TIPOS_EN : self::TIPOS)[$tipo]).'</td>'
                .'<td style="'.$tdb.'text-align:right">'.($tipo === 'pagar' ? e($fecha) : '').'</td></tr>';
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
     * Importe del 303 de una casilla: del PDF presentado (casilla 71, leer_303.py) o, si aún no está, del Excel de la casilla (libro de IVA 📒:
     * fila «RESULTADO FINAL», o «IVA Pagar» del Excel de SAGE), y después del PDF borrador. Devuelve [importe ≥ 0, de dónde, tipo] o null;
     * tipo = pagar | compensar | devolver (por el texto que acompaña al resultado o por el signo).
     */
    public static function buscar303(int $entidadId, int $ejercicio, string $periodo): ?array
    {
        $docs = \App\Models\ImpuestoDocumento::where('entidad_id', $entidadId)->where('modelo', '303')->where('ejercicio', $ejercicio)
            ->where('periodo', $periodo)->whereNull('quitado_at')->orderByDesc('id')->get();
        $esPdf = fn ($d) => (bool) preg_match('/\.pdf$/i', $d->nombre);
        $orden = fn ($d) => $esPdf($d) ? ($d->tipo === 'presentado' ? 0 : 2) : 1;
        foreach ($docs->sortBy($orden) as $d) {
            $ruta = storage_path('app/'.$d->almacen);
            if (! is_file($ruta)) {
                continue;
            }
            $r = $esPdf($d) ? self::leerPdf303($ruta) : self::leerExcel303($ruta);
            if ($r) {
                [$v, $tipo, $donde] = $r;
                return [round(abs($v), 2), ($esPdf($d) ? 'PDF' : 'Excel').' «'.$d->nombre.'» ('.$donde.')', $tipo];
            }
        }
        return null;
    }

    /**
     * Importe de cualquier impuesto de la casilla (15-oct-2026): el 303 como buscar303; el resto, del PDF presentado, del Excel de la
     * casilla (listado de retenciones de SAGE: suma de «ImporteRetencion»; Excel resumen del M111: «Resultado a ingresar») o del PDF
     * borrador (leer_resultado_aeat.py: el importe que se repite). Devuelve [importe ≥ 0, de dónde, tipo] o null.
     */
    public static function buscarImporte(int $entidadId, int $ejercicio, string $codigo, string $periodo, string $etiqueta = ''): ?array
    {
        if ($codigo === '303') {
            return self::buscar303($entidadId, $ejercicio, $periodo);
        }
        $docs = \App\Models\ImpuestoDocumento::where(['entidad_id' => $entidadId, 'modelo' => $codigo, 'ejercicio' => $ejercicio, 'periodo' => $periodo])
            ->where('etiqueta', $etiqueta)->whereNull('quitado_at')->orderByDesc('id')->get();
        $esPdf = fn ($d) => (bool) preg_match('/\.pdf$/i', $d->nombre);
        $orden = fn ($d) => $esPdf($d) ? ($d->tipo === 'presentado' ? 0 : 2) : 1;
        foreach ($docs->sortBy($orden) as $d) {
            $ruta = storage_path('app/'.$d->almacen);
            if (! is_file($ruta)) {
                continue;
            }
            $r = $esPdf($d) ? self::leerPdfAeat($ruta) : self::leerExcelImporte($ruta);
            if ($r !== null) {
                [$v, $donde] = $r;
                return [round(abs($v), 2), ($esPdf($d) ? 'PDF' : 'Excel').' «'.$d->nombre.'» ('.$donde.')', $v < 0 ? 'compensar' : 'pagar'];
            }
        }
        return null;
    }

    protected static function leerPdfAeat(string $ruta): ?array
    {
        $py = storage_path('app/venv-facturasocr/bin/python');
        $r = \Illuminate\Support\Facades\Process::timeout(60)->run([is_executable($py) ? $py : 'python3', resource_path('python/leer_resultado_aeat.py'), $ruta]);
        $j = json_decode(trim($r->output()), true);
        return is_array($j) && isset($j['resultado']) ? [(float) $j['resultado'], 'resultado del formulario'] : null;
    }

    /** Excel de la casilla: listado de retenciones (columna ImporteRetencion, se suma) o una fila «Resultado a ingresar» / «A ingresar» / «Total». */
    protected static function leerExcelImporte(string $ruta): ?array
    {
        try {
            $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($libro->getAllSheets() as $hoja) {
            $filas = array_map('array_values', $hoja->toArray(null, true, false, false));
            foreach ($filas as $i => $fila) {
                $col = array_search('importeretencion', array_map(fn ($c) => mb_strtolower(preg_replace('/\s+/', '', (string) $c)), $fila), true);
                if ($col !== false) {
                    $suma = 0.0;
                    $n = 0;
                    foreach (array_slice($filas, $i + 1) as $f) {
                        if (is_numeric($f[$col] ?? null)) {
                            $suma += (float) $f[$col];
                            $n++;
                        }
                    }
                    if ($n) {
                        return [round($suma, 2), "suma de ImporteRetencion, {$n} líneas"];
                    }
                }
            }
            foreach ($filas as $fila) {
                foreach ($fila as $k => $c) {
                    $etq = trim(str_replace("\u{a0}", ' ', (string) $c));
                    if (! preg_match('/^(resultado a ingresar|a ingresar|importe a ingresar|resultado final)\b/i', $etq)) {
                        continue;
                    }
                    for ($j = $k + 1; $j < count($fila); $j++) {
                        if (is_numeric($fila[$j])) {
                            return [(float) $fila[$j], '«'.$etq.'»'];
                        }
                    }
                }
            }
        }
        return null;
    }

    protected static function tipoDe(float $v, string $texto = ''): string
    {
        $t = mb_strtolower($texto);
        return match (true) {
            str_contains($t, 'devol') => 'devolver',
            str_contains($t, 'compens') => 'compensar',
            str_contains($t, 'pagar') || str_contains($t, 'ingresar') => 'pagar',
            default => $v < 0 ? 'compensar' : 'pagar',
        };
    }

    protected static function leerPdf303(string $ruta): ?array
    {
        $script = rtrim((string) config('contabilidad.facturasocr_dir'), '/').'/leer_303.py';
        if (! is_file($script)) {
            return null;
        }
        $py = storage_path('app/venv-facturasocr/bin/python');
        $r = \Illuminate\Support\Facades\Process::timeout(60)->run([is_executable($py) ? $py : 'python3', $script, $ruta]);
        $j = json_decode(trim($r->output()), true);
        if (! is_array($j) || ! isset($j['resultado'])) {
            return null;
        }
        $v = (float) $j['resultado'];
        $tipo = $v >= 0 ? 'pagar' : ((float) ($j['casillas']['73'] ?? 0) > 0 ? 'devolver' : 'compensar');
        return [$v, $tipo, 'casilla 71'];
    }

    /** Fila «RESULTADO FINAL» / «IVA Pagar»: primer número a su derecha y, si lo hay, el texto que lo acompaña («A PAGAR», «A COMPENSAR»…). */
    protected static function leerExcel303(string $ruta): ?array
    {
        try {
            $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($libro->getAllSheets() as $hoja) {
            // toArray (no el iterador de celdas: sus objetos Cell se desconectan al guardarlos y dan NULL)
            foreach ($hoja->toArray(null, true, false, false) as $fila) {
                $fila = array_values($fila);
                foreach ($fila as $i => $c) {
                    $etq = trim(str_replace("\u{a0}", ' ', (string) $c));
                    if (! preg_match('/^(RESULTADO FINAL|IVA Pagar)$/i', $etq)) {
                        continue;
                    }
                    for ($k = $i + 1; $k < count($fila); $k++) {
                        $n = $fila[$k];
                        if (is_numeric($n)) {
                            $texto = '';
                            for ($j = $k + 1; $j < count($fila); $j++) {
                                if (is_string($fila[$j]) && trim($fila[$j]) !== '') {
                                    $texto = trim($fila[$j]);
                                    break;
                                }
                            }
                            return [(float) $n, self::tipoDe((float) $n, $texto), '«'.$etq.'»'.($texto !== '' ? ' '.$texto : '')];
                        }
                    }
                }
            }
        }
        return null;
    }
}
