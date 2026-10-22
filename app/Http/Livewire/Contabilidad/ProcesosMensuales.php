<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\ProcesoEstado;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026; desde el 16-oct-2026 con el antiguo «Seguimiento mensual» dentro):
 * el checklist de TODOS los procesos mensuales, una fila por proceso (seguimiento_procesos) y una columna por mes del año.
 * - Proceso «general»: un check por mes (ok → «no toca» → vacío).
 * - Proceso «cliente»: la celda del mes resume n/N empresas; con ▸ se despliegan las empresas del usuario
 *   (las del panel de control, Accesos::entidadesPropias), cada una con su check por mes.
 * - ejecucion «local»: el proceso se hace desde un PC (los datos están allí); aquí solo se anota.
 * Clic en el nombre del proceso: modal con su detalle y, si el proceso tiene pantalla propia (PROCESOS: Revisión del mayor,
 * Certificados), esa pantalla dentro del modal.
 * Revisión del mayor pasó el 18-oct-2026 a Impuestos → Seguimiento (modelo MAYOR, casilla mensual; componente RevisionMayor en modo modal).
 * Pet. Documentación Impuestos pasó el 15-oct-2026 a Impuestos → Seguimiento (ImpuestoPeticion); de aquí siguen
 * textoPeriodo() y destinatarios(), que usa ese componente.
 * Ver Contabilidad/ProcesosMensuales/PLAN.md.
 */
class ProcesosMensuales extends Component
{
    /** [clave de seguimiento_procesos => [icono, componente Livewire, parámetros]]: procesos con pantalla propia, que se abre en el modal. */
    public const PROCESOS = [
        'certificados' => ['icono' => '🔐', 'componente' => 'contabilidad.certificados', 'parametros' => ['embebido' => true]],
        'limpieza' => ['icono' => '🧹', 'componente' => 'contabilidad.limpieza', 'parametros' => [], 'ancho' => '720px'],
        'prefacturacion' => ['icono' => '🧾', 'componente' => 'contabilidad.prefacturacion', 'parametros' => [], 'ancho' => '1500px'],
    ];

    /** Icono de los procesos que no tienen pantalla propia (los de PROCESOS ya llevan el suyo). */
    public const ICONOS = ['whatsapp' => '💬'];

    /** Orden en que pasa el ciclo de impuestos con cada clic (ids de la tabla ciclos; lo usa Entidades). */
    public const ORDEN_CICLOS = [1, 3, 12, 20, 0];

    public const MESES = [
        'ES' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'EN' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    /**
     * Texto de {periodo} según el ciclo de impuestos de la entidad (ciclos: 1 Mensual,
     * 3 Trimestral): «septiembre de 2026» / «3.er trimestre de 2026». Con otro ciclo o sin
     * definir se usa el mes y $definido queda a false (se avisa en pantalla).
     */
    public static function textoPeriodo(string $periodo, $cicloId, string $idioma, ?bool &$definido = null): string
    {
        [$anio, $mes] = array_map('intval', explode('-', $periodo) + [1 => 1]);
        $definido = in_array((int) $cicloId, [1, 3], true);
        if ((int) $cicloId === 3) {
            $t = intdiv($mes - 1, 3) + 1;
            return $idioma === 'EN' ? "Q{$t} {$anio}" : ['1.er', '2.º', '3.er', '4.º'][$t - 1]." trimestre de {$anio}";
        }
        $nombre = self::MESES[$idioma === 'EN' ? 'EN' : 'ES'][$mes - 1] ?? '';
        return $idioma === 'EN' ? "{$nombre} {$anio}" : "{$nombre} de {$anio}";
    }

    /** Destinatarios: los correos de emailadm de la entidad (varios separados por ; o ,). */
    public static function destinatarios(?string $emailadm): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', (string) $emailadm))));
    }

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

    /** Proceso abierto en el modal (id de seguimiento_procesos). */
    public ?int $modal = null;

