<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Año de trabajo de los procesos (27-oct-2026): ya no hay «2026» escrito a mano. Misma lógica que Contabilidad/_comun/anio.py:
 * un proceso con mes/periodo usa el año de ese periodo (de()); un proceso mensual sin mes elegido, el del mes anterior al actual (defecto());
 * lo diario, el de hoy (actual()). APPMOS_ANIO en el .env fuerza el año en actual() y defecto().
 */
class Anio
{
    protected static function forzado(): ?int
    {
        $v = trim((string) env('APPMOS_ANIO', ''));

        return preg_match('/^20\d\d$/', $v) ? (int) $v : null;
    }

    public static function actual(): int
    {
        return self::forzado() ?? (int) now()->year;
    }

    /** Año del mes anterior al actual (en enero, el año pasado). */
    public static function defecto(): int
    {
        return self::forzado() ?? (int) now()->subMonthNoOverflow()->year;
    }

    /** Mes anterior al actual (1-12). */
    public static function mesDefecto(): int
    {
        return (int) now()->subMonthNoOverflow()->month;
    }

    /** Año de un mes «suelto» (1-12) que se trabaja ya o ya pasó: el más reciente en que no es futuro (en enero, el 12 es del año pasado). */
    public static function paraMes(int $mes): int
    {
        return self::forzado() ?? ($mes > (int) now()->month ? (int) now()->year - 1 : (int) now()->year);
    }

    /** Año de '2026-09', '2026-3T', 2026 o Carbon/DateTime. */
    public static function de($periodo): int
    {
        if ($periodo instanceof \DateTimeInterface) {
            return (int) $periodo->format('Y');
        }
        abort_unless(preg_match('/^\s*(20\d\d)/', (string) $periodo, $m), 422, 'Periodo sin año');

        return (int) $m[1];
    }

    /** Años que se ofrecen en un desplegable: del primero con datos (2026) al actual (+1 en diciembre). */
    public static function lista(int $desde = 2026): array
    {
        $hasta = max(self::actual(), self::defecto()) + (now()->month === 12 ? 1 : 0);

        return range($hasta, min($desde, $hasta));
    }
}
