<?php

namespace App\Http\Livewire;

use App\Models\ImpuestoDocumento;
use App\Support\Impuestos as Imp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Impuestos → M216 (20-oct-2026): IRPF no residentes, declaración NEGATIVA (rentas no sometidas a retención) de las empresas que presentan el 216
 * sin retenciones (Durcal). Los datos salen de la tabla «M216» del libro de IVA 303 del periodo (documento tipo libro_iva del 303, el que genera
 * Libros IVA): casilla 17 = nº de proveedores, 18/19 = suma de «Total factura» (sin coeficiente: así se presentó el 1T y el 3T de 2026), 21 = 0.
 * Solo propone: se presenta a mano en la Sede. Cuando haya retenciones habrá que añadir ese camino (pendiente, lo explicará Alex).
 * Motor: resources/motores/m216/lee216.py (M216_DIR solo para otra ruta). Guía: Contabilidad/Impuestos/M303/APRENDIZAJE.md (§M216).
 */
class ImpuestoM216 extends Component
{
    public ?int $entidadId = null;
    public int $ejercicio;
    public string $periodo = '';
    public bool $verTodos = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $g = (array) session('m216', []);
        $this->verTodos = Imp::esGestor() && (bool) ($g['verTodos'] ?? false);
        $this->ejercicio = (int) ($g['ejercicio'] ?? now()->format('Y'));
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? 0]) ? (int) $g['entidad'] : ((int) (array_key_first($emp) ?? 0) ?: null);
        $this->periodo = (string) ($g['periodo'] ?? '');
        $this->ajustarPeriodo();
    }

    public function updated($campo): void
    {
        if ($campo === 'verTodos' && ! isset($this->empresas()[$this->entidadId])) {
            $this->entidadId = (int) (array_key_first($this->empresas()) ?? 0) ?: null;
        }
        $this->ajustarPeriodo();
        session(['m216' => ['entidad' => (string) $this->entidadId, 'ejercicio' => (string) $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
    }

    public function cambiarEjercicio(int $d): void
    {
        $this->ejercicio += $d;
        $this->updated('ejercicio');
    }

    /** Empresas con el 216 activo en Impuestos (las del usuario; Admin/Suma «todas»). */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', '216')->where('e.estado', 1)->whereNull('ei.baja_ejercicio');
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->orderBy('e.entidad')->get(['e.id', 'e.entidad', 'e.alias', 'e.nif', 'ei.periodicidad'])
            ->mapWithKeys(fn ($r) => [$r->id => ($r->alias ?: $r->entidad).' · '.($r->periodicidad === 'M' ? 'mensual' : 'trimestral').($r->nif ? ' · '.$r->nif : '')])->all();
    }

    protected function periodicidad(): string
    {
        return (string) (DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '216')->value('ei.periodicidad') ?: 'T');
    }

    public function periodos(): array
    {
        return $this->periodicidad() === 'M'
            ? collect(range(1, 12))->mapWithKeys(fn ($m) => [sprintf('%02d', $m) => Imp::MESES[$m - 1]])->all()
            : ['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'];
    }

    protected function ajustarPeriodo(): void
    {
        if (! $this->entidadId || isset($this->periodos()[$this->periodo])) {
            return;
        }
        $mes = (int) now()->format('n') - 1 ?: 12;
        $this->periodo = $this->periodicidad() === 'M' ? sprintf('%02d', $mes) : 'T'.(int) ceil($mes / 3);
    }

    protected function libro(): ?ImpuestoDocumento
    {
        return ImpuestoDocumento::where('entidad_id', (int) $this->entidadId)->where('modelo', '303')->where('tipo', 'libro_iva')
            ->where('ejercicio', $this->ejercicio)->where('periodo', $this->periodo)->whereNull('quitado_at')->orderByDesc('id')->first();
    }

    /** Declaración ya presentada de ese periodo (PDF de la AEAT subido a Impuestos), si la hay. */
    protected function presentado(): ?ImpuestoDocumento
    {
        return ImpuestoDocumento::where('entidad_id', (int) $this->entidadId)->where('modelo', '216')->where('tipo', 'presentado')
            ->where('ejercicio', $this->ejercicio)->where('periodo', $this->periodo)->whereNull('quitado_at')->orderByDesc('id')->first();
    }

    /** Lo que dice el PDF presentado: [nº de rentas, base] o null. */
    protected function leerPresentado(ImpuestoDocumento $d): ?array
    {
        $ruta = storage_path('app/'.$d->almacen);
        if (! is_file($ruta)) {
            return null;
        }
        $py = storage_path('app/venv-facturasocr/bin/python');
        $r = Process::timeout(30)->run([is_executable($py) ? $py : 'python3', '-I', $this->motor(), '--pdf', $ruta]);
        $d = json_decode(trim($r->output()), true);

        return is_array($d) && isset($d['n'], $d['base']) ? $d : null;
    }

    protected function motor(): string
    {
        return rtrim((string) (env('M216_DIR') ?: resource_path('motores/m216')), '/').'/lee216.py';
    }

    public function render()
    {
        $res = $error = $presentado = null;
        $libro = null;
        if ($this->entidadId) {
            $libro = $this->libro();
            if (! $libro) {
                $error = 'No hay libro de IVA del '.$this->periodo.' '.$this->ejercicio.' de esta empresa: genéralo antes en IVA M303 → Libros IVA.';
            } else {
                $r = Process::timeout(60)->run(['python3', '-I', $this->motor(), storage_path('app/'.$libro->almacen)]);
                $res = json_decode(trim($r->output()), true);
                if (! is_array($res) || isset($res['error'])) {
                    $error = is_array($res) ? $res['error'] : 'No se pudo leer el libro de IVA.';
                    $res = null;
                }
            }
            if ($p = $this->presentado()) {
                $presentado = ['nombre' => $p->nombre, 'id' => $p->id, 'datos' => $this->leerPresentado($p)];
            }
        }

        return view('livewire.impuesto-m216', ['empresas' => $this->empresas(), 'periodosLista' => $this->entidadId ? $this->periodos() : [],
            'res' => $res, 'error' => $error, 'libro' => $libro, 'presentado' => $presentado]);
    }
}
