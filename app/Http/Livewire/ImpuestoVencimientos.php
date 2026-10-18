<?php

namespace App\Http\Livewire;

use App\Models\ProcesoEstado;
use App\Support\FicherosBase;
use App\Support\Impuestos as Imp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuestos → Seguimiento: ventana «Vencimientos» (modelo VENC, 18-oct-2026) de una empresa del Grupo y un mes (AAAA-MM).
 * Sube el mayor (fichero base central, el mismo para todos los procesos), exige marcar que los bancos están pasados (si no, no sigue) y ejecuta
 * Contabilidad/FacturasOcr/vencimientos.py: facturas abiertas de proveedores/acreedores del mayor → propuesta de pagos con el formato
 * de «Vtos\NN Vtos <Empresa>.xlsx». Fase 1: propuesta y descarga. Pendiente: guardarla en la carpeta Vtos del cliente y llevarla al
 * fichero del grupo (Grupo 2026\Vencimientos\MM 2026) cuando Alex la dé por buena, y la comprobación de pagos en Z:\VENCIMIENTOS.
 */
class ImpuestoVencimientos extends Component
{
    use WithFileUploads;

    const PROCESO = 'vencimientos';

    public int $entId;
    public string $periodo = '';
    public bool $bancos = false;
    public $subMayor = null;
    public string $error = '';
    public string $salida = '';
    /** Justificantes que Seguimiento da por presentados pero de los que no hay PDF: «303 09/2026»… */
    public array $faltan = [];

    /** Alta de notarios (Vencimientos): nombre, ámbito («todas» o «algunas») y empresas elegidas. */
    public bool $verNotarios = false;
    public string $nNombre = '';
    public string $nAmbito = 'todas';
    public array $nEmpresas = [];

