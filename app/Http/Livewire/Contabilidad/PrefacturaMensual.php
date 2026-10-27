<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Prefacturación mensual (27-oct-2026, Proc.Mensuales; clave prefacturacion_mensual; no confundir con «Notas de cargo», clave prefacturacion).
 * Del Excel «CabeceraAlbaranCliente_*.xlsx» que Alex exporta de SAGE (se sube aquí) un PC trabajador deja en OneDrive/_Suma/Facturas/2026 el
 * «Prevision facturacion 2026 MM.xlsx» con el formato del mes anterior (tarea pc.prefacturacion, accion generar) y, en un segundo paso con
 * confirmación en el servidor, lo envía a Susana y Marta con la firma de Alex (accion enviar). Script: Contabilidad/ProcesosMensuales/Prefacturacion.
 */
class PrefacturaMensual extends Component
{
    use WithFileUploads;

    public const CLAVE = 'prefacturacion_mensual';

    public int $mes;
    public $excel = null;
    public string $aviso = '';
    public bool $confirmarEnvio = false;

    public function mount(): void
    {
        $this->mes = (int) now()->month;
    }

    protected function puede(): bool
    {
        $u = auth()->user();

        return $u && $u->can('contabilidad.procesosmensuales') && $u->can('proceso.pm.'.self::CLAVE);
    }

    protected function colaLista(): bool
    {
        return Schema::hasTable('tareas') && Schema::hasTable('trabajadores');
    }

    public function updatedMes(): void
    {
        $this->mes = max(1, min(12, (int) $this->mes));
        $this->confirmarEnvio = false;
        $this->aviso = '';
    }

    /** Tareas de este proceso del mes elegido, de la más nueva a la más vieja, con sus parámetros y resultado decodificados. */
    protected function tareas(): array
    {
        if (! $this->colaLista()) {
            return [];
        }

        return DB::table('tareas')->where('proceso', 'pc.prefacturacion')->orderByDesc('id')->limit(40)
            ->get(['id', 'estado', 'parametros', 'resultado', 'terminada_at', 'created_at'])
            ->map(function ($t) {
                $t->p = json_decode((string) $t->parametros, true) ?: [];
                $t->r = $t->resultado ? (json_decode($t->resultado, true) ?: []) : [];

                return $t;
            })->filter(fn ($t) => (int) ($t->p['mes'] ?? 0) === $this->mes)->values()->all();
    }

    protected function hayPc(): bool
    {
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if ($vivos === 0) {
            $this->aviso = '⚠️ No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
        }

        return $vivos > 0;
    }

    protected function enCurso(array $tareas): bool
    {
        return collect($tareas)->contains(fn ($t) => in_array($t->estado, ['pendiente', 'en_curso', 'preparando'], true));
    }

    public function generar(): void
    {
        $this->confirmarEnvio = false;
        if (! $this->puede() || ! $this->colaLista()) {
            return;
        }
        if (! $this->excel) {
            $this->aviso = 'Sube primero el Excel de SAGE (CabeceraAlbaranCliente…).';

            return;
        }
        $this->validate(['excel' => 'file|max:20480|mimes:xlsx', 'mes' => 'integer|between:1,12']);
        if ($this->enCurso($this->tareas()) || ! $this->hayPc()) {
            $this->aviso = $this->aviso ?: 'Ya hay una tarea de este mes en marcha.';

            return;
        }
        $id = ColaTareas::crear('pc.prefacturacion', [
            'accion' => 'generar', 'mes' => $this->mes,
            'entrada' => ['archivo' => 'export.xlsx', 'nombre' => basename(str_replace('\\', '/', $this->excel->getClientOriginalName()))],
        ], ColaTareas::pcElegido() ?: null, auth()->id(), null, null, 'preparando');
        $dir = ColaTareas::carpetaEntradas($id);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($this->excel->getRealPath(), $dir.'/export.xlsx');
        ColaTareas::liberar($id);
        $this->excel = null;
        $this->aviso = 'Pedido a un PC: monta el Excel con el formato del mes anterior…';
    }

    /** Paso 1 del envío: solo enseña la confirmación (el envío real es confirmarEnviar). */
    public function pedirEnvio(): void
    {
        if ($this->puede() && $this->generadoOk($this->tareas())) {
            $this->confirmarEnvio = true;
        }
    }

    public function cancelarEnvio(): void
    {
        $this->confirmarEnvio = false;
    }

    /** Paso 2: crea la tarea que envía. Solo si el último «generar» del mes terminó bien. */
    public function confirmarEnviar(): void
    {
        $tareas = $this->tareas();
        $this->confirmarEnvio = false;
        if (! $this->puede() || ! $this->generadoOk($tareas) || $this->enCurso($tareas) || ! $this->hayPc()) {
            return;
        }
        ColaTareas::crear('pc.prefacturacion', ['accion' => 'enviar', 'mes' => $this->mes], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->aviso = 'Pedido a un PC: envía el correo a Susana y Marta…';
    }

    /** Última tarea «generar» del mes si acabó bien y es más nueva que cualquier otra (si no, null). */
    protected function generadoOk(array $tareas): ?object
    {
        $g = collect($tareas)->first(fn ($t) => ($t->p['accion'] ?? '') === 'generar');

        return $g && $g->estado === 'ok' ? $g : null;
    }

    public function render()
    {
        $tareas = $this->puede() ? $this->tareas() : [];
        $generado = $this->generadoOk($tareas);
        $ultimaGenerar = collect($tareas)->first(fn ($t) => ($t->p['accion'] ?? '') === 'generar');
        $envio = collect($tareas)->first(fn ($t) => ($t->p['accion'] ?? '') === 'enviar');
        $yaEnviado = collect($tareas)->first(fn ($t) => ($t->p['accion'] ?? '') === 'enviar' && $t->estado === 'ok');
        if ($yaEnviado && $generado && $yaEnviado->id > $generado->id) {
            $this->marcarChecklist();
        }
        $enlace = $generado && ! empty($generado->r['ficheros'][0])
            ? URL::temporarySignedRoute('contabilidad.tarea-fichero', now()->addHours(2), ['id' => $generado->id, 'nombre' => $generado->r['ficheros'][0]])
            : null;

        return view('livewire.contabilidad.prefactura-mensual', [
            'meses' => ProcesosMensuales::MESES['ES'],
            'puede' => $this->puede(),
            'generado' => $generado, 'ultimaGenerar' => $ultimaGenerar, 'envio' => $envio, 'yaEnviado' => $yaEnviado,
            'enCurso' => $this->enCurso($tareas), 'enlace' => $enlace,
        ]);
    }

    /** Enviado el correo del mes: se marca el check del mes en el seguimiento de Proc.Mensuales (si no estaba ya). */
    protected function marcarChecklist(): void
    {
        $p = DB::table('seguimiento_procesos')->where('clave', self::CLAVE)->first();
        if (! $p) {
            return;
        }
        $periodo = sprintf('2026-%02d', $this->mes);
        $where = ['proceso_id' => $p->id, 'entidad_id' => 0, 'periodo' => $periodo, 'propietario_id' => 0];
        if (! DB::table('seguimiento_marcas')->where($where)->exists()) {
            DB::table('seguimiento_marcas')->insert($where + ['estado' => 'ok', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
