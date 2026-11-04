<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Support\Anio;
use App\Support\FicherosBase;
use Livewire\Component;

/**
 * Informes de Balerga (4-nov-2026, Proc.Mensuales; clave informes_balerga). Albena (Balerga Digital) pidió el 26-oct: cada mes un resumen de
 * alquileres facturados, cobrados y pendientes de cobro + principales gastos, y cada trimestre un P&L detallado. Esta pantalla dice qué ficheros
 * de SAGE hay que subir y los recibe en los ficheros base centrales de la entidad (mayor, sumas y saldos, P&G, balance). La generación del
 * informe se hace a partir de ellos en una sesión con Claude (formato aún por decidir). Ver Contabilidad/Balerga/PLAN.md.
 */
class InformesBalerga extends Component
{
    public const CLAVE = 'informes_balerga';
    public const NIF = 'B21740998';

    /** Mes que se informa (AAAA-MM); por defecto el mes anterior. */
    public string $periodo = '';

    /** Al subir un fichero, se vuelve a mirar qué hay (si no, el cuadro de «falta subirlo» se queda desfasado). */
    protected $listeners = ['ficheros-base-cambiados' => '$refresh'];

    public function mount(): void
    {
        $this->periodo = now()->subMonth()->format('Y-m');
    }

    protected function puede(): bool
    {
        $u = auth()->user();

        return $u && $u->can('contabilidad.procesosmensuales') && $u->can('proceso.pm.'.self::CLAVE);
    }

    protected function entidad(): ?Entidad
    {
        return Entidad::where('nif', self::NIF)->first() ?? Entidad::where('entidad', 'like', '%alerga%')->orderBy('id')->first();
    }

    public function render()
    {
        $puede = $this->puede();
        $ent = $puede ? $this->entidad() : null;
        $valido = (bool) preg_match('/^\d{4}-\d{2}$/', $this->periodo);
        $mes = $valido ? (int) substr($this->periodo, 5) : 0;
        $estado = [];
        if ($ent && $valido) {
            $inicioMesActual = strtotime(now()->startOfMonth()->toDateString());
            $mayor = FicherosBase::ultimo((int) $ent->id, 'mayor');
            $estado['mayor'] = ['ultimo' => $mayor, 'antiguo' => $mayor && $mayor['fecha'] < $inicioMesActual];
            foreach (['sys', 'pyg', 'balance'] as $t) {
                $estado[$t] = ['ultimo' => FicherosBase::ultimo((int) $ent->id, $t, $this->periodo), 'antiguo' => false];
            }
        }

        return view('livewire.contabilidad.informes-balerga', [
            'puede' => $puede, 'ent' => $ent, 'valido' => $valido, 'estado' => $estado,
            'trimestre' => $valido && $mes % 3 === 0 ? intdiv($mes, 3) : null,
            'anio' => $valido ? (int) substr($this->periodo, 0, 4) : Anio::actual(),
        ]);
    }
}
