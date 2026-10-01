<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\MailEnviado;
use App\Models\Suma;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026): agrupa varios procesos que se hacen cada mes.
 * Los procesos van por empresa: cada usuario ve solo las empresas que gestiona
 * (las del panel de control: Responsable Suma + asignadas, Accesos::entidadesPropias).
 * También Admin y gestores: aunque en Entidades vean todas, aquí solo las suyas.
 * Ver Contabilidad/ProcesosMensuales/PLAN.md.
 *
 * Pet. Documentación Impuestos: por empresa, el check entidades.mail_peticion_check
 * (de inicio todas marcadas; solo salen las marcadas salvo «ver también las no marcadas»),
 * el idioma (entidades.idioma: cambiarlo aquí lo cambia en la entidad) y el texto
 * entidades.mail_peticion, que parte de la plantilla de su idioma (tabla plantillas_mail,
 * ES / EN) y se personaliza. {periodo} se cambia al enviar por el mes o el
 * trimestre según el ciclo de impuestos de la entidad (textoPeriodo).
 * El check y el idioma se guardan al momento.
 *
 * Aunque tenga el check, solo se le enviará si está marcada «enviar ahora» para el
 * periodo: es una fila pendiente (enviado_at null) de mails_enviados, que al enviar
 * se queda como registro de lo enviado.
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class ProcesosMensuales extends Component
{
    /** [clave => ['icono', 'titulo', 'descripcion']]: una pestaña por proceso. */
    public const PROCESOS = [
        'petdocimpuestos' => ['icono' => '📨', 'titulo' => 'Pet. Documentación Impuestos',
            'descripcion' => 'Petición mensual de la documentación para los impuestos.'],
    ];

    public const IDIOMAS = ['ES' => 'Español', 'EN' => 'Inglés'];

    public string $proceso = 'petdocimpuestos';

    public string $buscar = '';

    /** Por empresa (id => valor), lo que se está editando en pantalla. */
    public array $textos = [];
    public array $checks = [];
    public array $idiomas = [];
    public array $ccs = [];
    /** id => activa (entidades.estado: 1 activo, 0 baja). Solo salen las activas. */
    public array $activas = [];
    /** id => Responsable Suma (entidades.suma_id), editable aquí. */
    public array $sumaIds = [];

    /** Con permiso de editar entidades: enseñar también las que no tienen Responsable Suma, para asignarlas. */
    public bool $verSinResponsable = false;

    /** Plantillas del proceso (idioma => texto). */
    public array $plantillas = [];
    public bool $verPlantillas = false;

    /** Mes al que se refiere la petición (AAAA-MM); por defecto, el anterior. */
    public string $periodo = '';

    /** id => enviar ahora (fila pendiente en mails_enviados para el periodo). */
    public array $ahora = [];

    /** Empresa cuyo correo se ve a la derecha (ninguna = no se ve texto). */
    public ?int $seleccionada = null;

    /** Por defecto solo las marcadas. */
    public bool $verNoMarcadas = false;

    /** Por defecto solo las activas; con esto salen también las de baja (para reactivarlas). */
    public bool $verBajas = false;

    public function mount(): void
    {
        foreach ($this->empresas() as $e) {
            $this->textos[$e->id] = (string) $e->mail_peticion;
            $this->ccs[$e->id] = (string) $e->mail_peticion_cc;
            $this->activas[$e->id] = (int) $e->estado === 1;
            $this->sumaIds[$e->id] = $e->suma_id ? (string) $e->suma_id : '';
            $this->checks[$e->id] = (bool) $e->mail_peticion_check;
            $this->idiomas[$e->id] = $e->idioma === 'EN' ? 'EN' : 'ES';
        }
        $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
        $this->cargarPlantillas();
        $this->cargarAhora();
    }

    protected function cargarAhora(): void
    {
        $pendientes = MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
            ->whereNull('enviado_at')->where('enviar_ahora', true)->pluck('entidad_id')->all();
        $this->ahora = [];
        foreach (array_keys($this->checks) as $id) {
            $this->ahora[$id] = in_array($id, $pendientes);
        }
    }

    public function updatedPeriodo(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->periodo)) {
            $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
        }
        $this->cargarAhora();
    }

    /** Marca o desmarca «enviar ahora» (crea o quita la fila pendiente del periodo). */
    protected function ponerAhora(int $id, bool $valor): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $pendiente = MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
            ->where('entidad_id', $id)->whereNull('enviado_at');
        if ($valor) {
            ($pendiente->first() ?? new MailEnviado(['proceso' => $this->proceso, 'periodo' => $this->periodo, 'entidad_id' => $id]))
                ->fill(['enviar_ahora' => true, 'user_id' => auth()->id()])->save();
        } else {
            $pendiente->delete();
        }
        $this->ahora[$id] = $valor;
    }

    /** «Enviar ahora» en todas las que tienen el check (o quitarlo de todas). */
    public function marcarTodasAhora(bool $valor): void
    {
        foreach ($this->checks as $id => $check) {
            if (($check && ($this->activas[$id] ?? false) && in_array((int) $id, Accesos::entidadesPropias(auth()->user()), true)) || ! $valor) {
                $this->ponerAhora((int) $id, $valor);
            }
        }
    }

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

    /** Las que gestiona y, si puede editar entidades, también las que no tienen Responsable Suma. */
    protected function empresas()
    {
        $editar = auth()->user()->can('entidades.editar');
        return Entidad::withoutGlobalScopes()
            ->where(fn ($q) => $q->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])
                ->when($editar, fn ($q) => $q->orWhereNull('suma_id')))
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'idioma', 'emailadm', 'cicloimpuesto_id', 'mail_peticion_check', 'mail_peticion', 'mail_peticion_cc', 'estado', 'suma_id']);
    }

    /** Solo se toca una empresa que gestiona el usuario (o sin responsable, si puede editar entidades). */
    protected function mia(int $id): bool
    {
        return in_array($id, Accesos::entidadesPropias(auth()->user()), true)
            || (auth()->user()->can('entidades.editar') && Entidad::withoutGlobalScopes()->whereKey($id)->whereNull('suma_id')->exists());
    }

    protected function cargarPlantillas(): void
    {
        $guardadas = DB::table('plantillas_mail')->where('proceso', $this->proceso)->pluck('texto', 'idioma')->all();
        foreach (array_keys(self::IDIOMAS) as $i) {
            $this->plantillas[$i] = (string) ($guardadas[$i] ?? '');
        }
    }

    public function guardarPlantillas(): void
    {
        foreach ($this->plantillas as $idioma => $texto) {
            if (isset(self::IDIOMAS[$idioma])) {
                DB::table('plantillas_mail')->updateOrInsert(
                    ['proceso' => $this->proceso, 'idioma' => $idioma],
                    ['texto' => $texto, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
        $this->dispatch('proceso-terminado', mensaje: '✅ Plantillas guardadas');
    }

    protected function desdePlantilla(int $id, string $idioma): string
    {
        $nombre = Entidad::withoutGlobalScopes()->whereKey($id)->value('entidad');
        return str_replace('{empresa}', (string) $nombre, $this->plantillas[$idioma] ?? '');
    }

    public function seleccionar(int $id): void
    {
        $this->seleccionada = $this->mia($id) && $this->seleccionada !== $id ? $id : null;
    }

    /** Pone en el cuadro de la empresa la plantilla del idioma elegido (no guarda). */
    public function aplicarPlantilla(int $id): void
    {
        if ($this->mia($id)) {
            $this->textos[$id] = $this->desdePlantilla($id, $this->idiomas[$id] ?? 'ES');
        }
    }

    /** Rellena con la plantilla de su idioma las empresas marcadas que aún no tienen texto, y guarda. */
    public function rellenarVacias(): void
    {
        $n = 0;
        foreach ($this->checks as $id => $check) {
            if ($check && trim($this->textos[$id] ?? '') === '' && $this->mia((int) $id)) {
                $this->textos[$id] = $this->desdePlantilla((int) $id, $this->idiomas[$id] ?? 'ES');
                $this->guardar((int) $id, false);
                $n++;
            }
        }
        $this->dispatch('proceso-terminado', mensaje: $n ? "✅ {$n} empresas rellenadas con la plantilla" : 'No había empresas marcadas sin texto');
    }

    /** El check y el idioma se guardan en la entidad en cuanto se cambian. */
    public function updated(string $propiedad, $valor): void
    {
        if (! preg_match('/^(checks|idiomas|ahora|activas|sumaIds)\.(\d+)$/', $propiedad, $m) || ! $this->mia((int) $m[2])) {
            return;
        }
        if ($m[1] === 'ahora') {
            $this->ponerAhora((int) $m[2], (bool) $valor);
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find((int) $m[2]);
        if ($m[1] === 'sumaIds') {
            if (! auth()->user()->can('entidades.editar')) {
                $this->sumaIds[$e->id] = $e->suma_id ? (string) $e->suma_id : '';
                return;
            }
            $e->suma_id = $valor !== '' && Suma::whereKey((int) $valor)->exists() ? (int) $valor : null;
            Accesos::olvidar();
        } elseif ($m[1] === 'activas') {
            $e->estado = $valor ? 1 : 0;
            if (! $valor) {
                $this->ponerAhora($e->id, false);
            }
        } elseif ($m[1] === 'checks') {
            $e->mail_peticion_check = (bool) $valor;
            if (! $valor) {
                $this->ponerAhora($e->id, false);
            }
        } elseif (isset(self::IDIOMAS[$valor])) {
            $e->idioma = $valor;
        }
        $e->save();
    }

    public function guardar(int $id, bool $avisar = true): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find($id);
        $e->mail_peticion_check = (bool) ($this->checks[$id] ?? false);
        $e->mail_peticion = trim($this->textos[$id] ?? '') === '' ? null : $this->textos[$id];
        $e->mail_peticion_cc = implode('; ', self::destinatarios($this->ccs[$id] ?? '')) ?: null;
        $this->ccs[$id] = (string) $e->mail_peticion_cc;
        $e->save();
        if ($avisar) {
            $this->dispatch('proceso-terminado', mensaje: '✅ Guardado: '.$e->entidad);
        }
    }

    public function render()
    {
        $propias = Accesos::entidadesPropias(auth()->user());
        $empresas = $this->empresas()
            ->filter(fn ($e) => in_array($e->id, $propias, true) || ($this->verSinResponsable && ($this->sumaIds[$e->id] ?? '') === ''))
            ->when(! $this->verBajas, fn ($c) => $c->filter(fn ($e) => $this->activas[$e->id] ?? false))
            ->when(! $this->verNoMarcadas, fn ($c) => $c->filter(fn ($e) => $this->checks[$e->id] ?? false))
            ->when($this->buscar !== '', fn ($c) => $c->filter(fn ($e) => stripos($e->entidad.' '.$e->alias, $this->buscar) !== false));

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => self::PROCESOS,
            'idiomasDisponibles' => self::IDIOMAS,
            'usuario' => auth()->user(),
            'empresas' => $empresas,
            'marcadas' => count(array_filter($this->checks, fn ($c, $id) => $c && ($this->activas[$id] ?? false) && in_array($id, $propias), ARRAY_FILTER_USE_BOTH)),
            'total' => count(array_filter($this->activas, fn ($a, $id) => $a && in_array($id, $propias), ARRAY_FILTER_USE_BOTH)),
            'sumas' => Suma::orderBy('nombre')->get(['id', 'nombre']),
            'puedeEditar' => auth()->user()->can('entidades.editar'),
            'nAhora' => count(array_filter($this->ahora)),
            // Último envío de cada empresa en el periodo
            'enviados' => MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
                ->whereNotNull('enviado_at')->selectRaw('entidad_id, max(enviado_at) as ultimo')->groupBy('entidad_id')
                ->pluck('ultimo', 'entidad_id')->all(),
        ]);
    }
}
