<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * «Preparar pagos a cuenta» de la pestaña IS (9-oct-2026): del IS del ejercicio anterior (PDF presentado) saca la cuota y genera el
 * fichero .202 (modelo 202, modalidad 40.2) que se importa en el formulario de la AEAT. Motor: Contabilidad/Impuestos/M202/motor
 * (prepara202.py); guía: M202/PLAN.md. No presenta nada.
 *
 * Clientes: los que tienen el 202 en la pestaña Impuestos (entidad_impuestos, modelo 202) y NIF. Datos en la misma estructura que el IS:
 *   <IS_DIR>/clientes/<NIF>/<ejercicio-1>/fuentes/m200_presentado.pdf   (IS anterior)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/fuentes/m202_anterior.pdf       (opcional: un 202 de este año, para el CNAE)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/ajustes202.json                 (cnae…)
 *   <IS_DIR>/clientes/<NIF>/<ejercicio>/salida202/<NIF>_<ejercicio>_<periodo>.202 y .json
 */
class IsPagos extends Component
{
    use WithFileUploads;

    public bool $abierto = false;
    public int $ejercicio;
    public string $periodo = '2P';
    public bool $verTodos = false;

    /** Marcados para preparar: [entidad_id => true]. */
    public array $marcados = [];
    /** CNAE a mano por entidad. */
    public array $cnae = [];
    /** Subidas: ['is' => [id => archivo], 'p202' => [id => archivo]]. */
    public $subidaIs = [];
    public $subida202 = [];

    public string $salida = '';

    public function mount(): void
    {
        $this->ejercicio = (int) date('Y');
        $m = (int) date('n');
        $this->periodo = $m <= 6 ? '1P' : ($m >= 11 ? '3P' : '2P');
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.is_dir'), '/');
    }

