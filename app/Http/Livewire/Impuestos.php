<?php

namespace App\Http\Livewire;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use App\Support\Impuestos as Imp;
use App\Support\ImpuestosPdfs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pestaña «Impuestos» del TO-DO (7-oct-2026): qué impuestos tiene pendientes cada cliente, por periodo.
 * Una fila por cliente e impuesto; una casilla por periodo (clic = siguiente estado: vacío → pendiente rojo → revisión naranja →
 * revisado azul → presentado verde). Al lado de la marca, el PDF (presentado, o borrador en revisión/revisado).
 * Vistas: año completo (meses agrupados por trimestre + anual), un trimestre o un mes.
 * Cada usuario ve solo sus impuestos (los de sus entidades o asignados a él); Admin y Suma pueden pedir verlos todos.
 * Ver App\Support\Impuestos y Contabilidad/Impuestos/LEEME.md.
 */
class Impuestos extends Component
{
    use WithFileUploads;

    public int $ejercicio;

    /** 'anio' | 'T1'..'T4' | '01'..'12' */
    public string $vista = 'anio';

    public string $buscar = '';
    public string $filtroModelo = '';
    public bool $soloPendientes = false;
    public bool $incluirBajas = false;
    public bool $verTodos = false;

    /** Casilla a la que se sube el PDF: «obligación|periodo». */
    public string $subirA = '';
    public $archivo;

    // PDF sin cliente asignado
    public bool $mostrarSinAsignar = false;
    public string $asignarTexto = '';
    public string $buscarEnt = '';
    public string $asignarEtiqueta = '';