    /** Proceso al que se está añadiendo un usuario (desplegable con buscador) y usuario elegido. */
    public ?int $asignando = null;
    public string $addUsuario = '';

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
        $dueno = $p->por_usuario ? (int) auth()->id() : 0;   // en los procesos por usuario cada uno tiene sus checks
        $q = DB::table('seguimiento_marcas')->where(['proceso_id' => $proceso, 'entidad_id' => $entidad, 'periodo' => $periodo, 'propietario_id' => $dueno]);
        $actual = $q->value('estado');
        if ($actual === 'na') {
            $q->delete();
        } elseif ($actual === null) {
            DB::table('seguimiento_marcas')->insert(['proceso_id' => $proceso, 'entidad_id' => $entidad, 'periodo' => $periodo, 'propietario_id' => $dueno,
                'estado' => 'ok', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $q->update(['estado' => 'na', 'user_id' => auth()->id(), 'updated_at' => now()]);
        }
    }

    /** Los usuarios de cada proceso son informativos (los permisos reales están en el panel de control): solo Alex los cambia, el resto los ve. */
    protected function puedeAsignarUsuarios(): bool
    {
        return \App\Support\TodoClaude::esGestor(auth()->user());
    }

    public function asignar(int $proceso): void
    {
        if (! $this->puedeAsignarUsuarios()) {
            return;
        }
        $this->asignando = $this->asignando === $proceso ? null : $proceso;
        $this->addUsuario = '';
    }

    public function updatedAddUsuario(string $valor): void
    {
        if ($this->puedeAsignarUsuarios() && $this->asignando && $valor !== '' && \App\Models\User::whereKey((int) $valor)->exists()) {
            DB::table('seguimiento_proceso_user')->insertOrIgnore(['proceso_id' => $this->asignando, 'user_id' => (int) $valor]);
        }
        $this->asignando = null;
        $this->addUsuario = '';
    }

    public function quitarUsuario(int $proceso, int $user): void
    {
        if ($this->puedeAsignarUsuarios()) {
            DB::table('seguimiento_proceso_user')->where('proceso_id', $proceso)->where('user_id', $user)->delete();
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

    /** Se ve el proceso si tiene permiso propio (proceso.pm.<clave>, panel de control) o no lo tiene definido. */
    protected function visible(object $p): bool
    {
        $conPermiso = array_keys(config('accesos.procesos', [])['contabilidad.procesosmensuales']['lista'] ?? []);   // la clave lleva un punto: config('a.b.c') no la encuentra;
        return ! in_array($p->clave, $conPermiso, true) || auth()->user()->can('proceso.pm.'.$p->clave);
    }

    public function abrir(int $id): void
    {
        $p = DB::table('seguimiento_procesos')->find($id);
        $this->modal = $p && $this->visible($p) ? $id : null;
    }

    public function cerrar(): void
    {
        $this->modal = null;
    }

    public function render()
    {
        $procesos = DB::table('seguimiento_procesos')->where('activo', true)->orderBy('orden')->orderBy('id')->get()
            ->filter(fn ($p) => $this->visible($p))->values();
        $meses = array_keys($this->meses);
        $empresas = $this->empresas();
        $hayCliente = $procesos->contains('ambito', 'cliente');

        // estado[proceso][entidad][periodo] = ok|proc|na
        $estado = [];
        foreach (DB::table('seguimiento_marcas')->whereIn('periodo', $meses)->whereIn('propietario_id', [0, (int) auth()->id()])->get() as $m) {
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

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => $procesos,
            'responsables' => DB::table('seguimiento_proceso_user')->join('users', 'users.id', '=', 'seguimiento_proceso_user.user_id')
                ->orderBy('users.name')->get(['proceso_id', 'user_id', 'users.name'])->groupBy('proceso_id'),
            'usuarios' => \App\Models\User::where('activo', true)->orderBy('name')->pluck('name', 'id'),
            'estado' => $estado,
            'aplican' => $aplican,
            'hayCliente' => $hayCliente,
            'mesActual' => now()->format('Y-m'),
            'puede' => $this->permitido(),
            'editaUsuarios' => $this->puedeAsignarUsuarios(),
            'filtro' => $this->buscar,
            'pantallas' => self::PROCESOS,
            'iconos' => self::ICONOS,
            'enModal' => $this->modal ? $procesos->firstWhere('id', $this->modal) : null,
        ]);
    }
}
