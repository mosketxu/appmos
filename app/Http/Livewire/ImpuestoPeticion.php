<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Contabilidad\ProcesosMensuales as PM;
use App\Models\Entidad;
use App\Models\MailEnviado;
use App\Models\ProcesoEstado;
use App\Support\GraphMail;
use App\Support\Impuestos as Imp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuestos → Seguimiento: ventana «Petición de documentación» (15-oct-2026; antes era la pestaña Pet. Documentación Impuestos de
 * Proc.Mensuales, que se quita). Por cliente y periodo (AAAA-MM: el mes, o el mes que cierra el trimestre en clientes trimestrales).
 * Mismos datos de siempre: texto/asunto/CC/Para en la entidad (mail_peticion, mail_peticion_asunto, mail_peticion_cc, emailadm),
 * plantillas en plantillas_mail (proceso petdocimpuestos), lo enviado en mails_enviados y el estado en procesos_estado.
 * Borrador en Outlook, prueba a uno mismo o envío REAL en dos pasos (como el correo de impuestos, ImpuestoCorreo). Al enviar:
 * estado «solicitado» (sin bajar un «recibido») y el correo se mueve a la carpeta de Outlook del cliente. Avisa al padre con
 * pd-cerrar / pd-hecho.
 */
class ImpuestoPeticion extends Component
{
    use WithFileUploads;

    const PROCESO = 'petdocimpuestos';

    public int $ejercicio;
    public bool $verTodos = false;
    public int $pdEnt;
    public string $pdPer = '';
    public string $pdPara = '';
    public string $pdCc = '';
    public string $pdIdioma = 'ES';
    public string $pdAsunto = '';
    public string $pdTexto = '';
    public string $pdMsg = '';
    public bool $pdOk = false;
    public string $pdArmado = '';
    /** [['nombre', 'almacen', 'tam']] subidos para este correo */
    public array $pdAdjuntos = [];
    public $pdSubidas = [];

