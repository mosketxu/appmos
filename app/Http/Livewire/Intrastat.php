<?php

namespace App\Http\Livewire;

use App\Support\IntrastatMotor;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Intrastat (10-oct-2026): pestaña de Impuestos. Se elige el CLIENTE (cada uno manda sus ficheros con un formato propio,
 * `Contabilidad/Impuestos/Intrastat/clientes.json` + lector en intrastat.py), el mes y se suben sus ficheros; el motor deja
 * el CSV que se sube a la Sede de la AEAT y un resumen con avisos. Todo en el servidor, sin PC trabajador.
 * Motor: INTRASTAT_DIR (/var/www/intrastat en el VPS; en un PC, la carpeta del repo). Datos: storage/app/intrastat/<cliente>/<AAAA-MM>/{entrada,salida}.
 */
class Intrastat extends Component
{
    use WithFileUploads;

    public array $subidas = [];
    public string $cliente = '';
    public string $periodo = '';
    public array $clientes = [];
    public ?array $resultado = null;
    public string $error = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $this->clientes = $this->leerClientes();
        $this->cliente = (string) session('intrastat.cliente', array_key_first($this->clientes) ?? '');
        if (! isset($this->clientes[$this->cliente])) {
            $this->cliente = (string) (array_key_first($this->clientes) ?? '');
        }
        // por defecto, el mes anterior (se declara hasta el día 12 del siguiente)
        $this->periodo = (string) session('intrastat.periodo', date('Y-m', strtotime('first day of last month')));
        $this->cargar();
    }

    protected function leerClientes(): array
    {
        try {
            $r = IntrastatMotor::ejecutar(['clientes'], 30);
            return $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    protected function validos(): bool
    {
        return isset($this->clientes[$this->cliente]) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->periodo);
    }

    protected function carpeta(string $sub): string
    {
        abort_unless($this->validos(), 422, 'Cliente o mes no válidos.');
        return IntrastatMotor::carpeta($this->cliente, $this->periodo, $sub);
    }

    public function updatedCliente(): void
    {
        session(['intrastat.cliente' => $this->cliente]);
        $this->cargar();
    }

    public function updatedPeriodo(): void
    {
        session(['intrastat.periodo' => $this->periodo]);
        $this->cargar();
    }

    public function cargar(): void
    {
        $this->resultado = null;
        if (! $this->validos()) {
            return;
        }
        $this->resultado = IntrastatMotor::resumen($this->cliente, $this->periodo);
    }

    public function entradas(): array
    {
        return $this->validos() ? array_map('basename', glob($this->carpeta('entrada').'/*') ?: []) : [];
    }

    public function procesarSubidas(): void
    {
        $this->error = '';
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        foreach ($ficheros as $f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xlsm'], true)) {
                $this->error = "«{$f->getClientOriginalName()}» no es un Excel (.xlsx).";
                continue;
            }
            copy($f->getRealPath(), $this->carpeta('entrada').'/'.IntrastatMotor::nombreEntrada($f->getClientOriginalName()));
        }
    }

    public function borrarEntradas(): void
    {
        foreach (glob($this->carpeta('entrada').'/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function generar(): void
    {
        $this->error = '';
        if (! $this->validos()) {
            $this->error = 'Elige cliente y mes.';
            return;
        }
        if ($this->error = IntrastatMotor::generar($this->cliente, $this->periodo)) {
            return;
        }
        $this->cargar();
        $n = count($this->resultado['avisos'] ?? []);
        $this->dispatch('proceso-terminado', mensaje: '✅ Intrastat '.$this->periodo.' generado: '.($this->resultado['partidas'] ?? 0).' partidas'.($n ? " y {$n} avisos para revisar." : '.'));
    }

    public function render()
    {
        return view('livewire.intrastat');
    }
}
