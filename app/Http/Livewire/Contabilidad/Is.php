<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuesto sobre Sociedades: genera el fichero .200 que se importa en Sociedades WEB.
 * El motor está en Contabilidad/IS/motor (construye.py); ver Contabilidad/IS/PLAN.md.
 *
 * Por cliente (entidad de Appmos con NIF) y ejercicio, en <IS_DIR>/clientes/<NIF>/<AAAA>:
 *   fuentes/   un fichero por cada entrada de FUENTES (se sube uno a uno; el anterior
 *              se guarda en fuentes/anteriores con fecha)
 *   ajustes.json   correcciones, forma de pago, IBAN, rectificativa y valores fijados a mano
 *   salida/    datos.json, revision.json y <NIF>_<AAAA>.200
 * y en <IS_DIR>/clientes/<NIF>/criterios.json los criterios del cliente que valen
 * todos los años (cuenta → casilla).
 *
 * No presenta nada: el .200 se descarga y la persona lo importa en Sociedades WEB,
 * lo revisa y lo presenta con su certificado desde su navegador.
 */
class Is extends Component
{
    use WithFileUploads;

    public const FUENTES = [
        'sumas_saldos' => ['Balance de sumas y saldos (SAGE)', ['xlsx', 'csv'], true,
            'Listado de sumas y saldos del ejercicio a 31/12, sin cerrar (con los grupos 6 y 7), exportado a Excel.'],
        'datos_fiscales' => ['Datos fiscales del IS (AEAT)', ['pdf'], true,
            'Sede AEAT → Impuesto sobre Sociedades → Datos fiscales del ejercicio (PDF).'],
        'm200_anterior' => ['Modelo 200 del ejercicio anterior', ['pdf'], true,
            'PDF de la declaración presentada el año pasado: de ahí salen administradores, socios, titular real, representantes e INCN.'],
        'mayor' => ['Mayor (SAGE)', ['xlsx', 'csv'], false,
            'Opcional: para ver el detalle de las cuentas que suelen llevar ajustes (678 multas, etc.).'],
        'm200_presentado' => ['Modelo 200 ya presentado de este ejercicio', ['pdf'], false,
            'Opcional: si ya se presentó (o para probar), se compara casilla a casilla.'],
    ];

    public $entidadId = '';
    public $ejercicio;

    /** Subidas, una por fuente (clave de FUENTES). */
    public $subida = [];

    /** Criterios del cliente: [['prefijo' => '649', 'casilla' => '00263'], ...] */
    public array $criterios = [];

    /** Ajustes del ejercicio. */
    public array $correcciones = [];
    public string $formaPago = '';
    public string $iban = '';
    public string $telefono = '';
    public bool $rectificativa = false;
    public string $justificanteAnterior = '';
    public string $ingresadoAnterior = '';
    public string $fijos = '';

    public string $salida = '';
    public ?array $revision = null;

    public function mount(): void
    {
        $this->ejercicio = (int) date('Y') - 1;
        $this->entidadId = (string) ($this->entidades()->first()->id ?? '');
        $this->cargar();
    }

    protected function entidades()
    {
        return Entidad::query()->whereNotNull('nif')->where('nif', '!=', '')->orderBy('entidad')->get(['id', 'entidad', 'nif', 'tfno', 'iban1']);
    }

    protected function entidad(): ?Entidad
    {
        return $this->entidadId !== '' ? Entidad::query()->find($this->entidadId) : null;
    }

