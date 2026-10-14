<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Último día de plazo de las autoliquidaciones de la AEAT (= día de cargo si se domicilia). Mismo criterio que
 * Contabilidad/_comun/plazos_aeat.py, validado con el Calendario del contribuyente 2026 (14-oct-2026):
 * 111/115/216 mensual y trimestral el 20 del mes siguiente; 303 mensual el 30 (enero → fin de febrero, julio → 31 de
 * agosto); 303 trimestral el 20 (4T → 30 de enero). Sábado, domingo o festivo nacional → siguiente hábil.
 */
class PlazosAeat
{
    public static function pascua(int $anio): Carbon
    {
        $a = $anio % 19; $b = intdiv($anio, 100); $c = $anio % 100;
        $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        return Carbon::create($anio, intdiv($h + $l - 7 * $m + 114, 31), ($h + $l - 7 * $m + 114) % 31 + 1);
    }

    /** @return string[] Y-m-d */
    public static function festivosNacionales(int $anio): array
    {
        $f = array_map(fn ($md) => sprintf('%d-%s', $anio, $md), ['01-01', '01-06', '05-01', '08-15', '10-12', '11-01', '12-06', '12-08', '12-25']);
        $f[] = self::pascua($anio)->subDays(2)->format('Y-m-d'); // Viernes Santo
        return $f;
    }

    /** @param string[] $extra festivos autonómicos/locales, Y-m-d */
    public static function habil(Carbon $d, array $extra = []): Carbon
    {
        while ($d->isWeekend() || in_array($d->format('Y-m-d'), array_merge(self::festivosNacionales($d->year), $extra), true)) {
            $d->addDay();
        }
        return $d;
    }

    /** $mes = último mes del periodo (trimestral: 3, 6, 9, 12). */
    public static function finPlazo(string $modelo, int $anio, int $mes, bool $trimestral = false, array $extra = []): Carbon
    {
        $d = Carbon::create($anio, $mes, 1)->addMonthNoOverflow();
        if (ltrim(strtolower($modelo), 'm') !== '303') {
            $dia = 20;
        } elseif ($trimestral) {
            $dia = $mes === 12 ? 30 : 20;
        } else {
            $dia = in_array($mes, [1, 7], true) ? $d->daysInMonth : 30;
        }
        return self::habil($d->day($dia), $extra);
    }
}
