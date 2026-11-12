<?php

namespace App\Http\Livewire;

use App\Support\ColaTareas;
use App\Support\Impuestos as Imp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Libro de IVA (303) maquetado (9-oct-2026). Pestaña Impuestos → «📒 Libro de IVA». Un PC trabajador, con OneDrive, hace el trabajo
 * (tarea `pc.libro_iva`, motor Contabilidad/Impuestos/M303/libro_iva.py): busca el Excel original del periodo en la carpeta IVA del cliente,
 * monta el libro (EXPEDIDAS/RECIBIDAS con subtotales por tipo de IVA y resultado) y lo deja en esa misma carpeta; aquí se ve el resultado
 * y se descargan el Excel y los avisos. La carpeta de cada empresa se elige con el selector de Windows (`pc.elegir_carpeta`) y se recuerda.
 */
class LibroIva extends Component
{
    use WithFileUploads;

    /** Excel original del periodo elegido en pantalla cuando no está en la carpeta: el PC lo deja allí al generar. */
    public $excelSubido = null;

    public string $entidadId = '';
    public string $ejercicio = '';
    public string $periodo = '3T';
    public string $carpeta = '';
    /** Si el resultado sale a favor: true = «A DEVOLVER», false = «A COMPENSAR». Parte de la marca REDEME de la entidad, se puede cambiar en cada cálculo. */
    public bool $devolver = false;
    /** Admin/Suma: ver las empresas de todos (por defecto solo las suyas, como en Impuestos). */
    public bool $verTodos = false;

    /** Qué se espera de un PC: '' | 'ventana' | 'listar' | 'generar'. */
    public string $espera = '';
    public string $ventanaToken = '';
    public ?int $tareaId = null;
    public string $mensaje = '';
    public string $error = '';
    public ?array $listado = null;
    public ?array $libro = null;

    public function mount(): void
    {
        $g = (array) session('libro_iva', []);
        $this->verTodos = $this->puedeTodos() && (bool) ($g['verTodos'] ?? false);
        $emp = $this->empresas();
        $this->entidadId = (string) (\App\Support\EntidadActiva::entre(array_keys($emp)) ?? (isset($emp[$g['entidad'] ?? '']) ? $g['entidad'] : ($emp ? array_key_first($emp) : '')));
        $m = (int) date('n');
        $t = intdiv($m - 1, 3);   // trimestre natural ya terminado
        $this->ejercicio = (string) ($g['ejercicio'] ?? ($t === 0 ? date('Y') - 1 : date('Y')));
        $this->periodo = (string) ($g['periodo'] ?? ($t === 0 ? '4T' : $t.'T'));
        $this->alCambiarEmpresa();
        if (! $this->carpetasIva()) {   // primera vez: un PC lee las carpetas IVA de OneDrive para el desplegable
            $this->actualizarCarpetas();
        }
    }