    public ?int $tareaPdfs = null;
    public string $aviso = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $this->ejercicio = (int) now()->format('Y');
        Imp::asegurarEjercicio($this->ejercicio);
    }

    public function cambiarEjercicio(int $d): void
    {
        $this->ejercicio += $d;
        Imp::asegurarEjercicio($this->ejercicio);
    }

    public function puedeVisto(): bool
    {
        return Imp::puedeVisto();
    }

    public function puedeTodos(): bool
    {
        return Imp::esGestor();
    }

    // ------------------------------------------------------------------ datos

    protected function esMes(): bool
    {
        return ctype_digit($this->vista);
    }

    /** Trimestre al que pertenece cada periodo (los anuales, el de su mes) para colocarlo en las columnas de trimestre. */
    protected function trimestreDeCelda(object $ob, string $periodo): int
    {
        return Imp::trimestreDe($periodo) ?? (int) ceil(Imp::mesDe('A', $ob->mes_anual) / 3);
    }

    /**
     * Columnas de un impuesto en la vista: [['k' => clave, 't' => título]].
     * Anuales (catálogo periodicidad A): una columna «Anual» (en un trimestre o un mes, solo si caen en él).
     * Resto: año → T1..T4; un trimestre → esa columna (con los 3 meses dentro si es mensual); un mes → una columna.
     */
    public function columnasDe(object $modelo): array
    {
        if ($modelo->periodicidad === 'A') {
            $mes = Imp::mesDe('A', $modelo->mes_anual);
            $cae = match (true) {
                $this->vista === 'anio' => true,
                $this->esMes() => $mes === (int) $this->vista,
                default => (int) ceil($mes / 3) === (int) substr($this->vista, 1),
            };

            return $cae ? [['k' => 'A', 't' => 'Anual']] : [];
        }
        if ($this->vista === 'anio') {
            return array_map(fn ($q) => ['k' => $q, 't' => 'T'.$q], [1, 2, 3, 4]);
        }
        if ($this->esMes()) {
            return [['k' => (int) $this->vista, 't' => Imp::MESES[(int) $this->vista - 1]]];
        }

        return [['k' => (int) substr($this->vista, 1), 't' => $this->vista]];
    }

    /** Periodos de una obligación que van en una columna de la vista. */
    public function periodosEn(object $ob, $k): array
    {
        if ($k === 'A') {
            return $ob->periodicidad === 'A' ? ['A'] : [];
        }
        if ($this->esMes()) {
            $m = (int) $this->vista;
            $p = match ($ob->periodicidad) {
                'M' => sprintf('%02d', $m),
                'T' => $m % 3 === 0 ? 'T'.($m / 3) : null,
                'P' => [3 => 'P1', 9 => 'P2', 12 => 'P3'][$m] ?? null,
                default => Imp::mesDe('A', $ob->mes_anual) === $m ? 'A' : null,
            };

            return $p ? [$p] : [];
        }
        $q = (int) $k;

        return match ($ob->periodicidad) {
            'M' => array_map(fn ($m) => sprintf('%02d', $m), range(3 * $q - 2, 3 * $q)),
            'T' => ['T'.$q],
            'P' => ($pp = [1 => 'P1', 3 => 'P2', 4 => 'P3'][$q] ?? null) ? [$pp] : [],
            default => $this->trimestreDeCelda($ob, 'A') === $q ? ['A'] : [],
        };
    }

    protected function consulta()
    {
        $q = DB::table('entidad_impuestos as ei')
            ->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->leftJoin('sumas as s', 's.id', '=', 'e.suma_id')
            ->where('m.activo', true)
            ->select('ei.id', 'ei.entidad_id', 'ei.etiqueta', 'ei.periodicidad', 'ei.user_id', 'e.entidad', 'e.alias', 'e.estado as estado_ent', 's.nombre as resp',
                'm.codigo', 'm.nombre as modelo_nombre', 'm.orden', 'm.mes_anual', 'm.desfase')
            ->orderBy('e.entidad')->orderBy('m.orden');
        Imp::soloVisibles($q, null, $this->verTodos);
        if (! $this->incluirBajas) {
            $q->where('e.estado', 1);
        }
        if ($this->filtroModelo !== '') {
            $q->where('m.codigo', $this->filtroModelo);
        }
        if (trim($this->buscar) !== '') {
            $b = '%'.trim($this->buscar).'%';
            $q->where(fn ($w) => $w->where('e.entidad', 'like', $b)->orWhere('e.alias', 'like', $b)->orWhere('e.codigo_cliente', 'like', $b));
        }

        return $q;
    }

    // ------------------------------------------------------------------ acciones

    protected function autorizarCasilla(int $obId): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver') && Imp::puedeVer($obId, $this->verTodos), 403);
    }

    /** Clic en una casilla: pasa al siguiente estado. */
    public function clic(int $obId, string $periodo): void
    {
        $this->autorizarCasilla($obId);
        $ob = DB::table('entidad_impuestos')->find($obId);
        if (! $ob || ! in_array($periodo, Imp::periodos($ob->periodicidad), true)) {
            return;
        }
        $fila = DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $obId, 'ejercicio' => $this->ejercicio, 'periodo' => $periodo])->first();
        $actual = $fila->estado ?? 'no';
        if ($actual === 'visto' && ! Imp::puedeVisto()) {
            return;   // lo ha validado Marta: solo ella lo cambia
        }
        $nuevo = Imp::siguiente($actual);
        if ($nuevo === 'visto' && ! Imp::puedeVisto()) {
            $nuevo = Imp::siguiente('visto');   // los demás se saltan «visto»: de presentado a vacío
        }
        if ($fila) {
            DB::table('impuesto_estados')->where('id', $fila->id)->update(['estado' => $nuevo, 'user_id' => auth()->id(), 'updated_at' => now()]);
        } else {
            DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $obId, 'ejercicio' => $this->ejercicio, 'periodo' => $periodo, 'estado' => $nuevo,
                'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Se ha elegido un PDF para la casilla $subirA: se guarda como borrador (revisión/revisado) o como presentado. */
    public function updatedArchivo(): void
    {
        $this->validate(['archivo' => 'file|mimes:pdf|max:30720'], ['archivo.mimes' => 'Tiene que ser un PDF.', 'archivo.max' => 'El PDF pesa más de 30 MB.']);
        [$obId, $periodo] = array_pad(explode('|', $this->subirA, 2), 2, '');
        $obId = (int) $obId;
        $this->autorizarCasilla($obId);
        $ob = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->where('ei.id', $obId)->first(['ei.*', 'm.codigo']);
        $estado = DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $obId, 'ejercicio' => $this->ejercicio, 'periodo' => $periodo])->value('estado');
        if (! $ob || ! $estado) {
            $this->archivo = null;

            return;
        }
        $nombre = basename((string) $this->archivo->getClientOriginalName());
        $sha = hash_file('sha256', $this->archivo->getRealPath());
        $almacen = 'impuestos/docs/'.$sha.'.pdf';
        Storage::disk('local')->put($almacen, fopen($this->archivo->getRealPath(), 'rb'));
        ImpuestoDocumento::create(['entidad_id' => $ob->entidad_id, 'modelo' => $ob->codigo, 'etiqueta' => $ob->etiqueta, 'ejercicio' => $this->ejercicio, 'periodo' => $periodo,
            'tipo' => in_array($estado, ['presentado', 'visto'], true) ? 'presentado' : 'borrador', 'nombre' => $nombre, 'almacen' => $almacen, 'tam' => $this->archivo->getSize(),
            'sha256' => $sha, 'origen' => 'web', 'user_id' => auth()->id()]);
        $this->archivo = null;
        $this->subirA = '';
    }

    // ------------------------------------------------------------------ PDF de OneDrive

    public function actualizarPdfs(): void
    {
        abort_unless($this->puedeTodos(), 403);
        if (DB::table('tareas')->where('proceso', 'pc.impuestos_pdfs')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->aviso = 'Ya hay una búsqueda de PDF en marcha.';

            return;
        }
        $this->tareaPdfs = ColaTareas::crear('pc.impuestos_pdfs', ['anios' => [$this->ejercicio]], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->aviso = 'Pedido a un PC: buscará los PDF en OneDrive (puede tardar unos minutos).';
    }

    public function getTareaPdfsEstadoProperty(): ?object
    {
        return DB::table('tareas')->where('proceso', 'pc.impuestos_pdfs')->orderByDesc('id')->first(['id', 'estado', 'resultado', 'terminada_at', 'created_at']);
    }

    public function getSinAsignarProperty()
    {
        if (! $this->puedeTodos()) {
            return collect();
        }

        return DB::table('impuesto_documentos')->whereNull('entidad_id')->where('origen', 'onedrive')->whereNotNull('modelo')
            ->select('cliente_texto', DB::raw('count(*) as n'))->groupBy('cliente_texto')->orderByDesc('n')->orderBy('cliente_texto')->get();
    }

    public function getResultadosEntidadProperty()
    {
        $b = trim($this->buscarEnt);
        if (strlen($b) < 2) {
            return collect();
        }

        return DB::table('entidades')->where(fn ($w) => $w->where('entidad', 'like', "%$b%")->orWhere('alias', 'like', "%$b%"))
            ->orderByDesc('estado')->orderBy('entidad')->limit(8)->get(['id', 'entidad', 'estado']);
    }

    /** Asigna a una entidad todos los PDF con ese texto de cliente y recuerda el nombre para los próximos. */
    public function asignar(int $entidadId): void
    {
        abort_unless($this->puedeTodos() && $this->asignarTexto !== '', 403);
        $a = ImpuestosPdfs::normalizar($this->asignarTexto);
        if (strlen($a) >= 2 && DB::table('entidades')->where('id', $entidadId)->exists()) {
            DB::table('impuesto_alias')->updateOrInsert(['alias' => $a], ['entidad_id' => $entidadId, 'etiqueta' => trim($this->asignarEtiqueta), 'created_at' => now(), 'updated_at' => now()]);
            ImpuestosPdfs::reasociarSinAsignar();
        }
        $this->asignarTexto = '';
        $this->buscarEnt = '';
        $this->asignarEtiqueta = '';
    }

    public function render()
    {
        $obs = $this->consulta()->get();
        $ids = $obs->pluck('id')->all();
        $estados = [];
        foreach (array_chunk($ids ?: [0], 1000) as $trozo) {
            DB::table('impuesto_estados')->where('ejercicio', $this->ejercicio)->whereIn('entidad_impuesto_id', $trozo)
                ->get(['entidad_impuesto_id', 'periodo', 'estado'])->each(function ($r) use (&$estados) {
                    $estados[$r->entidad_impuesto_id][$r->periodo] = $r->estado;
                });
        }
        $docs = [];
        $entIds = $obs->pluck('entidad_id')->unique()->all();
        foreach (array_chunk($entIds ?: [0], 1000) as $trozo) {
            DB::table('impuesto_documentos')->where('ejercicio', $this->ejercicio)->whereIn('entidad_id', $trozo)->whereNotNull('periodo')
                ->orderBy('id')->get(['id', 'entidad_id', 'modelo', 'etiqueta', 'periodo', 'tipo', 'nombre'])->each(function ($d) use (&$docs) {
                    $docs[$d->entidad_id.'|'.$d->modelo.'|'.$d->etiqueta.'|'.$d->periodo][] = $d;
                });
        }
        $filas = [];
        $grupos = [];   // impuestos presentes (por orden del catálogo) con sus columnas
        $cuenta = ['pendiente' => 0, 'revision' => 0, 'revisado' => 0, 'presentado' => 0, 'visto' => 0];
        foreach ($obs as $ob) {
            $modelo = (object) ['codigo' => $ob->codigo, 'nombre' => $ob->modelo_nombre, 'orden' => $ob->orden, 'mes_anual' => $ob->mes_anual,
                'periodicidad' => $ob->periodicidad === 'A' ? 'A' : 'Q'];
            $grupos[$ob->codigo] ??= $modelo;
            // un impuesto es «anual» si todas sus obligaciones lo son; si alguna no, se pinta por trimestres
            if ($ob->periodicidad !== 'A') {
                $grupos[$ob->codigo]->periodicidad = 'Q';
            }
        }
        foreach ($grupos as $g) {
            $g->columnas = $this->columnasDe($g);
        }
        $grupos = array_filter($grupos, fn ($g) => $g->columnas);
        uasort($grupos, fn ($x, $y) => $x->orden <=> $y->orden);
        foreach ($obs as $ob) {
            if (! isset($grupos[$ob->codigo])) {
                continue;
            }
            $celdas = [];
            $hay = $pend = false;
            foreach ($grupos[$ob->codigo]->columnas as $col) {
                $ps = $this->periodosEn($ob, $col['k']);
                $celdas[$col['k']] = $ps;
                foreach ($ps as $p) {
                    $hay = true;
                    $e = $estados[$ob->id][$p] ?? null;
                    if ($e && isset($cuenta[$e])) {
                        $cuenta[$e]++;
                    }
                    $pend = $pend || $e === 'pendiente';
                }
            }
            if (! $hay || ($this->soloPendientes && ! $pend)) {
                continue;
            }
            $filas[$ob->entidad_id]['ent'] ??= $ob;
            $filas[$ob->entidad_id]['por'][$ob->codigo][] = ['ob' => $ob, 'celdas' => $celdas];
        }
        // solo se pintan los impuestos que tiene alguna fila visible
        $usados = [];
        foreach ($filas as $f) {
            $usados += array_fill_keys(array_keys($f['por']), true);
        }
        $grupos = array_filter($grupos, fn ($g, $cod) => isset($usados[$cod]), ARRAY_FILTER_USE_BOTH);
        $modelos = DB::table('impuesto_modelos')->where('activo', true)->orderBy('orden')->get(['codigo', 'nombre']);

        return view('livewire.impuestos', ['filas' => $filas, 'grupos' => $grupos, 'estados' => $estados, 'docs' => $docs, 'cuenta' => $cuenta, 'modelos' => $modelos]);
    }
}
