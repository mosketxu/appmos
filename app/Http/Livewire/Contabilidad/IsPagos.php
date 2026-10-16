<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\Impuestos as Imp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use App\Support\FicherosBase;
use Livewire\WithFileUploads;

/**
 * Pestaña «Pago Cuenta M202» de Impuestos (9-oct-2026; antes un botón dentro del IS): del IS del ejercicio anterior (PDF presentado) saca la cuota y genera el
 * fichero .202 (modelo 202, modalidad 40.2) que se importa en el formulario de la AEAT. Motor: Contabilidad/Impuestos/M202/motor
 * (prepara202.py); guía: M202/PLAN.md. No presenta nada.
 *
 * Clientes: los que tienen el 202 en la pestaña Impuestos (entidad_impuestos, modelo 202) y NIF. Datos en la misma estructura que el IS:
 *   <IS_DIR>/clientes/<NIF>/<ejercicio-1>/fuentes/m200_presentado.pdf   (IS anterior)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/fuentes/m202_anterior.pdf       (opcional: un 202 de este año, para el CNAE)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/ajustes202.json                 (cnae…)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/salida202/<NIF>_<ejercicio>_<periodo>.202 y .json
 */
class IsPagos extends Component
{
    use WithFileUploads;

    public int $ejercicio;
    public string $periodo = '2P';
    /** Filtro por estado del seguimiento: 'activos' (todos menos «no tiene que presentarlo»/«no se presenta»), 'todos', 'sin' o una clave de Impuestos::ESTADOS. */
    public string $filtroEstado = 'activos';
    /** Admin/Suma: ver las empresas de todos (por defecto, como en el resto de Impuestos, solo las del usuario). */
    public bool $todasEmpresas = false;

    /** Cliente del desplegable: 'todos' (los que tienen el 202 pendiente) o el id de una entidad. */
    public string $clienteId = 'todos';

    /** Marcados para preparar: [entidad_id => true]. */
    public array $marcados = [];
    /** CNAE a mano por entidad. */
    public array $cnae = [];
    /** Subidas: ['is' => [id => archivo], 'p202' => [id => archivo]]. */
    public $subidaIs = [];
    public $subida202 = [];
    /** P&G / sumas y saldos de SAGE a la fecha del periodo (solo modalidad 40.3): [id => archivo]. */
    public $subidaPyg = [];
    /** Importes a mano de la modalidad 40.3: [id => [campo => texto]]; vacío = no tocar lo guardado. */
    public array $ajustes403 = [];

    /** Campos de ajustes202.json que se editan desde la pantalla (modalidad 40.3): clave => etiqueta. */
    public const CAMPOS_403 = [
        'resultado_contable' => 'Resultado contable (04)',
        'gasto_is' => 'Gasto por IS (05/06)',
        'otros_aumentos' => 'Otros aumentos (07)',
        'otras_disminuciones' => 'Otras disminuciones (08)',
        'bin' => 'Compens. bases negativas (14)',
        'retenciones' => 'Retenciones (28)',
        'bonificaciones' => 'Bonificaciones (27)',
        'pagos_anteriores' => 'Pagos anteriores (30)',
        'porcentaje' => 'Porcentaje (17)',
        'porcentaje_minimo' => '% del mínimo (33)',
    ];

    public string $salida = '';
    /** Al preparar, deja un borrador en Outlook (Graph) con el cálculo y lo no aplicado, para que lo revise otra persona. */
    public bool $borradorOutlook = true;

    public function mount(): void
    {
        $this->ejercicio = (int) date('Y');
        $m = (int) date('n');
        $this->periodo = $m <= 6 ? '1P' : ($m >= 11 ? '3P' : '2P');
    }

    public function updatedTodasEmpresas(): void
    {
        $this->todasEmpresas = $this->puedeTodas() && $this->todasEmpresas;
        $this->clienteId = 'todos';
        $this->marcados = [];
    }

    public function puedeTodas(): bool
    {
        return Imp::esGestor();
    }

    /** Limita una consulta sobre entidad_impuestos (alias ei) a las empresas que ve el usuario. */
    protected function visibles($q)
    {
        return Imp::soloVisibles($q, null, $this->todasEmpresas && $this->puedeTodas());
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.is_dir'), '/');
    }

