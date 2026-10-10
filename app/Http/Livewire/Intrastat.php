<?php

namespace App\Http\Livewire;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
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

    protected function motorDir(): string
    {
        return rtrim(config('contabilidad.intrastat_dir'), '/');
    }

    protected function datosDir(): string
    {
        return rtrim(config('contabilidad.intrastat_datos') ?: storage_path('app/intrastat'), '/');
    }

    protected function python(): string
    {
        foreach ([config('contabilidad.leoybra_python'), storage_path('app/venv-facturasocr/bin/python')] as $p) {
            if ($p && is_file($p)) {
                return $p;
            }
        }
        return 'python3';
    }

    protected function motor(array $args, int $timeout = 300)
    {
        return Process::path($this->motorDir())->timeout($timeout)->env(['PYTHONIOENCODING' => 'utf-8'])
            ->run(array_merge([$this->python(), '-I', $this->motorDir().'/intrastat.py'], $args));
    }

    protected function leerClientes(): array
    {
        try {
            $r = $this->motor(['clientes'], 30);
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
        $d = $this->datosDir().'/'.$this->cliente.'/'.$this->periodo.'/'.$sub;
        @mkdir($d, 0775, true);
        return $d;
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
        $f = $this->datosDir().'/'.$this->cliente.'/'.$this->periodo.'/salida/resumen.json';
        if (is_file($f)) {
            $this->resultado = json_decode((string) file_get_contents($f), true) ?: null;
            if ($this->resultado) {
                $this->resultado['generado'] = date('d/m/Y H:i', filemtime($f));
            }
        }
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
            copy($f->getRealPath(), $this->carpeta('entrada').'/'.preg_replace('/[^A-Za-z0-9._ &()-]+/', '_', $f->getClientOriginalName()));
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
        $entradas = glob($this->carpeta('entrada').'/*') ?: [];
        if (! $entradas) {
            $this->error = 'Sube primero los ficheros del cliente para este mes.';
            return;
        }
        $salida = $this->carpeta('salida');
        foreach (glob($salida.'/*') ?: [] as $viejo) {
            @unlink($viejo);
        }
        try {
            $r = $this->motor(array_merge(['generar', $this->cliente, $this->periodo], $entradas, ['--salida', $salida]));
        } catch (\Throwable $e) {
            $this->error = 'No se pudo generar: '.$e->getMessage();
            return;
        }
        if (! $r->successful()) {
            $this->error = 'No se pudo generar: '.trim($r->errorOutput() ?: $r->output());
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
