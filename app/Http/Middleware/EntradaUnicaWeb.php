<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Entrada única a Appmos (3-oct-2026, decisión de Alex): solo se entra por la web (https://appmos.sumaempresa.com), para evitar
 * datos duplicados y confusiones. En un PC (ENTRADA_WEB_URL en el .env; NUNCA en el VPS) cualquier página redirige a la misma
 * ruta de la web. Lo que aún solo funciona en el PC (config entrada_web_excepciones: Durcal y Facturas OCR hasta que pasen a la
 * web con el trabajador) y las llamadas internas (Livewire, API) se dejan pasar. Para desarrollar en local: quitar ENTRADA_WEB_URL.
 */
class EntradaUnicaWeb
{
    public function handle(Request $request, Closure $next)
    {
        $web = config('contabilidad.entrada_web_url');
        if (! $web || $request->is(['livewire/*', 'api/*', 'up']) || $request->is((array) config('contabilidad.entrada_web_excepciones', []))) {
            return $next($request);
        }
        if (rtrim($web, '/') === rtrim($request->getSchemeAndHttpHost(), '/')) {   // ya estamos en la web
            return $next($request);
        }

        return redirect()->away(rtrim($web, '/').$request->getRequestUri());
    }
}
