<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\MailEnviado;
use App\Models\ProcesoEstado;
use App\Models\Suma;
use App\Support\GraphMail;
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
    use \App\Http\Livewire\Concerns\CoResponsables;

    /** [clave => ['icono', 'titulo', 'descripcion']]: una pestaña por proceso. */
    public const PROCESOS = [
        'petdocimpuestos' => ['icono' => '📨', 'titulo' => 'Pet. Documentación Impuestos',
            'descripcion' => 'Petición mensual de la documentación para los impuestos.'],
        'certificados' => ['icono' => '🔐', 'titulo' => 'Certificados por caducar', 'local' => true,
            'descripcion' => 'Certificados digitales que caducan en los próximos meses (AlexMiniPC + PortalExomen). Se ejecuta en local.'],
    ];

    public const IDIOMAS = ['ES' => 'Español', 'EN' => 'Inglés'];

    public string $proceso = 'petdocimpuestos';

    public string $buscar = '';

    /** Por empresa (id => valor), lo que se está editando en pantalla. */
    public array $textos = [];
    public array $checks = [];
    public array $idiomas = [];
    public array $ccs = [];
    /** id => carpeta de Outlook del cliente, sin año (los enviados van a «<año>\___Suma <año>\<carpeta> <año>»). */
    public array $carpetas = [];
    /** id => Email Adm (entidades.emailadm): destinatarios, editable aquí. */
    public array $paras = [];
    /** id => asunto (entidades.mail_peticion_asunto; vacío en la entidad = el de la plantilla de su idioma). */
    public array $asuntos = [];
    /** id => activa (entidades.estado: 1 activo, 0 baja). Solo salen las activas. */
    public array $activas = [];
    /** id => Responsable Suma (entidades.suma_id), editable aquí. */
    public array $sumaIds = [];
    /** id => ciclo de impuestos (entidades.cicloimpuesto_id), editable aquí con clic. */
    public array $ciclosEnt = [];

    /** Orden en que pasa el ciclo con cada clic (ids de la tabla ciclos). */
    public const ORDEN_CICLOS = [1, 3, 12, 20, 0];

    /** Con permiso de editar entidades: enseñar también las que no tienen Responsable Suma, para asignarlas. */
    public bool $verSinResponsable = false;

    /** Plantillas del proceso (idioma => texto). */
    public array $plantillas = [];
    public array $plantillasAsunto = [];
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
        $this->cargarPlantillas();
        foreach ($this->empresas() as $e) {
            $this->textos[$e->id] = (string) $e->mail_peticion;
            $this->ccs[$e->id] = (string) $e->mail_peticion_cc;
            $this->carpetas[$e->id] = (string) $e->carpeta_outlook;
            $this->paras[$e->id] = (string) $e->emailadm;
            $this->activas[$e->id] = (int) $e->estado === 1;
            $this->sumaIds[$e->id] = $e->suma_id ? (string) $e->suma_id : '';
            $this->ciclosEnt[$e->id] = $e->cicloimpuesto_id;
            $this->checks[$e->id] = (bool) $e->mail_peticion_check;
            $this->idiomas[$e->id] = $e->idioma === 'EN' ? 'EN' : 'ES';
            $this->asuntos[$e->id] = (string) ($e->mail_peticion_asunto ?? $this->plantillasAsunto[$this->idiomas[$e->id]] ?? '');
            // Sin texto propio guardado: por defecto la plantilla de su idioma (no hace falta cargarla)
            if (trim($this->textos[$e->id]) === '') {
                $this->textos[$e->id] = str_replace('{empresa}', $e->entidad, $this->plantillas[$this->idiomas[$e->id]] ?? '');
            }
        }
        $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
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

    /**
     * Las que gestiona y, si puede editar entidades, también las que no tienen Responsable Suma.
     * Solo clientes (entidades.cliente = 1): ni proveedores ni contactos, salvo que también sean clientes.
     */
    protected function empresas()
    {
        $editar = auth()->user()->can('entidades.editar');
        return Entidad::withoutGlobalScopes()
            ->where('cliente', 1)
            ->where(fn ($q) => $q->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])
                ->when($editar, fn ($q) => $q->orWhereNull('suma_id')))
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'idioma', 'emailadm', 'cicloimpuesto_id', 'mail_peticion_check', 'mail_peticion', 'mail_peticion_cc', 'carpeta_outlook', 'estado', 'suma_id', 'cliente', 'proveedor', 'contacto']);
    }

    /** Solo se toca una empresa que gestiona el usuario (o sin responsable, si puede editar entidades). */
    protected function mia(int $id): bool
    {
        return in_array($id, Accesos::entidadesPropias(auth()->user()), true)
            || (auth()->user()->can('entidades.editar') && Entidad::withoutGlobalScopes()->whereKey($id)->whereNull('suma_id')->exists());
    }

    protected function cargarPlantillas(): void
    {
        $guardadas = DB::table('plantillas_mail')->where('proceso', $this->proceso)->get()->keyBy('idioma');
        foreach (array_keys(self::IDIOMAS) as $i) {
            $this->plantillas[$i] = (string) ($guardadas[$i]->texto ?? '');
            $this->plantillasAsunto[$i] = (string) ($guardadas[$i]->asunto ?? '');
        }
    }

    public function guardarPlantillas(): void
    {
        foreach ($this->plantillas as $idioma => $texto) {
            if (isset(self::IDIOMAS[$idioma])) {
                DB::table('plantillas_mail')->updateOrInsert(
                    ['proceso' => $this->proceso, 'idioma' => $idioma],
                    ['texto' => $texto, 'asunto' => $this->plantillasAsunto[$idioma] ?? null, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
        // Las que no tienen asunto propio pasan a ver el nuevo de la plantilla
        $propios = Entidad::withoutGlobalScopes()->whereIn('id', array_keys($this->asuntos))->whereNotNull('mail_peticion_asunto')->pluck('id')->all();
        foreach ($this->asuntos as $id => $a) {
            if (! in_array($id, $propios)) {
                $this->asuntos[$id] = $this->plantillasAsunto[$this->idiomas[$id] ?? 'ES'] ?? '';
            }
        }
        $this->dispatch('proceso-terminado', mensaje: '✅ Plantillas guardadas');
    }

    protected function desdePlantilla(int $id, string $idioma): string
    {
        $nombre = Entidad::withoutGlobalScopes()->whereKey($id)->value('entidad');
        return str_replace('{empresa}', (string) $nombre, $this->plantillas[$idioma] ?? '');
    }

    /** Clic en el idioma: pasa al siguiente y se guarda en la entidad. */
    public function siguienteIdioma(int $id): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $claves = array_keys(self::IDIOMAS);
        $i = array_search($this->idiomas[$id] ?? 'ES', $claves, true);
        $this->idiomas[$id] = $claves[(($i === false ? -1 : $i) + 1) % count($claves)];
        $this->updated("idiomas.{$id}", $this->idiomas[$id]);
    }

    /** Clic en el ciclo de impuestos: pasa al siguiente y se guarda en la entidad. */
    public function siguienteCiclo(int $id): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $i = array_search((int) ($this->ciclosEnt[$id] ?? -1), self::ORDEN_CICLOS, true);
        $nuevo = self::ORDEN_CICLOS[$i === false ? 0 : ($i + 1) % count(self::ORDEN_CICLOS)];
        Entidad::withoutGlobalScopes()->whereKey($id)->update(['cicloimpuesto_id' => $nuevo]);
        $this->ciclosEnt[$id] = $nuevo;
    }

    /** Clic en Cli / Pro / Con: cambia y se guarda en la entidad. Si deja de ser cliente, sale de la lista. */
    public function alternarRelacion(int $id, string $campo): void
    {
        if (! in_array($campo, ['cliente', 'proveedor', 'contacto'], true) || ! $this->mia($id) || ! auth()->user()->can('entidades.editar')) {
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find($id);
        $e->{$campo} = ! $e->{$campo};
        $e->save();
        if ($campo === 'cliente' && ! $e->cliente) {
            $this->ponerAhora($id, false);
            if ($this->seleccionada === $id) {
                $this->seleccionada = null;
            }
        }
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
            $this->asuntos[$id] = $this->plantillasAsunto[$this->idiomas[$id] ?? 'ES'] ?? '';
            $this->updated("asuntos.{$id}", $this->asuntos[$id]);
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
        if (! preg_match('/^(checks|idiomas|ahora|activas|sumaIds|paras|asuntos|carpetas)\.(\d+)$/', $propiedad, $m) || ! $this->mia((int) $m[2])) {
            return;
        }
        if ($m[1] === 'ahora') {
            $this->ponerAhora((int) $m[2], (bool) $valor);
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find((int) $m[2]);
        if ($m[1] === 'asuntos') {
            // Igual que la plantilla de su idioma o vacío = sin asunto propio (sigue a la plantilla)
            $porDefecto = $this->plantillasAsunto[$this->idiomas[$e->id] ?? 'ES'] ?? '';
            $a = trim((string) $valor);
            $e->mail_peticion_asunto = ($a === '' || $a === $porDefecto) ? null : mb_substr($a, 0, 255);
            $this->asuntos[$e->id] = $e->mail_peticion_asunto ?? $porDefecto;
        } elseif ($m[1] === 'carpetas') {
            // sin el año del final («Eric 2026» → «Eric»)
            $c = trim(preg_replace('/\s+20\d\d$/', '', trim((string) $valor)));
            $e->carpeta_outlook = $c === '' ? null : mb_substr($c, 0, 150);
            $this->carpetas[$e->id] = (string) $e->carpeta_outlook;
        } elseif ($m[1] === 'paras') {
            $e->emailadm = mb_substr(implode('; ', self::destinatarios((string) $valor)), 0, 500) ?: null;
            $this->paras[$e->id] = (string) $e->emailadm;
        } elseif ($m[1] === 'sumaIds') {
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
            if ($e->mail_peticion_asunto === null) {
                $this->asuntos[$e->id] = $this->plantillasAsunto[$valor] ?? '';
            }
            // Si no tiene texto propio guardado, el texto pasa a la plantilla del nuevo idioma
            if (blank($e->mail_peticion)) {
                $this->textos[$e->id] = str_replace('{empresa}', $e->entidad, $this->plantillas[$valor] ?? '');
            }
        }
        $e->save();
    }

    /** Lista de envío preparada (modal de confirmación) y resultado del último envío. */
    public array $envio = [];
    public bool $confirmarEnvio = false;
    public array $resultadoEnvio = [];

    /** Remitente: el usuario conectado si es de sumaempresa.com; si no, GRAPH_SENDER. */
    protected function remitente(): string
    {
        $mail = (string) auth()->user()->email;
        return str_ends_with(strtolower($mail), '@sumaempresa.com') ? $mail : config('contabilidad.graph.sender');
    }

    /** Correo final de una empresa: Para, CC, asunto y texto con {periodo} / {empresa} puestos. */
    protected function correoDe(Entidad $e): array
    {
        $idioma = $this->idiomas[$e->id] ?? 'ES';
        $pt = self::textoPeriodo($this->periodo, $this->ciclosEnt[$e->id] ?? $e->cicloimpuesto_id, $idioma);
        $texto = trim($this->textos[$e->id] ?? '') !== '' ? $this->textos[$e->id] : ($this->plantillas[$idioma] ?? '');
        $asunto = trim($this->asuntos[$e->id] ?? '') !== '' ? $this->asuntos[$e->id] : ($this->plantillasAsunto[$idioma] ?? '');
        $poner = fn ($s) => str_replace(['{periodo}', '{empresa}'], [$pt, $e->entidad], $s);
        $para = self::destinatarios($this->paras[$e->id] ?? $e->emailadm);
        $cc = self::destinatarios($this->ccs[$e->id] ?? $e->mail_peticion_cc);
        $malos = array_filter(array_merge($para, $cc), fn ($c) => ! filter_var($c, FILTER_VALIDATE_EMAIL));
        $error = ! $para ? 'Sin Email Adm' : ($malos ? 'Correo no válido: '.implode(', ', $malos) : (trim($texto) === '' ? 'Sin texto' : (trim($asunto) === '' ? 'Sin asunto' : null)));
        return ['id' => $e->id, 'empresa' => $e->entidad, 'idioma' => $idioma, 'para' => $para, 'cc' => $cc,
            'asunto' => $poner($asunto), 'texto' => $poner($texto), 'error' => $error];
    }

    /** «Enviar todos»: prepara la lista de las marcadas 🚀 y abre la confirmación (no envía nada). */
    public function prepararEnvio(): void
    {
        $this->resultadoEnvio = [];
        $ids = array_keys(array_filter($this->ahora));
        $this->envio = Entidad::withoutGlobalScopes()->whereIn('id', $ids ?: [0])->where('cliente', 1)->where('estado', 1)->orderBy('entidad')->get()
            ->filter(fn ($e) => ($this->checks[$e->id] ?? false) && $this->mia($e->id))
            ->map(fn ($e) => $this->correoDe($e))->values()->all();
        $this->confirmarEnvio = true;
    }

    /** Envía los de la lista confirmada que no tienen error; cada uno queda en mails_enviados. */
    public function enviarTodos(): void
    {
        $this->confirmarEnvio = false;
        $de = $this->remitente();
        $ok = 0;
        $fallos = [];
        foreach ($this->envio as $c) {
            if ($c['error'] || ! $this->mia((int) $c['id'])) {
                continue;
            }
            $fila = MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
                ->where('entidad_id', $c['id'])->whereNull('enviado_at')->first()
                ?? new MailEnviado(['proceso' => $this->proceso, 'periodo' => $this->periodo, 'entidad_id' => $c['id']]);
            $fila->fill(['user_id' => auth()->id(), 'idioma' => $c['idioma'], 'destinatarios' => implode('; ', $c['para']),
                'cc' => implode('; ', $c['cc']), 'asunto' => $c['asunto'], 'texto' => $c['texto']]);
            try {
                GraphMail::enviar($de, $c['para'], $c['cc'], $c['asunto'], $c['texto']);
                $fila->fill(['enviado_at' => now(), 'enviar_ahora' => false, 'error' => null, 'html' => GraphMail::html($c['texto'])])->save();
                $this->ahora[$c['id']] = false;
                $this->ponerEstado((int) $c['id'], 'solicitado', false);
                $this->archivar($fila, $de);
                if ($fila->archivo_error) {
                    $fallos[] = $c['empresa'].': enviado, pero no movido a su carpeta ('.$fila->archivo_error.')';
                }
                $ok++;
            } catch (\Throwable $ex) {
                $fila->fill(['error' => mb_substr($ex->getMessage(), 0, 1000), 'enviar_ahora' => true])->save();
                $fallos[] = $c['empresa'].': '.$ex->getMessage();
            }
        }
        $this->resultadoEnvio = ['ok' => $ok, 'fallos' => $fallos, 'de' => $de];
        $this->envio = [];
        $this->dispatch('proceso-terminado', mensaje: "✉ Enviados {$ok}".($fallos ? ' · ⚠ '.count($fallos).' con error' : ''));
    }

    /**
     * Mueve el correo enviado de Enviados a la carpeta de Outlook del cliente (pedido
     * 2026-10-02). Deja archivado_at o el motivo en archivo_error; nunca rompe el envío.
     */
    protected function archivar(MailEnviado $fila, string $buzon): void
    {
        $carpeta = trim((string) Entidad::withoutGlobalScopes()->whereKey($fila->entidad_id)->value('carpeta_outlook'));
        if ($carpeta === '') {
            $fila->fill(['archivo_error' => 'la empresa no tiene carpeta de Outlook'])->save();
            return;
        }
        try {
            GraphMail::moverEnviado($buzon, (string) $fila->asunto, $fila->enviado_at, $carpeta, (int) $fila->enviado_at->format('Y'));
            $fila->fill(['archivado_at' => now(), 'archivo_error' => null])->save();
        } catch (\Throwable $ex) {
            $fila->fill(['archivo_error' => mb_substr($ex->getMessage(), 0, 500)])->save();
        }
    }

    /** «📁 Archivar enviados»: los ya enviados del periodo que aún no están en su carpeta. */
    public function archivarPendientes(): void
    {
        $ok = 0;
        $fallos = [];
        $filas = MailEnviado::with('user:id,email')->where('proceso', $this->proceso)->where('periodo', $this->periodo)
            ->whereNotNull('enviado_at')->whereNull('error')->whereNull('archivado_at')->get();
        foreach ($filas as $f) {
            if (! $this->mia((int) $f->entidad_id)) {
                continue;
            }
            $this->archivar($f, $f->user?->email ?: $this->remitente());
            $f->archivado_at ? $ok++ : $fallos[] = ($f->entidad?->entidad ?? $f->entidad_id).': '.$f->archivo_error;
        }
        $this->resultadoEnvio = ['ok' => 0, 'archivados' => $ok, 'fallos' => $fallos, 'de' => $this->remitente()];
        $this->dispatch('proceso-terminado', mensaje: "📁 Archivados {$ok}".($fallos ? ' · ⚠ '.count($fallos).' sin mover' : ''));
    }

    /** Carpetas de cliente que hay en Outlook (para elegir), o [] si no se pueden leer. */
    public function getCarpetasDisponiblesProperty(): array
    {
        try {
            return GraphMail::configurado() ? GraphMail::carpetasCliente($this->remitente(), (int) now()->format('Y')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** La carpeta que más se parece al nombre de la empresa (primera palabra significativa). */
    public static function propuestaCarpeta(string $entidad, ?string $alias, array $lista): ?string
    {
        $norm = fn ($t) => mb_strtolower(\Illuminate\Support\Str::ascii((string) $t));
        $palabras = array_filter(preg_split('/[^a-z0-9]+/', $norm($alias.' '.$entidad)), fn ($w) => strlen($w) >= 3
            && ! in_array($w, ['sl', 'slu', 'sa', 'grupo', 'the', 'del', 'las', 'los', 'consulting', 'investments'], true));
        foreach ($lista as $c) {
            $pc = array_values(array_filter(preg_split('/[^a-z0-9]+/', $norm($c)), fn ($w) => strlen($w) >= 3));
            if ($pc && array_intersect($pc, $palabras)) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Estado de la empresa en el periodo (pedido 2026-10-02): sin fila = no solicitado;
     * «solicitado» se pone solo al enviar (sin bajar un «recibido»); «recibido» a mano.
     */
    protected function ponerEstado(int $id, ?string $estado, bool $forzar = true): void
    {
        $q = ProcesoEstado::where('proceso', $this->proceso)->where('entidad_id', $id)->where('periodo', $this->periodo);
        if ($estado === null) {
            $q->delete();
            return;
        }
        $fila = $q->first() ?? new ProcesoEstado(['proceso' => $this->proceso, 'entidad_id' => $id, 'periodo' => $this->periodo]);
        if (! $forzar && $fila->estado === 'recibido') {
            return;
        }
        $fila->estado = $estado;
        $fila->user_id = auth()->id();
        if ($estado === 'solicitado') {
            $fila->solicitado_at ??= now();
            $fila->recibido_at = null;
        } else {
            $fila->recibido_at = now();
        }
        $fila->save();
    }

    /** Clic en el estado: no solicitado → solicitado → recibido → no solicitado. */
    public function siguienteEstado(int $id): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $actual = ProcesoEstado::where('proceso', $this->proceso)->where('entidad_id', $id)->where('periodo', $this->periodo)->value('estado');
        $this->ponerEstado($id, match ($actual) { null => 'solicitado', 'solicitado' => 'recibido', default => null });
    }

    public function cancelarEnvio(): void
    {
        $this->confirmarEnvio = false;
        $this->envio = [];
    }

    public function guardar(int $id, bool $avisar = true): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find($id);
        $e->mail_peticion_check = (bool) ($this->checks[$id] ?? false);
        $plantilla = str_replace('{empresa}', $e->entidad, $this->plantillas[$this->idiomas[$id] ?? 'ES'] ?? '');
        $t = (string) ($this->textos[$id] ?? '');
        // Vacío o igual a la plantilla = sin texto propio (sigue a la plantilla)
        $e->mail_peticion = (trim($t) === '' || trim($t) === trim($plantilla)) ? null : $t;
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
        // Solo los procesos a los que tiene acceso (permisos de proceso del panel de control)
        $permitidos = array_filter(self::PROCESOS, fn ($p, $k) => auth()->user()->can('proceso.pm.'.$k), ARRAY_FILTER_USE_BOTH);
        if (! isset($permitidos[$this->proceso]) && $permitidos) {
            $this->proceso = array_key_first($permitidos);
        }
        $empresas = $this->empresas()
            ->filter(fn ($e) => in_array($e->id, $propias, true) || ($this->verSinResponsable && ($this->sumaIds[$e->id] ?? '') === ''))
            ->when(! $this->verBajas, fn ($c) => $c->filter(fn ($e) => $this->activas[$e->id] ?? false))
            ->when(! $this->verNoMarcadas, fn ($c) => $c->filter(fn ($e) => $this->checks[$e->id] ?? false))
            ->when($this->buscar !== '', fn ($c) => $c->filter(fn ($e) => stripos($e->entidad.' '.$e->alias, $this->buscar) !== false));

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => $permitidos,
            'idiomasDisponibles' => self::IDIOMAS,
            'usuario' => auth()->user(),
            'empresas' => $empresas,
            'marcadas' => count(array_filter($this->checks, fn ($c, $id) => $c && ($this->activas[$id] ?? false) && in_array($id, $propias), ARRAY_FILTER_USE_BOTH)),
            'total' => count(array_filter($this->activas, fn ($a, $id) => $a && in_array($id, $propias), ARRAY_FILTER_USE_BOTH)),
            'sumas' => Suma::orderBy('nombre')->get(['id', 'nombre', 'user_id']),
            'coResp' => self::coResponsablesDe($empresas->pluck('id')->all()),
            'nombresCiclo' => DB::table('ciclos')->whereIn('id', self::ORDEN_CICLOS)->pluck('ciclo', 'id')->map(fn ($c, $id) => $id === 0 ? 'Sin definir' : $c)->all(),
            'puedeEditar' => auth()->user()->can('entidades.editar'),
            'nAhora' => count(array_filter($this->ahora)),
            'graphOk' => GraphMail::configurado(),
            // Correos ya enviados de la empresa seleccionada (todos los periodos), para verlos
            'historial' => $this->seleccionada ? MailEnviado::with('user:id,name,email')->where('proceso', $this->proceso)
                ->where('entidad_id', $this->seleccionada)->whereNotNull('enviado_at')->orderByDesc('enviado_at')->get() : collect(),
            'remitente' => $this->remitente(),
            // Último envío de cada empresa en el periodo
            'pendientesArchivo' => MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
                ->whereNotNull('enviado_at')->whereNull('error')->whereNull('archivado_at')->count(),
            // Estado de cada empresa en el periodo: no solicitado / solicitado / recibido
            'estados' => ProcesoEstado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
                ->get()->keyBy('entidad_id')->all(),
            'enviados' => MailEnviado::where('proceso', $this->proceso)->where('periodo', $this->periodo)
                ->whereNotNull('enviado_at')->selectRaw('entidad_id, max(enviado_at) as ultimo')->groupBy('entidad_id')
                ->pluck('ultimo', 'entidad_id')->all(),
        ]);
    }
}
