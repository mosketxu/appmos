<?php

namespace App\Support;

/**
 * Cómo fue la última ejecución de una caja de "Salida" de Contabilidad, para
 * pintarla: verde = OK, naranja = avisos, rojo = errores, gris = vacía.
 * Solo mira el último bloque (cada ejecución empieza con "===== Título =====").
 */
class EstadoSalida
{
    /** @return array{estado: string, color: string, icono: string} */
    public static function de(string $salida): array
    {
        $bloques = preg_split('/^=====/m', $salida);
        $ultima = trim((string) end($bloques));

        if ($ultima === '') {
            return ['estado' => 'vacio', 'color' => '#6b7280', 'icono' => ''];
        }
        if (preg_match('/c[oó]digo de salida|EXCEPCI[OÓ]N|Traceback|^\s*ERROR\b|\bError:|Exception|❌|Opción no válida|No encuentro|No he podido|No se pudo/imu', $ultima)) {
            return ['estado' => 'error', 'color' => '#dc2626', 'icono' => '✖'];
        }
        if (preg_match('/⚠|\bAVISO\b|\bWARNING\b/iu', $ultima)) {
            return ['estado' => 'aviso', 'color' => '#ea580c', 'icono' => '⚠'];
        }

        return ['estado' => 'ok', 'color' => '#16a34a', 'icono' => '✔'];
    }
}