    public function updated($prop): void
    {
        if ($prop === 'verTodos') {
            $this->verTodos = $this->puedeTodos() && $this->verTodos;
            if (! isset($this->empresas()[$this->entidadId])) {
                $this->entidadId = (string) (array_key_first($this->empresas()) ?? '');
                $prop = 'entidadId';
            }
        }
        if ($prop === 'entidadId') {
            $this->alCambiarEmpresa();
        }
        if (in_array($prop, ['entidadId', 'ejercicio', 'periodo', 'verTodos'], true)) {
            \App\Support\EntidadActiva::poner($this->entidadId);
            session(['libro_iva' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
            $this->libro = null;
            $this->mensaje = $this->error = '';
            $this->cargarLibroGuardado();
        }
    }

    /** Al volver a la pantalla (o cambiar de empresa/periodo) se recupera el resultado de la última generación de ese periodo, que queda en la tarea. */
    protected function cargarLibroGuardado(): void
    {
        if (! $this->entidadId || ! $this->periodoValido()) {
            return;
        }
        $t = DB::table('tareas')->where('proceso', 'pc.libro_iva')->where('estado', 'ok')
            ->where('parametros', 'like', '%"accion":"generar"%')->where('parametros', 'like', '%"entidad_id":'.(int) $this->entidadId.',%')
            ->where('parametros', 'like', '%"periodo":"'.$this->periodoMotor().'"%')->orderByDesc('id')->first();
        $libro = $t ? (json_decode($t->resultado ?? 'null', true)['libro'] ?? null) : null;
        if (is_array($libro)) {
            $libro['tarea'] = $t->id;
            $libro['pc'] = (json_decode($t->resultado, true)['pc'] ?? '');
            $libro['fecha'] = $t->terminada_at ?? $t->updated_at;
            $this->libro = $libro;
        }
    }

    /** Periodicidad del 303 de la empresa en Impuestos: 'M' mensual, 'T' trimestral o '' si no consta. */
    public function periodicidad(): string
    {
        $p = (string) DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', (int) $this->entidadId)->where('m.codigo', '303')->whereNull('ei.baja_ejercicio')->value('ei.periodicidad');

        return in_array($p, ['M', 'T'], true) ? $p : '';
    }

    /** ¿Lleva la casilla mensual «303 · informe al cliente» (303C)? Entonces, aunque presente trimestral, se le puede hacer el libro de un mes. */
    public function conControlMensual(): bool
    {
        return DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', (int) $this->entidadId)->where('m.codigo', '303C')->whereNull('ei.baja_ejercicio')->exists();
    }

    /** Cambia la periodicidad del 303 de la empresa (M/T) en Impuestos (entidad_impuestos), que es de donde sale al abrir esta pantalla. */
    public function cambiarPeriodicidad(string $nueva): void
    {
        abort_unless(auth()->user()?->can('entidades.editar') && in_array($nueva, ['M', 'T'], true) && isset($this->empresas()[$this->entidadId]), 403);
        $modelo = \App\Models\ImpuestoModelo::where('codigo', '303')->firstOrFail();
        $ob = \App\Models\EntidadImpuesto::where('entidad_id', (int) $this->entidadId)->where('modelo_id', $modelo->id)->whereNull('baja_ejercicio')->first()
            ?? \App\Models\EntidadImpuesto::create(['entidad_id' => (int) $this->entidadId, 'modelo_id' => $modelo->id, 'etiqueta' => '', 'periodicidad' => $nueva]);
        $ob->update(['periodicidad' => $nueva]);
        foreach (\App\Support\Impuestos::ejercicios() as $ej) {
            \App\Support\Impuestos::asegurarEjercicio($ej, $ob->id);
        }
        if ($nueva === 'T') {
            // Trimestral: no puede ser REDEME (devolución mensual) ni gran empresa
            DB::table('entidades')->where('id', (int) $this->entidadId)->update(['redeme' => 0, 'gran_empresa' => 0, 'updated_at' => now()]);
            $this->devolver = false;
        }
        $this->ajustarPeriodo();
        $this->libro = null;
        $this->cargarLibroGuardado();
    }

    /** Una empresa mensual trabaja con meses (09 …) y una trimestral con trimestres (3T): si el periodo elegido no es de su tipo, pasa al último ya terminado. */
    protected function ajustarPeriodo(): void
    {
        $per = $this->periodicidad();
        $esTrimestre = (bool) preg_match('/^[1-4]T$/', $this->periodo);
        $m = (int) date('n');
        if ($per === 'M' && $esTrimestre) {
            $this->periodo = str_pad((string) ($m === 1 ? 12 : $m - 1), 2, '0', STR_PAD_LEFT);
            if ($m === 1 && (int) $this->ejercicio === (int) date('Y')) {
                $this->ejercicio = (string) ((int) date('Y') - 1);
            }
        } elseif ($per === 'T' && ! $esTrimestre && ! $this->conControlMensual()) {
            $t = intdiv($m - 1, 3);
            $this->periodo = $t === 0 ? '4T' : $t.'T';
            if ($t === 0 && (int) $this->ejercicio === (int) date('Y')) {
                $this->ejercicio = (string) ((int) date('Y') - 1);
            }
        }
    }

    /** En un trimestre solo se devuelve en el 4T (el motor ya lo fuerza); el resto de trimestres, a compensar aunque la marca diga otra cosa. */
    public function soloCuarto(): bool
    {
        return (bool) preg_match('/^[1-3]T$/', $this->periodo);
    }

    protected function devolverEfectivo(): bool
    {
        return $this->soloCuarto() ? false : $this->devolver;
    }

    protected function alCambiarEmpresa(): void
    {
        $this->ajustarPeriodo();
        // Gran empresa (impuestos mensuales, devolución mensual) o inscrita en REDEME: lo que sale a favor se DEVUELVE, no se compensa
        $marcas = DB::table('entidades')->where('id', (int) $this->entidadId)->first(['redeme', 'gran_empresa']);
        $this->devolver = (bool) ($marcas->redeme ?? false) || (bool) ($marcas->gran_empresa ?? false);
        $this->carpeta = (string) (DB::table('libro_iva_carpetas')->where('entidad_id', (int) $this->entidadId)->value('carpeta') ?? '');
        $this->listado = null;
        $this->libro = null;
        $this->espera = $this->ventanaToken = '';
        $this->tareaId = null;
        $this->recuperarUltimo();
        $this->cargarLibroGuardado();
    }

    public function puedeTodos(): bool
    {
        return Imp::esGestor();
    }

    /** Empresas con 303 visibles para el usuario (las mismas reglas que la pestaña Impuestos). */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->where('m.codigo', '303')->where('e.estado', 1)
            ->select('e.id', DB::raw('COALESCE(NULLIF(e.alias, ""), e.entidad) as nombre'))->distinct()->orderBy('nombre');
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->pluck('nombre', 'e.id')->all();
    }

    protected function nombreEmpresa(): string
    {
        return (string) ($this->empresas()[$this->entidadId] ?? '');
    }

    /** ¿La empresa presenta el modelo 216 (no residentes)? Entonces el libro lleva la tabla por proveedor junto al bloque ISP. */
    public function presentaM216(): bool
    {
        return DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', (int) $this->entidadId)->where('m.codigo', '216')->whereNull('ei.baja_ejercicio')->exists();
    }

    /** «3T» → 2026-3T · «07» → 2026-07 */
    protected function periodoMotor(): string
    {
        return $this->ejercicio.'-'.$this->periodo;
    }

    public function periodoValido(): bool
    {
        return (bool) preg_match('/^\d{4}$/', $this->ejercicio) && (bool) preg_match('/^([1-4]T|0[1-9]|1[0-2])$/', $this->periodo);
    }

    // ------------------------------------------------------------------ carpeta (selector de Windows en un PC)

    public function elegirCarpeta(): void
    {
        $this->error = $this->mensaje = '';
        if (! $this->hayPc()) {
            return;
        }
        $this->ventanaToken = bin2hex(random_bytes(6));
        $this->espera = 'ventana';
        ColaTareas::crear('pc.elegir_carpeta', ['token' => $this->ventanaToken], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->mensaje = 'Se ha abierto el selector de carpetas de Windows en el PC trabajador: elige la carpeta IVA de la empresa.';
    }

    // ------------------------------------------------------------------ carpeta (desplegable con buscador, con las carpetas IVA que lee un PC)

    public string $carpetaElegida = '';

    /**
     * Raíces de OneDrive que puede ver el usuario en el explorador (10-oct-2026): `_Clientes` todos; `_RUR_Marta_Alex` solo Alex y Marta Ruiz
     * (config contabilidad.libro_iva_acceso_total); `_Suma` además los roles Suma y Admin.
     */
    public static function raicesPermitidas(?\App\Models\User $u = null): array
    {
        $u ??= auth()->user();
        $raices = ['_Clientes'];
        if ($u && in_array(strtolower((string) $u->email), array_map('strtolower', (array) config('contabilidad.libro_iva_acceso_total', [])), true)) {
            return ['_Clientes', '_RUR_Marta_Alex', '_Suma'];
        }
        if ($u && $u->hasAnyRole(['Admin', 'Suma'])) {
            $raices[] = '_Suma';
        }

        return $raices;
    }

    public static function rutaPermitida(string $ruta, ?\App\Models\User $u = null): bool
    {
        foreach (self::raicesPermitidas($u) as $r) {
            if ($ruta === $r || str_starts_with($ruta, $r.'/')) {
                return true;
            }
        }

        return false;
    }

    /** Árbol de carpetas de OneDrive que subió un PC (tarea pc.arbol_carpetas modo iva), filtrado por lo que puede ver el usuario: ['dirs' => [...], 'marcas' => [...]]. */
    public function arbolCarpetas(): array
    {
        try {
            $a = ColaTareas::estado('onedrive.arbol_iva');
        } catch (\Throwable $e) {
            $a = null;
        }
        $ok = fn ($d) => self::rutaPermitida((string) $d);

        return ['dirs' => array_values(array_filter((array) ($a['dirs'] ?? []), $ok)), 'marcas' => array_values(array_filter((array) ($a['marcas'] ?? []), $ok))];
    }

    public function carpetasIva(): array
    {
        return array_flip($this->arbolCarpetas()['dirs']);
    }

    public function actualizarCarpetas(): void
    {
        $this->error = '';
        if (! $this->hayPc()) {
            return;
        }
        if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            ColaTareas::crear('pc.arbol_carpetas', ['modo' => 'iva'], ColaTareas::pcElegido() ?: null, auth()->id());
        }
        $this->espera = 'carpetas';
        $this->mensaje = 'Un PC está leyendo las carpetas IVA de OneDrive (unos segundos)…';
    }

    public function updatedCarpetaElegida(string $ruta): void
    {
        if ($ruta === '' || ! isset($this->carpetasIva()[$ruta]) || ! ColaTareas::rutaRelativaSegura($ruta)) {
            return;
        }
        $this->carpeta = $ruta;
        $this->carpetaElegida = '';
        DB::table('libro_iva_carpetas')->updateOrInsert(['entidad_id' => (int) $this->entidadId], ['carpeta' => $ruta, 'user_id' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        $this->listado = null;
        $this->comprobar();
    }

    public function cancelarEspera(): void
    {
        $this->espera = $this->ventanaToken = '';
        $this->mensaje = '';
    }

    // ------------------------------------------------------------------ tareas

    protected function hayPc(): bool
    {
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if ($vivos === 0) {
            $this->error = 'No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
        }

        return $vivos > 0;
    }

    public function comprobar(): void
    {
        $this->lanzar('listar');
    }

    public function volverAComprobar(): void
    {
        $this->avisoSinLibro = '';
        $this->comprobar();
    }

    /** Texto del aviso «¿seguro?» cuando el periodo elegido no pega con la fecha de hoy; vacío = no se pregunta. */
    public string $avisoPeriodo = '';

    /** Aviso (modal) de que no está el Excel de SAGE del periodo en la carpeta ni se ha subido uno: sin él no hay libro que hacer. */
    public string $avisoSinLibro = '';

    public function generar(bool $confirmado = false): void
    {
        if (! $this->periodoValido()) {
            $this->error = 'Periodo no válido.';

            return;
        }
        $this->avisoSinLibro = '';
        if (! $this->excelSubido && $this->listado !== null && ! $this->originalDelPeriodo()) {   // según el último listado de la carpeta
            $this->avisoSinLibro = 'No encuentro en la carpeta el Excel del libro de IVA de '.$this->periodo.' '.$this->ejercicio.' (el export de SAGE, tipo «'
                .(preg_match('/^[1-4]T$/', $this->periodo) ? 'T'.$this->periodo[0] : $this->periodo).' '.$this->ejercicio.' …»). Sin él no se puede generar: súbelo con «Excel del libro de IVA» o cópialo a la carpeta y vuelve a comprobarla.';

            return;
        }
        if (! $confirmado && ($aviso = $this->periodoIlogico()) !== '') {
            $this->avisoPeriodo = $aviso;   // modal: revisar el periodo o generar de todos modos

            return;
        }
        $this->avisoPeriodo = '';
        $this->lanzar('generar');
    }

    /**
     * ¿Tiene sentido hacer hoy el libro de este periodo? Lo normal: el mes en curso o el anterior; el trimestre en curso o el anterior mientras
     * estamos en el mes siguiente a su cierre (octubre para el 3T). Cualquier otro (3T en noviembre, un mes futuro…) hace que el resultado del
     * periodo anterior que se arrastra no sea el que toca; se avisa y se deja decidir.
     */
    protected function periodoIlogico(): string
    {
        $hoy = now();
        $y = (int) $this->ejercicio;
        $p = $this->periodo;
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $idx = fn (int $anio, int $mes) => $anio * 12 + $mes - 1;   // mes absoluto
        $h = $idx((int) $hoy->format('Y'), (int) $hoy->format('n'));
        if (preg_match('/^([1-4])T$/', $p, $m)) {
            $ok = $h === $idx($y, 3 * (int) $m[1]) + 1;   // un trimestre solo se hace ya cerrado, en el mes siguiente a su cierre (3T en octubre, 4T en enero)
            $abierto = $h <= $idx($y, 3 * (int) $m[1]);
        } elseif (preg_match('/^(0[1-9]|1[0-2])$/', $p)) {
            $mi = $idx($y, (int) $p);
            $ok = $mi === $h || $mi === $h - 1;   // el mes en curso o el anterior
        } else {
            return '';
        }
        if ($ok) {
            return '';
        }
        $trim = fn (int $i) => (intdiv($i % 12, 3) + 1).'T '.intdiv($i, 12);
        if ($this->periodicidad() === 'T' && ! $this->conControlMensual()) {   // solo presenta trimestral: el libro es del trimestre ya cerrado
            $ult = $h % 12 % 3 === 0 ? $trim($h - 1) : null;
            $qAct = $trim($h);

            return 'Hoy es '.$hoy->format('d/m/Y').' y has elegido '.$p.' '.$y.'. Esta empresa solo presenta el 303 trimestral y no lleva informe mensual al cliente: '
                .($ult ? 'el libro se hace con el trimestre ya cerrado (ahora, el '.$ult.').' : 'el libro se hace cuando acaba el trimestre (el '.$qAct.' aún no ha terminado).').' '
                .'Si necesitas hacerlo cada mes, añade la casilla «303 · informe al cliente» en Entidades → Impuestos.';
        }
        $normales = [];
        if ($h % 12 % 3 === 0) {   // primer mes de un trimestre: se hace el que acaba de cerrarse
            $normales[] = $trim($h - 1);
        }
        $normales[] = 'el mes de '.$meses[($h - 1) % 12].' '.intdiv($h - 1, 12);

        return 'Hoy es '.$hoy->format('d/m/Y').' y has elegido '.$p.' '.$y.'. Lo normal sería '.implode(' o ', $normales).'.'.(($abierto ?? false) ? ' El '.$p.' '.$y.' todavía no ha terminado.' : '').' '
            .'Si el periodo no es el que toca, el resultado del periodo anterior que se arrastra al Excel tampoco será el correcto.';
    }

    protected function lanzar(string $accion): void
    {
        $this->error = $this->mensaje = '';
        if ($this->carpeta === '') {
            $this->error = 'Elige primero la carpeta donde están los Excel de IVA de esta empresa.';

            return;
        }
        abort_unless(\App\Support\ColaTareas::rutaRelativaSegura($this->carpeta), 422);
        if (! $this->hayPc()) {
            return;
        }
        if ($accion === 'generar') {
            $this->libro = null;
        }
        $subir = $accion === 'generar' && $this->excelSubido;
        if ($subir) {
            $this->validate(['excelSubido' => 'file|max:20480|mimes:xlsx,xlsm']);
        }
        $this->tareaId = ColaTareas::crear('pc.libro_iva', [
            'accion' => $accion, 'carpeta' => $this->carpeta, 'periodo' => $this->periodoMotor(),
            'cliente' => $this->nombreEmpresa(), 'entidad_id' => (int) $this->entidadId, 'm216' => $this->presentaM216(), 'devolver' => $this->devolverEfectivo(),
            'anterior' => $accion === 'generar' ? $this->anteriorDeImpuesto() : null,
            'entrada' => $subir ? ['archivo' => 'original.xlsx', 'nombre' => basename(str_replace('\\', '/', $this->excelSubido->getClientOriginalName()))] : null,
        ], ColaTareas::pcElegido() ?: null, auth()->id(), null, null, $subir ? 'preparando' : 'pendiente');
        if ($subir) {
            $dir = ColaTareas::carpetaEntradas($this->tareaId);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            copy($this->excelSubido->getRealPath(), $dir.'/original.xlsx');
            ColaTareas::liberar($this->tareaId);
            $this->excelSubido = null;
        }
        $this->espera = $accion;
        $this->mensaje = $accion === 'generar' ? 'Pedido a un PC: monta el libro en la carpeta de OneDrive…' : 'Pedido a un PC: mirando la carpeta…';
    }

    /**
     * Resultado del 303 del periodo anterior, tomado del impuesto presentado (PDF de la AEAT subido en Impuestos; casilla 71), no de los Excel,
     * que pueden estar desactualizados. Devuelve ['valor' (+ a pagar / − a compensar), 'origen'] o, si no hay PDF legible, ['aviso'] y el
     * motor usa los Excel como antes.
     */
    protected function anteriorDeImpuesto(): array
    {
        $ej = (int) $this->ejercicio;
        // Periodos candidatos del anterior, por orden: el mismo tipo y, si la empresa cambió de trimestral a mensual (o al revés), el otro
        // (septiembre → el 3T; el 4T → diciembre). Así el primer mes de una empresa que pasa a REDEME arrastra lo pendiente del último trimestre.
        if (preg_match('/^([1-4])T$/', $this->periodo, $m)) {
            [$ejA, $ultT, $ultM] = $m[1] === '1' ? [$ej - 1, 'T4', '12'] : [$ej, 'T'.($m[1] - 1), sprintf('%02d', 3 * ($m[1] - 1))];
            $candidatos = [[$ejA, $ultT], [$ejA, $ultM]];
        } else {
            $mes = (int) $this->periodo;
            [$ejA, $mesA] = $mes === 1 ? [$ej - 1, 12] : [$ej, $mes - 1];
            $candidatos = [[$ejA, sprintf('%02d', $mesA)]];
            if ($mesA % 3 === 0) {
                $candidatos[] = [$ejA, 'T'.($mesA / 3)];
            }
        }
        $doc = null;
        foreach ($candidatos as [$ejA, $perA]) {
            $doc = \App\Models\ImpuestoDocumento::where('entidad_id', (int) $this->entidadId)->where('modelo', '303')->where('tipo', 'presentado')
                ->where('ejercicio', $ejA)->where('periodo', $perA)->whereNull('quitado_at')->orderByDesc('id')->first();
            if ($doc) {
                break;
            }
        }
        if (! $doc) {
            return ['aviso' => 'no hay 303 presentado de '.collect($candidatos)->map(fn ($c) => $c[1].' '.$c[0])->implode(' ni de ').' en Impuestos'];
        }
        $ruta = storage_path('app/'.$doc->almacen);
        $script = rtrim((string) config('contabilidad.facturasocr_dir'), '/').'/leer_303.py';
        if (! is_file($ruta) || ! is_file($script)) {
            return ['aviso' => "no se puede leer el 303 presentado «{$doc->nombre}»"];
        }
        $py = storage_path('app/venv-facturasocr/bin/python');
        $r = \Illuminate\Support\Facades\Process::timeout(60)->run([is_executable($py) ? $py : 'python3', $script, $ruta]);
        $d = json_decode(trim($r->output()), true);
        if (! is_array($d) || ! isset($d['resultado'])) {
            return ['aviso' => "no encuentro el resultado en el 303 presentado «{$doc->nombre}»".(is_array($d) && isset($d['error']) ? ' ('.$d['error'].')' : '')];
        }

        // Lo que se arrastra no es solo la casilla 71 (resultado de ese periodo): las cuotas a compensar de periodos anteriores que quedaron sin
        // aplicar van en la casilla 87 (110 − 78; no incluye lo generado en el propio periodo). Si el resultado fue negativo, se suma a lo pendiente.
        $res = (float) $d['resultado'];
        $cas = (array) ($d['casillas'] ?? []);
        // Resultado negativo: el 303 dice qué se hizo con él: casilla 72 = se compensa, casilla 73 = se pide la DEVOLUCIÓN (no se arrastra).
        $comp72 = (float) ($cas['72'] ?? 0);
        $dev73 = (float) ($cas['73'] ?? 0);
        $eligio = $comp72 > 0 || $dev73 > 0;
        $pend = (float) ($d['pendientes'] ?? 0) + ($eligio ? $comp72 : max(0.0, -$res));
        $texto = $doc->nombre.' · Cas.71 = '.number_format($res, 2, ',', '.')
            .(isset($cas['87']) ? ' + Cas.87 = '.number_format((float) $cas['87'], 2, ',', '.') : '');
        if ($dev73 > 0) {
            $texto .= '; DEVOLUCIÓN SOLICITADA, casilla 73 = '.number_format($dev73, 2, ',', '.').' (no se compensa)';
        } elseif ($res < 0 && ! $eligio) {
            $texto .= '; ojo: no consta si se pidió devolución o compensación (casillas 72/73 vacías), se supone compensación';
        }
        if ($pend > 0.005) {
            return ['valor' => -round($pend, 2), 'origen' => $texto.' → A compensar '.number_format($pend, 2, ',', '.')];
        }

        return ['valor' => max(0.0, $res), 'origen' => $texto];
    }

    /** wire:poll mientras se espera a un PC. */
    public function revisar(): void
    {
        if ($this->espera === 'carpetas') {
            if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
                $this->espera = $this->mensaje = '';
                if (! $this->carpetasIva()) {
                    $this->error = 'El PC no ha encontrado carpetas IVA en OneDrive.';
                }
            }

            return;
        }
        if ($this->espera === 'ventana') {
            $e = ColaTareas::estado('onedrive.carpeta_elegida');
            if (! is_array($e) || ($e['token'] ?? '') !== $this->ventanaToken) {
                return;
            }
            $this->espera = $this->ventanaToken = '';
            $this->mensaje = '';
            if (! empty($e['cancelada'])) {
                return;
            }
            if (! empty($e['error']) || empty($e['ruta'])) {
                $this->error = (string) ($e['error'] ?: 'No he podido leer la carpeta elegida.');

                return;
            }
            $ruta = trim((string) $e['ruta'], '/');
            if (! ColaTareas::rutaRelativaSegura($ruta)) {
                $this->error = 'Carpeta no válida.';

                return;
            }
            $this->carpeta = $ruta;
            DB::table('libro_iva_carpetas')->updateOrInsert(['entidad_id' => (int) $this->entidadId], ['carpeta' => $ruta, 'user_id' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
            $this->listado = null;
            $this->comprobar();

            return;
        }
        if (! in_array($this->espera, ['listar', 'generar'], true) || ! $this->tareaId) {
            return;
        }
        $t = DB::table('tareas')->find($this->tareaId);
        if (! $t || in_array($t->estado, ['pendiente', 'en_curso'], true)) {
            return;
        }
        $accion = $this->espera;
        $this->espera = '';
        $r = json_decode($t->resultado ?? 'null', true) ?: [];
        if ($t->estado !== 'ok') {
            $this->error = 'No se ha podido: '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -400)) ?: 'error en el PC');

            return;
        }
        $this->mensaje = '';
        if ($accion === 'listar') {
            $this->listado = $r['listado'] ?? null;
        } else {
            $this->libro = $r['libro'] ?? null;
            if ($this->libro) {
                $this->libro['tarea'] = $t->id;
                $this->libro['pc'] = $r['pc'] ?? '';
            }
        }
    }

    protected function recuperarUltimo(): void
    {
        $e = ColaTareas::estado('impuestos.libro_iva.'.(int) $this->entidadId);
        $this->ultimo = is_array($e) ? $e : null;
    }

    public ?array $ultimo = null;

    public function enlace(string $fichero): string
    {
        return URL::temporarySignedRoute('contabilidad.tarea-fichero', now()->addHours(2), ['id' => $this->libro['tarea'], 'nombre' => $fichero]);
    }

    /** ¿Hay original del periodo elegido en la carpeta? Según el último listado. */
    public function originalDelPeriodo(): ?array
    {
        $per = $this->periodoMotor();
        foreach ($this->listado['originales'] ?? [] as $o) {
            if ($o['periodo'] === $per) {
                return $o;
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.libro-iva', ['empresas' => $this->empresas()]);
    }
}
