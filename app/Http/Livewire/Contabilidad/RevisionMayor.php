<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Support\Accesos;
use App\Support\FicherosBase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Revisión del mayor (4-oct-2026), proceso mensual por empresa dentro de Proc.Mensuales; no depende de Facturas OCR. Usa el mayor (y demás ficheros base) CENTRALES
 * de la empresa (App\Support\FicherosBase): si se sube aquí lo ven los otros procesos y al revés. Ejecuta revisar_mayor.py (Contabilidad/FacturasOcr) en el servidor y enseña:
 * pagos en 410000 con factura del mismo importe en otro proveedor, pago y factura abiertos del mismo importe (puntear), provisiones con la factura ya llegada (aplicarlas),
 * provisiones y aplicaciones del mismo importe (puntear) y lo abierto suelto. Solo informa: no cambia nada en SAGE. «✔ Revisado» (con nota) no lo vuelve a proponer.
 */
class RevisionMayor extends Component
{
    use WithFileUploads;

    public ?int $entidadId = null;

    public $subMayor = null;
    public $subPlan = null;
    public $subProveedores = null;
    public $subClientes = null;

    public bool $verRevisadas = false;
    public string $salida = '';
    public string $error = '';

    public function mount(): void
    {
        $this->entidadId = (int) session('revisionmayor.entidad', 0) ?: ($this->empresas()->first()->id ?? null);
        if ($this->entidadId && ! $this->empresas()->contains('id', $this->entidadId)) {
            $this->entidadId = $this->empresas()->first()->id ?? null;
        }
    }

