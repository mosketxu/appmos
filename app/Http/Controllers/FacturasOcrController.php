<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Process;

/**
 * PDF y miniatura de una factura de Contabilidad → Facturas OCR, para la pantalla de revisión
 * (la ruta del fichero sale de facturas.json, en la carpeta compartida del cliente).
 */
class FacturasOcrController extends Controller
{
    /** [carpeta local del cliente, factura] o aborta. */
    protected function factura(string $cliente, string $id): array
    {
        $base = rtrim(config('contabilidad.facturasocr_dir'), '/');
        $dir = $base.'/'.basename($cliente);
        $cfg = json_decode((string) @file_get_contents($dir.'/cliente.json'), true);
        abort_unless(is_array($cfg), 404);
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        abort_if($permitidas !== null && ! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true), 403);

        $datos = \App\Http\Livewire\Contabilidad\FacturasOcr::rutaDatos($dir);
        $estado = json_decode((string) @file_get_contents($datos.'/facturas.json'), true) ?: [];
        foreach ($estado['facturas'] ?? [] as $f) {
            if ($f['id'] === $id && is_file($f['ruta'])) {
                return [$dir, $f];
            }
        }
        abort(404);
    }

    public function pdf(string $cliente, string $id)
    {
        [, $f] = $this->factura($cliente, $id);
        // Cacheable (la URL lleva ?r=<hash de la ruta>): la siguiente factura se precarga mientras se revisa esta
        return response()->file($f['ruta'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($f['ruta'])).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Imagen ligera de la 1ª página para enseñarla al momento mientras carga el PDF. */
    public function miniatura(string $cliente, string $id)
    {
        [$dir, $f] = $this->factura($cliente, $id);
        // En la subcarpeta _miniaturas de la carpeta de facturas que se está tratando (ruta_miniatura() en Python)
        $jpg = ($f['carpeta_origen'] ?? dirname($f['ruta'])).'/_miniaturas/'.$id.'.jpg';
        if (! is_file($jpg)) {
            $rapido = storage_path('app/venv-facturasocr/bin/python');
            $python = config('contabilidad.facturasocr_python') ?: (is_executable($rapido) ? $rapido : dirname($dir).'/.venv/bin/python');
            $env = config('contabilidad.facturasocr_web')
                ? ['ONEDRIVE_ROOT' => rtrim((string) config('contabilidad.facturasocr_onedrive'), '/'), 'FACTURAS_OCR_MOTOR' => 'tesseract'] : [];
            Process::path(dirname($dir))->env($env)->timeout(30)->run([$python, 'facturas_ocr.py', basename($dir), 'miniatura', $id]);
        }
        abort_unless(is_file($jpg), 404);
        return response()->file($jpg, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * PDF de una carpeta del cliente que no es una factura de la lista (Chequeo mayor / Ordenar sueltas): la raíz de «_Facturas» ($carpeta = 'raiz'),
     * la de un mes ('01'..'12', del año ?a=AAAA) o la entrada de la web ('entrada'). Solo ficheros PDF de esas carpetas.
     */
    public function archivo(string $cliente, string $carpeta, string $nombre)
    {
        $dir = rtrim(config('contabilidad.facturasocr_dir'), '/').'/'.basename($cliente);
        $cfg = json_decode((string) @file_get_contents($dir.'/cliente.json'), true);
        abort_unless(is_array($cfg), 404);
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        abort_if($permitidas !== null && ! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true), 403);
        $anio = preg_match('/^\d{4}$/', (string) request()->query('a')) ? request()->query('a') : date('Y');
        $plantilla = str_replace(['{OneDrive}', '{AAAA}'], [rtrim((string) config('contabilidad.facturasocr_onedrive'), '/'), $anio], (string) ($cfg['carpeta_recibidas'] ?? ''));
        abort_unless($plantilla !== '' && str_contains($plantilla, '{MM}'), 404);
        $raiz = dirname(str_replace('{MM}', '01', $plantilla));
        if ($carpeta === 'raiz') {
            $base = $raiz;
        } elseif ($carpeta === 'entrada') {
            $base = \App\Http\Livewire\Contabilidad\FacturasOcr::rutaDatos($dir).'/Entrada';
        } elseif (preg_match('/^(0[1-9]|1[0-2])$/', $carpeta)) {
            $base = str_replace('{MM}', $carpeta, $plantilla);
        } else {
            abort(404);
        }
        $ruta = realpath($base.'/'.basename($nombre));
        abort_unless($ruta && is_file($ruta) && str_starts_with($ruta, (string) realpath($base).'/') && str_ends_with(strtolower($ruta), '.pdf'), 404);

        return response()->file($ruta, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.addslashes(basename($ruta)).'"']);
    }

    /** Excel para SAGE entregado en la web (Output/Entregados): enlace firmado que crea la pantalla al guardarlo. */
    public function excel(string $cliente, string $archivo)
    {
        $dir = rtrim(config('contabilidad.facturasocr_dir'), '/').'/'.basename($cliente);
        $cfg = json_decode((string) @file_get_contents($dir.'/cliente.json'), true);
        abort_unless(is_array($cfg), 404);
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        abort_if($permitidas !== null && ! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true), 403);
        $ruta = \App\Http\Livewire\Contabilidad\FacturasOcr::rutaDatos($dir).'/Output/Entregados/'.basename($archivo);
        abort_unless(is_file($ruta), 404);

        return response()->download($ruta, basename($archivo));
    }
}
