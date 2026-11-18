<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\FicherosBase;
use App\Support\FiqIc;
use App\Support\FiqIcValidacion;
use App\Support\MayorLector;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * «Validación OMC y Breuninger» (Procesos FIQ, 18-nov-2026): cuadra con el último mayor de SAGE (ficheros base) que las compras de Plein
 * y las ventas emitidas en Appmos están bien contabilizadas (nº, importe, tienda y cuentas). Si todo cuadra, marca el check del mes.
 */
class ValidacionIc extends Component
{
    public int $anio;
    public int $mes;
    public ?array $res = null;
    public ?array $mayor = null;
    public ?string $aviso = null;

    public function mount(int $anio, int $mes): void
    {
        $this->anio = $anio;
        $this->mes = $mes;
    }

    protected function permitido(): bool
    {
        $u = auth()->user();

        return ! $u || ! \Illuminate\Support\Facades\Schema::hasTable('permissions') || $u->can('proceso.fiq.validacion_ic');
    }

    #[On('validar-ic')]
    public function validar(): void
    {
        $this->aviso = null;
        $this->res = null;
        if (! $this->permitido()) {
            $this->aviso = '⚠️ No tienes acceso a este proceso.';

            return;
        }
        $eid = (int) config('contabilidad.fiq_entidad');
        $m = $eid ? FicherosBase::ultimo($eid, 'mayor', null, $this->anio) : null;
        if (! $m || ! FicherosBase::esMayorLineal($m['ruta'])) {
            $this->aviso = '⚠️ No hay un mayor de SAGE en columnas en los ficheros base de Fashion IQ (recuadro «Ficheros base de SAGE» arriba): súbelo y vuelve a validar.';

            return;
        }
        @ini_set('memory_limit', '768M');
        @set_time_limit(180);
        try {
            $l = MayorLector::leer($m['ruta'], FiqIc::cuentasMayor());
            $r = FiqIcValidacion::validar($this->anio, $this->mes, $l['filas'], $l['max_fecha']);
        } catch (\Throwable $e) {
            $this->aviso = '⚠️ No he podido leer el mayor: '.$e->getMessage();

            return;
        }
        $this->mayor = ['nombre' => $m['nombre'], 'subido' => date('d/m/Y H:i', $m['fecha']), 'hasta' => $l['max_fecha'] ? date('d/m/Y', strtotime($l['max_fecha'])) : null, 'origen' => $m['origen'] ?? ''];
        $this->res = json_decode(json_encode($r), true);   // Livewire no guarda objetos
        if ($r['problemas'] === 0 && ! $r['aviso_mayor']) {
            $this->dispatch('ic-marcar', id: 'validacion_ic', mes: $this->mes);
        }
    }

    public function render()
    {
        return view('livewire.contabilidad.validacion-ic');
    }
}
