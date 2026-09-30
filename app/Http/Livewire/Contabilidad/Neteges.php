<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Neteges (30-sep-2026): conciliación bancaria como Bancos, pero consultando
 * más ficheros (de momento, además, el fichero de Ventas). Se replica aparte
 * porque tendrá características propias. Ver Contabilidad/Neteges/PLAN.md.
 *
 * Por ahora solo recoge los ficheros (el proceso se hará después):
 *   - Ficheros base, cada uno en su fila (botón o arrastrando): plan de cuentas,
 *     mayor de cada cuenta de banco (572xxx...), mayor de otra cuenta y fichero
 *     de Ventas. Se guardan en Base/Recibidos con fecha/hora delante y se apuntan
 *     en Base/recibidos.json (de ahí salen las cuentas de banco y el estado de cada fila).
 *   - Extracto del banco con su cuenta: se guarda en Input.
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class Neteges extends Component
{
    use WithFileUploads;

    /** Ficheros base recién subidos; al terminar la subida el navegador llama a procesarSubidas(fila). */
    public array $subidas = [];

    /** Cuenta del "mayor de otra cuenta" si el nombre del fichero no empieza por ella. */
    public string $otraCuenta = '';

    /** Extracto del banco y cuenta a la que pertenece. */
    public $extracto = null;
    public string $cuenta = '';

    public const FILAS = [
        'plan' => ['icono' => '📘', 'titulo' => 'Plan de cuentas', 'boton' => 'Subir plan'],
        'ventas' => ['icono' => '🧾', 'titulo' => 'Fichero Ventas', 'boton' => 'Subir ventas'],
    ];

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.neteges_dir'), '/');
    }

    protected function manifiestoPath(): string
    {
        return $this->baseDir().'/Base/recibidos.json';
    }

    /** [{fila, cuenta, fichero, guardado, fecha}] en orden de subida. */
    protected function manifiesto(): array
    {
        $d = json_decode((string) @file_get_contents($this->manifiestoPath()), true);
        return is_array($d) ? $d : [];
    }

    /** Cuentas de banco con mayor subido, ordenadas. */
    protected function cuentasBanco(): array
    {
        $c = array_values(array_unique(array_filter(array_column($this->manifiesto(), 'cuenta'))));
        sort($c);
        return $c;
    }

    /** Último fichero y número de ficheros por fila ('plan', 'ventas' o la cuenta). */
    protected function estado(): array
    {
        $out = [];
        foreach ($this->manifiesto() as $m) {
            $clave = $m['cuenta'] ?: $m['fila'];
            $out[$clave] = ['n' => ($out[$clave]['n'] ?? 0) + 1, 'ultimo' => $m];
        }
        return $out;
    }

    public function procesarSubidas(string $fila): void
    {
        $this->resetErrorBag('subidas');
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }
        if (! isset(self::FILAS[$fila]) && $fila !== 'mayor' && ! preg_match('/^\d{6,}$/', $fila)) {
            $this->addError('subidas', 'Fila desconocida.');
            return;
        }
        $etiqueta = 'Neteges · '.(self::FILAS[$fila]['titulo'] ?? ($fila === 'mayor' ? 'mayor de otra cuenta' : "mayor {$fila}"));
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

        // Cuenta de cada mayor: la de la fila, o la del principio del nombre, o la escrita a mano
        $cuentas = [];
        foreach ($ficheros as $i => $f) {
            if (isset(self::FILAS[$fila])) {
                $cuentas[$i] = '';
            } elseif ($fila !== 'mayor') {
                $cuentas[$i] = $fila;
            } elseif (preg_match('/^(\d{6,})/', $f->getClientOriginalName(), $m)) {
                $cuentas[$i] = $m[1];
            } elseif (preg_match('/^\d{6,}$/', trim($this->otraCuenta))) {
                $cuentas[$i] = trim($this->otraCuenta);
            } else {
                $this->addError('subidas', "No sé de qué cuenta es {$f->getClientOriginalName()}: escribe la cuenta (p.ej. 551002) o pon el código al principio del nombre del fichero.");
                return;
            }
        }

        $dir = $this->baseDir().'/Base/Recibidos';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('subidas', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $sello = date('Ymd-His');
        $manifiesto = $this->manifiesto();
        $guardados = [];
        foreach ($ficheros as $i => $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            if (! @copy($f->getRealPath(), "{$dir}/{$sello} {$nombre}")) {
                $this->addError('subidas', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                break;
            }
            $manifiesto[] = ['fila' => isset(self::FILAS[$fila]) ? $fila : 'mayor', 'cuenta' => $cuentas[$i],
                'fichero' => $nombre, 'guardado' => "Base/Recibidos/{$sello} {$nombre}", 'fecha' => date('c')];
            $guardados[] = $nombre.($cuentas[$i] !== '' ? " (cuenta {$cuentas[$i]})" : '');
        }
        file_put_contents($this->manifiestoPath(), json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($guardados) {
            $this->otraCuenta = '';
            $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nGuardado: ".implode(', ', $guardados));
        }
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
        if (! in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            $this->addError('extracto', 'El extracto tiene que ser un Excel (.xlsx / .xls).');
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

    /** Descarga un fichero de la carpeta de Neteges (Base/Recibidos/..., Input/...). */
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
            'estado' => $this->estado(),
            'recibidos' => array_slice($this->ficheros('Base/Recibidos', true), 0, 15),
            'pendientes' => $this->ficheros('Input'),
            'carpeta' => $this->rutaWindows($this->baseDir()),
        ]);
    }
}
