<?php

namespace App\Http\Livewire;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use App\Support\FicherosBase;
use App\Support\Impuestos as Imp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuestos → IRPF M111 (15-oct-2026): fichero del modelo 111 para importar en la AEAT y Excel resumen, juntando NÓMINAS (111.txt de
 * la gestoría, PDF del 111 o mayor de SAGE de la 4751/640) y PROFESIONALES (listado de retenciones de SAGE, agrupado por NIF).
 * Cada tipo de fichero tiene su casilla de subida: no hay que renombrar nada. Todo en el servidor (motor Python, sin PC trabajador).
 * Motor: resources/motores/m111/prepara111.py (en el repo de Appmos: viaja con el despliegue; M111_DIR solo para otra ruta). Datos: storage/app/m111/<entidad>/<AAAA>-<periodo>/.
 */
class ImpuestoM111 extends Component
{
    use WithFileUploads;

    public const TIPOS = [
        'txt' => ['Nóminas · fichero 111 de la gestoría', '.txt', 'El 111.txt que manda la gestoría de nóminas para subir a Hacienda.'],
        'pdf' => ['Nóminas · PDF del 111', '.pdf', 'Borrador o justificante del 111 de nóminas (se leen las casillas). Si también hay txt, manda el txt.'],
        'mayor' => ['Nóminas · mayor de SAGE', '.xlsx', 'Mayor de la 4751 (retenciones) y, si puede ser, el completo (la 640 da las percepciones). Se usa solo si no hay txt ni PDF; si los hay, sirve para cuadrar.'],
        'profesionales' => ['Profesionales · listado de retenciones de SAGE', '.xlsx', 'ESTLisRetencionTMP_….xlsx: una fila por factura; se agrupa por NIF.'],
    ];

