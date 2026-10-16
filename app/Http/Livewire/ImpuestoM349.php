<?php

namespace App\Http\Livewire;

use App\Support\ColaTareas;
use App\Support\Impuestos as Imp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuestos → M349 operaciones intracomunitarias (16-oct-2026). De momento solo para NAE Comunicacions (NIF en NIF_OPERATIVOS); con otro cliente
 * la pantalla dice «en construcción». Primer paso: carpeta de trabajo (OneDrive, explorador de LibroIva con el árbol onedrive.arbol_iva que sube un PC;
 * se recuerda por empresa) y fichero a tratar (se sube al servidor). Datos: storage/app/m349/<entidad>/ (carpeta.txt) y <AAAA>-<periodo>/entrada/.
 * Guía: Contabilidad/Impuestos/M349/PLAN.md.
 */
class ImpuestoM349 extends Component
{
    use WithFileUploads;

    /** Clientes para los que el proceso ya funciona (NAE Comunicacions, S.L.). */
    public const NIF_OPERATIVOS = ['B63442701'];

    public ?int $entidadId = null;
    public int $ejercicio;
    public string $periodo = '';
    public bool $verTodos = false;
    public string $carpeta = '';
    public string $carpetaElegida = '';
    public $subida = null;
    public bool $esperandoCarpetas = false;
    public string $error = '';
    public string $msg = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $g = (array) session('m349', []);
        $this->verTodos = Imp::esGestor() && (bool) ($g['verTodos'] ?? false);
        $this->ejercicio = (int) ($g['ejercicio'] ?? now()->format('Y'));
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? 0]) ? (int) $g['entidad'] : null;
        $this->periodo = (string) ($g['periodo'] ?? '');
        $this->alCambiarEmpresa();
    }

    // ------------------------------------------------------------------ empresas y periodos

    /** Empresas con el 349 en Impuestos (las del usuario; Admin/Suma «todas»): [id => nombre]. */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', '349')->where('e.estado', 1);
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->orderBy('e.entidad')->get(['e.id', 'e.entidad', 'e.alias', 'e.nif', 'ei.periodicidad'])
            ->mapWithKeys(fn ($r) => [$r->id => ($r->alias ?: $r->entidad).' · '.($r->periodicidad === 'M' ? 'mensual' : 'trimestral').($r->nif ? ' · '.$r->nif : '')])->all();
    }

    public function operativo(): bool
    {
        if (! $this->entidadId) {
            return false;
        }
        $nif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) DB::table('entidades')->where('id', $this->entidadId)->value('nif')));

        return in_array(preg_replace('/^ES/', '', $nif), self::NIF_OPERATIVOS, true);
    }

    protected function periodicidad(): string
    {
        return (string) (DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '349')->value('ei.periodicidad') ?: 'T');
    }

    /** ['09' => 'septiembre'] o ['T3' => '3T']. */
    public function periodos(): array
    {
        return $this->periodicidad() === 'M'
            ? collect(range(1, 12))->mapWithKeys(fn ($m) => [sprintf('%02d', $m) => Imp::MESES[$m - 1]])->all()
            : ['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'];
    }

    protected function alCambiarEmpresa(): void
    {
        $this->error = $this->msg = '';
        $this->carpeta = '';
        if (! $this->entidadId) {
            return;
        }
        if (! isset($this->periodos()[$this->periodo])) {   // por defecto, el último periodo terminado
            $mes = (int) now()->format('n') - 1 ?: 12;
            $this->periodo = $this->periodicidad() === 'M' ? sprintf('%02d', $mes) : 'T'.(int) ceil($mes / 3);
        }
        $f = $this->dirEmpresa().'/carpeta.txt';
        $this->carpeta = is_file($f) ? trim((string) file_get_contents($f)) : '';
    }

    protected function recordar(): void
    {
        session(['m349' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
    }

    public function updatedEntidadId(): void
    {
        $this->entidadId = (int) $this->entidadId ?: null;
        $this->alCambiarEmpresa();
        $this->recordar();
    }

    public function updatedPeriodo(): void
    {
        $this->recordar();
    }

    public function cambiarEjercicio(int $d): void
    {
        $this->ejercicio += $d;
        $this->recordar();
    }

    public function updatedVerTodos(): void
    {
        $this->verTodos = Imp::esGestor() && $this->verTodos;
        if (! isset($this->empresas()[$this->entidadId])) {
            $this->entidadId = null;
            $this->alCambiarEmpresa();
        }
        $this->recordar();
    }

    // ------------------------------------------------------------------ carpeta de trabajo (OneDrive)

    protected function autorizar(): void
    {
        abort_unless($this->entidadId && isset($this->empresas()[$this->entidadId]) && $this->operativo(), 403);
    }

    protected function dirEmpresa(): string
    {
        $d = storage_path('app/m349/'.(int) $this->entidadId);
        @mkdir($d, 0775, true);

        return $d;
    }

    protected function dirPeriodo(string $sub = ''): string
    {
        $this->autorizar();
        abort_unless(isset($this->periodos()[$this->periodo]) && $this->ejercicio > 2000, 422);
        $d = $this->dirEmpresa().'/'.$this->ejercicio.'-'.$this->periodo.($sub !== '' ? '/'.$sub : '');
        @mkdir($d, 0775, true);

        return $d;
    }

    /** El mismo árbol de carpetas de OneDrive que el Libro de IVA (filtrado por lo que puede ver el usuario). */
    public function arbolCarpetas(): array
    {
        try {
            $a = ColaTareas::estado('onedrive.arbol_iva');
        } catch (\Throwable $e) {
            $a = null;
        }
        $ok = fn ($d) => LibroIva::rutaPermitida((string) $d);

        return ['dirs' => array_values(array_filter((array) ($a['dirs'] ?? []), $ok)), 'marcas' => [], 'fecha' => (string) ($a['fecha'] ?? '')];
    }

    public function updatedCarpetaElegida(string $ruta): void
    {
        $this->autorizar();
        if ($ruta === '' || ! in_array($ruta, $this->arbolCarpetas()['dirs'], true) || ! ColaTareas::rutaRelativaSegura($ruta)) {
            return;
        }
        $this->carpeta = $ruta;
        $this->carpetaElegida = '';
        file_put_contents($this->dirEmpresa().'/carpeta.txt', $ruta);
        $this->msg = 'Carpeta de trabajo guardada para esta empresa.';
    }

    public function actualizarCarpetas(): void
    {
        $this->error = '';
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if (! $vivos) {
            $this->error = 'No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
            return;
        }
        if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            ColaTareas::crear('pc.arbol_carpetas', ['modo' => 'iva'], ColaTareas::pcElegido() ?: null, auth()->id());
        }
        $this->esperandoCarpetas = true;
    }

    public function revisar(): void
    {
        if ($this->esperandoCarpetas && ! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->esperandoCarpetas = false;
        }
    }

    // ------------------------------------------------------------------ fichero a tratar

    public function ficheros(): array
    {
        if (! $this->operativo() || ! isset($this->periodos()[$this->periodo])) {
            return [];
        }
        $out = [];
        foreach (glob($this->dirPeriodo('entrada').'/*') ?: [] as $f) {
            $out[] = ['nombre' => basename($f), 'tam' => filesize($f), 'fecha' => filemtime($f)];
        }

        return $out;
    }

    public function updatedSubida(): void
    {
        $this->error = $this->msg = '';
        if (! $this->subida instanceof UploadedFile) {
            return;
        }
        $ext = strtolower($this->subida->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'pdf'], true)) {
            $this->error = '«'.$this->subida->getClientOriginalName().'»: solo Excel, CSV, txt o PDF.';
            $this->subida = null;
            return;
        }
        $dir = $this->dirPeriodo('entrada');
        foreach (glob($dir.'/*') ?: [] as $viejo) {   // un fichero a tratar por periodo: el nuevo sustituye al anterior
            @unlink($viejo);
        }
        $nombre = preg_replace('/[^A-Za-z0-9._ &()-]+/', '_', basename($this->subida->getClientOriginalName()));
        copy($this->subida->getRealPath(), $dir.'/'.$nombre);
        $this->subida = null;
        $this->msg = 'Fichero «'.$nombre.'» guardado para '.$this->periodos()[$this->periodo].' '.$this->ejercicio.'.';
    }

    public function quitar(string $nombre): void
    {
        @unlink($this->dirPeriodo('entrada').'/'.basename($nombre));
        $this->msg = '';
    }

    public function render()
    {
        return view('livewire.impuesto-m349', ['empresas' => $this->empresas(), 'periodosLista' => $this->entidadId ? $this->periodos() : [],
            'esOperativo' => $this->operativo(), 'ficherosLista' => $this->ficheros(), 'arbol' => $this->operativo() ? $this->arbolCarpetas() : ['dirs' => [], 'marcas' => [], 'fecha' => '']]);
    }
}
