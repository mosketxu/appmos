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

    // Comentarios de una casilla (ventana abierta): «obligación», periodo y texto nuevo
    public ?int $comOb = null;
    public string $comPer = '';
    public string $comTexto = '';

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

    /** Bloques plegados (T1..T4, A). */
    public array $plegados = [];

    public function alternarBloque(string $k): void
    {
        in_array($k, ['T1', 'T2', 'T3', 'T4', 'A'], true) || abort(422);
        $this->plegados = in_array($k, $this->plegados, true) ? array_values(array_diff($this->plegados, [$k])) : [...$this->plegados, $k];
    }

    /** ¿Cae un impuesto anual en la vista actual? (año: siempre; trimestre o mes: si su mes cae ahí). */
    protected function anualEnVista(object $m): bool
    {
        $mes = Imp::mesDe('A', $m->mes_anual);

        return match (true) {
            $this->vista === 'anio' => true,
            $this->esMes() => $mes === (int) $this->vista,
            default => (int) ceil($mes / 3) === (int) substr($this->vista, 1),
        };
    }

    /**
     * Orden de la pantalla: trimestre → mes → impuesto. Bloques de columnas, cada uno con sus columnas [ps periodo según la periodicidad de la obligación, g grupo («01», «03 / T1»…), cod impuesto]:
     * T1 [01 02 03 | T1] (con los impuestos mensuales bajo cada mes y los trimestrales bajo T1), …, y al final las «Anuales».
     * Un anual con `despues_de` (D2) va justo después de ese trimestre. $tiene = [kind => [codigo => true]] de las obligaciones visibles.
     */
    protected function bloques(array $modelos, array $tiene): array
    {
        $mesSel = $this->esMes() ? (int) $this->vista : null;
        $qs = $this->vista === 'anio' ? [1, 2, 3, 4] : [$mesSel ? (int) ceil($mesSel / 3) : (int) substr($this->vista, 1)];
        $mens = array_filter($modelos, fn ($m) => isset($tiene['M'][$m->codigo]));
        $trim = array_filter($modelos, fn ($m) => isset($tiene['T'][$m->codigo]) || isset($tiene['P'][$m->codigo]));
        $anuales = array_filter($modelos, fn ($m) => isset($tiene['A'][$m->codigo]) && $this->anualEnVista($m));
        $out = [];
        foreach ($qs as $q) {
            $cols = [];
            $pagos = [1 => 'P1', 3 => 'P2', 4 => 'P3'][$q] ?? null;
            foreach ($mesSel ? [$mesSel] : range(3 * $q - 2, 3 * $q) as $m) {
                // El mes que cierra el trimestre y el trimestre comparten columnas (03 / T1): un 303 mensual y uno trimestral van en la misma
                $cierra = $m % 3 === 0;
                $mods = $cierra ? $mens + $trim : $mens;
                uasort($mods, fn ($x, $y) => $x->orden <=> $y->orden);
                foreach ($mods as $mod) {
                    $ps = [];
                    if (isset($tiene['M'][$mod->codigo])) {
                        $ps['M'] = sprintf('%02d', $m);
                    }
                    if ($cierra && isset($tiene['T'][$mod->codigo])) {
                        $ps['T'] = 'T'.$q;
                    }
                    if ($cierra && $pagos && isset($tiene['P'][$mod->codigo])) {
                        $ps['P'] = $pagos;
                    }
                    if ($ps) {
                        $cols[] = ['ps' => $ps, 'g' => $cierra ? sprintf('%02d / T%d', $m, $q) : sprintf('%02d', $m), 'cod' => $mod->codigo];
                    }
                }
            }
            if ($cols) {
                $out[] = ['k' => 'T'.$q, 'titulo' => 'T'.$q, 'plegable' => true, 'cols' => $cols];
            }
            foreach ($anuales as $cod => $mod) {
                if ($mod->despues_de === 'T'.$q) {
                    $out[] = ['k' => 'D'.$cod, 'titulo' => $cod, 'plegable' => false, 'cols' => [['ps' => ['A' => 'A'], 'g' => 'Anual', 'cod' => $cod]]];
                }
            }
        }
        $cols = [];
        foreach ($anuales as $cod => $mod) {
            $enSuTrimestre = in_array($mod->despues_de, ['T1', 'T2', 'T3', 'T4'], true) && in_array((int) substr($mod->despues_de, 1), $qs, true);
            if (! $enSuTrimestre) {
                $cols[] = ['ps' => ['A' => 'A'], 'g' => 'Anual', 'cod' => $cod];
            }
        }
        if ($cols) {
            $out[] = ['k' => 'A', 'titulo' => 'Anuales', 'plegable' => true, 'cols' => $cols];
        }

        return $out;
    }

    protected function consulta()
    {
        $q = DB::table('entidad_impuestos as ei')
            ->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->leftJoin('sumas as s', 's.id', '=', 'e.suma_id')
            ->where('m.activo', true)
            ->select('ei.id', 'ei.entidad_id', 'ei.etiqueta', 'ei.periodicidad', 'ei.user_id', 'e.entidad', 'e.alias', 'e.estado as estado_ent', 's.nombre as resp',
                'm.codigo', 'm.nombre as modelo_nombre', 'm.orden', 'm.mes_anual', 'm.despues_de', 'm.desfase')
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

    // ------------------------------------------------------------------ comentarios

    public function abrirComentarios(int $obId, string $periodo): void
    {
        $this->autorizarCasilla($obId);
        $this->comOb = $obId;
        $this->comPer = $periodo;
        $this->comTexto = '';
    }

    public function cerrarComentarios(): void
    {
        $this->comOb = null;
        $this->comPer = '';
        $this->comTexto = '';
    }

    public function comentar(): void
    {
        abort_unless($this->comOb, 422);
        $this->autorizarCasilla($this->comOb);
        $texto = trim($this->comTexto);
        if ($texto === '') {
            return;
        }
        DB::table('impuesto_comentarios')->insert(['entidad_impuesto_id' => $this->comOb, 'ejercicio' => $this->ejercicio, 'periodo' => $this->comPer,
            'texto' => mb_substr($texto, 0, 2000), 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->comTexto = '';
    }

    public function borrarComentario(int $id): void
    {
        $c = DB::table('impuesto_comentarios')->find($id);
        if (! $c) {
            return;
        }
        $this->autorizarCasilla($c->entidad_impuesto_id);
        abort_unless($c->user_id === auth()->id() || $this->puedeTodos(), 403);   // el suyo; Admin y Suma, cualquiera
        DB::table('impuesto_comentarios')->where('id', $id)->delete();
    }

    /** Datos de la ventana de comentarios abierta. */
    public function getComentariosAbiertosProperty(): array
    {
        if (! $this->comOb) {
            return [];
        }
        $ob = DB::table('entidad_impuestos as ei')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.id', $this->comOb)->first(['ei.etiqueta', 'e.entidad', 'm.codigo', 'm.desfase']);
        $lista = DB::table('impuesto_comentarios as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where(['c.entidad_impuesto_id' => $this->comOb, 'c.ejercicio' => $this->ejercicio, 'c.periodo' => $this->comPer])
            ->orderBy('c.id')->get(['c.id', 'c.texto', 'c.user_id', 'c.created_at', 'u.name']);

        return ['ob' => $ob, 'lista' => $lista];
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
        $cuenta = ['pendiente' => 0, 'revision' => 0, 'revisado' => 0, 'presentado' => 0, 'visto' => 0];
        $modelosVis = [];
        $tiene = ['M' => [], 'T' => [], 'P' => [], 'A' => []];
        $porEnt = [];
        foreach ($obs as $ob) {
            $modelosVis[$ob->codigo] ??= (object) ['codigo' => $ob->codigo, 'nombre' => $ob->modelo_nombre, 'orden' => $ob->orden, 'mes_anual' => $ob->mes_anual, 'despues_de' => $ob->despues_de];
            $tiene[$ob->periodicidad][$ob->codigo] = true;
            $porEnt[$ob->entidad_id][$ob->codigo][] = $ob;
        }
        uasort($modelosVis, fn ($x, $y) => $x->orden <=> $y->orden);
        $bloques = $this->bloques($modelosVis, $tiene);
        $coment = [];   // [«obligación|periodo»] => [[texto, autor, fecha]] de las casillas visibles
        foreach (array_chunk($ids ?: [0], 1000) as $trozo) {
            DB::table('impuesto_comentarios as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->where('c.ejercicio', $this->ejercicio)->whereIn('c.entidad_impuesto_id', $trozo)
                ->orderBy('c.id')->get(['c.entidad_impuesto_id', 'c.periodo', 'c.texto', 'c.created_at', 'u.name'])->each(function ($c) use (&$coment) {
                    $coment[$c->entidad_impuesto_id.'|'.$c->periodo][] = [$c->texto, $c->name, $c->created_at];
                });
        }
        $filas = [];
        foreach ($obs->groupBy('entidad_id') as $entId => $lista) {
            $matriz = [];    // [bloque][columna] => [[ob, periodo, estado]]
            $resumen = [];   // [bloque] => [estado => n]  (para los bloques plegados)
            $hay = $pend = false;
            foreach ($bloques as $bl) {
                foreach ($bl['cols'] as $ci => $col) {
                    foreach ($porEnt[$entId][$col['cod']] ?? [] as $ob) {
                        $per = $col['ps'][$ob->periodicidad] ?? null;
                        $e = $per ? ($estados[$ob->id][$per] ?? null) : null;
                        if ($e === null) {
                            continue;
                        }
                        $matriz[$bl['k']][$ci][] = [$ob, $per, $e];
                        $resumen[$bl['k']][$e] = ($resumen[$bl['k']][$e] ?? 0) + 1;
                        $hay = true;
                        $pend = $pend || $e === 'pendiente';
                        if (isset($cuenta[$e])) {
                            $cuenta[$e]++;
                        }
                    }
                }
            }
            if (! $hay || ($this->soloPendientes && ! $pend)) {
                continue;
            }
            $filas[$entId] = ['ent' => $lista->first(), 'matriz' => $matriz, 'resumen' => $resumen];
        }
        // columnas que de verdad se usan (no se pintan impuestos/bloques sin ninguna casilla)
        $usadas = [];
        foreach ($filas as $f) {
            foreach ($f['matriz'] as $bk => $cols) {
                foreach (array_keys($cols) as $ci) {
                    $usadas[$bk][$ci] = true;
                }
            }
        }
        foreach ($bloques as $i => $bl) {
            $bloques[$i]['cols'] = array_filter($bl['cols'], fn ($ci) => isset($usadas[$bl['k']][$ci]), ARRAY_FILTER_USE_KEY);
            if (! $bloques[$i]['cols']) {
                unset($bloques[$i]);
            }
        }
        $modelos = DB::table('impuesto_modelos')->where('activo', true)->orderBy('orden')->get(['codigo', 'nombre']);

        return view('livewire.impuestos', ['filas' => $filas, 'bloques' => $bloques, 'coment' => $coment, 'obsPorId' => $obs->keyBy('id'), 'estados' => $estados, 'docs' => $docs, 'cuenta' => $cuenta, 'modelos' => $modelos]);
    }
}