    protected function nif(string $nif): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nif));
    }

    protected function dirCliente(string $nif): string
    {
        return $this->baseDir().'/clientes/'.$this->nif($nif);
    }

    protected function periodoEstado(): string
    {
        return 'P'.(int) substr($this->periodo, 0, 1);
    }

    protected function autorizado(): bool
    {
        if (config('contabilidad.is_ejecucion')) {
            return true;
        }
        $this->dispatch('proceso-terminado', mensaje: "⚠️ IS · pagos a cuenta\nOpción no válida. Solo ejecutable desde un terminal autorizado.");

        return false;
    }

    /** Clientes con el 202 en Impuestos y NIF; por defecto solo los que tienen ese pago pendiente (no «no», «no se presenta» ni presentado). */
    protected function clientes(): array
    {
        $est = $this->periodoEstado();
        $filas = DB::table('entidad_impuestos as ei')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->leftJoin('impuesto_estados as s', function ($j) use ($est) {
                $j->on('s.entidad_impuesto_id', '=', 'ei.id')->where('s.ejercicio', $this->ejercicio)->where('s.periodo', $est);
            })
            ->where('m.codigo', '202')->whereNull('e.deleted_at')
            ->tap(fn ($q) => $this->visibles($q))
            ->when($this->clienteId !== 'todos', fn ($q) => $q->where('e.id', (int) $this->clienteId))
            ->whereNotNull('e.nif')->where('e.nif', '!=', '')
            ->orderBy('e.entidad')
            ->get(['e.id', 'e.entidad', 'e.nif', 's.estado']);
        $out = [];
        foreach ($filas as $f) {
            $estado = $f->estado ?: 'sin';
            if ($this->clienteId === 'todos') {
                $f_ = $this->filtroEstado;
                if ($f_ === 'activos' ? in_array($estado, ['no', 'nopresenta'], true) : ($f_ !== 'todos' && $estado !== $f_)) {
                    continue;
                }
            }
            $dir = $this->dirCliente($f->nif);
            $nif = $this->nif($f->nif);
            $res = $dir.'/'.$this->ejercicio.'/salida202/'.$nif.'_'.$this->ejercicio.'_'.$this->periodo.'.json';
            $r = is_file($res) ? json_decode((string) file_get_contents($res), true) : null;
            $isPdf = $dir.'/'.($this->ejercicio - 1).'/fuentes/m200_presentado.pdf';
            $pyg = collect(glob($dir.'/'.$this->ejercicio.'/fuentes/pyg202.*') ?: [])->reject(fn ($x) => str_ends_with($x, '.origen.txt'))->first();
            $ajf = $dir.'/'.$this->ejercicio.'/ajustes202.json';
            $out[] = [
                'tienePyg' => (bool) $pyg, 'fechaPyg' => $pyg ? date('d/m/Y', filemtime($pyg)) : null,
                'ajustes' => is_file($ajf) ? (json_decode((string) file_get_contents($ajf), true) ?: []) : [],
                'id' => $f->id, 'entidad' => $f->entidad, 'nif' => $nif, 'estado' => $estado,
                'tieneIs' => is_file($isPdf), 'fechaIs' => is_file($isPdf) ? date('d/m/Y', filemtime($isPdf)) : null,
                'tiene202' => is_file($dir.'/'.$this->ejercicio.'/fuentes/m202_anterior.pdf'),
                'resultado' => is_array($r) ? $r : null,
                'fichero' => is_array($r) && ! empty($r['fichero']) && is_file(dirname($res).'/'.$r['fichero']) ? dirname($res).'/'.$r['fichero'] : null,
            ];
        }

        return $out;
    }

    public function updatedSubidaIs($valor, $id): void
    {
        $this->guardarSubida('is', (int) $id);
    }

    public function updatedSubida202($valor, $id): void
    {
        $this->guardarSubida('p202', (int) $id);
    }

    public function updatedSubidaPyg($valor, $id): void
    {
        $this->guardarSubida('pyg', (int) $id);
    }

    protected function guardarSubida(string $tipo, int $id): void
    {
        $prop = ['is' => 'subidaIs', 'p202' => 'subida202', 'pyg' => 'subidaPyg'][$tipo];
        $f = $this->{$prop}[$id] ?? null;
        unset($this->{$prop}[$id]);
        $this->resetErrorBag($prop.'.'.$id);
        if (! $f instanceof UploadedFile || ! $this->autorizado()) {
            return;
        }
        $visible = $this->visibles(DB::table('entidad_impuestos as ei')->where('ei.entidad_id', $id))->exists();
        $nif = $visible ? (string) DB::table('entidades')->where('id', $id)->value('nif') : '';
        if (! $visible) {
            $this->addError($prop.'.'.$id, 'No tienes acceso a esa empresa.');
            return;
        }
        if ($this->nif($nif) === '') {
            $this->addError($prop.'.'.$id, 'La entidad no tiene NIF.');
            return;
        }
        $ext = strtolower($f->getClientOriginalExtension());
        if ($tipo === 'pyg' ? ! in_array($ext, ['xlsx', 'csv'], true) : $ext !== 'pdf') {
            $this->addError($prop.'.'.$id, $tipo === 'pyg' ? 'Tiene que ser un Excel (.xlsx) o un CSV de SAGE.' : 'Tiene que ser un PDF.');
            return;
        }
        $ej = $tipo === 'is' ? $this->ejercicio - 1 : $this->ejercicio;
        $clave = ['is' => 'm200_presentado', 'p202' => 'm202_anterior', 'pyg' => 'pyg202'][$tipo];
        $dir = $this->dirCliente($nif).'/'.$ej.'/fuentes';
        if (! is_dir($dir.'/anteriores') && ! @mkdir($dir.'/anteriores', 0777, true)) {
            $this->addError($prop.'.'.$id, 'No se ha podido crear la carpeta del cliente.');
            return;
        }
        foreach (glob($dir.'/'.$clave.'.*') ?: [] as $viejo) {
            @rename($viejo, $dir.'/anteriores/'.date('Ymd-His').' '.basename($viejo));
        }
        if (! @copy($f->getRealPath(), $dir.'/'.$clave.'.'.$ext)) {
            $this->addError($prop.'.'.$id, 'No se ha podido guardar el fichero.');
            return;
        }
        file_put_contents($dir.'/'.$clave.'.origen.txt', $f->getClientOriginalName()."\n".date('d/m/Y H:i')."\n");
        if ($tipo === 'pyg' && $this->mesPeriodo() && FicherosBase::esSumasSaldos($dir.'/'.$clave.'.'.$ext)) {
            // a los ficheros base de la empresa, para todos los procesos (16-oct-2026)
            if (! FicherosBase::existeContenido($id, 'sys', $dir.'/'.$clave.'.'.$ext)) {
                FicherosBase::guardar($id, 'sys', $dir.'/'.$clave.'.'.$ext, $f->getClientOriginalName(), '202'.(auth()->user() ? ' · '.auth()->user()->name : ''), $this->mesPeriodo());
            }
            FicherosBase::traer($id, 'sys', $this->mesPeriodo(), null, $dir, 'pyg202');   // apunta que ya lo tiene
        }
        $this->dispatch('proceso-terminado', mensaje: '✅ '.['is' => "IS {$ej}", 'p202' => "202 {$ej}", 'pyg' => "P&G {$ej}"][$tipo].': '.$f->getClientOriginalName());
    }

    /** Mes (AAAA-MM) al que llega el sumas y saldos del periodo del 202 (40.3: 3, 9 u 11 primeros meses). */
    protected function mesPeriodo(): ?string
    {
        $m = ['1P' => '03', '2P' => '09', '3P' => '11'][$this->periodo] ?? null;

        return $m ? $this->ejercicio.'-'.$m : null;
    }

    /** Importe escrito a mano ('1.234,56' o '1234.56') → número, o null si no es un número. */
    protected function numero(string $v): ?float
    {
        $v = trim(str_replace(' ', '', $v));
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',') || preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {   // 1.234,56 · 300.000 (punto de miles)
            $v = str_replace(',', '.', str_replace('.', '', $v));
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /** Guarda en ajustes202.json los importes 40.3 escritos en la pantalla (lo vacío no toca lo ya guardado). */
    protected function guardarAjustes403(string $nif, array $valores): ?string
    {
        $ruta = $this->dirCliente($nif).'/'.$this->ejercicio.'/ajustes202.json';
        $aj = is_file($ruta) ? (json_decode((string) file_get_contents($ruta), true) ?: []) : [];
        $cambio = false;
        foreach (array_keys(self::CAMPOS_403) as $campo) {
            $v = trim((string) ($valores[$campo] ?? ''));
            if ($v === '') {
                continue;
            }
            $n = $this->numero($v);
            if ($n === null) {
                return self::CAMPOS_403[$campo].': «'.$v.'» no es un número.';
            }
            $aj[$campo] = $n;
            $cambio = true;
        }
        if ($cambio) {
            @mkdir(dirname($ruta), 0777, true);
            file_put_contents($ruta, json_encode($aj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return null;
    }

    public function marcarTodos(): void
    {
        $this->marcados = [];
        foreach ($this->clientes() as $c) {
            if ($c['tieneIs']) {
                $this->marcados[$c['id']] = true;
            }
        }
    }

    public function desmarcarTodos(): void
    {
        $this->marcados = [];
    }

    protected function pythonBin(): string
    {
        return config('contabilidad.is_python') ?: 'python3';
    }

    public function preparar(): void
    {
        if (! $this->autorizado()) {
            return;
        }
        $ids = $this->clienteId !== 'todos' ? [(int) $this->clienteId] : array_map('intval', array_keys(array_filter($this->marcados)));
        if (! $ids) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ Marca al menos un cliente.');
            return;
        }
        $motor = rtrim((string) config('contabilidad.is_motor202'), '/');
        $this->salida = '';
        $hechos = 0;
        foreach ($this->clientes() as $c) {
            if (! in_array($c['id'], $ids, true)) {
                continue;
            }
            // el sumas y saldos del periodo subido en cualquier pantalla (ficheros base de la empresa), si es nuevo para el 202
            if ($this->mesPeriodo() && ($t = FicherosBase::traer((int) $c['id'], 'sys', $this->mesPeriodo(), null, $this->dirCliente($c['nif']).'/'.$this->ejercicio.'/fuentes', 'pyg202'))) {
                $this->salida .= "📥 {$c['entidad']}: uso el sumas y saldos a ".$this->mesPeriodo()." de los ficheros base («{$t['nombre']}»".($t['origen'] ? ", de {$t['origen']}" : '').").\n";
            }
            $ajustes = $this->dirCliente($c['nif']).'/'.$this->ejercicio.'/ajustes202.json';
            $cnae = trim((string) ($this->cnae[$c['id']] ?? ''));
            if ($cnae !== '') {
                if (! preg_match('/^\d{4}$/', $cnae)) {
                    $this->salida .= "⚠️ {$c['entidad']}: el CNAE son 4 dígitos.\n";
                    continue;
                }
                @mkdir(dirname($ajustes), 0777, true);
                $aj = is_file($ajustes) ? (json_decode((string) file_get_contents($ajustes), true) ?: []) : [];
                $aj['cnae'] = $cnae;
                file_put_contents($ajustes, json_encode($aj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            if (! empty($this->ajustes403[$c['id']])) {
                $err = $this->guardarAjustes403($c['nif'], $this->ajustes403[$c['id']]);
                if ($err) {
                    $this->salida .= "⚠️ {$c['entidad']}: {$err}\n";
                    continue;
                }
                $this->ajustes403[$c['id']] = [];
            }
            try {
                $r = Process::path($motor)->timeout(180)->env(['IS_MOTOR' => $this->baseDir().'/motor'])
                    ->run([$this->pythonBin(), 'prepara202.py', $this->dirCliente($c['nif']), (string) $this->ejercicio, $this->periodo]);
                $ok = $r->successful();
                if ($ok && $this->borradorOutlook) {
                    $this->dejarBorrador($c);
                }
                if (! $ok && ! is_file($this->dirCliente($c['nif']).'/'.$this->ejercicio.'/salida202/'.$c['nif'].'_'.$this->ejercicio.'_'.$this->periodo.'.json')) {
                    $this->salida .= "⚠️ {$c['entidad']}: ".trim($r->errorOutput() ?: $r->output())."\n";
                } else {
                    $hechos += $ok ? 1 : 0;
                }
            } catch (\Throwable $e) {
                $this->salida .= "⚠️ {$c['entidad']}: ".$e->getMessage()."\n";
                report($e);
            }
        }
        $this->dispatch('proceso-terminado', mensaje: "✅ Pagos a cuenta {$this->periodo} {$this->ejercicio}\n{$hechos} fichero(s) .202 generado(s). Revisa los avisos y errores de la tabla.");
    }

    /** Borrador en Outlook (sin enviar) con el resultado, lo no aplicado y el .202 adjunto; Alex añade el borrador de la AEAT y se lo pasa a quien revisa. */
    protected function dejarBorrador(array $c): void
    {
        $nombre = $c['nif'].'_'.$this->ejercicio.'_'.$this->periodo;
        $dir = $this->dirCliente($c['nif']).'/'.$this->ejercicio.'/salida202/';
        $r = is_file($dir.$nombre.'.json') ? json_decode((string) file_get_contents($dir.$nombre.'.json'), true) : null;
        if (! is_array($r) || empty($r['fichero']) || ! is_file($dir.$r['fichero'])) {
            return;
        }
        $e = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
        $d = $r['detalle'] ?? [];
        $t = "Pago fraccionado del Impuesto sobre Sociedades (modelo 202) · {$r['nombre']} · {$this->periodo} {$this->ejercicio}\n";
        $t .= 'Modalidad '.($r['modalidad'] ?? '40.2').' · NIF '.$r['nif']."\n\n";
        if (($r['modalidad'] ?? '') === '40.3') {
            $t .= 'Resultado contable '.$e($d['04'] ?? 0).' → base imponible previa '.$e($d['13'] ?? 0).' → base del pago '.$e($d['16'] ?? 0)
                .' × '.$r['porcentaje']." % = {$e($d['18'] ?? 0)}\nPagos anteriores {$e($d['30'] ?? 0)} · mínimo a ingresar {$e($d['33'] ?? 0)}\n";
        } else {
            $t .= "Cuota del IS anterior {$e($r['cuota_is'] ?? 0)} × {$r['porcentaje']} %\n";
        }
        $t .= 'A INGRESAR: '.$e($r['a_ingresar'] ?? 0)."\n";
        if (! empty($r['no_aplicado'])) {
            $t .= "\nNO APLICADO / A REVISAR:\n".implode("\n", array_map(fn ($x) => '- '.$x, $r['no_aplicado']))."\n";
        }
        if (! empty($r['avisos'])) {
            $t .= "\nAvisos:\n".implode("\n", array_map(fn ($x) => '- '.$x, $r['avisos']))."\n";
        }
        $t .= "\nAdjunto el fichero .202 para importar en la sede de la AEAT. Pendiente: añadir el borrador que se baja de Hacienda tras importarlo.\n";
        $u = auth()->user();
        $buzon = $u && str_ends_with(strtolower((string) $u->email), '@sumaempresa.com') ? $u->email : (string) config('contabilidad.graph.sender');
        try {
            \App\Support\GraphMail::crearBorrador($buzon, (array) config('contabilidad.graph.m202_revisores', []),
                "202 {$this->periodo} {$this->ejercicio} · {$r['nombre']} · para revisar", $t,
                [['nombre' => $r['fichero'], 'contenido' => (string) file_get_contents($dir.$r['fichero']), 'tipo' => 'text/plain']]);
            $this->salida .= "✉️ {$c['entidad']}: borrador dejado en Outlook ({$buzon}).\n";
        } catch (\Throwable $ex) {
            $this->salida .= "⚠️ {$c['entidad']}: no he podido dejar el borrador en Outlook: ".$ex->getMessage()."\n";
        }
    }

    public function descargar(int $id)
    {
        $c = collect($this->clientes())->firstWhere('id', $id);
        if (! $c || ! $c['fichero']) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay fichero .202 de ese cliente: pulsa «Preparar».');
            return null;
        }

        return response()->download($c['fichero'], basename($c['fichero']));
    }

    public function descargarZip()
    {
        $ficheros = array_filter(array_column($this->clientes(), 'fichero'));
        if (! $ficheros || ! class_exists(\ZipArchive::class)) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay ficheros .202 que descargar.');
            return null;
        }
        $zip = tempnam(sys_get_temp_dir(), 'p202');
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::OVERWRITE);
        foreach ($ficheros as $f) {
            $z->addFile($f, basename($f));
        }
        $z->close();

        return response()->download($zip, "202_{$this->ejercicio}_{$this->periodo}.zip")->deleteFileAfterSend(true);
    }

    /** Opciones del desplegable: todos los clientes con el 202 en Impuestos y NIF. */
    protected function opciones(): array
    {
        $o = ['todos' => 'Todos los clientes con pago pendiente'];
        $filas = DB::table('entidad_impuestos as ei')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', '202')->whereNull('e.deleted_at')->tap(fn ($q) => $this->visibles($q))->whereNotNull('e.nif')->where('e.nif', '!=', '')
            ->orderBy('e.entidad')->get(['e.id', 'e.entidad', 'e.nif']);
        foreach ($filas as $f) {
            $o[(string) $f->id] = $f->entidad.' · '.$this->nif($f->nif);
        }

        return $o;
    }

    public function render()
    {
        return view('livewire.contabilidad.is-pagos', ['clientes' => $this->clientes(), 'opciones' => $this->opciones()]);
    }
}
