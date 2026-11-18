<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\FiqIc;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Facturación Breuninger / OMC de Fashion IQ (panel de Procesos FIQ, 18-nov-2026). Sustituye al Excel.
 * Alex teclea las facturas de compra de Plein (proveedor, nº, fecha, importe, tienda); Appmos calcula el 5 %,
 * enseña las cuentas de cada compra y de la venta, el nº de la nueva factura y el total por tienda.
 * El PDF de la factura y los asientos siguen haciéndose en SAGE (por ahora a mano).
 */
class FacturacionIc extends Component
{
    public string $empresa = 'Breuninger';
    public int $anio;
    public int $mes;

    public $cuenta = '';
    public string $numero = '';
    public string $fecha = '';
    public string $importe = '';
    public $canal = '';
    public ?string $aviso = null;
    public ?string $fechaEmision = null;

    public function mount(string $empresa, int $anio, int $mes): void
    {
        $this->empresa = in_array($empresa, FiqIc::empresas(), true) ? $empresa : 'Breuninger';
        $this->anio = $anio;
        $this->mes = $mes;
        $this->fechaEmision = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
        $cuentas = array_keys(FiqIc::cuentasDe($this->empresa));
        $this->cuenta = $cuentas[0] ?? '';
    }

    public function getPeriodoProperty(): string
    {
        return sprintf('%d-%02d', $this->anio, $this->mes);
    }

    /** Id del check «Emitir ventas» de esta empresa en el checklist. */
    protected function idEmitir(): string
    {
        return $this->empresa === 'OMC' ? 'ocm_ventas' : 'breuninger_ventas';
    }

    protected function permitido(): bool
    {
        $u = auth()->user();

        return ! $u || ! Schema::hasTable('permissions') || $u->can('proceso.fiq.'.$this->idEmitir());
    }

    protected function listo(): bool
    {
        return Schema::hasTable('fiq_ic_compras') && Schema::hasTable('fiq_ic_emisiones');
    }

