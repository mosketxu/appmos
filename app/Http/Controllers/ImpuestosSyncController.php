<?php

namespace App\Http\Controllers;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use App\Support\ImpuestosPdfs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $conocidos = ImpuestoDocumento::whereNotNull('ruta_origen')->where(fn ($q) => $q->whereNull('origen')->orWhere('origen', '!=', 'libro_iva'))->get(['ruta_origen', 'tam', 'mtime', 'quitado_at'])->keyBy('ruta_origen');
        foreach ((array) $r->input('ficheros', []) as $f) {
            $ruta = (string) ($f['ruta'] ?? '');
            if (! $this->rutaValida($ruta)) {
                continue;
            }
            $d = $conocidos->get($ruta);
            if ($d && $d->quitado_at) {
                continue;   // borrado a propósito de Appmos: no se vuelve a subir
            }
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
                if (! $d->quitado_at && ! isset($presentes[$ruta]) && collect($raices)->contains(fn ($x) => str_starts_with($ruta, $x))) {
                    $ausentes[$ruta] = $d;
                }
            }
            $quitados = count($ausentes);
            // Un fichero «nuevo» con el mismo tamaño y fecha que uno ausente (y solo uno) es el mismo PDF movido de carpeta o renombrado:
            // se actualiza su ruta en vez de crear un duplicado (se conservan sus marcas y su casilla)
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

    /** Libros de IVA registrados en casillas (origen libro_iva): el PC comprueba si el Excel de OneDrive ha cambiado desde lo que tiene Appmos. */
    public function libros(Request $r)
    {
        $this->autorizar($r);

        return response()->json(['libros' => ImpuestoDocumento::where('origen', 'libro_iva')->whereNull('quitado_at')->get(['id', 'ruta_origen', 'tam', 'mtime', 'sha256'])]);
    }

    /**
     * Cuerpo = el Excel «IVA …» de la carpeta IVA del cliente. Lo usa «Generar» (generado=1: lo registra en la casilla 303 de ese periodo sin tocar
     * su estado) y la sincronización (el Excel se ha modificado a mano en OneDrive: sustituye la copia de Appmos si es más nuevo).
     */
    public function libroSubir(Request $r)
    {
        $this->autorizar($r);
        $ruta = (string) $r->query('ruta');
        abort_unless(ColaTareas::rutaRelativaSegura($ruta) && strtolower(substr($ruta, -5)) === '.xlsx' && strlen($ruta) < 700, 422, 'Ruta no válida');
        $cuerpo = $r->getContent();
        abort_if(strlen($cuerpo) < 100 || substr($cuerpo, 0, 2) !== 'PK', 422, 'No es un Excel');
        $mtime = (int) $r->query('mtime', 0);
        $sha = hash('sha256', $cuerpo);
        $generado = $r->boolean('generado');
        $doc = ImpuestoDocumento::where('origen', 'libro_iva')->where('ruta_origen', $ruta)->first();
        if ($doc && ! $generado && ($doc->quitado_at || $sha === $doc->sha256 || $mtime <= (int) $doc->mtime + 1)) {
            return response()->json(['ok' => true, 'sin_cambios' => true]);
        }
        if (! $doc) {
            $ent = (int) $r->query('entidad_id');
            $ej = (int) $r->query('ejercicio');
            $per = (string) $r->query('periodo');
            abort_unless($ent && $ej && preg_match('/^(T[1-4]|0[1-9]|1[0-2])$/', $per), 422, 'Falta cliente o periodo');
            $doc = new ImpuestoDocumento(['entidad_id' => $ent, 'modelo' => '303', 'etiqueta' => '', 'ejercicio' => $ej, 'periodo' => $per, 'tipo' => 'libro_iva', 'origen' => 'libro_iva', 'ruta_origen' => $ruta]);
        }
        $viejo = $doc->almacen;
        $almacen = 'impuestos/docs/'.$sha.'.xlsx';
        Storage::disk('local')->put($almacen, $cuerpo);
        $doc->fill(['nombre' => basename($ruta), 'almacen' => $almacen, 'tam' => strlen($cuerpo), 'mtime' => $mtime, 'sha256' => $sha, 'quitado_at' => null, 'quitado_por' => null]);
        $doc->save();
        if ($viejo && $viejo !== $almacen && ! ImpuestoDocumento::where('almacen', $viejo)->exists() && ! DB::table('impuesto_comentarios')->where('adjunto_almacen', $viejo)->exists()) {
            Storage::disk('local')->delete($viejo);
        }

        return response()->json(['ok' => true, 'id' => $doc->id, 'actualizado' => true]);
    }

    protected function rutaValida(string $ruta): bool
    {
        return ColaTareas::rutaRelativaSegura($ruta) && strtolower(substr($ruta, -4)) === '.pdf'
            && (str_starts_with($ruta, '_Clientes/') || str_starts_with($ruta, '_RUR_Marta_Alex/')) && strlen($ruta) < 700;
    }
}
