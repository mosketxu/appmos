<?php

namespace App\Console\Commands;

use App\Models\ImpuestoDocumento;
use App\Support\ImpuestosPdfs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Indexa los PDF de impuestos de una carpeta OneDrive de este equipo (los mismos que sube el trabajador con pc.impuestos_pdfs).
 * Sirve para probar en local y para cargar la primera vez desde un PC con OneDrive.
 *   php artisan impuestos:indexar-pdfs --raiz=/mnt/e/OneDrive --anios=2026 [--dry]
 */
class ImpuestosIndexarPdfs extends Command
{
    protected $signature = 'impuestos:indexar-pdfs {--raiz=} {--anios=} {--dry : solo cuenta y dice qué entendería de cada fichero}';

    protected $description = 'Guarda y asocia los PDF de las carpetas de impuestos de OneDrive';

    public function handle(): int
    {
        $raiz = rtrim((string) ($this->option('raiz') ?: ''), '/');
        foreach ($raiz ? [] : ['/mnt/e/OneDrive', '/mnt/f/OneDrive', '/mnt/d/OneDrive'] as $c) {
            if (is_dir($c)) {
                $raiz = $c;
                break;
            }
        }
        if (! $raiz || ! is_dir($raiz)) {
            $this->error('No encuentro OneDrive (usa --raiz).');

            return 1;
        }
        $anios = $this->option('anios') ? array_map('intval', explode(',', $this->option('anios'))) : [(int) date('Y')];
        $idx = ImpuestosPdfs::indiceNombres();
        $nuevos = $iguales = $sinCliente = 0;
        foreach (ImpuestosPdfs::raices($anios) as $rel) {
            foreach (['', 'x'] as $v) {   // _Clientes o Clientes
                $dir = $raiz.'/'.($v ? str_replace('_Clientes/', 'Clientes/', $rel) : $rel);
                if (! is_dir($dir) || ($v && $dir === $raiz.'/'.$rel)) {
                    continue;
                }
                foreach (File::allFiles($dir) as $f) {
                    if (strtolower($f->getExtension()) !== 'pdf' || str_starts_with($f->getFilename(), '~$')) {
                        continue;
                    }
                    $ruta = ltrim(str_replace($raiz, '', str_replace('\\', '/', $f->getPathname())), '/');
                    $meta = ['ruta' => $ruta, 'tam' => $f->getSize(), 'mtime' => $f->getMTime()];
                    if ($this->option('dry')) {
                        $p = ImpuestosPdfs::parsear($f->getFilename(), ImpuestosPdfs::anioDeRuta($ruta));
                        $e = ImpuestosPdfs::entidadPorTexto((string) $p['cliente_texto'], $idx);
                        $this->line(sprintf('%-60s → %-28s M%s %s %s %s ent=%s', mb_strimwidth($f->getFilename(), 0, 60), mb_strimwidth((string) $p['cliente_texto'], 0, 28), $p['modelo'], $p['ejercicio'], $p['periodo'], $p['tipo'], $e ?: '—'));
                        continue;
                    }
                    $d = ImpuestoDocumento::where('ruta_origen', $ruta)->first();
                    if ($d && $d->tam === $meta['tam'] && $d->mtime === $meta['mtime']) {
                        $iguales++;
                        continue;
                    }
                    $doc = ImpuestosPdfs::registrar($meta, $f->getPathname(), $idx);
                    $nuevos++;
                    $sinCliente += $doc->entidad_id ? 0 : 1;
                }
            }
        }
        $this->info("Nuevos o cambiados: $nuevos · ya estaban: $iguales · sin cliente: $sinCliente");

        return 0;
    }
}
