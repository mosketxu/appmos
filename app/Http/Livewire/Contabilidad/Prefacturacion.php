<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Prefacturación (21-oct-2026, tarea #74; Proc.Mensuales): costes que Marta prepara cada mes para facturar después en SAGE.
 * Cada línea: cliente (entidad o texto libre), importe, periodo en que se factura (mes), motivo, adjunto opcional (albarán/factura)
 * y estado pendiente/facturado. Tabla prefacturas; adjuntos en storage/app/prefacturas.
 */
class Prefacturacion extends Component
{
    use WithFileUploads;

    public string $periodo = '';
    public string $estadoFiltro = '';

    public ?int $editando = null;
    public string $fEntidad = '';
    public string $fCliente = '';
    public string $fImporte = '';
    public string $fPeriodo = '';
    public string $fMotivo = '';
    public $fAdjunto = null;
    public string $aviso = '';

    public function mount(): void
    {
        $this->periodo = now()->format('Y-m');
        $this->fPeriodo = $this->periodo;
    }

    protected function permitido(): bool
    {
        return auth()->user()->can('contabilidad.procesosmensuales');
    }

    public function mes(int $d): void
    {
        $this->periodo = \Carbon\Carbon::createFromFormat('Y-m-d', $this->periodo.'-01')->addMonths($d)->format('Y-m');
        $this->fPeriodo = $this->periodo;
    }

    public function editar(?int $id = null): void
    {
        $this->reset('fEntidad', 'fCliente', 'fImporte', 'fMotivo', 'fAdjunto', 'aviso');
        $this->fPeriodo = $this->periodo;
        $this->editando = $id ?? 0;
        if ($id && ($p = DB::table('prefacturas')->find($id))) {
            $this->fEntidad = (string) ($p->entidad_id ?? '');
            $this->fCliente = (string) $p->cliente;
            $this->fImporte = number_format($p->importe, 2, ',', '');
            $this->fPeriodo = $p->periodo;
            $this->fMotivo = $p->motivo;
        }
    }

    public function cancelar(): void
    {
        $this->editando = null;
        $this->reset('fAdjunto', 'aviso');
    }

    public function guardar(): void
    {
        if (! $this->permitido()) {
            return;
        }
        $importe = trim(str_replace([' ', '€'], '', $this->fImporte));
        if (str_contains($importe, ',')) {
            $importe = str_replace(',', '.', str_replace('.', '', $importe)); // 1.234,56 → 1234.56
        }
        if (! is_numeric($importe)) {
            $this->aviso = '⚠️ El importe no es válido.';

            return;
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->fPeriodo)) {
            $this->aviso = '⚠️ Indica el periodo (mes) en que se factura.';

            return;
        }
        if (trim($this->fMotivo) === '') {
            $this->aviso = '⚠️ Falta el motivo.';

            return;
        }
        $this->validate(['fAdjunto' => 'nullable|file|max:20480']);
        $entidad = $this->fEntidad !== '' ? Entidad::withoutGlobalScopes()->find((int) $this->fEntidad) : null;
        $datos = [
            'entidad_id' => $entidad?->id,
            'cliente' => $entidad ? $entidad->entidad : (trim($this->fCliente) ?: null),
            'importe' => round((float) $importe, 2),
            'periodo' => $this->fPeriodo,
            'motivo' => trim($this->fMotivo),
            'updated_at' => now(),
        ];
        if ($this->fAdjunto) {
            $datos['adjunto_nombre'] = $this->fAdjunto->getClientOriginalName();
            $datos['adjunto_ruta'] = $this->fAdjunto->store('prefacturas');
        }
        if ($this->editando) {
            if ($this->fAdjunto && ($vieja = DB::table('prefacturas')->where('id', $this->editando)->value('adjunto_ruta'))) {
                Storage::delete($vieja);
            }
            DB::table('prefacturas')->where('id', $this->editando)->update($datos);
        } else {
            DB::table('prefacturas')->insert($datos + ['user_id' => auth()->id(), 'created_at' => now()]);
        }
        $this->periodo = $this->fPeriodo;
        $this->cancelar();
    }

    public function alternarEstado(int $id): void
    {
        if ($this->permitido()) {
            $e = DB::table('prefacturas')->where('id', $id)->value('estado');
            DB::table('prefacturas')->where('id', $id)->update(['estado' => $e === 'facturado' ? 'pendiente' : 'facturado', 'updated_at' => now()]);
        }
    }

    public function quitarAdjunto(int $id): void
    {
        if ($this->permitido() && ($ruta = DB::table('prefacturas')->where('id', $id)->value('adjunto_ruta'))) {
            Storage::delete($ruta);
            DB::table('prefacturas')->where('id', $id)->update(['adjunto_ruta' => null, 'adjunto_nombre' => null, 'updated_at' => now()]);
        }
    }

    public function borrar(int $id): void
    {
        if ($this->permitido()) {
            if ($ruta = DB::table('prefacturas')->where('id', $id)->value('adjunto_ruta')) {
                Storage::delete($ruta);
            }
            DB::table('prefacturas')->where('id', $id)->delete();
        }
    }

    public function descargar(int $id)
    {
        $p = DB::table('prefacturas')->find($id);
        if ($this->permitido() && $p && $p->adjunto_ruta && Storage::exists($p->adjunto_ruta)) {
            return Storage::download($p->adjunto_ruta, $p->adjunto_nombre);
        }
    }

    public function render()
    {
        $filas = DB::table('prefacturas')->where('periodo', $this->periodo)
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->orderBy('cliente')->orderBy('id')->get();
        $todas = DB::table('prefacturas')->where('periodo', $this->periodo)->get();
        $empresas = Entidad::withoutGlobalScopes()->where('cliente', 1)->where('estado', 1)
            ->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])->orderBy('entidad')->get(['id', 'entidad']);

        return view('livewire.contabilidad.prefacturacion', [
            'filas' => $filas,
            'empresas' => $empresas,
            'totalPendiente' => $todas->where('estado', 'pendiente')->sum('importe'),
            'totalFacturado' => $todas->where('estado', 'facturado')->sum('importe'),
            'puede' => $this->permitido(),
        ]);
    }
}