    protected function nif(string $nif): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nif));
    }

    protected function dirCliente(string $nif): string
    {
        return $this->baseDir().'/clientes/'.$this->nif($nif);
    }

    protected function periodoEstado(): string
    {
        return 'P'.(int) substr($this->periodo, 0, 1);
    }

    protected function autorizado(): bool
    {
        if (config('contabilidad.is_ejecucion')) {
            return true;
        }
        $this->dispatch('proceso-terminado', mensaje: "⚠️ IS · pagos a cuenta\nOpción no válida. Solo ejecutable desde un terminal autorizado.");

        return false;
    }

    /** Clientes con el 202 en Impuestos y NIF; por defecto solo los que tienen ese pago pendiente (no «no», «no se presenta» ni presentado). */
    protected function clientes(): array
    {
        $est = $this->periodoEstado();
        $filas = DB::table('entidad_impuestos as ei')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->leftJoin('impuesto_estados as s', function ($j) use ($est) {
                $j->on('s.entidad_impuesto_id', '=', 'ei.id')->where('s.ejercicio', $this->ejercicio)->where('s.periodo', $est);
            })
            ->where('m.codigo', '202')->whereNull('e.deleted_at')
            ->whereNotNull('e.nif')->where('e.nif', '!=', '')
            ->orderBy('e.entidad')
            ->get(['e.id', 'e.entidad', 'e.nif', 's.estado']);
        $out = [];
        foreach ($filas as $f) {
            $estado = $f->estado ?: 'sin';
            if (! $this->verTodos && in_array($estado, ['no', 'nopresenta', 'presentado', 'visto'], true)) {
                continue;
            }
            $dir = $this->dirCliente($f->nif);
            $nif = $this->nif($f->nif);
            $res = $dir.'/'.$this->ejercicio.'/salida202/'.$nif.'_'.$this->ejercicio.'_'.$this->periodo.'.json';
            $r = is_file($res) ? json_decode((string) file_get_contents($res), true) : null;
            $isPdf = $dir.'/'.($this->ejercicio - 1).'/fuentes/m200_presentado.pdf';
            $out[] = [
                'id' => $f->id, 'entidad' => $f->entidad, 'nif' => $nif, 'estado' => $estado,
                'tieneIs' => is_file($isPdf), 'fechaIs' => is_file($isPdf) ? date('d/m/Y', filemtime($isPdf)) : null,
                'tiene202' => is_file($dir.'/'.$this->ejercicio.'/fuentes/m202_anterior.pdf'),
                'resultado' => is_array($r) ? $r : null,
                'fichero' => is_array($r) && ! empty($r['fichero']) && is_file(dirname($res).'/'.$r['fichero']) ? dirname($res).'/'.$r['fichero'] : null,
            ];
        }

        return $out;
    }

    public function updatedSubidaIs($valor, $id): void
    {
        $this->guardarSubida('is', (int) $id);
    }

    public function updatedSubida202($valor, $id): void
    {
        $this->guardarSubida('p202', (int) $id);
    }

    protected function guardarSubida(string $tipo, int $id): void
    {
        $prop = $tipo === 'is' ? 'subidaIs' : 'subida202';
        $f = $this->{$prop}[$id] ?? null;
        unset($this->{$prop}[$id]);
        $this->resetErrorBag($prop.'.'.$id);
        if (! $f instanceof UploadedFile || ! $this->autorizado()) {
            return;
        }
        $nif = (string) DB::table('entidades')->where('id', $id)->value('nif');
        if ($this->nif($nif) === '') {
            $this->addError($prop.'.'.$id, 'La entidad no tiene NIF.');
            return;
        }
        if (strtolower($f->getClientOriginalExtension()) !== 'pdf') {
            $this->addError($prop.'.'.$id, 'Tiene que ser un PDF.');
            return;
        }
        $ej = $tipo === 'is' ? $this->ejercicio - 1 : $this->ejercicio;
        $clave = $tipo === 'is' ? 'm200_presentado' : 'm202_anterior';
        $dir = $this->dirCliente($nif).'/'.$ej.'/fuentes';
        if (! is_dir($dir.'/anteriores') && ! @mkdir($dir.'/anteriores', 0777, true)) {
            $this->addError($prop.'.'.$id, 'No se ha podido crear la carpeta del cliente.');
            return;
        }
        foreach (glob($dir.'/'.$clave.'.*') ?: [] as $viejo) {
            @rename($viejo, $dir.'/anteriores/'.date('Ymd-His').' '.basename($viejo));
        }
        if (! @copy($f->getRealPath(), $dir.'/'.$clave.'.pdf')) {
            $this->addError($prop.'.'.$id, 'No se ha podido guardar el fichero.');
            return;
        }
        file_put_contents($dir.'/'.$clave.'.origen.txt', $f->getClientOriginalName()."\n".date('d/m/Y H:i')."\n");
        $this->dispatch('proceso-terminado', mensaje: '✅ '.($tipo === 'is' ? "IS {$ej}" : "202 {$ej}").': '.$f->getClientOriginalName());
    }

    public function marcarTodos(): void
    {
        $this->marcados = [];
        foreach ($this->clientes() as $c) {
            if ($c['tieneIs']) {
                $this->marcados[$c['id']] = true;
            }
        }
    }

    public function desmarcarTodos(): void
    {
        $this->marcados = [];
    }

    protected function pythonBin(): string
    {
        return config('contabilidad.is_python') ?: 'python3';
    }

    public function preparar(): void
    {
        if (! $this->autorizado()) {
            return;
        }
        $ids = array_keys(array_filter($this->marcados));
        if (! $ids) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ Marca al menos un cliente.');
            return;
        }
        $motor = rtrim((string) config('contabilidad.is_motor202'), '/');
        $this->salida = '';
        $hechos = 0;
        foreach ($this->clientes() as $c) {
            if (! in_array($c['id'], $ids, true)) {
                continue;
            }
            $ajustes = $this->dirCliente($c['nif']).'/'.$this->ejercicio.'/ajustes202.json';
            $cnae = trim((string) ($this->cnae[$c['id']] ?? ''));
            if ($cnae !== '') {
                if (! preg_match('/^\d{4}$/', $cnae)) {
                    $this->salida .= "⚠️ {$c['entidad']}: el CNAE son 4 dígitos.\n";
                    continue;
                }
                @mkdir(dirname($ajustes), 0777, true);
                $aj = is_file($ajustes) ? (json_decode((string) file_get_contents($ajustes), true) ?: []) : [];
                $aj['cnae'] = $cnae;
                file_put_contents($ajustes, json_encode($aj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            try {
                $r = Process::path($motor)->timeout(180)->env(['IS_MOTOR' => $this->baseDir().'/motor'])
                    ->run([$this->pythonBin(), 'prepara202.py', $this->dirCliente($c['nif']), (string) $this->ejercicio, $this->periodo]);
                $ok = $r->successful();
                if (! $ok && ! is_file($this->dirCliente($c['nif']).'/'.$this->ejercicio.'/salida202/'.$c['nif'].'_'.$this->ejercicio.'_'.$this->periodo.'.json')) {
                    $this->salida .= "⚠️ {$c['entidad']}: ".trim($r->errorOutput() ?: $r->output())."\n";
                } else {
                    $hechos += $ok ? 1 : 0;
                }
            } catch (\Throwable $e) {
                $this->salida .= "⚠️ {$c['entidad']}: ".$e->getMessage()."\n";
                report($e);
            }
        }
        $this->dispatch('proceso-terminado', mensaje: "✅ Pagos a cuenta {$this->periodo} {$this->ejercicio}\n{$hechos} fichero(s) .202 generado(s). Revisa los avisos y errores de la tabla.");
    }

    public function descargar(int $id)
    {
        $c = collect($this->clientes())->firstWhere('id', $id);
        if (! $c || ! $c['fichero']) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay fichero .202 de ese cliente: pulsa «Preparar».');
            return null;
        }

        return response()->download($c['fichero'], basename($c['fichero']));
    }

    public function descargarZip()
    {
        $ficheros = array_filter(array_column($this->clientes(), 'fichero'));
        if (! $ficheros || ! class_exists(\ZipArchive::class)) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay ficheros .202 que descargar.');
            return null;
        }
        $zip = tempnam(sys_get_temp_dir(), 'p202');
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::OVERWRITE);
        foreach ($ficheros as $f) {
            $z->addFile($f, basename($f));
        }
        $z->close();

        return response()->download($zip, "202_{$this->ejercicio}_{$this->periodo}.zip")->deleteFileAfterSend(true);
    }

    public function render()
    {
        return view('livewire.contabilidad.is-pagos', ['clientes' => $this->abierto ? $this->clientes() : []]);
    }
}
