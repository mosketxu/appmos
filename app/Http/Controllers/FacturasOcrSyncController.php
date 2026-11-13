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
        if ($plantilla !== '' && str_contains($plantilla, '{T}')) {   // por trimestres: la carpeta de recibidas de cada trimestre
            foreach ([date('Y'), date('Y') - 1] as $anio) {
                foreach ([1, 2, 3, 4] as $t) {
                    $c = $raiz.substr(str_replace(['{AAAA}', '{T}'], [(string) $anio, (string) $t], $plantilla), strlen('{OneDrive}'));
                    if (is_dir($c)) {
                        $out[] = $c;
                    }
                }
            }
        } elseif ($plantilla !== '' && str_contains($plantilla, '{MM}')) {
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

        // Para completar el «mover» en el PC: las validadas de este cliente con su nombre original y su huella. El PC borra el original que
        // siga en la raíz de _Facturas solo si es idéntico (SHA-256) a la copia ya renombrada en su carpeta del mes.
        $sha = [];
        foreach ($ficheros as $f) {
            $sha[$f['ruta']] = $f['sha256'];
        }
        $originales = [];
        [, $dir] = $this->cliente($cliente);
        $estado = json_decode((string) @file_get_contents(FacturasOcr::rutaDatos($dir).'/facturas.json'), true);
        foreach ($estado['facturas'] ?? [] as $fa) {
            if (($fa['estado'] ?? '') !== 'validada' || empty($fa['nombre_original']) || ! str_starts_with((string) ($fa['ruta'] ?? ''), $raiz.'/')) {
                continue;
            }
            $rel = substr($fa['ruta'], strlen($raiz) + 1);
            // También las que conservan su nombre (lo normal): el PC mueve = copia en la carpeta del mes y quita la suelta de la raíz si es idéntica (SHA-256)
            if (isset($sha[$rel])) {
                $originales[] = ['nombre' => $fa['nombre_original'], 'destino' => $rel, 'sha256' => $sha[$rel]];
            }
        }

        // Lo que «Ordenar facturas sueltas» ha movido o quitado en el servidor: el PC quita también su copia suelta si es idéntica
        $registro = json_decode((string) @file_get_contents(FacturasOcr::rutaDatos($dir).'/originales_movidos.json'), true) ?: [];
        foreach ($registro as $m) {
            if (isset($m['nombre'], $m['destino'], $m['sha256']) && ($sha[$m['destino']] ?? null) === $m['sha256']) {
                $originales[] = ['nombre' => $m['nombre'], 'destino' => $m['destino'], 'sha256' => $m['sha256']];
            }
        }

        // Rechazadas (con el motivo al final del nombre, 14-oct-2026), en la Entrada del servidor o en su copia de OneDrive: el PC renombra
        // su copia con el mismo contenido, sin moverla
        $entrada = [];
        $datos = realpath(FacturasOcr::rutaDatos($dir).'/Entrada');
        foreach ($estado['facturas'] ?? [] as $fa) {
            $ruta = realpath((string) ($fa['ruta'] ?? ''));
            if (($fa['estado'] ?? '') === 'rechazada' && $ruta && is_file($ruta)
                    && (($datos && str_starts_with($ruta, $datos.'/')) || str_starts_with($ruta, $raiz.'/'))) {
                $entrada[] = ['nombre' => basename($ruta), 'sha256' => hash_file('sha256', $ruta), 'tam' => filesize($ruta)];
            }
        }

        return response()->json(['raiz' => '{OneDrive}', 'ficheros' => $ficheros, 'originales' => $originales, 'entrada' => $entrada]);
    }

    public function archivo(Request $r, string $cliente)
    {
        $this->autorizar($r);
        $ruta = $this->absoluta($cliente, (string) $r->query('ruta'));
        abort_unless($ruta, 404);

        return response()->file($ruta);
    }

    /**
     * Carpeta de entrada por PC (13-nov-2026): el PC trabajador mira «Facturas recibidas\Entrada» de cada cliente (cliente.json
     * «carpeta_facturas_recibidas», con {OneDrive}/{AAAA}), sube los PDF nuevos y los mueve a «Subidas VPS». Esto le da los clientes
     * con su plantilla y los ids de lo ya contabilizado (para vaciar «Subidas VPS» de lo que ya está en su ruta definitiva).
     */
    public function entradaConfig(Request $r)
    {
        $this->autorizar($r);
        $out = [];
        foreach (\App\Support\FacturasOcrEntrada::todosLosClientes() as $n => $cfg) {
            if (! empty($cfg['carpeta_facturas_recibidas'])) {
                $out[] = ['cliente' => $n, 'carpeta' => $cfg['carpeta_facturas_recibidas'], 'validadas' => \App\Support\FacturasOcrEntrada::idsValidadas($n)];
            }
        }

        return response()->json(['clientes' => $out]);
    }

    /** Un PDF de la carpeta de entrada (cuerpo = el fichero, X-Nombre = nombre en base64). Misma regla de duplicados que la subida de la web. */
    public function entradaSubir(Request $r, string $cliente)
    {
        $this->autorizar($r);
        $nombre = basename((string) base64_decode((string) $r->header('X-Nombre'), true));
        abort_if($nombre === '' || ! array_key_exists($cliente, \App\Support\FacturasOcrEntrada::todosLosClientes()), 404);
        $tmp = tempnam(sys_get_temp_dir(), 'focr');
        file_put_contents($tmp, $r->getContent());
        try {
            $res = \App\Support\FacturasOcrEntrada::importar($cliente, [['ruta' => $tmp, 'nombre' => $nombre]], false);
        } finally {
            @unlink($tmp);
        }
        $estado = $res['guardadas'] ? 'guardada' : ($res['repetidas'] ? 'repetida' : 'rechazada');

        return response()->json(['estado' => $estado]);
    }

    /**
     * Lectura automática (13-nov-2026): lanza en segundo plano, sin pantalla, «leer_auto» del cliente (lee lo nuevo de su Entrada con el ciclo
     * de la última lectura). Si ya hay un facturas_ocr.py de ese cliente trabajando, no hace nada (la siguiente pasada recogerá lo nuevo).
     */
    public function entradaLeer(Request $r, string $cliente)
    {
        $this->autorizar($r);
        abort_unless(array_key_exists($cliente, \App\Support\FacturasOcrEntrada::todosLosClientes()), 404);
        $base = rtrim((string) config('contabilidad.facturasocr_dir'), '/');
        $datos = FacturasOcr::rutaDatos($base.'/'.basename($cliente));
        $lock = '/tmp/facturasocr-'.strtolower(preg_replace('/[^A-Za-z0-9]/', '', $cliente)).'.lock';
        $f = @fopen($lock, 'c');
        if ($f && ! flock($f, LOCK_EX | LOCK_NB)) {
            return response()->json(['estado' => 'ocupado']);
        }
        if ($f) {
            flock($f, LOCK_UN);
        }
        $python = config('contabilidad.facturasocr_python') ?: storage_path('app/venv-facturasocr/bin/python');
        $env = 'ONEDRIVE_ROOT='.escapeshellarg(rtrim((string) config('contabilidad.facturasocr_onedrive'), '/')).' FACTURAS_OCR_MOTOR=tesseract OCR_CACHE_DIR='.escapeshellarg($datos.'/_ocr').' ';
        $cola = $datos.'/_cola';
        @mkdir($cola, 0775, true);
        file_put_contents($cola.'/lectura.inicio', (string) time());
        @unlink($cola.'/lectura.fin');
        $cmd = 'cd '.escapeshellarg($base).' && ('.$env.escapeshellarg($python).' facturas_ocr.py '.escapeshellarg($cliente).' leer_auto > '.escapeshellarg($cola.'/lectura.log')
            .' 2>&1; date +%s > '.escapeshellarg($cola.'/lectura.fin').') < /dev/null > /dev/null 2>&1 &';
        \Illuminate\Support\Facades\Process::run(['bash', '-c', 'nohup setsid bash -c '.escapeshellarg($cmd).' &']);

        return response()->json(['estado' => 'lanzada']);
    }
}
