<?php

namespace App\Support;

/** Ayuda de cada pantalla (config/ayuda.php): el icono del libro con «?» junto al título abre un modal con ella. */
class Ayuda
{
    /** Alias: rutas que comparten la ayuda de otra. */
    protected const ALIAS = ['contabilidad.seguimiento-mensual' => 'contabilidad.procesos-mensuales'];

    /** @return array{titulo:string, html:string}|null */
    public static function para(?string $ruta): ?array
    {
        if (! $ruta) {
            return null;
        }
        $ruta = self::ALIAS[$ruta] ?? $ruta;
        $a = config('ayuda')[$ruta] ?? null;       // las claves llevan puntos: no sirve config('ayuda.clave')
        if (! is_array($a) || empty($a['html'])) {
            return null;
        }
        $pie = '<p class="ayuda-pie">¿Algo de esta ayuda no cuadra o falta? Dile a Alex qué y se corrige.</p>';

        return ['titulo' => (string) ($a['titulo'] ?? ''), 'html' => $a['html'].$pie];
    }
}
