<?php

namespace App\Support;

/**
 * Entidad «activa» compartida entre pantallas (11-nov-2026): la última que el usuario eligió en Impuestos (303, 111, 216, 349…), Libros IVA,
 * Bancos, Facturas OCR, IS, Revisión del mayor… se mantiene al pasar de una pestaña a otra. Vive en la sesión (por usuario y navegador).
 * Las pantallas de «cliente» (carpeta de OneDrive) la traducen con el entidad_id de su cliente.json.
 */
class EntidadActiva
{
    public static function id(): ?int
    {
        return ((int) session('entidad_activa', 0)) ?: null;
    }

    public static function poner($id): void
    {
        if ((int) $id > 0) {
            session(['entidad_activa' => (int) $id]);
        }
    }

    /** La activa si está entre las permitidas (ids), si no null. */
    public static function entre(array $ids): ?int
    {
        $a = self::id();

        return $a && in_array($a, array_map('intval', $ids), true) ? $a : null;
    }

    /** Nombre de cliente (carpeta) de la entidad activa, si existe en $lista y en $baseDir. */
    public static function cliente(array $lista, string $baseDir): ?string
    {
        $a = self::id();
        if (! $a) {
            return null;
        }
        $n = ClientesEntidad::carpetas($baseDir)[$a] ?? null;

        return $n !== null && in_array($n, $lista, true) ? $n : null;
    }

    /** Al elegir un cliente (carpeta) se fija su entidad como activa. */
    public static function ponerCliente(string $cliente, string $baseDir): void
    {
        $id = array_search($cliente, ClientesEntidad::carpetas($baseDir), true);
        if ($id !== false) {
            self::poner($id);
        }
    }
}
