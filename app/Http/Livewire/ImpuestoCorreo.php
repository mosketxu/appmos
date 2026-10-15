<?php

namespace App\Http\Livewire;

use App\Support\ComunicarImpuestos as CI;
use App\Support\GraphMail;
use App\Support\Impuestos as Imp;
use App\Support\PlazosAeat;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Impuestos → Seguimiento: ventana «Preparar correo al cliente» del sobre ✉ (15-oct-2026). Componente aparte para que escribir aquí no
 * vuelva a pintar la tabla entera de Impuestos. Propone el correo con el resultado de los impuestos del periodo y el día de cargo, en el
 * idioma del cliente; lo deja como BORRADOR en Outlook (buzón del usuario) o, en dos pasos, lo ENVÍA. Lo usado con cada cliente
 * (destinatarios, texto, idioma…) queda en impuesto_comunicaciones y es la base de la próxima vez. Al terminar avisa al padre
 * (eventos ci-cerrar / ci-hecho).
 */
class ImpuestoCorreo extends Component
{

    public int $ejercicio;
    public bool $verTodos = false;
    public ?int $ciEnt = null;
    public string $ciBloque = '';
    public string $ciClave = '';
    /** [[id, cod, per, texto, imp, fecha]] */
    public array $ciFilas = [];
    public string $ciPara = '';
    public string $ciCc = '';
    public string $ciIdioma = 'ES';
    public string $ciAsunto = '';
    public string $ciTexto = '';
    public string $ciBanco = '';
    public string $ciMsg = '';
    public bool $ciOk = false;
    /** Primer paso del envío real; guarda la huella de lo que se va a mandar (si cambia algo, hay que volver a pedirlo). */
    public string $ciArmado = '';