    public function anadir(): void
    {
        $this->aviso = null;
        if (! $this->listo() || ! $this->permitido()) {
            return;
        }
        $cuentas = FiqIc::cuentasDe($this->empresa);
        $imp = FiqIc::numero($this->importe);
        $numero = trim($this->numero);
        if (! isset($cuentas[(int) $this->cuenta]) || $numero === '' || $imp === null || ! array_key_exists((int) $this->canal, FiqIc::TIENDAS) || $this->canal === '') {
            $this->aviso = '⚠️ Faltan datos: proveedor, nº de factura, importe (abono en negativo) y tienda.';

            return;
        }
        $fecha = $this->fecha !== '' ? $this->fecha : null;
        // Alex decide: si ya existe, se avisa (no se bloquea).
        $dup = DB::table('fiq_ic_compras')->where('cuenta', (int) $this->cuenta)->where('numero', $numero)->where('importe', $imp)->first();
        DB::table('fiq_ic_compras')->insert([
            'empresa' => $this->empresa, 'periodo' => $this->periodo, 'cuenta' => (int) $this->cuenta, 'numero' => $numero,
            'fecha' => $fecha, 'canal' => (int) $this->canal, 'importe' => $imp, 'origen' => 'web',
            'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($dup) {
            $this->aviso = "⚠️ Ojo: la factura {$numero} por ".number_format($imp, 2, ',', '.').' ya estaba apuntada'.($dup->emision_id ? ' (y emitida)' : '').'. Se ha añadido igualmente; bórrala si es un duplicado.';
        }
        $this->numero = '';
        $this->importe = '';
    }

    public function borrar(int $id): void
    {
        if (! $this->listo() || ! $this->permitido()) {
            return;
        }
        DB::table('fiq_ic_compras')->where('id', $id)->where('empresa', $this->empresa)->whereNull('emision_id')->delete();
    }

    /** Crea la factura con las compras pendientes del mes y le da el siguiente nº. */
    public function emitir(): void
    {
        $this->aviso = null;
        if (! $this->listo() || ! $this->permitido()) {
            return;
        }
        $res = DB::transaction(function () {
            $pend = DB::table('fiq_ic_compras')->where('empresa', $this->empresa)->where('periodo', $this->periodo)->whereNull('emision_id')->lockForUpdate()->get();
            if ($pend->isEmpty()) {
                return null;
            }
            $r = FiqIc::resumen($pend);
            $serie = FiqIc::serie($this->empresa, $this->anio);
            $num = FiqIc::siguienteNumero($this->empresa, $this->anio);
            $fecha = preg_match('/^\d{4}-\d\d-\d\d$/', (string) $this->fechaEmision) ? $this->fechaEmision : Carbon::create($this->anio, $this->mes, 1)->endOfMonth()->toDateString();
            $id = DB::table('fiq_ic_emisiones')->insertGetId([
                'empresa' => $this->empresa, 'periodo' => $this->periodo, 'serie' => $serie, 'numero' => $num, 'tipo' => 'factura',
                'fecha' => $fecha, 'total' => $r['total'], 'total_calculado' => $r['total'], 'origen' => 'web', 'user_id' => auth()->id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fiq_ic_compras')->whereIn('id', $pend->pluck('id'))->update(['emision_id' => $id, 'updated_at' => now()]);

            return FiqIc::etiqueta($serie, $num);
        });
        if ($res === null) {
            $this->aviso = '⚠️ No hay compras pendientes en este mes.';

            return;
        }
        $this->aviso = "✓ Emitida {$res}. Haz el PDF en SAGE con ese número y el total de abajo.";
        $this->dispatch('ic-marcar', id: $this->idEmitir(), mes: $this->mes);
    }

    /** Deshace la emisión (solo la última de la serie, para no dejar huecos): las compras vuelven a pendientes. */
    public function deshacer(int $id): void
    {
        if (! $this->listo() || ! $this->permitido()) {
            return;
        }
        $e = DB::table('fiq_ic_emisiones')->where('id', $id)->where('empresa', $this->empresa)->first();
        if (! $e) {
            return;
        }
        if ($e->origen !== 'web') {
            $this->aviso = '⚠️ Esta factura viene del Excel histórico: no se deshace desde aquí.';

            return;
        }
        if ($e->numero !== null && $e->serie === FiqIc::serie($this->empresa, $this->anio) && $e->numero != FiqIc::siguienteNumero($this->empresa, $this->anio) - 1) {
            $this->aviso = '⚠️ Solo se puede deshacer la última factura de la serie (si no, quedaría un hueco en la numeración).';

            return;
        }
        DB::transaction(function () use ($id) {
            DB::table('fiq_ic_compras')->where('emision_id', $id)->update(['emision_id' => null, 'updated_at' => now()]);
            DB::table('fiq_ic_emisiones')->where('id', $id)->delete();
        });
        $this->aviso = 'Emisión deshecha: las compras vuelven a estar pendientes (desmarca a mano «Emitir» si ya no toca).';
    }

    public function render()
    {
        $datos = ['listo' => $this->listo(), 'pendientes' => collect(), 'emisiones' => collect(), 'resumenPend' => null, 'resumenes' => [], 'cuentas' => FiqIc::cuentasDe($this->empresa)];
        if ($datos['listo']) {
            $comp = DB::table('fiq_ic_compras')->where('empresa', $this->empresa)->where('periodo', $this->periodo)->orderBy('cuenta')->orderBy('id')->get();
            $datos['pendientes'] = $comp->whereNull('emision_id')->values();
            $datos['resumenPend'] = $datos['pendientes']->isEmpty() ? null : FiqIc::resumen($datos['pendientes']);
            $datos['emisiones'] = DB::table('fiq_ic_emisiones')->where('empresa', $this->empresa)->where('periodo', $this->periodo)->orderBy('id')->get();
            foreach ($datos['emisiones'] as $e) {
                $ls = $comp->where('emision_id', $e->id)->values();
                $datos['resumenes'][$e->id] = ['lineas' => $ls, 'r' => FiqIc::resumen($ls)];
            }
            $datos['siguiente'] = FiqIc::etiqueta(FiqIc::serie($this->empresa, $this->anio), FiqIc::siguienteNumero($this->empresa, $this->anio));
        }

        return view('livewire.contabilidad.facturacion-ic', $datos);
    }
}