    public function mount(int $entId, string $periodo, int $ejercicio, bool $verTodos = false): void
    {
        $this->ejercicio = $ejercicio;
        $this->verTodos = $verTodos;
        $this->autorizar($entId);
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo), 422);
        $this->pdEnt = $entId;
        $this->pdPer = $periodo;
        $e = $this->ent();
        $this->pdIdioma = $e->idioma === 'EN' ? 'EN' : 'ES';
        $this->pdPara = implode('; ', PM::destinatarios($e->emailadm));
        $this->pdCc = (string) ($e->mail_peticion_cc ?? '');
        $this->cargarTexto(true);
    }

    protected function autorizar(int $entidadId): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $ver = DB::table('entidad_impuestos')->where('entidad_id', $entidadId)->pluck('id')->first(fn ($id) => Imp::puedeVer($id, $this->verTodos));
        abort_unless($ver, 403);
    }

    protected function ent(): Entidad
    {
        return Entidad::withoutGlobalScopes()->findOrFail($this->pdEnt);
    }

    protected function plantilla(string $idioma): object
    {
        return DB::table('plantillas_mail')->where('proceso', self::PROCESO)->where('idioma', $idioma)->first() ?? (object) ['texto' => '', 'asunto' => ''];
    }

    /** Texto y asunto: los propios del cliente o, si no tiene, la plantilla de su idioma. */
    protected function cargarTexto(bool $propios): void
    {
        $e = $this->ent();
        $pl = $this->plantilla($this->pdIdioma);
        $this->pdTexto = $propios && trim((string) $e->mail_peticion) !== '' ? (string) $e->mail_peticion : str_replace('{empresa}', $e->entidad, (string) $pl->texto);
        $this->pdAsunto = $propios && trim((string) $e->mail_peticion_asunto) !== '' ? (string) $e->mail_peticion_asunto : (string) $pl->asunto;
    }

    public function updated($prop = null): void
    {
        $this->pdArmado = '';
    }

    public function updatedPdIdioma(): void
    {
        $this->pdIdioma = $this->pdIdioma === 'EN' ? 'EN' : 'ES';
        $e = $this->ent();
        // sin texto propio, pasa a la plantilla del otro idioma
        if (trim((string) $e->mail_peticion) === '') {
            $this->cargarTexto(false);
        }
    }

    public function pdPlantilla(): void
    {
        $this->cargarTexto(false);
        $this->pdArmado = '';
    }

    public function updatedPdSubidas(): void
    {
        $this->autorizar($this->pdEnt);
        $this->validate(['pdSubidas.*' => 'file|max:20480'], ['pdSubidas.*.max' => 'Algún fichero pesa más de 20 MB.']);
        foreach ((array) $this->pdSubidas as $f) {
            $nombre = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $f->getClientOriginalName());
            $almacen = 'impuestos_correo/'.$this->pdEnt.'/'.uniqid().'/'.$nombre;
            Storage::disk('local')->put($almacen, fopen($f->getRealPath(), 'rb'));
            $this->pdAdjuntos[] = ['nombre' => $nombre, 'almacen' => $almacen, 'tam' => (int) $f->getSize()];
        }
        $this->pdSubidas = [];
        $this->pdArmado = '';
    }

    public function pdQuitarAdjunto(int $i): void
    {
        if ($a = $this->pdAdjuntos[$i] ?? null) {
            Storage::disk('local')->delete($a['almacen']);
            unset($this->pdAdjuntos[$i]);
            $this->pdAdjuntos = array_values($this->pdAdjuntos);
            $this->pdArmado = '';
        }
    }

    /** Asunto y texto con {periodo} (mes o trimestre según el ciclo del cliente) y {empresa} puestos. */
    protected function montar(): array
    {
        $e = $this->ent();
        $pt = PM::textoPeriodo($this->pdPer, $e->cicloimpuesto_id, $this->pdIdioma);
        $poner = fn ($s) => str_replace(['{periodo}', '{empresa}'], [$pt, $e->entidad], (string) $s);

        return [$poner($this->pdAsunto), $poner($this->pdTexto)];
    }

    public function getPdVistaProperty(): string
    {
        [$asunto, $texto] = $this->montar();
        $adj = array_column($this->pdAdjuntos, 'nombre');

        return '<div style="font-size:12px;color:#6b7280;margin-bottom:6px"><b>Asunto:</b> '.e($asunto)
            .($adj ? '<br><b>📎</b> '.e(implode(' · ', $adj)) : '').'</div>'.str_replace('cid:logo_suma', asset('img/logo_suma.gif'), GraphMail::html($texto));
    }

    /** Remitente: el usuario si es de sumaempresa.com; si no, GRAPH_SENDER (como en Proc.Mensuales). */
    protected function remitente(): string
    {
        $mail = (string) auth()->user()->email;

        return str_ends_with(strtolower($mail), '@sumaempresa.com') ? $mail : (string) config('contabilidad.graph.sender');
    }

    /** [para, cc, asunto, html, texto, adjuntos] o null con el motivo en pdMsg. */
    protected function listo(): ?array
    {
        $para = PM::destinatarios($this->pdPara);
        $cc = PM::destinatarios($this->pdCc);
        [$asunto, $texto] = $this->montar();
        if (! $para) {
            $this->pdMsg = 'Falta el destinatario (Para).';
            return null;
        }
        if ($malas = array_filter(array_merge($para, $cc), fn ($a) => ! filter_var($a, FILTER_VALIDATE_EMAIL))) {
            $this->pdMsg = 'Dirección no válida: '.implode(', ', $malas);
            return null;
        }
        if (trim($asunto) === '' || trim($texto) === '') {
            $this->pdMsg = 'Falta el asunto o el texto.';
            return null;
        }
        $adj = array_map(fn ($a) => ['nombre' => $a['nombre'], 'ruta' => Storage::disk('local')->path($a['almacen']), 'tam' => $a['tam']], $this->pdAdjuntos);

        return [$para, $cc, $asunto, GraphMail::html($texto), $texto, $adj];
    }

    /** Guarda en la ficha del cliente lo de esta ventana (Para, CC, idioma; texto y asunto solo si no son los de la plantilla). */
    public function pdGuardar(bool $avisar = true): void
    {
        $this->autorizar($this->pdEnt);
        $e = $this->ent();
        $pl = $this->plantilla($this->pdIdioma);
        $e->emailadm = mb_substr(implode('; ', PM::destinatarios($this->pdPara)), 0, 500) ?: null;
        $e->mail_peticion_cc = mb_substr(implode('; ', PM::destinatarios($this->pdCc)), 0, 500) ?: null;
        $e->idioma = $this->pdIdioma;
        $t = trim($this->pdTexto);
        $e->mail_peticion = ($t === '' || $t === trim(str_replace('{empresa}', $e->entidad, (string) $pl->texto))) ? null : $this->pdTexto;
        $a = trim($this->pdAsunto);
        $e->mail_peticion_asunto = ($a === '' || $a === trim((string) $pl->asunto)) ? null : mb_substr($a, 0, 255);
        $e->save();
        if ($avisar) {
            $this->pdOk = true;
            $this->pdMsg = 'Guardado en la ficha del cliente: la próxima vez saldrá así.';
        }
    }

    /** Guarda el texto y el asunto como plantilla general del idioma (para los clientes sin texto propio). */
    public function pdGuardarPlantilla(): void
    {
        $this->autorizar($this->pdEnt);
        $e = $this->ent();
        DB::table('plantillas_mail')->updateOrInsert(['proceso' => self::PROCESO, 'idioma' => $this->pdIdioma],
            ['texto' => str_replace($e->entidad, '{empresa}', $this->pdTexto), 'asunto' => $this->pdAsunto, 'updated_at' => now(), 'created_at' => now()]);
        $this->pdOk = true;
        $this->pdMsg = 'Plantilla '.($this->pdIdioma === 'EN' ? 'en inglés' : 'en español').' guardada (el nombre del cliente queda como {empresa}).';
    }

    public function pdBorrador(): void
    {
        $this->autorizar($this->pdEnt);
        $this->pdArmado = '';
        $this->pdOk = false;
        if (! $l = $this->listo()) {
            return;
        }
        [$para, $cc, $asunto, $html, , $adj] = $l;
        try {
            GraphMail::crearBorradorHtml($this->remitente(), $para, $cc, $asunto, $html, $adj);
        } catch (\Throwable $ex) {
            $this->pdMsg = 'No se ha podido crear el borrador: '.$ex->getMessage();
            return;
        }
        $this->pdGuardar(false);
        $this->pdOk = true;
        $this->pdMsg = 'Borrador listo en Outlook (Borradores de '.$this->remitente().'). Envíalo desde allí y marca la petición como «Solicitado».';
        $this->dispatch('notify', '✉ Borrador listo en Outlook');
        $this->dispatch('pd-hecho');
    }

    public function pdPrueba(): void
    {
        $this->autorizar($this->pdEnt);
        $this->pdArmado = '';
        $this->pdOk = false;
        if (! $l = $this->listo()) {
            return;
        }
        [$para, $cc, $asunto, $html, , $adj] = $l;
        $yo = (string) auth()->user()->email;
        try {
            GraphMail::enviarHtml($this->remitente(), [$yo], [], '[PRUEBA] '.$asunto, '<p style="font-family:Calibri,Arial;font-size:9pt;color:#6b7280">Prueba. Iría a: '
                .e(implode(', ', $para)).($cc ? ' · CC: '.e(implode(', ', $cc)) : '').'</p>'.$html, $adj);
        } catch (\Throwable $ex) {
            $this->pdMsg = 'No se ha podido enviar la prueba: '.$ex->getMessage();
            return;
        }
        $this->pdOk = true;
        $this->pdMsg = 'Prueba enviada a '.$yo.'.';
        $this->dispatch('notify', '✉ Prueba enviada a '.$yo);
    }

    public function pdArmar(): void
    {
        $this->autorizar($this->pdEnt);
        $this->pdOk = false;
        $l = $this->listo();
        $this->pdArmado = $l ? md5(serialize($l)) : '';
        if ($l) {
            $this->pdMsg = '';
        }
    }

    public function pdEnviar(): void
    {
        $this->autorizar($this->pdEnt);
        $this->pdOk = false;
        $l = $this->listo();
        if (! $l || $this->pdArmado === '' || $this->pdArmado !== md5(serialize($l))) {
            $this->pdMsg = ! $l ? $this->pdMsg : ($this->pdArmado === '' ? 'Pulsa primero «Enviar al cliente…».' : 'Ha cambiado algo: vuelve a pulsar «Enviar al cliente…».');
            $this->pdArmado = '';
            return;
        }
        $this->pdArmado = '';
        [$para, $cc, $asunto, $html, $texto, $adj] = $l;
        $de = $this->remitente();
        $fila = MailEnviado::where(['proceso' => self::PROCESO, 'periodo' => $this->pdPer, 'entidad_id' => $this->pdEnt])->whereNull('enviado_at')->first()
            ?? new MailEnviado(['proceso' => self::PROCESO, 'periodo' => $this->pdPer, 'entidad_id' => $this->pdEnt]);
        $fila->fill(['user_id' => auth()->id(), 'idioma' => $this->pdIdioma, 'destinatarios' => implode('; ', $para), 'cc' => implode('; ', $cc),
            'asunto' => $asunto, 'texto' => $texto, 'enviar_ahora' => false]);
        try {
            GraphMail::enviarHtml($de, $para, $cc, $asunto, $html, $adj);
        } catch (\Throwable $ex) {
            $this->pdMsg = 'No se ha podido enviar: '.$ex->getMessage();
            return;
        }
        $fila->fill(['enviado_at' => now(), 'error' => null, 'html' => $html])->save();
        $this->pdGuardar(false);
        // estado «solicitado» (sin bajar un «recibido»)
        $est = ProcesoEstado::firstOrNew(['proceso' => self::PROCESO, 'entidad_id' => $this->pdEnt, 'periodo' => $this->pdPer]);
        if ($est->estado !== 'recibido') {
            $est->fill(['estado' => 'solicitado', 'user_id' => auth()->id()]);
            $est->solicitado_at ??= now();
            $est->recibido_at = null;
            $est->save();
        }
        // a la carpeta de Outlook del cliente (si la tiene); nunca rompe el envío
        $carpeta = trim((string) $this->ent()->carpeta_outlook);
        $archivo = '';
        if ($carpeta !== '') {
            try {
                GraphMail::moverEnviado($de, $asunto, $fila->enviado_at, $carpeta, (int) $fila->enviado_at->format('Y'));
                $fila->fill(['archivado_at' => now(), 'archivo_error' => null])->save();
                $archivo = ' Movido a su carpeta de Outlook.';
            } catch (\Throwable $ex) {
                $fila->fill(['archivo_error' => mb_substr($ex->getMessage(), 0, 500)])->save();
                $archivo = ' (No se ha podido mover a su carpeta de Outlook: '.$ex->getMessage().')';
            }
        }
        $this->pdOk = true;
        $this->pdMsg = 'Petición ENVIADA a '.implode(', ', $para).'. Queda como «Solicitado».'.$archivo;
        $this->dispatch('notify', '✉ Petición de documentación enviada');
        $this->dispatch('pd-hecho');
    }

    public function render()
    {
        $e = $this->ent();

        return view('livewire.impuesto-peticion', [
            'nombreEnt' => $e->entidad,
            'textoPeriodo' => PM::textoPeriodo($this->pdPer, $e->cicloimpuesto_id, 'ES'),
            'enviados' => MailEnviado::where(['proceso' => self::PROCESO, 'entidad_id' => $this->pdEnt])->whereNotNull('enviado_at')->orderByDesc('enviado_at')->limit(5)->get(),
            'remitente' => $this->remitente(),
        ]);
    }
}
