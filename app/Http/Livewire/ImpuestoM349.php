<?php

namespace App\Http\Livewire;

use App\Models\ImpuestoDocumento;
use App\Support\ColaTareas;
use App\Support\ComunicarImpuestos;
use App\Support\GraphMail;
use App\Support\Impuestos as Imp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Impuestos → M349 operaciones intracomunitarias (16-oct-2026). De momento solo para NAE Comunicacions (NIF en NIF_OPERATIVOS); con otro cliente
 * la pantalla dice «en construcción». Carpeta de trabajo (OneDrive, explorador de LibroIva con el árbol onedrive.arbol_iva que sube un PC; se recuerda
 * por empresa) y fichero a tratar: el Excel anual del cliente (pestañas de ventas y compras intracomunitarias), que se sube al servidor. El motor
 * (Contabilidad/Impuestos/M349/motor/prepara349.py; M349_DIR, en el VPS /var/www/m349) saca las operaciones del mes y deja el «<MM> <NIF>.349»
 * para importar en la AEAT; se descarga o se deja en la carpeta de trabajo (tarea pc.dejar_en_onedrive de un PC).
 * Borrador de la AEAT: lo sube el usuario o, al pulsar «Enviar a Marta», un PC lo trae de la carpeta de trabajo (pc.traer_de_onedrive); el correo
 * (Graph, desde el buzón del usuario, dos pasos) lo lleva adjunto, y el PDF queda como borrador en la casilla 349 de Seguimiento (estado «revisión»).
 * Datos: storage/app/m349/<entidad>/ (carpeta.txt, ajustes.json) y <AAAA>-<periodo>/entrada|salida|borrador. Guía: Contabilidad/Impuestos/M349/PLAN.md.
 */
class ImpuestoM349 extends Component
{
    use WithFileUploads;

    /** Clientes para los que el proceso ya funciona (NAE Comunicacions, S.L.). */
    public const NIF_OPERATIVOS = ['B63442701'];

    /** Persona y teléfono de contacto de la cabecera del 349 si el cliente no tiene otros guardados (los de los .349 de NAE de 2026). */
    public const CONTACTO_DEFECTO = ['contacto' => 'MARTA MARIA RUIZ SIERRA', 'telefono' => '659501389'];

    public ?int $entidadId = null;
    public int $ejercicio;
    public string $periodo = '';
    public bool $verTodos = false;
    public string $carpeta = '';
    public string $carpetaElegida = '';
    public $subida = null;
    public bool $esperandoCarpetas = false;
    public array $aj = ['contacto' => '', 'telefono' => ''];
    public ?array $res = null;
    public ?int $tareaGuardar = null;
    public $subidaBorrador = null;
    public ?int $tareaTraer = null;
    /** Paso 1 del envío hecho: se enseña el correo y el botón de confirmar (huella de lo que se va a enviar). */
    public string $mailArmado = '';