    /** Obligaciones con pago del cliente en ese bloque: [clave => [[ob, per]]] (clave = mes de cierre o 'A'). */
    protected function ciOpciones(int $entId, string $bloque): array
    {
        $obs = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $entId)->where('m.activo', true)->whereIn('m.codigo', CI::CON_PAGO)
            ->orderBy('m.orden')->get(['ei.id', 'ei.etiqueta', 'ei.periodicidad', 'ei.baja_ejercicio', 'ei.baja_periodo', 'm.codigo', 'm.desfase']);
        $estados = DB::table('impuesto_estados')->where('ejercicio', $this->ejercicio)->whereIn('entidad_impuesto_id', $obs->pluck('id')->all() ?: [0])
            ->get(['entidad_impuesto_id', 'periodo', 'estado'])->mapWithKeys(fn ($r) => [$r->entidad_impuesto_id.'|'.$r->periodo => $r->estado])->all();
        $vale = fn ($ob, $per) => ! in_array($estados[$ob->id.'|'.$per] ?? 'no', ['no', 'nopresenta'], true) && ! Imp::dadaDeBaja($ob, $this->ejercicio, $per);
        $out = [];
        if (preg_match('/^T([1-4])$/', $bloque, $mq)) {
            $q = (int) $mq[1];
            foreach (range(3 * $q - 2, 3 * $q) as $m) {
                foreach ($obs as $ob) {
                    $per = match ($ob->periodicidad) {
                        'M' => sprintf('%02d', $m),
                        'T' => $m === 3 * $q ? 'T'.$q : null,
                        'P' => $m === 3 * $q ? ([1 => 'P1', 3 => 'P2', 4 => 'P3'][$q] ?? null) : null,
                        default => null,
                    };
                    if ($per && $vale($ob, $per)) {
                        $out[sprintf('%02d', $m)][] = [$ob, $per];
                    }
                }
            }
        } else {
            foreach ($obs as $ob) {
                if ($ob->periodicidad === 'A' && ($bloque === 'A' || $bloque === 'D'.$ob->codigo) && $vale($ob, 'A')) {
                    $out['A'][] = [$ob, 'A'];
                }
            }
        }
        return $out;
    }

    /** Para la vista: [clave => [periodos]] del cliente y bloque abiertos. */
    public function getCiOpsProperty(): array
    {
        return $this->ciEnt ? array_map(fn ($l) => array_column($l, 1), $this->ciOpciones($this->ciEnt, $this->ciBloque)) : [];
    }

    public function mount(int $entId, string $bloque, int $ejercicio, bool $verTodos = false): void
    {
        $this->ejercicio = $ejercicio;
        $this->verTodos = $verTodos;
        $this->autorizarAviso($entId);
        $ops = $this->ciOpciones($entId, $bloque);
        $this->ciEnt = $entId;
        $this->ciBloque = $bloque;
        $this->ciMsg = '';
        $this->ciOk = false;
        if (! $ops) {
            $this->ciClave = '';
            $this->ciFilas = [];
            $this->ciMsg = 'Este cliente no tiene en este periodo impuestos con pago (303, 111, 115, 202…) pendientes de presentar.';
            return;
        }
        // Por defecto: el último mes del bloque que ya ha terminado (los impuestos de septiembre se comunican en octubre)
        $hoy = (int) now()->format('Y') === $this->ejercicio ? (int) now()->format('n') : 13;
        $claves = array_keys($ops);
        $pasados = array_filter($claves, fn ($c) => $c === 'A' || (int) $c < $hoy);
        $this->ciClave = (string) (end($pasados) ?: end($claves));
        $this->ciCargar();
    }

    /** Cualquier cambio en la ventana desarma el envío real. Un importe negativo pasa a «A compensar» (sin signo). */
    public function updated($prop = null): void
    {
        $this->ciArmado = '';
        if (is_string($prop) && preg_match('/^ciFilas\.(\d+)\.imp$/', $prop, $m)) {
            $v = CI::importe((string) ($this->ciFilas[$m[1]]['imp'] ?? ''));
            if ($v !== null && $v < 0) {
                $this->ciFilas[$m[1]]['imp'] = CI::eur(-$v);
                $this->ciFilas[$m[1]]['tipo'] = 'compensar';
            }
        }
    }

    public function updatedCiClave(): void
    {
        $this->ciCargar();
    }

    public function updatedCiIdioma(): void
    {
        $this->ciIdioma = CI::idioma($this->ciIdioma);
        // textos de las filas y, si no se habían tocado, plantilla del nuevo idioma
        foreach ($this->ciFilas as $i => $f) {
            $this->ciFilas[$i]['texto'] = (CI::DESCR[$this->ciIdioma][$f['cod']] ?? $f['cod']).($f['etq'] !== '' ? ' ('.$f['etq'].')' : '');
        }
        $pl = DB::table('plantillas_mail')->where('proceso', 'comunicar_impuestos')->where('idioma', $this->ciIdioma)->first();
        if ($pl) {
            $this->ciAsunto = (string) $pl->asunto;
            $this->ciTexto = (string) $pl->texto;
        }
    }

    protected function ciCargar(): void
    {
        $ops = $this->ciOpciones($this->ciEnt, $this->ciBloque)[$this->ciClave] ?? [];
        $ent = DB::table('entidades')->find($this->ciEnt);
        $guardado = DB::table('impuesto_comunicaciones')->where(['entidad_id' => $this->ciEnt, 'ejercicio' => $this->ejercicio, 'clave' => $this->ciClave])->first();
        $base = $guardado ?? DB::table('impuesto_comunicaciones')->where('entidad_id', $this->ciEnt)->orderByDesc('updated_at')->first();
        $this->ciIdioma = CI::idioma($base->idioma ?? $ent->idioma ?? 'ES');
        $pl = DB::table('plantillas_mail')->where('proceso', 'comunicar_impuestos')->where('idioma', $this->ciIdioma)->first();
        $this->ciPara = (string) ($base->para ?? implode(', ', \App\Http\Livewire\Contabilidad\ProcesosMensuales::destinatarios($ent->emailadm ?? '')));
        $this->ciCc = (string) ($base->cc ?? '');
        $this->ciAsunto = (string) (($base->asunto ?? '') ?: ($pl->asunto ?? ''));
        $this->ciTexto = (string) (($base->texto ?? '') ?: ($pl->texto ?? ''));
        $this->ciBanco = (string) ($base->banco ?? '');
        $previas = collect(json_decode((string) ($guardado->filas ?? '[]'), true))->keyBy(fn ($f) => $f['id'].'|'.$f['per']);
        $this->ciFilas = [];
        foreach ($ops as [$ob, $per]) {
            $anio = $this->ejercicio - (int) $ob->desfase;
            $prev = $previas[$ob->id.'|'.$per] ?? [];
            $this->ciFilas[] = [
                'id' => $ob->id, 'cod' => $ob->codigo, 'etq' => (string) $ob->etiqueta, 'per' => $per,
                'texto' => (CI::DESCR[$this->ciIdioma][$ob->codigo] ?? $ob->codigo).($ob->etiqueta !== '' ? ' ('.$ob->etiqueta.')' : ''),
                'imp' => (string) ($prev['imp'] ?? ''),
                'tipo' => (string) ($prev['tipo'] ?? 'pagar'),
                'fecha' => (string) (($prev['fecha'] ?? '') ?: (PlazosAeat::finPeriodo($ob->codigo, $anio, $per)?->format('d/m/Y') ?? '')),
            ];
        }
        $this->ciMsg = trim(($guardado?->enviado_at ? 'Ya se ENVIÓ el '.\Illuminate\Support\Carbon::parse($guardado->enviado_at)->format('d/m/Y H:i').'. ' : '')
            .($guardado?->borrador_at ? 'Borrador preparado el '.\Illuminate\Support\Carbon::parse($guardado->borrador_at)->format('d/m/Y H:i').'.' : ''));
        $this->ciArmado = '';
        $this->ciOk = false;
        // 303 vacío: se busca en sus documentos (PDF o libro de IVA)
        foreach ($this->ciFilas as $i => $f) {
            if ($f['cod'] === '303' && $f['imp'] === '') {
                $this->ciBuscar303($i, true);
            }
        }
    }

    public function ciBuscar303(int $i, bool $callado = false): void
    {
        $f = $this->ciFilas[$i] ?? null;
        if (! $f || $f['cod'] !== '303') {
            return;
        }
        $r = CI::buscar303($this->ciEnt, $this->ejercicio, $f['per']);
        if ($r) {
            $this->ciFilas[$i]['imp'] = CI::eur($r[0]);
            $this->ciFilas[$i]['tipo'] = $r[2];
            $this->ciMsg = '303 sacado de '.$r[1].'.';
        } elseif (! $callado) {
            $this->ciMsg = 'No encuentro el 303 de este periodo: no hay libro de IVA en la casilla (genéralo en Libros IVA) ni PDF presentado; escribe el importe.';
        }
    }

    /** Contexto del correo: [asunto, html] con lo que hay en pantalla. */
    protected function ciMontar(): array
    {
        $anio = $this->ejercicio - (int) (DB::table('impuesto_modelos')->where('codigo', $this->ciFilas[0]['cod'] ?? '')->value('desfase') ?? 0);
        $pers = array_column($this->ciFilas, 'per');
        $perPpal = collect($pers)->first(fn ($p) => ctype_digit($p)) ?? collect($pers)->first(fn ($p) => $p[0] === 'T') ?? ($pers[0] ?? 'A');
        $periodo = CI::periodo($perPpal, $anio, $this->ciIdioma);
        $mes = ctype_digit($this->ciClave) ? CI::MESES[$this->ciIdioma][(int) $this->ciClave - 1] : $periodo;
        $filas = [];
        foreach ($this->ciFilas as $f) {
            $v = CI::importe((string) $f['imp']);
            if ($v !== null) {
                $filas[] = [$f['texto'], $v, trim($f['fecha']), $f['tipo'] ?? 'pagar'];
            }
        }
        $titulo = ctype_digit($perPpal) ? (CI::MESES[$this->ciIdioma][(int) $perPpal - 1].' '.$anio) : $periodo;
        $rep = ['{periodo}' => $periodo, '{mes}' => $mes, '{nombre}' => auth()->user()->name ?? ''];
        $texto = strtr($this->ciTexto, $rep);

        return [strtr($this->ciAsunto, $rep), CI::html($texto, CI::tabla($filas, $titulo, $this->ciIdioma, trim($this->ciBanco))), count($filas)];
    }

    public function getCiVistaProperty(): string
    {
        if (! $this->ciEnt || ! $this->ciFilas) {
            return '';
        }
        [$asunto, $html] = $this->ciMontar();
        return '<div style="font-size:12px;color:#6b7280;margin-bottom:6px"><b>Asunto:</b> '.e($asunto).'</div>'.str_replace('cid:logo_suma', asset('img/logo_suma.gif'), $html);
    }

    protected function ciGuardar(bool $borrador, bool $enviado = false): void
    {
        DB::table('impuesto_comunicaciones')->updateOrInsert(
            ['entidad_id' => $this->ciEnt, 'ejercicio' => $this->ejercicio, 'clave' => $this->ciClave],
            ['filas' => json_encode(array_map(fn ($f) => array_intersect_key($f, array_flip(['id', 'cod', 'per', 'imp', 'tipo', 'fecha'])), $this->ciFilas)),
                'para' => mb_substr($this->ciPara, 0, 500), 'cc' => mb_substr($this->ciCc, 0, 500), 'idioma' => $this->ciIdioma, 'asunto' => mb_substr($this->ciAsunto, 0, 255),
                'texto' => $this->ciTexto, 'banco' => mb_substr($this->ciBanco, 0, 60), 'user_id' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]
            + ($borrador ? ['borrador_at' => now()] : []) + ($enviado ? ['enviado_at' => now()] : []));
    }

    public function ciGuardarSinBorrador(): void
    {
        $this->autorizarAviso((int) $this->ciEnt);
        $this->ciArmado = '';
        $this->ciGuardar(false);
        $this->ciMsg = 'Guardado (sin crear borrador).';
    }

    /** Deja el correo en Borradores de Outlook del usuario. NO se envía. */
    public function ciBorrador(): void
    {
        $this->autorizarAviso((int) $this->ciEnt);
        $this->ciArmado = '';
        $this->ciOk = false;
        if (! $l = $this->ciListo()) {
            return;
        }
        [$para, $cc, $asunto, $html, $n] = $l;
        try {
            GraphMail::crearBorradorHtml((string) auth()->user()->email, $para, $cc, $asunto, $html);
        } catch (\Throwable $e) {
            $this->ciMsg = 'No se ha podido crear el borrador: '.$e->getMessage();
            return;
        }
        $this->ciGuardar(true);
        $this->ciComentario('📝 Borrador «'.$asunto.'» preparado en Outlook ('.$n.' impuesto'.($n > 1 ? 's' : '').'), pendiente de enviar.');
        $this->ciOk = true;
        $this->ciMsg = 'Borrador listo en Outlook (carpeta Borradores de '.auth()->user()->email.'). Revísalo y envíalo desde allí; luego marca el sobre como «Comunicado».';
        $this->dispatch('notify', '✉ Borrador listo en Outlook');
        $this->dispatch('ci-hecho');
    }

    protected function autorizarAviso(int $entidadId): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $ver = DB::table('entidad_impuestos')->where('entidad_id', $entidadId)->pluck('id')->first(fn ($id) => Imp::puedeVer($id, $this->verTodos));
        abort_unless($ver, 403);
    }

    protected function ciComentario(string $texto): void
    {
        DB::table('impuesto_aviso_comentarios')->insert(['entidad_id' => $this->ciEnt, 'ejercicio' => $this->ejercicio, 'bloque' => $this->ciBloque,
            'texto' => $texto, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Comprueba lo que hay en pantalla; devuelve [para, cc, asunto, html, n] o null (con el motivo en ciMsg). */
    protected function ciListo(): ?array
    {
        $para = CI::direcciones($this->ciPara);
        $cc = CI::direcciones($this->ciCc);
        [$asunto, $html, $n] = $this->ciMontar();
        if (! $n) {
            $this->ciMsg = 'Pon al menos un importe: las filas sin importe no salen en el correo.';
            return null;
        }
        if (! $para) {
            $this->ciMsg = 'Falta el destinatario (Para).';
            return null;
        }
        if ($malas = array_filter(array_merge($para, $cc), fn ($a) => ! filter_var($a, FILTER_VALIDATE_EMAIL))) {
            $this->ciMsg = 'Dirección no válida: '.implode(', ', $malas);
            return null;
        }
        return [$para, $cc, $asunto, $html, $n];
    }

    /** Prueba: el mismo correo solo a quien lo prepara, con [PRUEBA] en el asunto. No cambia nada. */
    public function ciPrueba(): void
    {
        $this->autorizarAviso((int) $this->ciEnt);
        $this->ciArmado = '';
        $this->ciOk = false;
        if (! $l = $this->ciListo()) {
            return;
        }
        [$para, $cc, $asunto, $html] = $l;
        $yo = (string) auth()->user()->email;
        try {
            GraphMail::enviarHtml($yo, [$yo], [], '[PRUEBA] '.$asunto, '<p style="font-family:Calibri,Arial;font-size:9pt;color:#6b7280">Prueba. Iría a: '.e(implode(', ', $para)).($cc ? ' · CC: '.e(implode(', ', $cc)) : '').'</p>'.$html);
        } catch (\Throwable $e) {
            $this->ciMsg = 'No se ha podido enviar la prueba: '.$e->getMessage();
            return;
        }
        $this->ciOk = true;
        $this->ciMsg = 'Prueba enviada a '.$yo.'.';
        $this->dispatch('notify', '✉ Prueba enviada a '.$yo);
    }

    /** Envío REAL, paso 1: solo «arma» (guarda la huella de lo que se enviará). */
    public function ciArmar(): void
    {
        $this->autorizarAviso((int) $this->ciEnt);
        $this->ciOk = false;
        if (! $l = $this->ciListo()) {
            $this->ciArmado = '';
            return;
        }
        $this->ciArmado = md5(serialize($l));
        $this->ciMsg = '';
    }

    /** Envío REAL, paso 2: envía solo si se armó antes y no ha cambiado nada desde entonces. */
    public function ciEnviar(): void
    {
        $this->autorizarAviso((int) $this->ciEnt);
        $this->ciOk = false;
        $l = $this->ciListo();
        if (! $l || $this->ciArmado === '' || $this->ciArmado !== md5(serialize($l))) {
            $this->ciMsg = ! $l ? $this->ciMsg : ($this->ciArmado === '' ? 'Pulsa primero «Enviar al cliente…».' : 'Ha cambiado algo desde que pulsaste «Enviar al cliente…»: vuelve a pulsarlo.');
            $this->ciArmado = '';
            return;
        }
        $this->ciArmado = '';
        [$para, $cc, $asunto, $html, $n] = $l;
        $yo = (string) auth()->user()->email;
        try {
            GraphMail::enviarHtml($yo, $para, $cc, $asunto, $html);
        } catch (\Throwable $e) {
            $this->ciMsg = 'No se ha podido enviar: '.$e->getMessage();
            return;
        }
        $this->ciGuardar(false, true);
        // El sobre pasa a «Comunicado» (con la fecha de hoy si aún no la tenía)
        $clave = ['entidad_id' => $this->ciEnt, 'ejercicio' => $this->ejercicio, 'bloque' => $this->ciBloque];
        $fila = DB::table('impuesto_avisos')->where($clave)->first();
        $datos = ['estado' => 'comunicado', 'avisado_at' => $fila->avisado_at ?? now(), 'user_id' => auth()->id(), 'updated_at' => now()];
        $fila ? DB::table('impuesto_avisos')->where('id', $fila->id)->update($datos) : DB::table('impuesto_avisos')->insert($clave + $datos + ['created_at' => now()]);
        $this->ciComentario('✉ Enviado «'.$asunto.'» a '.implode(', ', $para).($cc ? ' (CC '.implode(', ', $cc).')' : '').' · '.$n.' impuesto'.($n > 1 ? 's' : '').'.');
        $this->ciOk = true;
        $this->ciMsg = 'Correo ENVIADO a '.implode(', ', $para).'. El sobre queda como «Comunicado».';
        $this->dispatch('notify', '✉ Correo enviado al cliente');
        $this->dispatch('ci-hecho');
    }

    public function render()
    {
        return view('livewire.impuesto-correo', ['nombreEnt' => DB::table('entidades')->where('id', $this->ciEnt)->value('entidad')]);
    }
}