    public function mount(int $entId, string $periodo): void
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo), 422);
        $this->entId = $entId;
        $this->periodo = $periodo;
        $this->autorizar();
        $this->bancos = $this->bancosValidados();
    }

    /** Obligación BANC (casilla «Bancos» de Seguimiento) de esta empresa, si la tiene. */
    protected function bancoOb(): ?int
    {
        return DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where(['ei.entidad_id' => $this->entId, 'm.codigo' => 'BANC'])->whereNull('ei.baja_ejercicio')->value('ei.id');
    }

    /** Bancos validados = casilla «Bancos» del mes en «revisado» (validado). Sin casilla Bancos, la marca antigua de este proceso. */
    protected function bancosValidados(): bool
    {
        if ($ob = $this->bancoOb()) {
            return DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $ob, 'ejercicio' => (int) substr($this->periodo, 0, 4), 'periodo' => substr($this->periodo, 5)])->value('estado') === 'revisado';
        }

        return ProcesoEstado::where(['proceso' => self::PROCESO, 'entidad_id' => $this->entId, 'periodo' => $this->periodo])->where('estado', 'recibido')->exists();
    }

    protected function autorizar(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $ver = DB::table('entidad_impuestos')->where('entidad_id', $this->entId)->pluck('id')->first(fn ($id) => Imp::puedeVer($id, Imp::esGestor()));
        abort_unless($ver, 403);
    }

    protected function dir(): string
    {
        $d = storage_path('app/vencimientos/'.$this->entId.'/'.$this->periodo);
        @mkdir($d, 0775, true);

        return $d;
    }

    public function updatedBancos(): void
    {
        $this->autorizar();
        if ($ob = $this->bancoOb()) {
            $ej = (int) substr($this->periodo, 0, 4);
            $per = substr($this->periodo, 5);
            $fila = DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $ob, 'ejercicio' => $ej, 'periodo' => $per])->first();
            $nuevo = $this->bancos ? 'revisado' : 'pendiente';
            if ($fila) {
                DB::table('impuesto_estados')->where('id', $fila->id)->update(['estado' => $nuevo, 'user_id' => auth()->id(), 'updated_at' => now()]);
            } else {
                DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $ob, 'ejercicio' => $ej, 'periodo' => $per, 'estado' => $nuevo, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            }

            return;
        }
        $f = ProcesoEstado::firstOrNew(['proceso' => self::PROCESO, 'entidad_id' => $this->entId, 'periodo' => $this->periodo]);
        $f->fill(['estado' => $this->bancos ? 'recibido' : 'solicitado', 'user_id' => auth()->id()])->save();
    }

    public function updatedSubMayor(): void
    {
        $this->autorizar();
        $f = $this->subMayor;
        $this->subMayor = null;
        $this->error = '';
        if (! $f instanceof UploadedFile) {
            return;
        }
        if (! preg_match('/\.xlsx$/i', $f->getClientOriginalName())) {
            $this->error = '«'.$f->getClientOriginalName().'» tiene que ser un Excel (.xlsx) exportado de SAGE.';

            return;
        }
        if (! FicherosBase::guardar($this->entId, 'mayor', $f->getRealPath(), $f->getClientOriginalName(), 'Vencimientos '.$this->periodo.' · '.auth()->user()->name)) {
            $this->error = 'No se pudo guardar el fichero.';
        }
    }

    protected function python(): string
    {
        foreach ([config('contabilidad.facturasocr_python'), storage_path('app/venv-facturasocr/bin/python')] as $p) {
            if ($p && is_file($p)) {
                return $p;
            }
        }

        return 'python3';
    }

    /** Modelos que se pagan a la AEAT y cuándo: [modelo => true]; el mes de pago sale de pagoDe(). */
    const MODELOS_PAGO = ['303', '111', '115', '123', '130', '202', '216', '210'];

    /** Periodos [ejercicio, periodo] cuyo pago cae en el mes AAAA-MM: mensuales (mes anterior), trimestrales (abr/jul/oct/ene) y pagos fraccionados 202 (abr/oct/dic). */
    protected function periodosQuePagan(): array
    {
        [$a, $m] = array_map('intval', explode('-', $this->periodo));
        $out = [[$m === 1 ? $a - 1 : $a, sprintf('%02d', $m === 1 ? 12 : $m - 1)]];
        $t = [4 => 'T1', 7 => 'T2', 10 => 'T3'][$m] ?? null;
        if ($t) {
            $out[] = [$a, $t];
        }
        if ($m === 1) {
            $out[] = [$a - 1, 'T4'];
        }
        $p = [4 => 'P1', 10 => 'P2', 12 => 'P3'][$m] ?? null;
        if ($p) {
            $out[] = [$a, $p];
        }

        return $out;
    }

    /** Justificantes presentados de la empresa cuyo pago cae en el mes: [modelo, periodo, ejercicio, ruta]. */
    protected function justificantes(): array
    {
        $res = [];
        foreach ($this->periodosQuePagan() as [$ej, $per]) {
            $docs = DB::table('impuesto_documentos as d')
                ->join('entidad_impuestos as ei', fn ($j) => $j->on('ei.entidad_id', '=', 'd.entidad_id')->on('ei.modelo_id', '=', DB::raw('(select id from impuesto_modelos where codigo = d.modelo)')))
                ->join('impuesto_estados as s', fn ($j) => $j->on('s.entidad_impuesto_id', '=', 'ei.id')->where('s.ejercicio', $ej)->where('s.periodo', $per))
                ->where('d.entidad_id', $this->entId)->where('d.ejercicio', $ej)->where('d.periodo', $per)->where('d.tipo', 'presentado')->whereNull('d.quitado_at')
                ->whereIn('d.modelo', self::MODELOS_PAGO)->whereIn('s.estado', ['presentado', 'visto'])
                ->orderBy('d.id')->get(['d.id', 'd.modelo', 'd.nombre', 'd.almacen']);
            foreach ($docs->reject(fn ($d) => preg_match('/anexo/i', $d->nombre))->groupBy('modelo') as $modelo => $lista) {
                $d = $lista->last();   // si hay complementaria, el último subido
                $ruta = storage_path('app/'.$d->almacen);
                if (is_file($ruta)) {
                    $res[$modelo.'|'.$per] = ['modelo' => (string) $modelo, 'periodo' => $per, 'ejercicio' => $ej, 'ruta' => $ruta];
                }
            }
        }

        $this->faltan = $this->sinPdf($res);

        return array_values($res);
    }

    /** Obligaciones presentadas (o vistas) que se pagan este mes y no tienen PDF subido: [«303 09/2026»]. */
    protected function sinPdf(array $conPdf): array
    {
        $falta = [];
        foreach ($this->periodosQuePagan() as [$ej, $per]) {
            $filas = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
                ->join('impuesto_estados as s', fn ($j) => $j->on('s.entidad_impuesto_id', '=', 'ei.id')->where('s.ejercicio', $ej)->where('s.periodo', $per))
                ->where('ei.entidad_id', $this->entId)->whereIn('m.codigo', self::MODELOS_PAGO)->whereIn('s.estado', ['presentado', 'visto'])->get(['m.codigo']);
            foreach ($filas as $f) {
                if (! isset($conPdf[$f->codigo.'|'.$per])) {
                    $falta[] = $f->codigo.' '.$per.'/'.$ej;
                }
            }
        }

        return $falta;
    }

    /** Pide a un PC que busque en OneDrive (…/_Impuestos AAAA) los PDF que faltan: la misma tarea que «Buscar PDF en OneDrive» de Seguimiento. */
    protected function buscarEnOneDrive(): string
    {
        if (DB::table('tareas')->where('proceso', 'pc.impuestos_pdfs')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            return 'Ya hay una búsqueda de PDF en OneDrive en marcha.';
        }
        \App\Support\ColaTareas::crear('pc.impuestos_pdfs', ['anios' => [(int) substr($this->periodo, 0, 4)]], \App\Support\ColaTareas::pcElegido() ?: null, auth()->id());

        return 'He pedido a un PC que busque esos PDF en OneDrive.';
    }

    public function preparar(): void
    {
        $this->autorizar();
        $this->error = $this->salida = '';
        $this->bancos = $this->bancosValidados();   // por si se ha cambiado la casilla Bancos de Seguimiento con la ventana abierta
        if (! $this->bancos) {
            // Decisión de Alex (18-oct-2026), excepción a «avisar, no bloquear»: sin bancos pasados la propuesta saldría con pagos como pendientes
            $this->error = 'No se puede preparar hasta que los bancos de este mes estén pasados: pásalos (Bancos), sube el mayor con ellos y valídalos (casilla «Bancos» de Seguimiento o la casilla de abajo).';

            return;
        }
        $mayor = FicherosBase::ultimo($this->entId, 'mayor');
        if (! $mayor) {
            $this->error = 'Falta el mayor de esta empresa: súbelo arriba (con los bancos incluidos).';

            return;
        }
        $d = $this->dir();
        $empresa = (string) DB::table('entidades')->where('id', $this->entId)->value('entidad');
        $script = rtrim((string) config('contabilidad.facturasocr_dir'), '/').'/vencimientos.py';
        $orden = [$this->python(), $script, $mayor['ruta'], $d.'/vencimientos.xlsx', '--empresa', $empresa, '--mes', $this->periodo, '--json', $d.'/vencimientos.json', '--reglas', $this->reglasConNotarios($d, $script)];
        try {
            // Impuestos a pagar: importe de los justificantes ya presentados y subidos a Seguimiento (impuestos_a_pagar.py)
            $just = $this->justificantes();
            $aviso = $this->faltan ? 'Faltan los PDF de: '.implode(', ', $this->faltan).'. '.$this->buscarEnOneDrive().' Cuando termine (unos minutos), vuelve a preparar. ' : '';
            if ($just) {
                file_put_contents($d.'/justificantes.json', json_encode($just));
                $ri = Process::timeout(300)->run([$this->python(), dirname($script).'/impuestos_a_pagar.py', $d.'/justificantes.json', $d.'/impuestos.json']);
                if ($ri->successful() && is_file($d.'/impuestos.json')) {
                    $imp = json_decode((string) file_get_contents($d.'/impuestos.json'), true) ?: [];
                    foreach ($imp as $i => $x) {
                        $imp[$i]['ejercicio'] = collect($just)->first(fn ($j) => $j['modelo'] === $x['modelo'] && $j['periodo'] === $x['periodo'])['ejercicio'] ?? (int) substr($this->periodo, 0, 4);
                    }
                    file_put_contents($d.'/impuestos.json', json_encode($imp));
                    array_push($orden, '--impuestos', $d.'/impuestos.json');
                }
            }
            $r = Process::timeout(300)->run($orden);
        } catch (\Throwable $e) {
            $this->error = 'No se pudo preparar: '.$e->getMessage();

            return;
        }
        if (! $r->successful()) {
            $this->error = 'No se pudo preparar: '.trim($r->errorOutput() ?: $r->output());

            return;
        }
        $this->salida = ($aviso ?? '').trim($r->output());
        file_put_contents($d.'/mayor_usado.txt', $mayor['nombre']);
    }

    /** Empresas del Grupo con la obligación VENC: [id => nombre]. */
    protected function empresasVenc(): array
    {
        return DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', 'VENC')->whereNull('ei.baja_ejercicio')->orderBy('e.entidad')->pluck('e.entidad', 'e.id')->all();
    }

    public function anadirNotario(): void
    {
        $this->autorizar();
        $nombre = trim($this->nNombre);
        if ($nombre === '') {
            $this->error = 'Escribe el nombre del notario tal como sale en el mayor (vale una parte, p. ej. «Notarios Provenza»).';

            return;
        }
        $ids = $this->nAmbito === 'todas' ? [] : array_values(array_map('intval', $this->nEmpresas));
        if ($this->nAmbito !== 'todas' && ! $ids) {
            $this->error = 'Elige al menos una empresa, o marca «Todas las empresas».';

            return;
        }
        $this->error = '';
        DB::table('vencimientos_notarios')->insert(['nombre' => $nombre, 'todas' => $this->nAmbito === 'todas', 'entidades' => $ids ? json_encode($ids) : null, 'created_at' => now(), 'updated_at' => now()]);
        $this->nNombre = '';
        $this->nAmbito = 'todas';
        $this->nEmpresas = [];
    }

    public function quitarNotario(int $id): void
    {
        $this->autorizar();
        DB::table('vencimientos_notarios')->where('id', $id)->delete();
    }

    /** Reglas del fichero + los notarios que valen para esta empresa (todas, o ella entre las elegidas) → ruta del JSON a pasar a vencimientos.py. */
    protected function reglasConNotarios(string $dir, string $script): string
    {
        $base = dirname($script).'/vencimientos_reglas.json';
        $r = is_file($base) ? (json_decode((string) file_get_contents($base), true) ?: []) : [];
        foreach (DB::table('vencimientos_notarios')->get() as $n) {
            if ($n->todas || in_array($this->entId, json_decode((string) $n->entidades, true) ?: [], true)) {
                $r['excluir_proveedores'][] = $n->nombre;
            }
        }
        file_put_contents($dir.'/reglas.json', json_encode($r, JSON_UNESCAPED_UNICODE));

        return $dir.'/reglas.json';
    }

    public function descargar()
    {
        $this->autorizar();
        $f = $this->dir().'/vencimientos.xlsx';
        abort_unless(is_file($f), 404);
        $n = preg_replace('/[^A-Za-z0-9]+/', '_', (string) DB::table('entidades')->where('id', $this->entId)->value('entidad'));

        return response()->download($f, substr($this->periodo, 5).' Vtos '.trim($n, '_').'.xlsx');
    }

    public function cerrar(): void
    {
        $this->dispatch('vt-cerrar');
    }

    public function render()
    {
        $f = $this->dir().'/vencimientos.json';
        $res = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
        $u = FicherosBase::ultimo($this->entId, 'mayor');

        return view('livewire.impuesto-vencimientos', [
            'res' => $res, 'mayorUsado' => (string) @file_get_contents($this->dir().'/mayor_usado.txt'),
            'mayor' => $u ? ['nombre' => $u['nombre'], 'fecha' => date('d/m/Y H:i', $u['fecha'])] : null,
            'notarios' => DB::table('vencimientos_notarios')->orderBy('nombre')->get(), 'empresasVenc' => $this->empresasVenc(),
            'empresa' => DB::table('entidades')->where('id', $this->entId)->value('entidad'),
        ]);
    }
}