    public const TEXTO_MARTA = "Hola Marta,\n\nAdjunto el borrador del M349 de Nae de este mes.\n\nYa me respondes.";
    public string $error = '';
    public string $msg = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('impuestos.ver'), 403);
        $g = (array) session('m349', []);
        $this->verTodos = Imp::esGestor() && (bool) ($g['verTodos'] ?? false);
        $this->ejercicio = (int) ($g['ejercicio'] ?? now()->format('Y'));
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? 0]) ? (int) $g['entidad'] : null;
        $this->periodo = (string) ($g['periodo'] ?? '');
        $this->alCambiarEmpresa();
    }

    // ------------------------------------------------------------------ empresas y periodos

    /** Empresas con el 349 en Impuestos (las del usuario; Admin/Suma «todas»): [id => nombre]. */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->where('m.codigo', '349')->where('e.estado', 1);
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->orderBy('e.entidad')->get(['e.id', 'e.entidad', 'e.alias', 'e.nif', 'ei.periodicidad'])
            ->mapWithKeys(fn ($r) => [$r->id => ($r->alias ?: $r->entidad).' · '.($r->periodicidad === 'M' ? 'mensual' : 'trimestral').($r->nif ? ' · '.$r->nif : '')])->all();
    }

    public function operativo(): bool
    {
        if (! $this->entidadId) {
            return false;
        }
        $nif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) DB::table('entidades')->where('id', $this->entidadId)->value('nif')));

        return in_array(preg_replace('/^ES/', '', $nif), self::NIF_OPERATIVOS, true);
    }

    protected function periodicidad(): string
    {
        return (string) (DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '349')->value('ei.periodicidad') ?: 'T');
    }

    /** ['09' => 'septiembre'] o ['T3' => '3T']. */
    public function periodos(): array
    {
        return $this->periodicidad() === 'M'
            ? collect(range(1, 12))->mapWithKeys(fn ($m) => [sprintf('%02d', $m) => Imp::MESES[$m - 1]])->all()
            : ['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'];
    }

    protected function alCambiarEmpresa(): void
    {
        $this->error = $this->msg = '';
        $this->carpeta = '';
        if (! $this->entidadId) {
            return;
        }
        if (! isset($this->periodos()[$this->periodo])) {   // por defecto, el último periodo terminado
            $mes = (int) now()->format('n') - 1 ?: 12;
            $this->periodo = $this->periodicidad() === 'M' ? sprintf('%02d', $mes) : 'T'.(int) ceil($mes / 3);
        }
        $f = $this->dirEmpresa().'/carpeta.txt';
        $this->carpeta = is_file($f) ? trim((string) file_get_contents($f)) : '';
        $f = $this->dirEmpresa().'/ajustes.json';
        $g = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        $this->aj = array_merge(self::CONTACTO_DEFECTO, array_intersect_key($g, self::CONTACTO_DEFECTO));
        $this->cargar();
    }

    protected function cargar(): void
    {
        $this->res = null;
        $this->tareaGuardar = $this->tareaTraer = null;
        $this->mailArmado = '';
        if (! $this->operativo() || ! isset($this->periodos()[$this->periodo])) {
            return;
        }
        $f = $this->dirPeriodo('salida').'/resumen.json';
        $this->res = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
        if (! $this->res && $this->ficheroATratar()) {
            $this->generar();
        }
    }

    protected function recordar(): void
    {
        session(['m349' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
    }

    public function updatedEntidadId(): void
    {
        $this->entidadId = (int) $this->entidadId ?: null;
        $this->alCambiarEmpresa();
        $this->recordar();
    }

    public function updatedPeriodo(): void
    {
        $this->error = $this->msg = '';
        $this->recordar();
        $this->cargar();
    }

    public function cambiarEjercicio(int $d): void
    {
        $this->error = $this->msg = '';
        $this->ejercicio += $d;
        $this->recordar();
        $this->cargar();
    }

    public function updatedAj(): void
    {
        $this->autorizar();
        $this->aj = ['contacto' => mb_strtoupper(trim((string) $this->aj['contacto'])), 'telefono' => preg_replace('/\D/', '', (string) $this->aj['telefono'])];
        file_put_contents($this->dirEmpresa().'/ajustes.json', json_encode($this->aj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->generar();
    }

    public function updatedVerTodos(): void
    {
        $this->verTodos = Imp::esGestor() && $this->verTodos;
        if (! isset($this->empresas()[(int) $this->entidadId])) {
            $this->entidadId = null;
            $this->alCambiarEmpresa();
        }
        $this->recordar();
    }

    // ------------------------------------------------------------------ carpeta de trabajo (OneDrive)

    protected function autorizar(): void
    {
        abort_unless($this->entidadId && isset($this->empresas()[(int) $this->entidadId]) && $this->operativo(), 403);
    }

    protected function dirEmpresa(): string
    {
        $d = storage_path('app/m349/'.(int) $this->entidadId);
        @mkdir($d, 0775, true);

        return $d;
    }

    protected function dirPeriodo(string $sub = ''): string
    {
        $this->autorizar();
        abort_unless(isset($this->periodos()[$this->periodo]) && $this->ejercicio > 2000, 422);
        $d = $this->dirEmpresa().'/'.$this->ejercicio.'-'.$this->periodo.($sub !== '' ? '/'.$sub : '');
        @mkdir($d, 0775, true);

        return $d;
    }

    /** El mismo árbol de carpetas de OneDrive que el Libro de IVA (filtrado por lo que puede ver el usuario). */
    public function arbolCarpetas(): array
    {
        try {
            $a = ColaTareas::estado('onedrive.arbol_iva');
        } catch (\Throwable $e) {
            $a = null;
        }
        $ok = fn ($d) => LibroIva::rutaPermitida((string) $d);

        return ['dirs' => array_values(array_filter((array) ($a['dirs'] ?? []), $ok)), 'marcas' => [], 'fecha' => (string) ($a['fecha'] ?? '')];
    }

    public function updatedCarpetaElegida(string $ruta): void
    {
        $this->autorizar();
        if ($ruta === '' || ! in_array($ruta, $this->arbolCarpetas()['dirs'], true) || ! ColaTareas::rutaRelativaSegura($ruta)) {
            return;
        }
        $this->carpeta = $ruta;
        $this->carpetaElegida = '';
        file_put_contents($this->dirEmpresa().'/carpeta.txt', $ruta);
        $this->msg = 'Carpeta de trabajo guardada para esta empresa.';
    }

    public function actualizarCarpetas(): void
    {
        $this->error = '';
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if (! $vivos) {
            $this->error = 'No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
            return;
        }
        if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            ColaTareas::crear('pc.arbol_carpetas', ['modo' => 'iva'], ColaTareas::pcElegido() ?: null, auth()->id());
        }
        $this->esperandoCarpetas = true;
    }

    public function revisar(): void
    {
        if ($this->esperandoCarpetas && ! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->esperandoCarpetas = false;
        }
        if ($this->tareaTraer) {
            $t = DB::table('tareas')->find($this->tareaTraer);
            if ($t && ! in_array($t->estado, ['preparando', 'pendiente', 'en_curso'], true)) {
                $this->tareaTraer = null;
                $r = json_decode($t->resultado ?? 'null', true) ?: [];
                $src = ColaTareas::carpetaFicheros($t->id).'/'.basename((string) ($r['nombre'] ?? ''));
                if ($t->estado === 'ok' && ! empty($r['nombre']) && is_file($src)) {
                    $this->guardarBorrador($src, (string) $r['nombre'], (int) ($r['mtime'] ?? time()), 'onedrive');
                    $this->mailArmado = $this->huellaMail();
                } elseif ($t->estado === 'ok') {
                    $pdfs = array_map(fn ($c) => $c['nombre'], array_slice((array) ($r['candidatos'] ?? []), 0, 6));
                    $this->error = 'No encuentro el borrador en la carpeta de trabajo (busco un PDF con «borrador» y «'.$this->periodo.'» en el nombre, o uno descargado en los últimos 2 días).'
                        .($pdfs ? ' PDF que hay: '.implode(' · ', $pdfs).'.' : '').' Puedes subirlo tú en el paso 5.';
                } else {
                    $this->error = 'No se ha podido traer el borrador: '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -300)) ?: 'error en el PC');
                }
            }
        }
        if ($this->tareaGuardar) {
            $t = DB::table('tareas')->find($this->tareaGuardar);
            if ($t && ! in_array($t->estado, ['preparando', 'pendiente', 'en_curso'], true)) {
                $this->tareaGuardar = null;
                $r = json_decode($t->resultado ?? 'null', true) ?: [];
                if ($t->estado === 'ok') {
                    $this->msg = 'Guardado en OneDrive\\'.str_replace('/', '\\', (string) ($r['ruta'] ?? '')).' (desde '.($r['pc'] ?? 'un PC').')'
                        .(! empty($r['apartado']) ? '. El que había se ha renombrado a «'.$r['apartado'].'».' : '.');
                } else {
                    $this->error = 'No se ha podido guardar en OneDrive: '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -300)) ?: 'error en el PC');
                }
            }
        }
    }

    // ------------------------------------------------------------------ fichero a tratar

    /**
     * Fichero que se trata en este periodo: el subido en el mes o, si no hay, el último subido en otro mes del mismo ejercicio
     * (el Excel trae el año entero). [ruta, nombre, propio (subido en este mes), periodo] o null.
     */
    public function ficheroATratar(): ?array
    {
        if (! $this->operativo() || ! isset($this->periodos()[$this->periodo])) {
            return null;
        }
        $propio = glob($this->dirPeriodo('entrada').'/*') ?: [];
        if ($propio) {
            return ['ruta' => $propio[0], 'nombre' => basename($propio[0]), 'propio' => true, 'periodo' => $this->periodo];
        }
        $f = collect(glob($this->dirEmpresa().'/'.$this->ejercicio.'-*/entrada/*') ?: [])->sortByDesc(fn ($f) => filemtime($f))->first();

        return $f ? ['ruta' => $f, 'nombre' => basename($f), 'propio' => false, 'periodo' => substr(basename(dirname($f, 2)), 5)] : null;
    }

    public function ficheros(): array
    {
        if (! $this->operativo() || ! isset($this->periodos()[$this->periodo])) {
            return [];
        }
        $out = [];
        foreach (glob($this->dirPeriodo('entrada').'/*') ?: [] as $f) {
            $out[] = ['nombre' => basename($f), 'tam' => filesize($f), 'fecha' => filemtime($f)];
        }

        return $out;
    }

    public function updatedSubida(): void
    {
        $this->error = $this->msg = '';
        if (! $this->subida instanceof UploadedFile) {
            return;
        }
        $ext = strtolower($this->subida->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'pdf'], true)) {
            $this->error = '«'.$this->subida->getClientOriginalName().'»: solo Excel, CSV, txt o PDF.';
            $this->subida = null;
            return;
        }
        $dir = $this->dirPeriodo('entrada');
        foreach (glob($dir.'/*') ?: [] as $viejo) {   // un fichero a tratar por periodo: el nuevo sustituye al anterior
            @unlink($viejo);
        }
        $nombre = preg_replace('/[^A-Za-z0-9._ &()-]+/', '_', basename($this->subida->getClientOriginalName()));
        copy($this->subida->getRealPath(), $dir.'/'.$nombre);
        $this->subida = null;
        $this->generar();
    }

    public function quitar(string $nombre): void
    {
        @unlink($this->dirPeriodo('entrada').'/'.basename($nombre));
        $this->msg = '';
        $this->generar();
    }

    // ------------------------------------------------------------------ generar el .349

    public function generar(): void
    {
        $this->error = '';
        $salida = $this->dirPeriodo('salida');
        foreach (glob($salida.'/*') ?: [] as $viejo) {
            @unlink($viejo);
        }
        $this->res = null;
        $fic = $this->ficheroATratar();
        if (! $fic) {
            return;
        }
        if (! in_array(strtolower(pathinfo($fic['nombre'], PATHINFO_EXTENSION)), ['xlsx', 'xlsm'], true)) {
            $this->error = 'De momento el 349 se prepara desde el Excel (.xlsx) con las pestañas de ventas y compras intracomunitarias.';
            return;
        }
        $ent = DB::table('entidades')->find($this->entidadId);
        $spec = ['xlsx' => $fic['ruta'], 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'nif' => (string) $ent->nif,
            'nombre' => (string) $ent->entidad, 'contacto' => $this->aj['contacto'], 'telefono' => $this->aj['telefono'], 'salida' => $salida];
        $specF = $this->dirPeriodo().'/spec.json';
        file_put_contents($specF, json_encode($spec, JSON_UNESCAPED_UNICODE));
        $motor = rtrim((string) config('contabilidad.m349_dir'), '/').'/prepara349.py';
        try {
            $r = Process::timeout(120)->env(['PYTHONIOENCODING' => 'utf-8'])->run([\App\Support\IntrastatMotor::python(), '-I', $motor, $specF]);
        } catch (\Throwable $e) {
            $this->error = 'No se ha podido ejecutar el motor del 349: '.$e->getMessage();
            return;
        }
        if (! $r->successful()) {
            $this->error = 'El motor del 349 ha fallado: '.mb_substr(trim($r->errorOutput() ?: $r->output()), -600);
            return;
        }
        $f = $salida.'/resumen.json';
        $this->res = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    public function descargar()
    {
        $nombre = (string) ($this->res['fichero'] ?? '');
        $ruta = $this->dirPeriodo('salida').'/'.basename($nombre);
        abort_unless($nombre !== '' && is_file($ruta), 404);

        return response()->download($ruta, $nombre, ['Content-Type' => 'text/plain; charset=ISO-8859-1']);
    }

    /** Un PC trabajador deja el .349 en la carpeta de trabajo de OneDrive. */
    public function guardarEnCarpeta(): void
    {
        $this->error = $this->msg = '';
        $nombre = (string) ($this->res['fichero'] ?? '');
        $ruta = $this->dirPeriodo('salida').'/'.basename($nombre);
        if ($nombre === '' || ! is_file($ruta)) {
            $this->error = 'No hay fichero .349 que guardar.';
            return;
        }
        if ($this->carpeta === '' || ! ColaTareas::rutaRelativaSegura($this->carpeta)) {
            $this->error = 'Elige primero la carpeta de trabajo.';
            return;
        }
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if (! $vivos) {
            $this->error = 'No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
            return;
        }
        $id = ColaTareas::crear('pc.dejar_en_onedrive', ['carpeta' => $this->carpeta, 'entrada' => ['archivo' => 'fichero.349', 'nombre' => $nombre]],
            ColaTareas::pcElegido() ?: null, auth()->id(), null, null, 'preparando');
        $dir = ColaTareas::carpetaEntradas($id);
        @mkdir($dir, 0775, true);
        copy($ruta, $dir.'/fichero.349');
        ColaTareas::liberar($id);
        $this->tareaGuardar = $id;
    }

    /** Nombre y NIF del cliente tal como van al 349 (para copiarlos en la Sede). */
    public function declarante(): ?array
    {
        $e = $this->entidadId ? DB::table('entidades')->find($this->entidadId) : null;
        if (! $e) {
            return null;
        }
        $nif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $e->nif));

        return ['nombre' => mb_strtoupper((string) $e->entidad), 'nif' => preg_replace('/^ES(?=[A-Z0-9]{9}$)/', '', $nif)];
    }

    // ------------------------------------------------------------------ borrador de la AEAT y correo a Marta

    /** Borrador del periodo guardado aquí: ['ruta', 'nombre', 'fecha', 'origen'] o null. */
    public function borrador(): ?array
    {
        if (! $this->operativo() || ! isset($this->periodos()[$this->periodo])) {
            return null;
        }
        $f = $this->dirPeriodo('borrador').'/info.json';
        $i = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;

        return $i && is_file($this->dirPeriodo('borrador').'/borrador.pdf') ? $i + ['ruta' => $this->dirPeriodo('borrador').'/borrador.pdf'] : null;
    }

    protected function guardarBorrador(string $origen, string $nombre, int $fecha, string $de): void
    {
        $dir = $this->dirPeriodo('borrador');
        copy($origen, $dir.'/borrador.pdf');
        file_put_contents($dir.'/info.json', json_encode(['nombre' => $nombre, 'fecha' => $fecha, 'origen' => $de], JSON_UNESCAPED_UNICODE));
    }

    public function updatedSubidaBorrador(): void
    {
        $this->error = $this->msg = '';
        $this->mailArmado = '';
        if (! $this->subidaBorrador instanceof UploadedFile) {
            return;
        }
        if (strtolower($this->subidaBorrador->getClientOriginalExtension()) !== 'pdf') {
            $this->error = 'El borrador tiene que ser el PDF que da la AEAT.';
            $this->subidaBorrador = null;
            return;
        }
        $this->guardarBorrador($this->subidaBorrador->getRealPath(), basename($this->subidaBorrador->getClientOriginalName()), time(), 'web');
        $this->subidaBorrador = null;
    }

    public function quitarBorrador(): void
    {
        foreach (glob($this->dirPeriodo('borrador').'/*') ?: [] as $f) {
            @unlink($f);
        }
        $this->mailArmado = '';
    }

    public function asuntoMail(): string
    {
        return 'Borrador M349 Nae '.(Imp::MESES[(int) $this->periodo - 1] ?? $this->periodo).' '.$this->ejercicio;
    }

    protected function huellaMail(): string
    {
        $b = $this->borrador();

        return $b ? md5($this->entidadId.'|'.$this->ejercicio.'|'.$this->periodo.'|'.hash_file('sha256', $b['ruta'])) : '';
    }

    /** Paso 1: con borrador subido, enseña el correo; si no, un PC lo trae de la carpeta de trabajo y después lo enseña. */
    public function prepararMail(): void
    {
        $this->autorizar();
        $this->error = $this->msg = '';
        if ($this->borrador()) {
            $this->mailArmado = $this->huellaMail();
            return;
        }
        if ($this->carpeta === '' || ! ColaTareas::rutaRelativaSegura($this->carpeta)) {
            $this->error = 'No hay borrador: súbelo en el paso 5 o elige la carpeta de trabajo donde lo has descargado.';
            return;
        }
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if (! $vivos) {
            $this->error = 'No hay ningún PC trabajador conectado para traer el borrador de OneDrive: súbelo tú en el paso 5.';
            return;
        }
        $this->tareaTraer = ColaTareas::crear('pc.traer_de_onedrive', ['carpeta' => $this->carpeta, 'ext' => 'pdf', 'contiene' => ['borrador'], 'periodo' => $this->periodo, 'dias' => 2],
            ColaTareas::pcElegido() ?: null, auth()->id());
    }

    public function cancelarMail(): void
    {
        $this->mailArmado = '';
        $this->tareaTraer = null;
    }

    /** Paso 2: envía (solo si se preparó y no ha cambiado el borrador) y deja el borrador en la casilla 349 de Seguimiento. */
    public function enviarMail(): void
    {
        $this->autorizar();
        $this->error = $this->msg = '';
        $b = $this->borrador();
        if (! $b || $this->mailArmado === '' || $this->mailArmado !== $this->huellaMail()) {
            $this->error = 'Ha cambiado el borrador desde que pulsaste «Enviar a Marta…»: vuelve a pulsarlo.';
            $this->mailArmado = '';
            return;
        }
        $this->mailArmado = '';
        $de = (string) auth()->user()->email;
        $nombreAdj = preg_match('/\.pdf$/i', $b['nombre']) ? $b['nombre'] : 'borrador 349 '.$this->periodo.'.pdf';
        try {
            GraphMail::enviarHtml($de, [ComunicarImpuestos::CC_DEFECTO], [], $this->asuntoMail(), GraphMail::html(self::TEXTO_MARTA), [['nombre' => $nombreAdj, 'ruta' => $b['ruta']]]);
        } catch (\Throwable $e) {
            $this->error = 'No se ha podido enviar el correo: '.$e->getMessage();
            return;
        }
        $extra = $this->registrarEnSeguimiento($b, $nombreAdj);
        $this->msg = 'Correo enviado a '.ComunicarImpuestos::CC_DEFECTO.' con «'.$nombreAdj.'».'.$extra;
        $this->dispatch('notify', '✉ Borrador enviado a Marta');
    }

    /** El borrador va a la casilla 349 del periodo (tipo borrador; sustituye al anterior de este proceso) y la casilla pasa a «revisión». */
    protected function registrarEnSeguimiento(array $b, string $nombre): string
    {
        $ob = DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', $this->entidadId)->where('m.codigo', '349')->first(['ei.id', 'ei.etiqueta']);
        if (! $ob) {
            return '';
        }
        $sha = hash_file('sha256', $b['ruta']);
        $almacen = 'impuestos/docs/'.$sha.'.pdf';
        Storage::disk('local')->put($almacen, fopen($b['ruta'], 'rb'));
        ImpuestoDocumento::where(['entidad_id' => $this->entidadId, 'modelo' => '349', 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'origen' => 'm349'])
            ->whereNull('quitado_at')->update(['quitado_at' => now()]);
        ImpuestoDocumento::create(['entidad_id' => $this->entidadId, 'modelo' => '349', 'etiqueta' => (string) $ob->etiqueta, 'ejercicio' => $this->ejercicio,
            'periodo' => $this->periodo, 'tipo' => 'borrador', 'nombre' => $nombre, 'almacen' => $almacen, 'tam' => filesize($b['ruta']),
            'sha256' => $sha, 'origen' => 'm349', 'user_id' => auth()->id()]);
        $clave = ['entidad_impuesto_id' => $ob->id, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo];
        $fila = DB::table('impuesto_estados')->where($clave)->first();
        $cambio = '';
        if (! $fila || in_array($fila->estado, ['no', 'pendiente'], true)) {
            $fila ? DB::table('impuesto_estados')->where('id', $fila->id)->update(['estado' => 'revision', 'user_id' => auth()->id(), 'updated_at' => now()])
                  : DB::table('impuesto_estados')->insert($clave + ['estado' => 'revision', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $cambio = ' La casilla 349 de '.$this->periodo.' pasa a «Listo para revisión».';
        }
        DB::table('impuesto_comentarios')->insert($clave + ['texto' => '✉ Borrador enviado a Marta («'.$nombre.'»).', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);

        return ' Queda en Seguimiento de impuestos (casilla 349 de '.$this->periodo.').'.$cambio;
    }

    public function render()
    {
        return view('livewire.impuesto-m349', ['empresas' => $this->empresas(), 'periodosLista' => $this->entidadId ? $this->periodos() : [],
            'esOperativo' => $this->operativo(), 'ficherosLista' => $this->ficheros(), 'aTratar' => $this->ficheroATratar(), 'decl' => $this->declarante(), 'borradorInfo' => $this->operativo() ? $this->borrador() : null, 'arbol' => $this->operativo() ? $this->arbolCarpetas() : ['dirs' => [], 'marcas' => [], 'fecha' => '']]);
    }
}