    public ?int $entidadId = null;
    public int $ejercicio;
    public string $periodo = '';
    public bool $verTodos = false;
    public array $subidas = ['txt' => [], 'pdf' => [], 'mayor' => [], 'profesionales' => []];
    public bool $usarMayorBase = false;
    /** Tarea pc.aeat_111: un PC carga el fichero en el formulario de la AEAT y deja Edge abierto; validar y presentar lo hace el usuario. */
    public ?int $tareaCarga = null;
    /** Ajustes a mano (los guarda ajustes.json del periodo). */
    public array $aj = ['tipo' => '', 'iban' => '', 'perceptores_nominas' => '', 'percepciones_nominas' => '', 'retenciones_nominas' => '',
        'resultado_anterior' => '', 'complementaria' => false, 'justificante_anterior' => ''];
    public array $excluir = [];
    public array $incluir = [];
    public ?array $res = null;
    public string $error = '';
    public string $msg = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $g = (array) session('m111', []);
        $this->verTodos = Imp::esGestor() && (bool) ($g['verTodos'] ?? false);
        $this->ejercicio = (int) ($g['ejercicio'] ?? now()->format('Y'));
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? 0]) ? (int) $g['entidad'] : ((int) (array_key_first($emp) ?? 0) ?: null);
        $this->periodo = (string) ($g['periodo'] ?? '');
        $this->ajustarPeriodo();
        $this->cargar();
    }

    // ------------------------------------------------------------------ empresas y periodos

    /** Empresas con el 111 en Impuestos (las del usuario; Admin/Suma «todas»): [id => nombre]. */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', '111')->where('e.estado', 1);
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->orderBy('e.entidad')->get(['e.id', 'e.entidad', 'e.alias', 'e.nif', 'ei.periodicidad'])
            ->mapWithKeys(fn ($r) => [$r->id => ($r->alias ?: $r->entidad).' · '.($r->periodicidad === 'M' ? 'mensual' : 'trimestral').($r->nif ? ' · '.$r->nif : '')])->all();
    }

    protected function periodicidad(): string
    {
        return (string) (DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '111')->value('ei.periodicidad') ?: 'T');
    }

    /** Periodos del ejercicio según la periodicidad: ['09' => 'septiembre'] o ['T3' => '3T']. */
    public function periodos(): array
    {
        return $this->periodicidad() === 'M'
            ? collect(range(1, 12))->mapWithKeys(fn ($m) => [sprintf('%02d', $m) => Imp::MESES[$m - 1]])->all()
            : ['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'];
    }

    protected function ajustarPeriodo(): void
    {
        $ps = $this->periodos();
        if (! isset($ps[$this->periodo])) {
            // por defecto, el último periodo terminado
            $mes = (int) now()->format('n') - 1 ?: 12;
            $this->periodo = $this->periodicidad() === 'M' ? sprintf('%02d', $mes) : 'T'.(int) ceil($mes / 3);
        }
    }

    /** Periodo como lo pide el fichero de la AEAT: '09' o '3T'. */
    protected function perAeat(): string
    {
        return $this->periodo[0] === 'T' ? substr($this->periodo, 1).'T' : $this->periodo;
    }

    protected function recordar(): void
    {
        session(['m111' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
    }

    public function updatedEntidadId(): void
    {
        $this->entidadId = (int) $this->entidadId ?: null;
        $this->ajustarPeriodo();
        $this->recordar();
        $this->cargar();
    }

    public function updatedPeriodo(): void
    {
        $this->recordar();
        $this->cargar();
    }

    public function cambiarEjercicio(int $d): void
    {
        $this->ejercicio += $d;
        $this->recordar();
        $this->cargar();
    }

    public function updatedVerTodos(): void
    {
        $this->verTodos = Imp::esGestor() && $this->verTodos;
        $emp = $this->empresas();
        if (! isset($emp[$this->entidadId])) {
            $this->entidadId = (int) (array_key_first($emp) ?? 0) ?: null;
            $this->ajustarPeriodo();
        }
        $this->recordar();
        $this->cargar();
    }

    // ------------------------------------------------------------------ ficheros

    protected function autorizar(): void
    {
        abort_unless($this->entidadId && isset($this->empresas()[$this->entidadId]), 403);
        abort_unless(isset($this->periodos()[$this->periodo]) && $this->ejercicio > 2000, 422);
    }

    protected function dir(string $sub = ''): string
    {
        $this->autorizar();
        $d = storage_path('app/m111/'.$this->entidadId.'/'.$this->ejercicio.'-'.$this->periodo.($sub !== '' ? '/'.$sub : ''));
        @mkdir($d, 0775, true);

        return $d;
    }

    /** [tipo => [nombres]] de lo subido para este periodo. */
    public function ficheros(): array
    {
        if (! $this->entidadId) {
            return [];
        }
        $out = [];
        foreach (array_keys(self::TIPOS) as $t) {
            $out[$t] = array_map('basename', glob($this->dir('entrada/'.$t).'/*') ?: []);
        }

        return $out;
    }

    public function updatedSubidas($valor, $clave): void
    {
        $tipo = explode('.', (string) $clave)[0];
        if (! isset(self::TIPOS[$tipo])) {
            return;
        }
        $this->error = '';
        $lista = is_array($this->subidas[$tipo] ?? null) ? $this->subidas[$tipo] : [$this->subidas[$tipo]];
        foreach ($lista as $f) {
            if (! $f instanceof UploadedFile) {
                continue;
            }
            $ext = strtolower($f->getClientOriginalExtension());
            $vale = ['txt' => ['txt'], 'pdf' => ['pdf'], 'mayor' => ['xlsx', 'xls'], 'profesionales' => ['xlsx', 'xls']][$tipo];
            if (! in_array($ext, $vale, true)) {
                $this->error = '«'.$f->getClientOriginalName().'» no vale aquí: '.self::TIPOS[$tipo][0].' espera '.implode(' o ', $vale).'.';
                continue;
            }
            $nombre = preg_replace('/[^A-Za-z0-9._ &()-]+/', '_', basename($f->getClientOriginalName()));
            copy($f->getRealPath(), $this->dir('entrada/'.$tipo).'/'.$nombre);
            if ($tipo === 'mayor') {
                FicherosBase::guardar($this->entidadId, 'mayor', $f->getRealPath(), $f->getClientOriginalName(), 'm111');
            }
        }
        $this->subidas[$tipo] = [];
        $this->generar();
    }

    public function quitar(string $tipo, string $nombre): void
    {
        abort_unless(isset(self::TIPOS[$tipo]), 422);
        @unlink($this->dir('entrada/'.$tipo).'/'.basename($nombre));
        $this->generar();
    }

    public function mayorBase(): ?array
    {
        return $this->entidadId ? FicherosBase::ultimo($this->entidadId, 'mayor') : null;
    }

    /** Se ha subido un fichero base desde el recuadro de la pantalla: se rehace el resultado con él (p. ej. el código postal de los proveedores). */
    #[On('ficheros-base-cambiados')]
    public function ficherosBaseCambiados(): void
    {
        $this->generar();
    }

    public function updatedUsarMayorBase(): void
    {
        $this->generar();
    }

    // ------------------------------------------------------------------ generar

    protected function cargar(): void
    {
        $this->res = null;
        $this->error = '';
        $this->msg = '';
        $this->excluir = $this->incluir = [];
        $this->aj = ['tipo' => '', 'iban' => '', 'perceptores_nominas' => '', 'percepciones_nominas' => '', 'retenciones_nominas' => '',
            'resultado_anterior' => '', 'complementaria' => false, 'justificante_anterior' => ''];
        $this->usarMayorBase = false;
        if (! $this->entidadId || ! isset($this->periodos()[$this->periodo])) {
            return;
        }
        $f = $this->dir().'/ajustes.json';
        if (is_file($f)) {
            $g = json_decode((string) file_get_contents($f), true) ?: [];
            $this->aj = array_merge($this->aj, array_intersect_key($g, $this->aj));
            $this->excluir = (array) ($g['excluir_4751'] ?? []);
            $this->incluir = (array) ($g['incluir_4751'] ?? []);
            $this->usarMayorBase = (bool) ($g['usar_mayor_base'] ?? false);
        } else {
            // IBAN y perceptores: los de la última vez de esta empresa
            $prev = collect(glob(storage_path('app/m111/'.$this->entidadId.'/*/ajustes.json')) ?: [])->sortDesc()->first();
            if ($prev) {
                $g = json_decode((string) file_get_contents($prev), true) ?: [];
                $this->aj['iban'] = (string) ($g['iban'] ?? '');
                $this->aj['tipo'] = (string) ($g['tipo'] ?? '');
                $this->aj['perceptores_nominas'] = (string) ($g['perceptores_nominas'] ?? '');
            }
        }
        // Siempre se rehace con lo último (motor, ficheros base, listado de proveedores…): el resultado guardado de otro día puede ser de una versión anterior.
        $this->generar();
    }

    public function updatedAj(): void
    {
        $this->generar();
    }

    public function alternarLinea(string $id, bool $incluida): void
    {
        $this->excluir = array_values(array_diff($this->excluir, [$id]));
        $this->incluir = array_values(array_diff($this->incluir, [$id]));
        if ($incluida) {
            $this->excluir[] = $id;
        } else {
            $this->incluir[] = $id;
        }
        $this->generar();
    }

    public function generar(): void
    {
        $this->error = '';
        $this->msg = '';
        if (! $this->entidadId) {
            return;
        }
        $ent = DB::table('entidades')->find($this->entidadId);
        $fic = [];
        foreach (array_keys(self::TIPOS) as $t) {
            $fic[$t] = glob($this->dir('entrada/'.$t).'/*') ?: [];
        }
        if ($this->usarMayorBase && ($mb = $this->mayorBase())) {
            $fic['mayor'][] = $mb['ruta'];
        }
        if (! array_filter($fic)) {
            $this->res = null;
            @unlink($this->dir('salida').'/resumen.json');
            return;
        }
        if ($pb = FicherosBase::ultimo($this->entidadId, 'proveedores')) {
            $fic['proveedores'] = [$pb['ruta']];   // código postal de los profesionales en el Excel (no es motivo para generar por sí solo)
        }
        $ajustes = $this->aj + ['excluir_4751' => $this->excluir, 'incluir_4751' => $this->incluir, 'usar_mayor_base' => $this->usarMayorBase];
        file_put_contents($this->dir().'/ajustes.json', json_encode($ajustes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $spec = ['nif' => (string) $ent->nif, 'nombre' => (string) $ent->entidad, 'ejercicio' => $this->ejercicio, 'periodo' => $this->perAeat(),
            'salida' => $this->dir('salida'), 'ficheros' => $fic, 'ajustes' => $ajustes];
        $specF = $this->dir().'/spec.json';
        file_put_contents($specF, json_encode($spec, JSON_UNESCAPED_UNICODE));
        $motor = rtrim((string) config('contabilidad.m111_dir'), '/').'/prepara111.py';
        try {
            $r = Process::timeout(120)->env(['PYTHONIOENCODING' => 'utf-8'])->run([\App\Support\IntrastatMotor::python(), '-I', $motor, $specF]);
        } catch (\Throwable $e) {
            $this->error = 'No se ha podido ejecutar el motor del 111: '.$e->getMessage();
            return;
        }
        if (! $r->successful()) {
            $this->error = 'El motor del 111 ha fallado: '.mb_substr(trim($r->errorOutput() ?: $r->output()), -600);
            return;
        }
        $f = $this->dir('salida').'/resumen.json';
        $this->res = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    public function descargar(string $cual)
    {
        $this->autorizar();
        $this->generar();   // lo que se descarga es siempre la última versión (motor + ficheros actuales), no el de la última vez que se generó
        $nombre = (string) ($this->res[$cual === 'excel' ? 'excel' : 'fichero'] ?? '');
        $ruta = $this->dir('salida').'/'.basename($nombre);
        abort_unless($nombre !== '' && is_file($ruta), 404);

        return response()->download($ruta, $nombre);
    }

    /** Estado de la casilla 111 del periodo en Seguimiento (null si no hay). */
    public function estadoCasilla(): ?string
    {
        return DB::table('impuesto_estados as e')->join('entidad_impuestos as ei', 'ei.id', '=', 'e.entidad_impuesto_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where(['ei.entidad_id' => $this->entidadId, 'm.codigo' => '111', 'e.ejercicio' => $this->ejercicio, 'e.periodo' => $this->periodo])->value('e.estado');
    }

    /**
     * Un PC (el de «Ejecutar en») abre Edge con ventana, quien lo lanza elige el certificado y el fichero 111 queda importado en el formulario de la AEAT.
     * NO valida ni presenta: lo hace el usuario a mano. El justificante que baje se guarda en «_Clientes/<año>/_Impuestos <año>/Impuestos <alias>» como
     * «<Alias> M111 <periodo> <año>.pdf» (si la carpeta no existe en ese PC, en Descargas).
     */
    public function cargarEnAeat(): void
    {
        $this->autorizar();
        $this->error = $this->msg = '';
        $nombre = (string) ($this->res['fichero'] ?? '');
        $ruta = $this->dir('salida').'/'.basename($nombre);
        if ($nombre === '' || ! is_file($ruta) || ! empty($this->res['errores'])) {
            $this->error = 'Primero tiene que haber un fichero 111 sin errores.';
            return;
        }
        $pc = ColaTareas::pcElegido();
        if (! $pc) {
            $this->error = 'Elige arriba en «Ejecutar en» el PC en el que estás: ahí se abrirá Edge para que elijas el certificado.';
            return;
        }
        $e = DB::table('entidades')->find($this->entidadId);
        $nif = preg_replace('/^ES(?=[A-Z0-9]{9}$)/', '', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $e->nif)));
        $alias = trim((string) ($e->alias ?: $e->entidad));
        $id = ColaTareas::crear('pc.aeat_111', ['nif' => $nif, 'ejercicio' => (string) $this->ejercicio, 'periodo' => $this->perAeat(),
            'justificante_dir' => '_Clientes/'.$this->ejercicio.'/_Impuestos '.$this->ejercicio.'/Impuestos '.mb_strtoupper($alias),
            'justificante_nombre' => mb_convert_case(mb_strtolower($alias), MB_CASE_TITLE).' M111 '.$this->perAeat().' '.$this->ejercicio.'.pdf',
            'entrada' => ['archivo' => 'fichero.111', 'nombre' => $nombre]], $pc, auth()->id(), null, null, 'preparando');
        $dir = ColaTareas::carpetaEntradas($id);
        @mkdir($dir, 0775, true);
        copy($ruta, $dir.'/fichero.111');
        ColaTareas::liberar($id);
        $this->tareaCarga = $id;
    }

    /** Sigue la tarea de carga (la pantalla la consulta cada pocos segundos mientras dura). */
    public function revisar(): void
    {
        if (! $this->tareaCarga) {
            return;
        }
        $t = DB::table('tareas')->find($this->tareaCarga);
        if (! $t || in_array($t->estado, ['preparando', 'pendiente', 'en_curso'], true)) {
            return;
        }
        $this->tareaCarga = null;
        $r = json_decode($t->resultado ?? 'null', true) ?: [];
        if ($t->estado === 'ok' && ! empty($r['cargado'])) {
            $this->msg = 'Terminada la sesión de la AEAT en '.($r['pc'] ?? 'el PC').'. Si has presentado el 111, sube el justificante a la casilla 111 de Seguimiento y márcala como presentada.';
        } else {
            $err = array_values(array_filter((array) ($r['errores'] ?? [])));
            $this->error = 'No se ha podido cargar el fichero en la AEAT'.($err ? ': '.$err[0] : ': '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -300)) ?: 'error en el PC'));
        }
    }

    /** Deja el Excel resumen (y el .txt) en la casilla 111 del periodo en Seguimiento de impuestos. */
    public function guardarEnCasilla(): void
    {
        $this->autorizar();
        $ob = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '111')->first(['ei.id', 'ei.etiqueta']);
        if (! $ob || empty($this->res['excel'])) {
            $this->error = 'No hay Excel que guardar.';
            return;
        }
        $ruta = $this->dir('salida').'/'.$this->res['excel'];
        $sha = hash_file('sha256', $ruta);
        $almacen = 'impuestos/docs/'.$sha.'.xlsx';
        Storage::disk('local')->put($almacen, fopen($ruta, 'rb'));
        // el de la vez anterior de este mismo proceso se sustituye
        ImpuestoDocumento::where(['entidad_id' => $this->entidadId, 'modelo' => '111', 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'origen' => 'm111'])
            ->whereNull('quitado_at')->update(['quitado_at' => now()]);
        ImpuestoDocumento::create(['entidad_id' => $this->entidadId, 'modelo' => '111', 'etiqueta' => (string) $ob->etiqueta, 'ejercicio' => $this->ejercicio,
            'periodo' => $this->periodo, 'tipo' => 'otro', 'nombre' => $this->res['excel'], 'almacen' => $almacen, 'tam' => filesize($ruta),
            'sha256' => $sha, 'origen' => 'm111', 'user_id' => auth()->id()]);
        $this->msg = 'Excel guardado en la casilla 111 de '.$this->periodo.' en Seguimiento de impuestos.';
        $this->dispatch('notify', 'Excel guardado en la casilla del 111');
    }

    public function render()
    {
        return view('livewire.impuesto-m111', ['empresas' => $this->empresas(), 'periodosLista' => $this->periodos(), 'ficheros' => $this->ficheros(), 'mayorBase' => $this->mayorBase()]);
    }
}
