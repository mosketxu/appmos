<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * LeoyBra (4-oct-2026): Grupo Leoybra, S.L. Con los ficheros base (mayor, plan, proveedores, clientes) y el fichero del
 * trimestre (ventas, compras, banco y tarjeta) genera solo el PluginFacturas y el PluginBancos de SAGE, el resumen de IVA
 * para validarlo y un informe de avisos. El motor es Contabilidad/LeoyBra/leoybra.py (ver PLAN.md); la pantalla solo
 * sube ficheros, lo ejecuta y deja descargar el resultado. Todo en el servidor (no depende de ningún PC trabajador):
 * LEOYBRA_DIR=/var/www/leoybra con leoybra.py, cliente.json, reglas_bancos.json y las carpetas Base/, Datos/, Output/.
 */
class LeoyBra extends Component
{
    use WithFileUploads;

    /** Ficheros recién subidos; el navegador llama a procesarSubidas(fila) al terminar. */
    public array $subidas = [];

    public string $periodo = '';
    public string $numeroInicial = '';

    public array $estado = [];
    public ?array $resultado = null;
    public string $error = '';

    private const BASE = ['mayor' => 'mayor', 'plan' => 'plan', 'proveedores' => 'proveedores', 'clientes' => 'clientes'];

    public function mount(): void
    {
        // El trimestre que se estaba viendo (sesión); si no, el último con resultado; si no, el último terminado
        $this->periodo = (string) session('leoybra.periodo', '');
        $this->cargar();
        if (! preg_match('/^\d{4}-[1-4]T$/', $this->periodo)) {
            $con = collect($this->estado['periodos'] ?? [])->filter(fn ($v) => isset($v['resumen']))->keys()->sort()->last();
            $this->periodo = $con ?: $this->periodoPorDefecto();
            $this->cargar();
        }
    }

    /** El trimestre natural ya terminado (en octubre, el 3T). */
    protected function periodoPorDefecto(): string
    {
        $m = (int) date('n');
        $t = intdiv($m - 1, 3);   // trimestre anterior (0..3)
        return $t === 0 ? (date('Y') - 1).'-4T' : date('Y').'-'.$t.'T';
    }

    protected function dir(): string
    {
        return rtrim(config('contabilidad.leoybra_dir'), '/');
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
        return Process::path($this->dir())->timeout($timeout)->env(['LEOYBRA_DATOS' => $this->dir(), 'PYTHONIOENCODING' => 'utf-8'])
            ->run(array_merge([$this->python(), $this->dir().'/leoybra.py'], $args));
    }

    public function cargar(): void
    {
        try {
            $r = $this->motor(['estado'], 60);
            $this->estado = $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
            if (! $r->successful()) {
                $this->error = 'No puedo leer el estado: '.trim($r->errorOutput() ?: $r->output());
            }
        } catch (\Throwable $e) {
            $this->estado = [];
            $this->error = 'No puedo leer el estado: '.$e->getMessage();
        }
        $res = $this->estado['periodos'][$this->periodo]['resumen'] ?? null;
        $this->resultado = $res;
    }

    public function updatedPeriodo(): void
    {
        $this->periodo = strtoupper(trim($this->periodo));
        session(['leoybra.periodo' => $this->periodo]);
        $this->cargar();
    }

    /** Sube un fichero base (mayor, plan, proveedores o clientes): el último manda; los mayores se acumulan. */
    public function procesarSubidas(string $fila): void
    {
        $this->error = '';
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }
        if ($fila === 'datos') {
            $this->guardarDatos($ficheros);
            return;
        }
        if ($fila === 'pdfs') {
            $this->guardarPdfs($ficheros);
            return;
        }
        if (! isset(self::BASE[$fila])) {
            return;
        }
        foreach ($ficheros as $f) {
            $ext = strtolower($f->getClientOriginalExtension());
            if (! in_array($ext, ['xlsx', 'xls'], true)) {
                $this->error = "«{$f->getClientOriginalName()}» no es un Excel (.xlsx o .xls).";
                continue;
            }
            $carpeta = $this->dir().'/Base';
            @mkdir($carpeta, 0775, true);
            $nombre = self::BASE[$fila].'_'.date('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $f->getClientOriginalName());
            copy($f->getRealPath(), $carpeta.'/'.$nombre);
            if ($fila !== 'mayor') {   // de los demás solo vale el último: se quitan los anteriores
                foreach (glob($carpeta.'/'.self::BASE[$fila].'_*') ?: [] as $viejo) {
                    if (basename($viejo) !== $nombre) {
                        @mkdir($carpeta.'/OLD', 0775, true);
                        @rename($viejo, $carpeta.'/OLD/'.basename($viejo));
                    }
                }
            }
        }
        $this->cargar();
        $this->dispatch('proceso-terminado', mensaje: '✅ Fichero base guardado.');
    }

