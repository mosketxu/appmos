<?php

namespace App\Support;

use App\Models\Provincia;
use App\Models\Suma;
use DOMDocument;
use DOMXPath;

/**
 * Interpreta el correo «Alta cliente» que manda Marta (4-nov-2026): una tabla de dos columnas (Cliente, NIF, Dirección, CP, Provincia, Cobro,
 * IBAN, Primera factura, Cuota, Importe, mail, SAGE, Equipo…) y debajo unas líneas de texto libre con instrucciones.
 * Devuelve los campos de la entidad que se pueden rellenar, el texto libre (para el historial) y avisos de lo que conviene revisar.
 * No guarda nada: la pantalla «Nuevo cliente» enseña el resultado para que Alex lo corrija antes de crear la entidad.
 */
class AltaClienteCorreo
{
    /** Quita acentos y signos, minúsculas: «Dirección » → «direccion». */
    public static function norm(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', '·' => '']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    public static function nifLimpio(?string $nif): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $nif));
    }

    /** @return array{campos: array, extra: string, avisos: array, nota: array, titulo: string} */
    public static function interpretar(string $html): array
    {
        $filas = [];
        $titulo = '';
        $extra = [];
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xp = new DOMXPath($dom);
        $limpia = fn (string $s) => trim(preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $s)));

        foreach ($xp->query('//tr') as $tr) {
            $celdas = [];
            foreach ($tr->childNodes as $c) {
                if (in_array($c->nodeName, ['td', 'th'], true)) {
                    $celdas[] = $limpia($c->textContent);
                }
            }
            if (count($celdas) === 1 && $titulo === '' && $celdas[0] !== '') {
                $titulo = $celdas[0];
            } elseif (count($celdas) >= 2 && $celdas[0] !== '') {
                $filas[self::norm($celdas[0])] = $celdas[1];
            }
        }

        // Texto libre: los párrafos que hay DESPUÉS de la última tabla, hasta la despedida / firma / RGPD.
        $tablas = $xp->query('//table');
        $ultima = $tablas->length ? $tablas->item($tablas->length - 1) : null;
        $pasada = $ultima === null;
        foreach ($xp->query('//p|//table') as $n) {
            if ($n->nodeName === 'table') {
                $pasada = $n->isSameNode($ultima) ? true : $pasada;
                continue;
            }
            if (! $pasada || self::dentroDeTabla($n)) {
                continue;
            }
            $linea = $limpia($n->textContent);
            if ($linea === '') {
                continue;
            }
            if (preg_match('/^(gracias|saludos|atentamente|un saludo|marta ruiz|tel\.|en cumplimiento|in compliance)/iu', $linea)) {
                break;
            }
            $extra[] = $linea;
        }

        $campos = [];
        $avisos = [];
        $nota = [];
        $dame = function (array $claves) use ($filas): ?string {
            foreach ($claves as $k) {
                if (isset($filas[$k]) && trim($filas[$k]) !== '') {
                    return trim($filas[$k]);
                }
            }

            return null;
        };

        $campos['entidad'] = $dame(['cliente', 'razon social', 'nombre']) ?? ($titulo ?: null);
        $campos['nif'] = ($v = $dame(['nif', 'cif', 'nif cif'])) ? strtoupper(preg_replace('/[\s.\-]/', '', $v)) : null;
        $campos['direccion'] = $dame(['direccion', 'domicilio']);
        $campos['localidad'] = $dame(['poblacion', 'localidad', 'ciudad', 'municipio']);
        $campos['tfno'] = $dame(['telefono', 'tel', 'tfno', 'movil']);
        $campos['web'] = $dame(['web']);

        // Código postal: solo dígitos; «8028 BNC» → 08028 (con aviso).
        if ($cp = $dame(['cp', 'codigo postal', 'c p'])) {
            $d = preg_replace('/\D/', '', $cp);
            if ($d !== '' && strlen($d) === 4) {
                $d = '0'.$d;
            }
            $campos['codpostal'] = $d !== '' ? substr($d, 0, 10) : null;
            if ($d !== $cp || strlen($d) !== 5) {
                $avisos[] = "El código postal del correo es «{$cp}»: lo he dejado como «{$d}». Revísalo.";
            }
        }

        // Provincia: por nombre (tabla provincias) y, si no, por las dos primeras cifras del CP.
        $campos['provincia_id'] = null;
        $prov = $dame(['provincia']);
        $todas = Provincia::pluck('provincia', 'id');
        if ($prov) {
            $p = self::norm($prov);
            foreach ($todas as $id => $nombre) {
                if (self::norm($nombre) === $p || str_contains(self::norm($nombre), $p) || ($p !== '' && str_contains($p, self::norm($nombre)) && self::norm($nombre) !== '')) {
                    $campos['provincia_id'] = (string) $id;
                    break;
                }
            }
        }
        if (! $campos['provincia_id'] && ! empty($campos['codpostal']) && strlen($campos['codpostal']) === 5 && isset($todas[substr($campos['codpostal'], 0, 2)])) {
            $campos['provincia_id'] = substr($campos['codpostal'], 0, 2);
        }
        if ($prov && ! $campos['provincia_id']) {
            $avisos[] = "No encuentro la provincia «{$prov}»: elígela a mano.";
        }
        // Población: si el correo no la trae, la última parte de la dirección si es solo letras («…, 3º 4ª, Barcelona»).
        if (empty($campos['localidad']) && ! empty($campos['direccion'])) {
            $partes = array_map('trim', explode(',', $campos['direccion']));
            $ult = end($partes);
            if (count($partes) > 1 && preg_match('/^[\p{L} .\'-]{3,}$/u', $ult)) {
                $campos['localidad'] = $ult;
            }
        }

        // Cobro: transferencia (1) / recibo domiciliado (2); sin decir, transferencia.
        $cobro = self::norm((string) $dame(['cobro', 'forma de pago', 'metodo de pago']));
        $campos['metodopago_id'] = str_contains($cobro, 'domic') || str_contains($cobro, 'recibo') ? 2 : 1;

        if ($iban = $dame(['iban', 'cuenta', 'cuenta bancaria'])) {
            $campos['iban1'] = strtoupper(preg_replace('/\s+/', '', $iban));
        }
        $correos = [];
        if ($m = $dame(['mail', 'email', 'e mail', 'correo', 'correo electronico'])) {
            preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $m, $mm);
            $correos = array_values(array_unique($mm[0]));
        }
        $campos['emailadm'] = $correos ? implode('; ', $correos) : null;

        // Cuota → ciclo de facturación (ids de la tabla ciclos) e importe.
        $cuota = self::norm((string) $dame(['cuota', 'periodicidad']));
        $ciclo = null;
        foreach (['mensual' => 1, 'trimestral' => 3, 'anual' => 12, 'puntual' => 20] as $palabra => $id) {
            if (str_contains($cuota, $palabra)) {
                $ciclo = $id;
            }
        }
        $campos['ciclo'] = $ciclo;
        $campos['importe'] = null;
        if ($imp = $dame(['importe', 'honorarios', 'precio'])) {
            $n = preg_replace('/[^\d,.\-]/', '', $imp);
            if (str_contains($n, ',')) {
                $n = str_replace(',', '.', str_replace('.', '', $n));
            }
            $campos['importe'] = is_numeric($n) ? (float) $n : null;
            if ($campos['importe'] === null) {
                $avisos[] = "No entiendo el importe «{$imp}».";
            }
        }

        // Equipo → responsable Suma (por nombre).
        $campos['suma_id'] = null;
        if ($eq = $dame(['equipo', 'responsable', 'gestor'])) {
            $e = self::norm($eq);
            foreach (Suma::all() as $s) {
                $sn = self::norm($s->nombre);
                if ($sn === $e || str_contains($sn, $e) || str_contains($e, $sn)) {
                    $campos['suma_id'] = $s->id;
                    break;
                }
            }
            if (! $campos['suma_id']) {
                $avisos[] = "No encuentro al responsable «{$eq}» en Suma: elígelo a mano.";
            }
        }

        // Lo que no tiene campo propio va al historial de la entidad.
        foreach (['fecha' => 'Fecha del correo', 'primera factura' => 'Primera factura', 'cuota' => 'Cuota', 'sage' => 'SAGE', 'equipo' => 'Equipo'] as $k => $etq) {
            if (($v = $dame([$k])) !== null) {
                $nota[$etq] = $v;
            }
        }
        if (empty($campos['entidad'])) {
            $avisos[] = 'No encuentro el nombre del cliente en el correo.';
        }
        if (empty($campos['nif'])) {
            $avisos[] = 'El correo no trae NIF.';
        }

        return ['campos' => $campos, 'extra' => implode("\n", $extra), 'avisos' => $avisos, 'nota' => $nota, 'titulo' => $titulo];
    }

    private static function dentroDeTabla(\DOMNode $n): bool
    {
        for ($p = $n->parentNode; $p; $p = $p->parentNode) {
            if ($p->nodeName === 'table') {
                return true;
            }
        }

        return false;
    }
}