    public function empresas()
    {
        return Entidad::withoutGlobalScopes()->where('cliente', 1)->where('estado', 1)
            ->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])->orderBy('entidad')->get(['id', 'entidad', 'alias']);
    }

    public function updatedEntidadId(): void
    {
        abort_unless($this->empresas()->contains('id', (int) $this->entidadId), 403);
        session(['revisionmayor.entidad' => (int) $this->entidadId]);
        $this->salida = $this->error = '';
    }

    protected function dirSalida(): string
    {
        abort_unless($this->entidadId && $this->empresas()->contains('id', (int) $this->entidadId), 403);
        $d = storage_path('app/revisionmayor/'.$this->entidadId);
        @mkdir($d, 0775, true);

        return $d;
    }

    // ------------------------------------------------------------ ficheros base centrales

    public function updatedSubMayor(): void { $this->subir('mayor', 'subMayor'); }
    public function updatedSubPlan(): void { $this->subir('plan', 'subPlan'); }
    public function updatedSubProveedores(): void { $this->subir('proveedores', 'subProveedores'); }
    public function updatedSubClientes(): void { $this->subir('clientes', 'subClientes'); }

    protected function subir(string $tipo, string $prop): void
    {
        $f = $this->{$prop};
        $this->{$prop} = null;
        $this->error = '';
        if (! $f instanceof UploadedFile || ! $this->entidadId) {
            return;
        }
        abort_unless($this->empresas()->contains('id', (int) $this->entidadId), 403);
        if (! preg_match('/\.xlsx$/i', $f->getClientOriginalName())) {
            $this->error = '«'.$f->getClientOriginalName().'» tiene que ser un Excel (.xlsx) exportado de SAGE.';

            return;
        }
        $r = FicherosBase::guardar((int) $this->entidadId, $tipo, $f->getRealPath(), $f->getClientOriginalName(), 'Revisión del mayor · '.auth()->user()->name);
        if (! $r) {
            $this->error = 'No se pudo guardar el fichero.';

            return;
        }
        $this->dispatch('proceso-terminado', mensaje: '✅ '.FicherosBase::TIPOS[$tipo]['titulo'].' guardado para todos los procesos de esta empresa.');
    }

    public function filasBase(): array
    {
        $out = [];
        foreach (FicherosBase::TIPOS as $t => $d) {
            $u = $this->entidadId ? FicherosBase::ultimo((int) $this->entidadId, $t) : null;
            $out[$t] = $d + ['ultimo' => $u ? ['nombre' => $u['nombre'], 'fecha' => date('d/m/Y H:i', $u['fecha']), 'origen' => $u['origen']] : null];
        }

        return $out;
    }

    // ------------------------------------------------------------ revisar

    protected function python(): string
    {
        foreach ([config('contabilidad.facturasocr_python'), storage_path('app/venv-facturasocr/bin/python')] as $p) {
            if ($p && is_file($p)) {
                return $p;
            }
        }

        return 'python3';
    }

    public function revisar(): void
    {
        $this->error = $this->salida = '';
        $mayor = $this->entidadId ? FicherosBase::ultimo((int) $this->entidadId, 'mayor') : null;
        if (! $mayor) {
            $this->error = 'Falta el mayor de esta empresa: súbelo arriba.';

            return;
        }
        $d = $this->dirSalida();
        $script = rtrim((string) config('contabilidad.facturasocr_dir'), '/').'/revisar_mayor.py';
        try {
            $r = Process::timeout(600)->run([$this->python(), $script, $mayor['ruta'], $d.'/revision.xlsx', '--json', $d.'/revision.json', '--revisados', $d.'/revisados.json']);
        } catch (\Throwable $e) {
            $this->error = 'No se pudo revisar: '.$e->getMessage();

            return;
        }
        if (! $r->successful()) {
            $this->error = 'No se pudo revisar: '.trim($r->errorOutput() ?: $r->output());

            return;
        }
        $this->salida = trim($r->output());
        file_put_contents($d.'/mayor_usado.txt', $mayor['nombre']);
        $this->dispatch('proceso-terminado', mensaje: '✅ Revisión del mayor hecha.');
    }

    protected function revisados(): array
    {
        $f = $this->dirSalida().'/revisados.json';
        $x = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];

        return is_array($x) ? $x : [];
    }

    public function marcarRevisado(string $clave, string $nota = ''): void
    {
        if (! preg_match('/^[a-z0-9]+\|/', $clave)) {
            return;
        }
        $r = $this->revisados();
        $r[$clave] = ['fecha' => now()->format('Y-m-d H:i'), 'nota' => mb_substr($nota, 0, 200), 'quien' => auth()->user()->name];
        file_put_contents($this->dirSalida().'/revisados.json', json_encode((object) $r, JSON_UNESCAPED_UNICODE));
        $this->quitarDeResultado($clave);
    }

    public function desmarcarRevisado(string $clave): void
    {
        $r = $this->revisados();
        unset($r[$clave]);
        file_put_contents($this->dirSalida().'/revisados.json', json_encode((object) $r, JSON_UNESCAPED_UNICODE));
        $this->dispatch('proceso-terminado', mensaje: 'Vuelve a proponerse en la próxima revisión.');
    }

    /** Al marcar, sale ya de la lista que se ve (el JSON se rehace en la próxima revisión). */
    protected function quitarDeResultado(string $clave): void
    {
        $f = $this->dirSalida().'/revision.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (! is_array($d)) {
            return;
        }
        foreach (['p410000', 'pago_factura', 'pago_suma', 'prov_aplicar', 'prov_puntear', 'prov_sin_factura', 'colgados'] as $sec) {
            foreach ($d[$sec] ?? [] as $i => $x) {
                if (($x['clave'] ?? '') === $clave) {
                    $d['revisadas'][$clave] = $this->revisados()[$clave] + ['seccion' => $sec] + $x;
                    unset($d[$sec][$i]);
                    $d[$sec] = array_values($d[$sec]);
                }
            }
        }
        file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
    }

    public function descargar()
    {
        $f = $this->dirSalida().'/revision.xlsx';
        abort_unless(is_file($f), 404);

        return response()->download($f, 'Revision_mayor_'.$this->entidadId.'_'.date('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $res = null;
        $mayorUsado = '';
        if ($this->entidadId && $this->empresas()->contains('id', (int) $this->entidadId)) {
            $f = $this->dirSalida().'/revision.json';
            $res = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
            $mayorUsado = (string) @file_get_contents($this->dirSalida().'/mayor_usado.txt');
        }

        return view('livewire.contabilidad.revision-mayor', [
            'empresas' => $this->empresas(), 'filas' => $this->filasBase(), 'res' => $res, 'mayorUsado' => $mayorUsado,
        ]);
    }
}
