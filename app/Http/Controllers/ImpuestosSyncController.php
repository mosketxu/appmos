<?php

namespace App\Http\Controllers;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use App\Support\ImpuestosPdfs;
use Illuminate\Http\Request;

/**
 * API de los PCs trabajadores (X-Token) para la pestaña Impuestos: el PC lista los PDF de las carpetas de impuestos de OneDrive
 * (raices), el servidor dice cuáles le faltan o han cambiado (manifest) y el PC los sube uno a uno (subir). Ver
 * trabajador.py → h_impuestos_pdfs y App\Support\ImpuestosPdfs.
 */
class ImpuestosSyncController extends Controller
{
    protected function autorizar(Request $r): void
    {
        abort_unless(ColaTareas::autenticar($r->header('X-Token')), 403);
    }

    /** Carpetas (relativas a OneDrive) a recorrer para los años pedidos. */
    public function raices(Request $r)
    {
        $this->autorizar($r);
        $anios = array_filter(array_map('intval', explode(',', (string) $r->query('anios', date('Y')))), fn ($a) => $a >= 2009 && $a <= 2100);

        return response()->json(['raices' => ImpuestosPdfs::raices($anios ?: [(int) date('Y')])]);
    }

    /** Recibe [{ruta, tam, mtime}] y devuelve las rutas que hay que subir. */
    public function manifest(Request $r)
    {
        $this->autorizar($r);
        $subir = [];
        $conocidos = ImpuestoDocumento::whereNotNull('ruta_origen')->get(['ruta_origen', 'tam', 'mtime'])->keyBy('ruta_origen');
        foreach ((array) $r->input('ficheros', []) as $f) {
            $ruta = (string) ($f['ruta'] ?? '');
            if (! $this->rutaValida($ruta)) {
                continue;
            }
            $d = $conocidos->get($ruta);
            if (! $d || $d->tam !== (int) ($f['tam'] ?? 0) || (int) ($f['mtime'] ?? 0) > $d->mtime + 1) {
                $subir[] = $ruta;
            }
        }

        // Lo que ya no está en OneDrive (movido, borrado) NO se quita de Appmos: sirve de copia de seguridad. Solo se cuenta, para el resumen.
        $presentes = array_flip(array_column((array) $r->input('ficheros', []), 'ruta'));
        $quitados = 0;
        $movidos = 0;
        if ($presentes && $r->input('completo')) {
            $raices = array_map(fn ($x) => rtrim((string) $x, '/').'/', (array) $r->input('raices', []));
            $ausentes = [];   // rutas conocidas que ya no están en las carpetas recorridas
            foreach ($conocidos as $ruta => $d) {
                if (! isset($presentes[$ruta]) && collect($raices)->contains(fn ($x) => str_starts_with($ruta, $x))) {
                    $ausentes[$ruta] = $d;
                }
            }
            $quitados = count($ausentes);
            // Un fichero «nuevo» con el mismo tamaño y fecha que uno ausente (y solo uno) es el mismo PDF movido de carpeta o renombrado:
            // se actualiza su ruta en vez de crear un duplicado (se conservan su papelera, sus marcas y su casilla)
            $nuevos = array_flip($subir);
            foreach ((array) $r->input('ficheros', []) as $f) {
                $ruta = (string) ($f['ruta'] ?? '');
                if (! isset($nuevos[$ruta]) || isset($conocidos[$ruta])) {
                    continue;
                }
                $cand = array_keys(array_filter($ausentes, fn ($d) => $d->tam === (int) ($f['tam'] ?? -1) && abs((int) $d->mtime - (int) ($f['mtime'] ?? 0)) <= 1));
                if (count($cand) !== 1) {
                    continue;
                }
                $doc = ImpuestoDocumento::where('ruta_origen', $cand[0])->first();
                if (! $doc) {
                    continue;
                }
                $doc->ruta_origen = $ruta;
                $doc->nombre = basename($ruta);
                $doc->save();
                \App\Support\ImpuestosPdfs::asociar($doc);
                unset($ausentes[$cand[0]], $nuevos[$ruta]);
                $movidos++;
                $quitados--;
            }
            $subir = array_values(array_flip($nuevos));
        }

        return response()->json(['subir' => $subir, 'quitados' => $quitados, 'movidos' => $movidos, 'total' => count((array) $r->input('ficheros', [])), 'conocidos' => $conocidos->count()]);
    }

    /** Cuerpo = el PDF; ruta, tam y mtime en la query. */
    public function subir(Request $r)
    {
        $this->autorizar($r);
        $ruta = (string) $r->query('ruta');
        abort_unless($this->rutaValida($ruta), 422, 'Ruta no válida');
        $cuerpo = $r->getContent();
        abort_if($cuerpo === '' || ! str_contains(substr($cuerpo, 0, 1024), '%PDF'), 422, 'No es un PDF');
        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($tmp, $cuerpo);
        try {
            $doc = ImpuestosPdfs::registrar(['ruta' => $ruta, 'tam' => strlen($cuerpo), 'mtime' => (int) $r->query('mtime', 0)], $tmp);
        } finally {
            @unlink($tmp);
        }

        return response()->json(['ok' => true, 'id' => $doc->id, 'entidad_id' => $doc->entidad_id, 'modelo' => $doc->modelo, 'periodo' => $doc->periodo]);
    }

    protected function rutaValida(string $ruta): bool
    {
        return ColaTareas::rutaRelativaSegura($ruta) && strtolower(substr($ruta, -4)) === '.pdf'
            && (str_starts_with($ruta, '_Clientes/') || str_starts_with($ruta, '_RUR_Marta_Alex/')) && strlen($ruta) < 700;
    }
}