    protected function carpetaPeriodo(string $sub): string
    {
        abort_unless(preg_match('/^\d{4}-[1-4]T$/', $this->periodo), 422, 'Periodo no válido (usa por ejemplo 2026-3T).');
        $d = $this->dir().'/'.$sub.'/'.$this->periodo;
        @mkdir($d, 0775, true);
        return $d;
    }

    protected function guardarDatos(array $ficheros): void
    {
        $d = $this->carpetaPeriodo('Datos');
        foreach ($ficheros as $f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xlsm'], true)) {
                $this->error = "«{$f->getClientOriginalName()}»: el fichero de datos del trimestre tiene que ser un .xlsx.";
                continue;
            }
            copy($f->getRealPath(), $d.'/'.date('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $f->getClientOriginalName()));
        }
        $this->cargar();
    }

    protected function guardarPdfs(array $ficheros): void
    {
        $d = $this->carpetaPeriodo('Datos').'/PDF';
        @mkdir($d, 0775, true);
        $n = 0;
        foreach ($ficheros as $f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                continue;
            }
            copy($f->getRealPath(), $d.'/'.preg_replace('/[^A-Za-z0-9._ -]+/', '_', $f->getClientOriginalName()));
            $n++;
        }
        $this->cargar();
        $this->dispatch('proceso-terminado', mensaje: "✅ {$n} facturas (PDF/imagen) guardadas para contrastar.");
    }

    public function borrarPdfs(): void
    {
        foreach (glob($this->carpetaPeriodo('Datos').'/PDF/*') ?: [] as $f) {
            @unlink($f);
        }
        $this->cargar();
    }

    public function generar(): void
    {
        $this->error = '';
        $datos = glob($this->carpetaPeriodo('Datos').'/*.xlsx') ?: [];
        if (! $datos) {
            $this->error = 'Falta el fichero de datos del trimestre (ventas, compras, banco y tarjeta).';
            return;
        }
        usort($datos, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $args = ['generar', $this->periodo, '--datos', $datos[0]];
        if (is_dir($pdf = $this->dir().'/Datos/'.$this->periodo.'/PDF')) {
            array_push($args, '--pdfs', $pdf);
        }
        if (preg_match('/^\d+$/', trim($this->numeroInicial))) {
            array_push($args, '--numero-inicial', trim($this->numeroInicial));
        }
        try {
            $r = $this->motor($args);
        } catch (\Throwable $e) {
            $this->error = 'No se pudo generar: '.$e->getMessage();
            return;
        }
        if (! $r->successful()) {
            $this->error = 'No se pudo generar: '.trim($r->errorOutput() ?: $r->output());
            return;
        }
        session(['leoybra.periodo' => $this->periodo]);
        $this->cargar();
        $n = count($this->resultado['avisos'] ?? []);
        $this->dispatch('proceso-terminado', mensaje: "✅ LeoyBra {$this->periodo} generado".($n ? " con {$n} avisos para revisar." : '.'));
    }

    public function descargar(string $relativa)
    {
        $raiz = realpath($this->dir());
        $ruta = realpath($this->dir().'/'.$relativa);
        if (! $raiz || ! $ruta || ! str_starts_with($ruta, $raiz.'/') || ! is_file($ruta) || ! str_starts_with($relativa, 'Output/')) {
            $this->error = "No se encuentra {$relativa}.";
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    public function render()
    {
        return view('livewire.contabilidad.leoy-bra');
    }
}
