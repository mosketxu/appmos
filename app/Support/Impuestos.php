<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Impuestos (pestaña del TO-DO, 7-oct-2026): periodos, estados y quién ve qué.
 *
 * Periodicidad de una obligación: M mensual (periodos 01..12), T trimestral (T1..T4), A anual (A), P pagos fraccionados del 202 (P1..P3).
 * Estados de un periodo (ciclo con cada clic): no (vacío: no tiene que presentarlo) → pendiente (rojo) → revision (naranja, listo para
 * revisar) → revisado (azul, listo para presentar) → presentado (verde) → visto (verde intenso: visto por Marta, el último).
 * Ejercicio = año del seguimiento. Para los modelos con «desfase» (200, D2, LIB) es el año siguiente al del impuesto: el IS 2025 se
 * presenta y se sigue en el ejercicio 2026, como en el ToDO Alex.
 */
class Impuestos
{
    public const ESTADOS = [
        'no' => 'No tiene que presentarlo',
        'pendiente' => 'Pendiente',
        'revision' => 'Listo para revisión',
        'revisado' => 'Revisado, listo para presentar',
        'presentado' => 'Presentado',
        'visto' => 'Visto por Marta',
    ];

    /** «Visto por Marta» solo lo pone (y lo quita) quien esté en config('contabilidad.impuestos_visto_emails'); ni el Admin lo salta. */
    public static function puedeVisto(?User $u = null): bool
    {
        $u ??= auth()->user();

        return $u && in_array(strtolower((string) $u->email), array_map('strtolower', (array) config('contabilidad.impuestos_visto_emails', [])), true);
    }

    /** Orden del clic. */
    public const CICLO = ['no', 'pendiente', 'revision', 'revisado', 'presentado', 'visto'];

    public const COLOR = ['pendiente' => '#dc2626', 'revision' => '#f97316', 'revisado' => '#2563eb', 'visto' => '#15803d', 'presentado' => '#16a34a'];
    public const LETRA = ['pendiente' => 'x', 'revision' => 'r', 'revisado' => 'p', 'presentado' => '✓', 'visto' => 'v'];

    public const PERIODICIDADES = ['M' => 'Mensual', 'T' => 'Trimestral', 'A' => 'Anual', 'P' => 'Pagos fraccionados (1P 2P 3P)'];

    public const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public static function periodos(string $periodicidad): array
    {
        return match ($periodicidad) {
            'M' => array_map(fn ($m) => sprintf('%02d', $m), range(1, 12)),
            'T' => ['T1', 'T2', 'T3', 'T4'],
            'P' => ['P1', 'P2', 'P3'],
            default => ['A'],
        };
    }

    public static function periodicidadDe(string $periodo): string
    {
        return match ($periodo[0] ?? '') {
            'T' => 'T', 'P' => 'P', 'A' => 'A', default => 'M',
        };
    }

    /** Trimestre (1-4) bajo el que se pinta en la vista del año; null para los anuales. */
    public static function trimestreDe(string $periodo): ?int
    {
        return match ($periodo[0] ?? '') {
            'T' => (int) substr($periodo, 1),
            'P' => [1 => 1, 2 => 3, 3 => 4][(int) substr($periodo, 1)] ?? null,
            'A' => null,
            default => (int) ceil(((int) $periodo) / 3),
        };
    }

    /** Mes de la vista mensual en el que aparece el periodo (el de cierre: T1 → marzo...; anuales: el mes del modelo, por defecto diciembre). */
    public static function mesDe(string $periodo, ?int $mesAnual = null): int
    {
        return match ($periodo[0] ?? '') {
            'T' => 3 * (int) substr($periodo, 1),
            'P' => [1 => 3, 2 => 9, 3 => 12][(int) substr($periodo, 1)] ?? 12,
            'A' => $mesAnual ?: 12,
            default => (int) $periodo,
        };
    }

    public static function etiquetaPeriodo(string $periodo, int $ejercicio, ?int $desfase = 0): string
    {
        $anio = $ejercicio - (int) $desfase;
        return match ($periodo[0] ?? '') {
            'T' => substr($periodo, 1).'T '.$anio,
            'P' => substr($periodo, 1).'P '.$anio,
            'A' => 'Anual '.$anio,
            default => self::MESES[(int) $periodo - 1].' '.$anio,
        };
    }

    public static function siguiente(string $estado): string
    {
        $i = array_search($estado, self::CICLO, true);

        return self::CICLO[(($i === false ? 0 : $i) + 1) % count(self::CICLO)];
    }

    /** Admin y Suma pueden pedir ver los impuestos de todos. */
    public static function esGestor(?User $u = null): bool
    {
        $u ??= auth()->user();

        return (bool) $u?->hasAnyRole(['Admin', 'Suma']);
    }

    /**
     * Limita una consulta sobre entidad_impuestos (alias $ei) a lo que ve el usuario: los impuestos que tiene asignados (user_id) y los
     * de sus entidades (Responsable Suma + co-responsables) que no tienen responsable propio. Con $todos (solo Admin/Suma) no limita.
     */
    public static function soloVisibles(Builder $q, ?User $u = null, bool $todos = false, string $ei = 'ei'): Builder
    {
        $u ??= auth()->user();
        if ($todos && self::esGestor($u)) {
            return $q;
        }
        $propias = Accesos::entidadesPropias($u);

        return $q->where(fn ($w) => $w->where("$ei.user_id", $u->id)
            ->orWhere(fn ($x) => $x->whereNull("$ei.user_id")->whereIn("$ei.entidad_id", $propias ?: [0])));
    }

    public static function puedeVer(int $entidadImpuestoId, bool $todos = false): bool
    {
        $q = DB::table('entidad_impuestos as ei')->where('ei.id', $entidadImpuestoId);

        return self::soloVisibles($q, null, $todos)->exists();
    }

    /** Ejercicios con datos (más el actual): para asegurar sus filas cuando se añade una obligación. */
    public static function ejercicios(): array
    {
        return DB::table('impuesto_estados')->distinct()->pluck('ejercicio')->push((int) now()->format('Y'))->unique()->sort()->values()->all();
    }

    /** Crea las filas «pendiente» que falten de cada obligación en ese ejercicio (las ya existentes, incluidas las «no», no se tocan). */
    public static function asegurarEjercicio(int $ejercicio, ?int $entidadImpuestoId = null): int
    {
        $obl = DB::table('entidad_impuestos')->when($entidadImpuestoId, fn ($q) => $q->where('id', $entidadImpuestoId))->get(['id', 'periodicidad']);
        $existen = [];
        DB::table('impuesto_estados')->where('ejercicio', $ejercicio)->when($entidadImpuestoId, fn ($q) => $q->where('entidad_impuesto_id', $entidadImpuestoId))
            ->get(['entidad_impuesto_id', 'periodo'])->each(function ($r) use (&$existen) {
                $existen[$r->entidad_impuesto_id.'|'.$r->periodo] = true;
            });
        $ahora = now();
        $nuevas = [];
        foreach ($obl as $o) {
            foreach (self::periodos($o->periodicidad) as $p) {
                if (! isset($existen[$o->id.'|'.$p])) {
                    $nuevas[] = ['entidad_impuesto_id' => $o->id, 'ejercicio' => $ejercicio, 'periodo' => $p, 'estado' => 'pendiente', 'created_at' => $ahora, 'updated_at' => $ahora];
                }
            }
        }
        foreach (array_chunk($nuevas, 500) as $trozo) {
            DB::table('impuesto_estados')->insertOrIgnore($trozo);
        }

        return count($nuevas);
    }
}
