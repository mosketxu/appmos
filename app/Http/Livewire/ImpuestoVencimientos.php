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

    public function mount(int $entId, string $periodo): void
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo), 422);
        $this->entId = $entId;
        $this->periodo = $periodo;
        $this->autorizar();
        $this->bancos = ProcesoEstado::where(['proceso' => self::PROCESO, 'entidad_id' => $entId, 'periodo' => $periodo])->where('estado', 'recibido')->exists();
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

    public function preparar(): void
    {
        $this->autorizar();
        $this->error = $this->salida = '';
        if (! $this->bancos) {
            // Decisión de Alex (18-oct-2026), excepción a «avisar, no bloquear»: sin bancos pasados la propuesta saldría con pagos como pendientes
            $this->error = 'No se puede preparar hasta que los bancos de este mes estén pasados: pásalos (Bancos), sube el mayor con ellos y marca la casilla.';

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
        try {
            $r = Process::timeout(300)->run([$this->python(), $script, $mayor['ruta'], $d.'/vencimientos.xlsx', '--empresa', $empresa, '--mes', $this->periodo, '--json', $d.'/vencimientos.json']);
        } catch (\Throwable $e) {
            $this->error = 'No se pudo preparar: '.$e->getMessage();

            return;
        }
        if (! $r->successful()) {
            $this->error = 'No se pudo preparar: '.trim($r->errorOutput() ?: $r->output());

            return;
        }
        $this->salida = trim($r->output());
        file_put_contents($d.'/mayor_usado.txt', $mayor['nombre']);
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
            'empresa' => DB::table('entidades')->where('id', $this->entId)->value('entidad'),
        ]);
    }
}
