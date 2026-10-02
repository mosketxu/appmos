<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\ProcesoEstado;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Seguimiento mensual (2-oct-2026): el checklist de TODOS los procesos mensuales, al estilo del de Procesos FIQ.
 * Una fila por proceso (seguimiento_procesos) y una columna por mes del año (se despliega hacia la derecha).
 * - Proceso «general»: un check por mes (ok → «no toca» → vacío).
 * - Proceso «cliente»: la celda del mes resume n/N empresas; con ▸ se despliegan las empresas del usuario
 *   (las del panel de control, Accesos::entidadesPropias), cada una con su check por mes.
 *   Si el proceso es automático (auto = petdocimpuestos) el estado de cada empresa es el de procesos_estado.
 * - ejecucion «local»: el proceso se hace desde un PC (los datos están allí); el Seguimiento solo lo anota.
 * Ver Contabilidad/ProcesosMensuales/PLAN.md.
 */
class SeguimientoMensual extends Component
{
    public int $anio;

    /** Procesos por cliente desplegados (ids). */
    public array $abiertos = [];

    public string $buscar = '';

    // Alta de un proceso nuevo
    public bool $nuevo = false;
    public string $nNombre = '';
    public string $nAmbito = 'general';
    public string $nEjecucion = 'web';
    public string $nDetalle = '';

    public function mount(): void
    {
        $this->anio = (int) now()->format('Y');
    }

    public function cambiarAnio(int $d): void
    {
        $this->anio += $d;
    }

    public function getMesesProperty(): array
    {
        $n = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $m = [];
        foreach ($n as $i => $nombre) {
            $m[sprintf('%d-%02d', $this->anio, $i + 1)] = $nombre;
        }
        return $m;
    }

    public function alternar(int $proceso): void
    {
        in_array($proceso, $this->abiertos, true)
            ? $this->abiertos = array_values(array_diff($this->abiertos, [$proceso]))
            : $this->abiertos[] = $proceso;
    }

    /** Empresas del usuario para los procesos por cliente: clientes activos que gestiona. */
    protected function empresas()
    {
        return Entidad::withoutGlobalScopes()->where('cliente', 1)->where('estado', 1)
            ->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'mail_peticion_check']);
    }

    protected function permitido(): bool
    {
        return auth()->user()->can('contabilidad.procesosmensuales');
    }

    /** Clic en el check de un proceso general: ok → no toca → vacío. */
    public function marcar(int $proceso, string $periodo, int $entidad = 0): void
    {
        if (! $this->permitido() || ! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return;
        }
        $p = DB::table('seguimiento_procesos')->find($proceso);
        if (! $p) {
            return;
        }
        if ($entidad && ! in_array($entidad, Accesos::entidadesPropias(auth()->user()), true)) {
            return;
        }
        if ($p->auto === 'petdocimpuestos' && $entidad) {
            // El estado vive en procesos_estado: vacío → solicitado → recibido → vacío
            $q = ProcesoEstado::where('proceso', 'petdocimpuestos')->where('entidad_id', $entidad)->where('periodo', $periodo);
            $actual = $q->value('estado');
            if ($actual === 'recibido') {
                $q->delete();
            } else {
                $fila = ProcesoEstado::firstOrNew(['proceso' => 'petdocimpuestos', 'entidad_id' => $entidad, 'periodo' => $periodo]);
                $fila->estado = $actual === null ? 'solicitado' : 'recibido';
                $fila->user_id = auth()->id();
                $fila->solicitado_at ??= now();
                if ($fila->estado === 'recibido') {
                    $fila->recibido_at = now();
                }
                $fila->save();
            }
            return;
        }
        $q = DB::table('seguimiento_marcas')->where(['proceso_id' => $proceso, 'entidad_id' => $entidad, 'periodo' => $periodo]);
        $actual = $q->value('estado');
        if ($actual === 'na') {
            $q->delete();
        } elseif ($actual === null) {
            DB::table('seguimiento_marcas')->insert(['proceso_id' => $proceso, 'entidad_id' => $entidad, 'periodo' => $periodo,
                'estado' => 'ok', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $q->update(['estado' => 'na', 'user_id' => auth()->id(), 'updated_at' => now()]);
        }
    }

    public function crear(): void
    {
        if (! $this->permitido() || trim($this->nNombre) === '') {
            return;
        }
        $clave = \Illuminate\Support\Str::slug($this->nNombre, '_');
        if (DB::table('seguimiento_procesos')->where('clave', $clave)->exists()) {
            $clave .= '_'.time();
        }
        DB::table('seguimiento_procesos')->insert([
            'clave' => $clave, 'nombre' => trim($this->nNombre), 'detalle' => trim($this->nDetalle) ?: null,
            'ambito' => $this->nAmbito === 'cliente' ? 'cliente' : 'general',
            'ejecucion' => in_array($this->nEjecucion, ['local', 'ambos'], true) ? $this->nEjecucion : 'web',
            'orden' => (int) DB::table('seguimiento_procesos')->max('orden') + 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reset('nuevo', 'nNombre', 'nDetalle');
        $this->nAmbito = 'general';
        $this->nEjecucion = 'web';
    }

    public function quitar(int $id): void
    {
        if ($this->permitido()) {
            DB::table('seguimiento_procesos')->where('id', $id)->update(['activo' => false]);
        }
    }

    /** Sube (-1) o baja (+1) un proceso en la lista. */
    public function mover(int $id, int $d): void
    {
        if (! $this->permitido()) {
            return;
        }
        $ids = DB::table('seguimiento_procesos')->where('activo', true)->orderBy('orden')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $i + $d;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $pid) {
            DB::table('seguimiento_procesos')->where('id', $pid)->update(['orden' => ($pos + 1) * 10]);
        }
    }

    public function render()
    {
        $procesos = DB::table('seguimiento_procesos')->where('activo', true)->orderBy('orden')->orderBy('id')->get();
        $meses = array_keys($this->meses);
        $empresas = $this->empresas();
        $hayCliente = $procesos->contains('ambito', 'cliente');

        // estado[proceso][entidad][periodo] = ok|proc|na
        $estado = [];
        foreach (DB::table('seguimiento_marcas')->whereIn('periodo', $meses)->get() as $m) {
            $estado[$m->proceso_id][$m->entidad_id][$m->periodo] = $m->estado;
        }
        foreach ($procesos->where('auto', 'petdocimpuestos') as $p) {
            foreach (ProcesoEstado::where('proceso', 'petdocimpuestos')->whereIn('periodo', $meses)->get() as $e) {
                $estado[$p->id][$e->entidad_id][$e->periodo] = $e->estado === 'recibido' ? 'ok' : 'proc';
            }
        }

        // Empresas a las que aplica cada proceso por cliente
        $aplican = [];
        foreach ($procesos->where('ambito', 'cliente') as $p) {
            $aplican[$p->id] = $p->auto === 'petdocimpuestos' ? $empresas->filter(fn ($e) => $e->mail_peticion_check) : $empresas;
        }

        return view('livewire.contabilidad.seguimiento-mensual', [
            'procesos' => $procesos,
            'estado' => $estado,
            'aplican' => $aplican,
            'hayCliente' => $hayCliente,
            'mesActual' => now()->format('Y-m'),
            'puede' => $this->permitido(),
            'filtro' => $this->buscar,
        ]);
    }
}