    protected function nif(): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) optional($this->entidad())->nif));
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.is_dir'), '/');
    }

    protected function dirCliente(): string
    {
        return $this->baseDir().'/clientes/'.$this->nif();
    }

    protected function dirEjercicio(): string
    {
        return $this->dirCliente().'/'.(int) $this->ejercicio;
    }

    protected function listo(): bool
    {
        return $this->nif() !== '' && (int) $this->ejercicio >= 2000;
    }

    public function updatedEntidadId(): void
    {
        $this->cargar();
    }

    public function updatedEjercicio(): void
    {
        $this->cargar();
    }

    /** Lee criterios, ajustes y la última revisión del cliente y ejercicio elegidos. */
    public function cargar(): void
    {
        $this->resetErrorBag();
        $this->revision = null;
        $this->criterios = [];
        $this->correcciones = [];
        $this->formaPago = $this->iban = $this->telefono = $this->justificanteAnterior = $this->ingresadoAnterior = $this->fijos = '';
        $this->rectificativa = false;
        if (! $this->listo()) {
            return;
        }
        $crit = $this->leerJson($this->dirCliente().'/criterios.json');
        foreach ($crit['cuentas'] ?? [] as $pref => $cas) {
            $this->criterios[] = ['prefijo' => (string) $pref, 'casilla' => (string) $cas];
        }
        $aj = $this->leerJson($this->dirEjercicio().'/ajustes.json');
        foreach ($aj['correcciones'] ?? [] as $c) {
            $this->correcciones[] = ['casilla' => (string) ($c['casilla'] ?? ''), 'importe' => (string) ($c['importe'] ?? ''), 'texto' => (string) ($c['texto'] ?? '')];
        }
        $ent = $this->entidad();
        $this->formaPago = (string) ($aj['forma_pago'] ?? '');
        $this->iban = (string) ($aj['iban'] ?? preg_replace('/\s+/', '', (string) ($ent->iban1 ?? '')));
        $this->telefono = (string) ($aj['telefono'] ?? preg_replace('/\D/', '', (string) ($ent->tfno ?? '')));
        if (! empty($aj['rectificativa'])) {
            $this->rectificativa = true;
            $this->justificanteAnterior = (string) ($aj['rectificativa']['justificante'] ?? '');
            $this->ingresadoAnterior = (string) ($aj['rectificativa']['ingresado'] ?? '');
        }
        $lineas = [];
        foreach ($aj['fijos'] ?? [] as $k => $v) {
            $lineas[] = $k.' = '.(is_null($v) ? 'null' : (is_string($v) ? $v : json_encode($v)));
        }
        $this->fijos = implode("\n", $lineas);
        $rev = $this->leerJson($this->dirEjercicio().'/salida/revision.json');
        $this->revision = $rev ?: null;
    }

    protected function leerJson(string $ruta): array
    {
        $d = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    /** Se guarda cada fichero en cuanto termina de subir. */
    public function updatedSubida($valor, $clave): void
    {
        $this->resetErrorBag('subida.'.$clave);
        $f = $this->subida[$clave] ?? null;
        unset($this->subida[$clave]);
        if (! $f instanceof UploadedFile || ! isset(self::FUENTES[$clave])) {
            return;
        }
        if (! $this->autorizado("IS · subir {$clave}") || ! $this->listo()) {
            return;
        }
        $ext = strtolower($f->getClientOriginalExtension());
        if (! in_array($ext, self::FUENTES[$clave][1], true)) {
            $this->addError('subida.'.$clave, 'Tiene que ser '.implode(' / ', self::FUENTES[$clave][1]).'.');
            return;
        }
        $dir = $this->dirEjercicio().'/fuentes';
        if (! is_dir($dir.'/anteriores') && ! @mkdir($dir.'/anteriores', 0777, true)) {
            $this->addError('subida.'.$clave, 'No se ha podido crear la carpeta del cliente.');
            return;
        }
        foreach (glob($dir.'/'.$clave.'.*') ?: [] as $viejo) {
            @rename($viejo, $dir.'/anteriores/'.date('Ymd-His').' '.basename($viejo));
        }
        if (! @copy($f->getRealPath(), "{$dir}/{$clave}.{$ext}")) {
            $this->addError('subida.'.$clave, 'No se ha podido guardar el fichero.');
            return;
        }
        file_put_contents("{$dir}/{$clave}.origen.txt", $f->getClientOriginalName()."\n".date('d/m/Y H:i')."\n");
        $this->dispatch('proceso-terminado', mensaje: '✅ '.self::FUENTES[$clave][0]."\n".$f->getClientOriginalName());
    }

    /** Estado de cada fuente: nombre original y fecha de subida, o null. */
    protected function estadoFuentes(): array
    {
        $out = [];
        foreach (array_keys(self::FUENTES) as $clave) {
            $out[$clave] = null;
            if (! $this->listo()) {
                continue;
            }
            $ficheros = array_filter(glob($this->dirEjercicio().'/fuentes/'.$clave.'.*') ?: [], fn ($p) => ! str_ends_with($p, '.origen.txt'));
            if ($ficheros) {
                $origen = @file($this->dirEjercicio().'/fuentes/'.$clave.'.origen.txt', FILE_IGNORE_NEW_LINES) ?: [];
                $out[$clave] = ['nombre' => $origen[0] ?? basename(reset($ficheros)), 'fecha' => $origen[1] ?? date('d/m/Y H:i', filemtime(reset($ficheros)))];
            }
        }
        return $out;
    }

    public function anadirCriterio(): void
    {
        $this->criterios[] = ['prefijo' => '', 'casilla' => ''];
    }

    public function quitarCriterio(int $i): void
    {
        unset($this->criterios[$i]);
        $this->criterios = array_values($this->criterios);
    }

    public function anadirCorreccion(string $casilla = '', string $importe = '', string $texto = ''): void
    {
        $this->correcciones[] = ['casilla' => $casilla, 'importe' => $importe, 'texto' => $texto];
    }

    public function quitarCorreccion(int $i): void
    {
        unset($this->correcciones[$i]);
        $this->correcciones = array_values($this->correcciones);
    }

    /** Guarda criterios y ajustes en sus JSON. Devuelve false si hay algo mal escrito. */
    protected function guardar(): bool
    {
        $this->resetErrorBag();
        $cuentas = [];
        foreach ($this->criterios as $i => $c) {
            $p = trim($c['prefijo']);
            $cas = trim($c['casilla']);
            if ($p === '' && $cas === '') {
                continue;
            }
            if (! preg_match('/^\d{2,}$/', $p) || ! preg_match('/^\d{5}$/', $cas)) {
                $this->addError('criterios', "Criterio ".($i + 1).": la cuenta son dígitos y la casilla 5 dígitos.");
                return false;
            }
            $cuentas[$p] = $cas;
        }
        $corr = [];
        foreach ($this->correcciones as $i => $c) {
            if (trim($c['casilla']) === '' && trim($c['importe']) === '') {
                continue;
            }
            $imp = $this->numero($c['importe']);
            if (! preg_match('/^\d{5}$/', trim($c['casilla'])) || $imp === null) {
                $this->addError('correcciones', 'Corrección '.($i + 1).': casilla de 5 dígitos e importe (p. ej. 150 o 1.234,56).');
                return false;
            }
            $corr[] = ['casilla' => trim($c['casilla']), 'importe' => $imp, 'texto' => trim($c['texto'])];
        }
        $fijos = [];
        foreach (preg_split('/\r?\n/', $this->fijos) as $n => $l) {
            if (trim($l) === '') {
                continue;
            }
            if (! preg_match('/^\s*(\w{5})\.(#?\d+)\s*=\s*(.*?)\s*$/', $l, $m)) {
                $this->addError('fijos', 'Línea '.($n + 1).': formato página.casilla = valor (p. ej. 14000.00558 = 2122).');
                return false;
            }
            $v = $m[3];
            if ($v === 'null') {
                $v = null;
            } elseif (preg_match('/^-?\d+[.,]\d{1,2}$/', $v) || preg_match('/^-?\d{1,3}(\.\d{3})+,\d{2}$/', $v)) {
                $v = $this->numero($v);   // importe
            }
            $fijos[strtoupper($m[1]).'.'.$m[2]] = $v;
        }
        $aj = ['correcciones' => $corr, 'fijos' => (object) $fijos];
        foreach (['forma_pago' => $this->formaPago, 'iban' => preg_replace('/\s+/', '', strtoupper($this->iban)), 'telefono' => preg_replace('/\D/', '', $this->telefono)] as $k => $v) {
            if ($v !== '') {
                $aj[$k] = $v;
            }
        }
        if ($this->rectificativa) {
            $ing = $this->numero($this->ingresadoAnterior);
            if (! preg_match('/^\d{13}$/', trim($this->justificanteAnterior)) || $ing === null) {
                $this->addError('rectificativa', 'Rectificativa: justificante de 13 dígitos e importe ingresado (0 si no se ingresó nada).');
                return false;
            }
            $aj['rectificativa'] = ['justificante' => trim($this->justificanteAnterior), 'ingresado' => $ing];
        }
        if (! is_dir($this->dirEjercicio()) && ! @mkdir($this->dirEjercicio(), 0777, true)) {
            $this->addError('criterios', 'No se ha podido crear la carpeta del cliente.');
            return false;
        }
        file_put_contents($this->dirCliente().'/criterios.json', json_encode(['cuentas' => (object) $cuentas], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($this->dirEjercicio().'/ajustes.json', json_encode($aj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    /** '1.234,56' / '1234.56' / '150' → float; null si no es un número. */
    protected function numero(string $s): ?float
    {
        $s = trim(str_replace(['€', ' '], '', $s));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }
        return is_numeric($s) ? round((float) $s, 2) : null;
    }

    public function calcular(): void
    {
        $ent = $this->entidad();
        $etiqueta = 'IS · '.($ent->entidad ?? '').' · '.$this->ejercicio;
        if (! $this->autorizado($etiqueta)) {
            return;
        }
        if (! $this->listo()) {
            $this->addError('calcular', 'Elige un cliente con NIF y el ejercicio.');
            return;
        }
        if (! $this->guardar()) {
            return;
        }
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        try {
            $r = Process::path($this->baseDir())->timeout(180)->run([$this->pythonBin(), 'motor/construye.py', $this->dirEjercicio()]);
            $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $r->output()."\n".$r->errorOutput()));
            $this->salida .= $texto;
            $ok = $r->successful();
        } catch (\Throwable $e) {
            $this->salida .= "\n⚠️ ".get_class($e).': '.$e->getMessage();
            $ok = false;
            report($e);
        }
        $this->revision = $this->leerJson($this->dirEjercicio().'/salida/revision.json') ?: null;
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '✅ ' : '⚠️ ').$etiqueta."\n".($ok ? 'Fichero .200 generado.' : 'Hay errores: míralos en la pantalla.'));
    }

    public function descargar(string $que)
    {
        if (! $this->listo()) {
            return null;
        }
        $dir = $this->dirEjercicio().'/salida';
        $ruta = match ($que) {
            '200' => $dir.'/'.$this->nif().'_'.(int) $this->ejercicio.'.200',
            'datos' => $dir.'/datos.json',
            default => null,
        };
        if (! $ruta || ! is_file($ruta)) {
            $this->addError('calcular', 'No hay fichero generado: pulsa Calcular.');
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    /** Aplica una corrección propuesta desde el detalle del mayor. */
    public function proponerCorreccion(string $casilla, string $importe, string $texto): void
    {
        $this->anadirCorreccion($casilla, $importe, $texto);
    }

    protected function pythonBin(): string
    {
        if ($p = config('contabilidad.is_python')) {
            return $p;
        }
        $venv = $this->baseDir().'/.venv/bin/python3';
        return is_file($venv) ? $venv : 'python3';
    }

    protected function autorizado(string $etiqueta): bool
    {
        if (config('contabilidad.is_ejecucion')) {
            return true;
        }
        $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
        $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
        return false;
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    public function render()
    {
        $paginas = [];
        foreach ($this->revision['casillas'] ?? [] as $f) {
            $paginas[$f['pagina']][] = $f;
        }
        return view('livewire.contabilidad.is', [
            'entidades' => $this->entidades(),
            'fuentes' => self::FUENTES,
            'estado' => $this->estadoFuentes(),
            'paginas' => $paginas,
            'hay200' => $this->listo() && is_file($this->dirEjercicio().'/salida/'.$this->nif().'_'.(int) $this->ejercicio.'.200'),
            'carpeta' => $this->listo() ? 'clientes/'.$this->nif().'/'.(int) $this->ejercicio : '',
        ]);
    }
}
