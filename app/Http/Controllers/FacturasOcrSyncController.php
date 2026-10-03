<?php

namespace App\Http\Controllers;

use App\Http\Livewire\Contabilidad\FacturasOcr;
use App\Support\ColaTareas;
use Illuminate\Http\Request;

/**
 * Facturas OCR en la web: el servidor es donde se trabaja y el OneDrive de un PC trabajador es el archivo. Esta API
 * le da al PC (X-Token del trabajador) la lista de ficheros que debe tener -- los PDF ya contabilizados en las
 * carpetas del mes y lo que hay en la carpeta de datos del cliente salvo lo regenerable -- con su huella SHA-256,
 * para que baje los que le falten y compruebe que han llegado enteros (trabajador.py, h_facturasocr_sync).
 * Las rutas van relativas a la raíz «{OneDrive}» de la copia del servidor.
 */
class FacturasOcrSyncController extends Controller
{
    /** Subcarpetas de la carpeta de datos que no hace falta llevar al PC (se regeneran). */
    protected const NO_LLEVAR = ['_texto', '_cola', '_miniaturas', 'Entrada'];

    protected function autorizar(Request $r): void
    {
        abort_unless(ColaTareas::autenticar($r->header('X-Token')), 403);
    }

    protected function raiz(): string
    {
        $raiz = rtrim((string) config('contabilidad.facturasocr_onedrive'), '/');
        abort_unless($raiz !== '' && is_dir($raiz), 404, 'Este servidor no tiene copia de trabajo de Facturas OCR');

        return $raiz;
    }

    /** [cliente.json, carpeta del cliente]. */
    protected function cliente(string $cliente): array
    {
        $dir = rtrim(config('contabilidad.facturasocr_dir'), '/').'/'.basename($cliente);
        $cfg = json_decode((string) @file_get_contents($dir.'/cliente.json'), true);
        abort_unless(is_array($cfg), 404);

        return [$cfg, $dir];
    }

    /** Carpetas (absolutas en el servidor) cuyo contenido va al PC. */
    protected function carpetas(string $cliente): array
    {
        [$cfg, $dir] = $this->cliente($cliente);
        $raiz = $this->raiz();
        $out = [FacturasOcr::rutaDatos($dir)];
        $plantilla = (string) ($cfg['carpeta_recibidas'] ?? '');
        if ($plantilla !== '' && str_contains($plantilla, '{MM}')) {
            $padre = dirname(substr($plantilla, 0, strpos($plantilla, '{MM}') + 4));   // …/_Facturas
            foreach ([date('Y'), date('Y') - 1] as $anio) {
                $c = $raiz.substr(str_replace('{AAAA}', (string) $anio, $padre), strlen('{OneDrive}'));
                if (is_dir($c)) {
                    $out[] = $c;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** ¿Es una ruta relativa a la raíz que cae dentro de las carpetas que se llevan al PC? Devuelve la absoluta. */
    protected function absoluta(string $cliente, string $relativa): ?string
    {
        $raiz = realpath($this->raiz());
        $ruta = realpath($raiz.'/'.$relativa);
        if (! $ruta || ! is_file($ruta)) {
            return null;
        }
        foreach ($this->carpetas($cliente) as $c) {
            $c = realpath($c);
            if ($c && str_starts_with($ruta, $c.'/') && $this->llevable($c, $ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    protected function llevable(string $carpeta, string $ruta): bool
    {
        $rel = substr($ruta, strlen($carpeta) + 1);
        foreach (self::NO_LLEVAR as $n) {
            if (str_starts_with($rel, $n.'/')) {
                return false;
            }
        }

        return ! str_starts_with(basename($ruta), '~$') && ! str_ends_with($ruta, '.lock');
    }

    public function manifest(Request $r, string $cliente)
    {
        $this->autorizar($r);
        $raiz = realpath($this->raiz());
        $ficheros = [];
        foreach ($this->carpetas($cliente) as $c) {
            $c = realpath($c);
            if (! $c) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($c, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && $this->llevable($c, $f->getPathname())) {
                    $ficheros[] = ['ruta' => substr($f->getPathname(), strlen($raiz) + 1), 'sha256' => hash_file('sha256', $f->getPathname()), 'tam' => $f->getSize()];
                }
            }
        }

        return response()->json(['raiz' => '{OneDrive}', 'ficheros' => $ficheros]);
    }

    public function archivo(Request $r, string $cliente)
    {
        $this->autorizar($r);
        $ruta = $this->absoluta($cliente, (string) $r->query('ruta'));
        abort_unless($ruta, 404);

        return response()->file($ruta);
    }
}
