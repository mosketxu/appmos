<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Neteges (30-sep-2026): conciliación bancaria como Bancos, pero consultando
 * más ficheros (de momento, además, el fichero de Ventas). Se replica aparte
 * porque tendrá características propias. Ver Contabilidad/Neteges/PLAN.md.
 *
 * Ficheros base, cada uno en su fila (botón o arrastrando), guardados en Base/Recibidos
 * con fecha/hora delante:
 *   - plan de cuentas y mayor de SAGE (uno solo con todas las cuentas): neteges_base.py los
 *     acumula en Base/Base Neteges.xlsx, como Bancos. Del mayor solo se guardan las cuentas
 *     de banco: 572... y las "otras cuentas de banco" marcadas en pantalla.
 *   - ficheros de Ventas (varios, de una vez o en varias): neteges_ventas.py los acumula sin
 *     duplicados en Base/Ventas Neteges.xlsx (con el que se trabaja) y avisa si una factura
 *     que ya estaba llega distinta.
 * Extracto del banco con su cuenta: de momento solo se guarda en Input (el proceso, después).
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class Neteges extends Component
{
    use WithFileUploads;

    /** Ficheros base recién subidos; al terminar la subida el navegador llama a procesarSubidas(fila). */
    public array $subidas = [];

    /** Cuenta que no empieza por 572 a marcar como de banco (p.ej. 551002). */
    public string $otraCuenta = '';

    /** Extracto del banco y cuenta a la que pertenece. */
    public $extracto = null;
    public string $cuenta = '';

    /** neteges_base.py --estado: plan, cada cuenta, último mayor, otras cuentas de banco, ventas. */
    public array $estadoBase = [];

    /** neteges_ventas.py --estado: líneas, facturas, desde/hasta, ficheros, cambios. */
    public array $estadoVentas = [];

    /** Salida de la última ejecución de neteges_base.py / neteges_ventas.py. */
    public string $salida = '';

    public function mount(): void
    {
        $this->cargarEstado();
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.neteges_dir'), '/');
    }

    protected function basePath(): string
    {
        return $this->baseDir().'/Base/Base Neteges.xlsx';
    }

    /** venv propio si existe (openpyxl + xlrd), si no el python3 del sistema. */
    protected function pythonBin(): string
    {
        $venv = $this->baseDir().'/.venv/bin/python3';
        return is_file($venv) ? $venv : 'python3';
    }

    public function cargarEstado(): void
    {
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'neteges_base.py', '--estado']);
            $this->estadoBase = $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $e) {
            $this->estadoBase = [];
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'neteges_ventas.py', '--estado']);
            $this->estadoVentas = $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $e) {
            $this->estadoVentas = [];
        }
    }

    /** Cuentas de banco cargadas en la base (pestañas con código de cuenta). */
    protected function cuentasBanco(): array
    {
        if (! is_file($this->basePath())) {
            return [];
        }
        try {
            $hojas = IOFactory::createReader('Xlsx')->listWorksheetNames($this->basePath());
        } catch (\Throwable $e) {
            return [];
        }
        $c = array_values(array_filter($hojas, fn ($n) => preg_match('/^\d{6,}$/', $n)));
        sort($c);
        return $c;
    }

    /** Ejecuta neteges_base.py (o el script que se diga) y deja el texto en $salida; devuelve si fue bien. */
    protected function ejecutar(array $args, string $etiqueta, string $script = 'neteges_base.py'): bool
    {
        try {
            $r = Process::path($this->baseDir())->timeout(300)->run(array_merge([$this->pythonBin(), $script], $args));
            $ok = $r->successful();
            $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $r->output()."\n".$r->errorOutput()));
        } catch (\Throwable $e) {
            $ok = false;
            $texto = $e->getMessage();
        }
        $this->salida = "===== {$etiqueta} =====\n{$texto}";
        $this->dispatch('proceso-terminado', mensaje: ($ok ? "✅ {$etiqueta}\nTerminado." : "⚠️ {$etiqueta}\nCon avisos o errores: mira la Salida."));
        $this->cargarEstado();
        return $ok;
    }

    public function procesarSubidas(string $fila): void
    {
        $this->resetErrorBag('subidas');
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }
        if (! in_array($fila, ['plan', 'mayor', 'ventas'], true)) {
            $this->addError('subidas', 'Fila desconocida.');
            return;
        }
        $etiqueta = 'Neteges · '.['plan' => 'plan de cuentas', 'mayor' => 'mayor', 'ventas' => 'fichero Ventas'][$fila];
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }

        $extensiones = $fila === 'ventas' ? ['xlsx', 'xls', 'csv'] : ['xlsx', 'xls'];
        $malos = array_map(fn ($f) => $f->getClientOriginalName(),
            array_filter($ficheros, fn ($f) => ! in_array(strtolower($f->getClientOriginalExtension()), $extensiones, true)));
        if ($malos) {
            $this->addError('subidas', 'Solo '.implode(' / ', array_map(fn ($e) => ".{$e}", $extensiones)).'. No se ha subido nada; sobran: '.implode(', ', $malos));
            return;
        }

        $dir = $this->baseDir().'/Base/Recibidos';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('subidas', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $sello = date('Ymd-His');
        $rutas = [];
        foreach ($ficheros as $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            if (! @copy($f->getRealPath(), "{$dir}/{$sello} {$nombre}")) {
                $this->addError('subidas', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                return;
            }
            $rutas[] = "{$dir}/{$sello} {$nombre}";
        }

        if ($fila === 'ventas') {
            $this->ejecutar($rutas, $etiqueta, 'neteges_ventas.py');
            return;
        }
        $this->ejecutar(array_merge(['--espera', $fila], $rutas), $etiqueta);
    }

    public function anadirOtraCuenta(): void
    {
        $cuenta = trim($this->otraCuenta);
        if (! preg_match('/^\d{6,}$/', $cuenta)) {
            $this->addError('otraCuenta', 'Escribe la cuenta completa (6 cifras o más).');
            return;
        }
        $this->otraCuenta = '';
        $this->editarOtrasCuentas('anadir', $cuenta);
    }

    public function quitarOtraCuenta(string $cuenta): void
    {
        $this->editarOtrasCuentas('quitar', $cuenta);
    }

    protected function editarOtrasCuentas(string $accion, string $cuenta): void
    {
        $this->resetErrorBag('otraCuenta');
        $etiqueta = 'Neteges · '.($accion === 'anadir' ? 'añadir' : 'quitar')." cuenta de banco {$cuenta}";
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        $this->ejecutar(['--otras-cuentas', $accion, $cuenta], $etiqueta);
    }

    /** Si el nombre del extracto empieza por una de las cuentas, se preselecciona. */
    public function updatedExtracto(): void
    {
        $this->resetErrorBag('extracto');
        if ($this->extracto instanceof UploadedFile && $this->cuenta === ''
            && preg_match('/^(\d{6,})/', $this->extracto->getClientOriginalName(), $m)
            && in_array($m[1], $this->cuentasBanco(), true)) {
            $this->cuenta = $m[1];
        }
    }

    public function guardarExtracto(): void
    {
        $this->resetErrorBag(['extracto', 'cuenta']);
        if (! config('contabilidad.ejecucion_local')) {
            $this->avisarNoAutorizado('Neteges · extracto');
            return;
        }
        if (! in_array($this->cuenta, $this->cuentasBanco(), true)) {
            $this->addError('cuenta', 'Elige la cuenta del banco.');
            return;
        }
        if (! $this->extracto instanceof UploadedFile) {
            $this->addError('extracto', 'Sube el extracto del banco.');
            return;
        }
        $nombre = str_replace(['/', '\\'], '_', $this->extracto->getClientOriginalName());
        if (! in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ['xlsx', 'xls', 'xml', 'txt', 'n43', 'csv'], true)) {
            $this->addError('extracto', 'El extracto tiene que ser Excel (.xlsx / .xls), XML o TXT (Norma 43).');
            return;
        }
        $dir = $this->baseDir().'/Input';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('extracto', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        // Con la cuenta delante, para saber a qué cuenta pertenece cuando se procese
        $destino = "{$dir}/".(str_starts_with($nombre, $this->cuenta) ? $nombre : "{$this->cuenta} {$nombre}");
        if (file_exists($destino)) {
            $destino = "{$dir}/".date('Ymd-His').' '.basename($destino);
        }
        if (! @copy($this->extracto->getRealPath(), $destino)) {
            $this->addError('extracto', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
            return;
        }
        $this->extracto = null;
        $this->dispatch('proceso-terminado', mensaje: "✅ Neteges · extracto {$this->cuenta}\nGuardado en Input como ".basename($destino).".\n(El proceso todavía no está hecho.)");
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    /** Descarga un fichero de la carpeta de Neteges (Base/..., Input/...). */
    public function descargar(string $relativa)
    {
        $raiz = realpath($this->baseDir());
        $ruta = realpath($this->baseDir().'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta)) {
            $this->addError('subidas', "No se encuentra {$relativa}.");
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    /** Ficheros sueltos de <sub>, más recientes primero si $recientes. */
    protected function ficheros(string $sub, bool $recientes = false): array
    {
        $out = [];
        foreach (glob($this->baseDir().'/'.$sub.'/*') ?: [] as $f) {
            if (is_file($f) && ! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        natcasesort($out);
        $out = array_values($out);
        return $recientes ? array_reverse($out) : $out;
    }

    /** /mnt/e/Foo/Bar -> E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    protected function avisarNoAutorizado(string $etiqueta): void
    {
        $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
    }

    public function render()
    {
        return view('livewire.contabilidad.neteges', [
            'cuentas' => $this->cuentasBanco(),
            'hayBase' => is_file($this->basePath()),
            'recibidos' => array_slice($this->ficheros('Base/Recibidos', true), 0, 15),
            'pendientes' => $this->ficheros('Input'),
            'carpeta' => $this->rutaWindows($this->baseDir()),
        ]);
    }
}
