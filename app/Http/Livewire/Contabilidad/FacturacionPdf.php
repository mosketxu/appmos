<?php

namespace App\Http\Livewire\Contabilidad;

use App\Http\Livewire\Concerns\EjecutaEnPcs;
use App\Models\Entidad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pantalla para lanzar, desde Appmos, FacturacionPDFyMail/procesar_facturas.py
 * (parte PDF+correo de Suma y Balerga). Suma y Balerga son procesos
 * independientes: cada uno se procesa y se envía por separado, nunca juntos.
 *
 * Pedido explícito del usuario (2026-09-17): en vez de depender de lo que
 * haya quedado en la carpeta <Cliente>\Entrada (no se sabe a simple vista
 * qué archivo se está procesando), el archivo se SUBE desde el navegador. Y
 * en vez de un único "procesar y enviar", son DOS fases distintas y
 * explícitas: 1) Separar PDFs (sin tocar el correo en absoluto) y 2) Enviar
 * correos (acción aparte, solo disponible tras la fase 1).
 *
 * El propio procesar_facturas.py ARCHIVA (mueve) el fichero de entrada al
 * terminar -- incluso en modo --no-mail -- así que cada fase usa una copia
 * fresca del PDF subido dentro de <Cliente>\Entrada; el original subido se
 * guarda aparte (disco 'local' privado de Appmos) para poder repetirlo en la
 * Fase 2 sin tener que volver a subirlo.
 */
class FacturacionPdf extends Component
{
    use EjecutaEnPcs;
    use WithFileUploads;

    // Web (VPS): Suma/Balerga los hace un PC trabajador con su OneDrive (cola de tareas, trait EjecutaEnPcs, 3-oct-2026).
    protected string $grupoPc = 'facturacion';

    protected bool $ultimoOk = false;

    /** Esta pantalla no guarda estado de PC: no hay nada que recargar cuando llega. */
    protected function recargarEstado(): void
    {
    }

    /** Fichero subido por cliente, mientras está en el formulario (antes de separarPdf). */
    public array $archivo = [];

    /**
     * Estado por cliente: ['fase' => 'vacio'|'separado'|'enviado',
     * 'nombreOriginal' => ..., 'rutaMaster' => ruta absoluta de la copia
     * privada guardada en Appmos].
     */
    public array $estado = [];

    public string $salida = '';

    /**
     * Salida de la última ejecución de cada cliente (Suma/Balerga), que se
     * enseña a la derecha de su tarjeta nada más ejecutar (2026-09-29). La
     * caja "Salida" del final sigue teniendo el historial completo.
     */
    public array $salidaCliente = [];

    /**
     * Carpeta de resultados de la última ejecución de cada cliente, para
     * enseñar el enlace igual que en Procesos FIQ (ver
     * Contabilidad\Procesos::$resultados). Forma:
     * ['Suma' => [['ruta' => 'E:\\...\\2026-09', 'url' => 'file:///E:/...'], ...]].
     */
    public array $resultados = [];

    /** Destinatarios cargados por cliente (ver cargarDestinatarios). */
    public array $destinatarios = [];

    /** Cambios de destinatarios aún sin guardar en el Excel: [cliente => [fila => ['cliente', 'mail'?, 'enviar'?, 'orig']]]. */
    public array $cambios = [];

    /** Solo los clientes con factura en el último lote separado: [cliente => bool]. */
    public array $soloLote = [];

    /** Enseñar también los clientes con Estado (baja, inactivo, liquidada...): [cliente => bool]. */
    public array $verInactivas = [];

    /** Filtro de la lista de destinatarios por cliente: 'todos' | 'si' | 'no'. */
    public array $filtroEnviar = [];

    /**
     * Separador genérico (separar_generico.py): PDF de facturas de cualquier
     * proveedor, normalmente escaneado/fotografiado (OCR). Sin correo y sin
     * OneDrive, así que también funciona en el VPS (ver
     * config/contabilidad.php 'generico_*'). Fases: 'vacio' -> 'analizado'
     * (tabla editable página -> número/proveedor) -> 'generado' (.zip
     * descargable).
     *
     * 2026-09-29: admite VARIOS ficheros (PDF o imágenes, que se pasan a PDF):
     * sirve tanto para partir un PDF con muchas facturas como para renombrar
     * un montón de facturas sueltas (proveedor + número delante del nombre
     * original). El navegador los sube de uno en uno a $nuevoArchivoGenerico
     * (así no hay tope de tamaño total por petición) y cada uno se guarda al
     * momento en $archivosGenerico.
     */
    public $nuevoArchivoGenerico = null;

    /** Ficheros ya subidos del lote: [['nombre' => original, 'ruta' => absoluta], ...]. */
    #[Locked]
    public array $archivosGenerico = [];

    /** Poner el nombre del archivo original detrás del nuevo nombre (por defecto sí con varios ficheros). */
    public bool $genericoNombreOriginal = false;

    /** Proceso que se ve en pantalla: 'Suma' | 'Balerga' | 'Generico' (botones junto al título). */
    public string $proceso = 'Suma';

    /** Rutas del PDF subido y del .zip: bloqueado para que el navegador no pueda cambiarlas. */
    #[Locked]
    public array $generico = [];

    /**
     * Genérico: a quién van las facturas ("Nombre (NIF)" de Entidades, o
     * texto libre). Se pasa al script para que nunca lo proponga como
     * proveedor. Se recuerda en la sesión.
     */
    public string $genericoCliente = '';

    /** De dónde ha salido el cliente propuesto solo ("la carpeta «Sunbelt 2026»"); '' si lo ha puesto Alex. */
    public string $genericoClienteOrigen = '';

    /** Tabla editable: una fila por página [pagina, numero, proveedor, tipo Fra|Abo|Pre|Prof, giro 0|90|180|270]. */
    public array $genericoPaginas = [];

    /**
     * 2026-09-20: misma unidad-de-disco variable que en Contabilidad\Procesos
     * (`/mnt/e/Claude` en un PC, `/mnt/f/Claude` en otro). 2026-09-28: el
     * proyecto se movió a Claude/Contabilidad/.
     */
    protected function scriptDir(): string
    {
        foreach (['/mnt/e/Claude/Contabilidad/FacturacionPDFyMail', '/mnt/f/Claude/Contabilidad/FacturacionPDFyMail'] as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return '/mnt/e/Claude/Contabilidad/FacturacionPDFyMail';
    }

    /**
     * El python3 de sistema de esta máquina no trae pip ni paquetes de
     * terceros (openpyxl, pypdf, pdfplumber, pymupdf...), así que el
     * proyecto tiene su propio venv (`.venv`, con `pip install -r
     * requirements.txt` ya hecho, no versionado). Si por lo que sea no
     * existe (otra máquina con Python del sistema completo), se cae al
     * python3 del PATH.
     */
    protected function pythonBin(): string
    {
        $venvPython = $this->scriptDir().'/.venv/bin/python3';
        return is_file($venvPython) ? $venvPython : 'python3';
    }

    protected function clientes(): array
    {
        return [
            'Suma' => [
                'label' => 'Suma',
                'ayuda' => 'Factura de renta mensual, un PDF y un correo por empresa inquilina.',
            ],
            'Balerga' => [
                'label' => 'Balerga',
                'ayuda' => 'Factura de renta de plaza, un PDF y un correo por inquilino.',
            ],
        ];
    }

    public function getClientesProperty(): array
    {
        return $this->clientes();
    }

    public function mount(): void
    {
        foreach (array_keys($this->clientes()) as $id) {
            $this->estado[$id] = $this->estadoGuardado($id) ?? ['fase' => 'vacio', 'nombreOriginal' => null, 'rutaMaster' => null];
            $this->filtroEnviar[$id] = 'todos';
        }
        $this->generico = $this->genericoVacio();
        $this->genericoCliente = (string) session('facturacion-pdf.generico-cliente', '');
        $proceso = (string) session('facturacion-pdf.proceso', 'Suma');
        $this->proceso = in_array($proceso, ['Suma', 'Balerga', 'Generico'], true) ? $proceso : 'Suma';
        $this->retomarTareas();
    }

    /**
     * La fase de cada cliente (separado / enviado) se recuerda entre recargas de la página: sin esto, al refrescar
     * desaparecía el botón de la Fase 2 aunque la Fase 1 ya estuviera hecha. Solo se restaura si el PDF subido sigue en Appmos.
     */
    protected function estadoGuardado(string $cliente): ?array
    {
        $e = \Illuminate\Support\Facades\Cache::get("facturacion-pdf.estado.{$cliente}");
        return is_array($e) && ! empty($e['rutaMaster']) && is_file($e['rutaMaster']) ? $e : null;
    }

    protected function guardarEstado(string $cliente): void
    {
        $e = $this->estado[$cliente] ?? null;
        if ($e && ($e['fase'] ?? 'vacio') !== 'vacio') {
            \Illuminate\Support\Facades\Cache::put("facturacion-pdf.estado.{$cliente}", $e, now()->addDays(14));
        } else {
            \Illuminate\Support\Facades\Cache::forget("facturacion-pdf.estado.{$cliente}");
        }
    }

    public function updatedProceso(string $valor): void
    {
        if (! in_array($valor, ['Suma', 'Balerga', 'Generico'], true)) {
            $this->proceso = 'Suma';
        }
        session(['facturacion-pdf.proceso' => $this->proceso]);
    }

    /** Opciones del combo de cliente del Genérico: "Nombre (NIF)" => [nombre, nif], solo entidades permitidas y no de baja. */
    public function getEntidadesClienteProperty(): array
    {
        $out = [];
        foreach (Entidad::where('estado', '!=', 0)->orderBy('entidad')->get(['entidad', 'nif']) as $e) {
            $nombre = trim((string) $e->entidad);
            $nif = trim((string) $e->nif);
            if ($nombre !== '') {
                $out[$nif !== '' ? "{$nombre} ({$nif})" : $nombre] = [$nombre, $nif];
            }
        }
        return $out;
    }

    /**
     * Genérico: propone el cliente por el nombre de la carpeta ("Sunbelt 2026" -> SUNBELT IBERICA...) o, si no
     * hay carpeta o no casa, por el de los ficheros ("Factura 24-000054 SUNBELT IBÉRICA ...pdf": la entidad que
     * sale en la mitad o más). Solo si hay una única candidata; siempre se puede cambiar a mano.
     */
    public function proponerClienteGenerico(string $carpeta, array $ficheros = []): void
    {
        $norm = function (string $s): string {
            $s = strtoupper(Str::ascii(str_replace(['´', "'", '’', '`'], '', $s)));
            $s = preg_replace('/\([^)]*\)|\.PDF$|[^A-Z0-9 ]/', ' ', $s);
            $s = preg_replace('/\b(20\d\d|S ?L ?U?|S ?A ?U?|SLNE|SL|SA|SLU|CLIENTES?|FACTURAS?|FRA|ABO)\b/', ' ', $s);
            return trim(preg_replace('/\s+/', ' ', $s));
        };
        $entidades = [];
        foreach ($this->entidadesCliente as $etiqueta => [$nombre]) {
            if (strlen($n = $norm($nombre)) >= 4) {
                $entidades[$etiqueta] = explode(' ', $n);
            }
        }
        // Palabras iniciales en común ("INVESTMENT" ~ "INVESTMENTS"); la primera, de 4+ letras y, si va sola, no genérica
        $igual = fn (string $x, string $y) => $x === $y
            || (min(strlen($x), strlen($y)) >= 5 && (str_starts_with($x, $y) || str_starts_with($y, $x)));
        $comunes = function (array $a, array $b) use ($igual): int {
            $k = 0;
            while (isset($a[$k], $b[$k]) && $igual($a[$k], $b[$k])) {
                $k++;
            }
            return ($k > 0 && strlen($a[0]) >= 4 && ($k > 1 || ! in_array($a[0], ['GRUPO', 'NUEVA', 'NUEVO', 'CARPETA', 'DOCUMENTOS'], true))) ? $k : 0;
        };
        $elegir = function (array $puntos): ?string {
            arsort($puntos);
            $puntos = array_filter($puntos);
            $top = array_slice($puntos, 0, 2, true);
            if (! $top || (count($top) === 2 && reset($top) === end($top))) {
                return null;   // ninguna o empate: mejor no proponer
            }
            return array_key_first($top);
        };

        $carpetaN = $norm($carpeta);
        if ($carpetaN !== '') {
            // Todas las palabras de la carpeta (o de la entidad) tienen que casar: "Xavier Ramis" no es "Xavier Martin"
            $palabras = explode(' ', $carpetaN);
            $puntos = array_map(function ($e) use ($comunes, $palabras) {
                $k = $comunes($palabras, $e);
                return ($k === count($palabras) || $k === count($e)) ? $k : 0;
            }, $entidades);
            if ($etiqueta = $elegir($puntos)) {
                $this->genericoCliente = $etiqueta;
                $this->genericoClienteOrigen = "la carpeta «{$carpeta}»";
                return;
            }
        }
        if ($ficheros) {
            $puntos = [];
            foreach ($entidades as $etiqueta => $palabras) {
                $clave = ' '.implode(' ', array_slice($palabras, 0, 2)).' ';
                if (strlen(trim($clave)) < 5) {
                    continue;
                }
                $veces = count(array_filter($ficheros, fn ($f) => str_contains(' '.$norm((string) $f).' ', $clave)));
                $puntos[$etiqueta] = $veces * 2 >= count($ficheros) ? $veces : 0;
            }
            if ($etiqueta = $elegir($puntos)) {
                $this->genericoCliente = $etiqueta;
                $this->genericoClienteOrigen = 'el nombre de los ficheros';
            }
        }
    }

    public function updatedGenericoCliente(): void
    {
        $this->genericoClienteOrigen = '';
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    // -- Fase 1: separar PDFs -------------------------------------------

    public function separarPdf(string $cliente): void
    {
        if (! isset($this->clientes()[$cliente])) {
            return;
        }
        $this->validate([
            "archivo.{$cliente}" => 'required|file|mimes:pdf|max:51200',
        ]);

        /** @var UploadedFile $subido */
        $subido = $this->archivo[$cliente];
        $nombreOriginal = $subido->getClientOriginalName();

        // Copia privada propia de Appmos (disco 'local', no público): sobrevive
        // a que procesar_facturas.py archive/mueva la copia de trabajo, para
        // poder repetir la Fase 2 sin volver a pedir el archivo.
        $rutaRelativa = $subido->storeAs("facturacion-pdf/{$cliente}", Str::uuid().'.pdf', 'local');
        $rutaMasterAbs = Storage::disk('local')->path($rutaRelativa);

        $this->estado[$cliente] = [
            'fase' => 'vacio',
            'nombreOriginal' => $nombreOriginal,
            'rutaMaster' => $rutaMasterAbs,
        ];
        $this->archivo[$cliente] = null;

        $ok = $this->ejecutarConCopiaFresca($cliente, enviar: false, etiquetaSufijo: 'Fase 1 · Separar PDFs (vista previa, sin correo)');

        if ($ok) {
            $this->estado[$cliente]['fase'] = 'separado';
        }
        $this->guardarEstado($cliente);
    }

    // -- Fase 2: enviar correos ------------------------------------------

    public function enviarCorreos(string $cliente): void
    {
        if (($this->estado[$cliente]['fase'] ?? '') !== 'separado') {
            return;
        }
        if (! empty($this->cambios[$cliente])) {
            $this->salida .= "\n\n⚠️ {$cliente}: hay cambios de destinatarios sin guardar en el TODO. Pulsa «Guardar en TODO» (o descártalos) antes de enviar: el envío lee el Excel.";
            return;
        }
        $ok = $this->ejecutarConCopiaFresca($cliente, enviar: true, etiquetaSufijo: 'Fase 2 · Enviar correos (REAL)');
        if ($ok) {
            $this->estado[$cliente]['fase'] = 'enviado';
        }
        $this->guardarEstado($cliente);
    }

    public function empezarDeNuevo(string $cliente): void
    {
        $ruta = $this->estado[$cliente]['rutaMaster'] ?? null;
        if ($ruta && is_file($ruta)) {
            @unlink($ruta);
        }
        $this->estado[$cliente] = ['fase' => 'vacio', 'nombreOriginal' => null, 'rutaMaster' => null];
        $this->archivo[$cliente] = null;
        $this->resultados[$cliente] = [];
        $this->salidaCliente[$cliente] = '';
        $this->guardarEstado($cliente);
    }

    /**
     * Copia la master privada a <Cliente>\Entrada con nombre nuevo (para que
     * procesar_facturas.py la archive sin tocar la master) y lanza el script
     * con esa copia como --input.
     */
    protected function ejecutarConCopiaFresca(string $cliente, bool $enviar, string $etiquetaSufijo): bool
    {
        $rutaMasterAbs = $this->estado[$cliente]['rutaMaster'] ?? null;
        if (! $rutaMasterAbs || ! is_file($rutaMasterAbs)) {
            $this->salida .= "\n\n⚠️ No encuentro el PDF subido para {$cliente}. Vuelve a subirlo.";
            return false;
        }

        $etiqueta = "Facturación PDF · {$cliente} ({$etiquetaSufijo})";
        if ($this->remoto()) {
            // El PDF viaja como entrada de la tarea: el PC lo deja en <Cliente>\Entrada con nombre nuevo (el script lo archiva)
            $this->resultados[$cliente] = [];
            $this->lanzarEnCola([[
                'script' => 'procesar_facturas.py', 'args' => ['--client', $cliente, '--input', '{E0}', $enviar ? '--send' : '--no-mail'],
                'timeout' => 180, 'etiqueta' => $etiqueta,
            ]], [
                'entradas' => [['ruta' => $rutaMasterAbs, 'nombre' => date('Ymd_His').'_'.basename($rutaMasterAbs), 'dir' => "{$cliente}/Entrada"]],
                'resultados' => $cliente, 'post' => 'postProcesarFacturas', 'ctx' => ['cliente' => $cliente, 'enviar' => $enviar],
            ]);
            return false;   // la fase avanza cuando el PC termina (postProcesarFacturas)
        }
        if (! config('contabilidad.ejecucion_local')) {
            // Sin acceso real al proyecto en esta máquina no tiene sentido ni
            // intentar copiar/crear nada.
            $this->salida .= "\n\n===== {$etiqueta} =====\n⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return false;
        }

        $entradaDir = $this->scriptDir()."/{$cliente}/Entrada";
        if (! is_dir($entradaDir)) {
            @mkdir($entradaDir, 0775, true);
        }

        $rutaCopia = $entradaDir.'/'.date('Ymd_His').'_'.basename($rutaMasterAbs);
        if (! @copy($rutaMasterAbs, $rutaCopia)) {
            $this->salida .= "\n\n⚠️ No he podido copiar el PDF a {$entradaDir}.";
            return false;
        }

        $args = [$this->pythonBin(), 'procesar_facturas.py', '--client', $cliente, '--input', $rutaCopia];
        $args[] = $enviar ? '--send' : '--no-mail';

        $inicio = strlen($this->salida);
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $res = $this->ejecutarScript($args, 180, $etiqueta);
        $this->salidaCliente[$cliente] = trim(substr($this->salida, $inicio));
        $this->anexarResultados($cliente, $res['archivos']);
        return $res['ok'];
    }

    /** Fin de una fase en el PC: la fase de la tarjeta avanza solo si fue bien y la salida de la tarjeta se actualiza. */
    protected function postProcesarFacturas(array $ctx, int $desde, array $oks): void
    {
        $this->salidaCliente[$ctx['cliente']] = trim(substr($this->salida, $desde));
        if (! in_array(false, $oks, true)) {
            $this->estado[$ctx['cliente']]['fase'] = $ctx['enviar'] ? 'enviado' : 'separado';
            $this->guardarEstado($ctx['cliente']);
        }
    }

    // -- Genérico: cualquier proveedor ------------------------------------

    protected function genericoVacio(): array
    {
        return ['fase' => 'vacio', 'nombreOriginal' => null, 'rutaMaster' => null, 'id' => null,
            'destinatario' => '', 'avisos' => [], 'zip' => null];
    }

    /** Local: la carpeta de FacturacionPDFyMail. VPS: FACTURACION_GENERICO_DIR. */
    protected function genericoDir(): string
    {
        return rtrim(config('contabilidad.generico_dir') ?: $this->scriptDir(), '/');
    }

    protected function genericoPython(): string
    {
        if ($p = config('contabilidad.generico_python')) {
            return $p;
        }
        $venv = $this->genericoDir().'/.venv/bin/python3';
        return is_file($venv) ? $venv : 'python3';
    }

    /** Imágenes p<N>.jpg de cada página del PDF subido (las sirve la ruta contabilidad.facturacion-pdf.miniatura). */
    public static function carpetaMiniaturas(string $id): string
    {
        return Storage::disk('local')->path("facturacion-pdf/Generico/{$id}.mini");
    }

    public function getGenericoPermitidoProperty(): bool
    {
        return (bool) (config('contabilidad.ejecucion_local') || config('contabilidad.generico_ejecucion'));
    }

    /** Bajo Apache falta WSL_INTEROP y powershell.exe (OCR de Windows) falla en silencio: igual que FacturasOcr. */
    protected function entornoWindows(): array
    {
        return getenv('WSL_INTEROP') || ! is_dir('/run/WSL') ? [] : ['WSL_INTEROP' => '/run/WSL/1_interop'];
    }

    /** Cada fichero que sube el navegador (de uno en uno) se guarda al momento y se añade al lote. */
    public function updatedNuevoArchivoGenerico(): void
    {
        $this->validate(['nuevoArchivoGenerico' => 'required|file|max:51200|mimes:pdf,jpg,jpeg,png,tif,tiff,bmp,gif,webp']);
        $f = $this->nuevoArchivoGenerico;
        $nombre = $f->getClientOriginalName();
        $ext = strtolower($f->getClientOriginalExtension() ?: 'pdf');
        $rel = $f->storeAs('facturacion-pdf/Generico/lotes/'.Str::uuid(), 'f.'.$ext, 'local');
        $this->archivosGenerico[] = ['nombre' => $nombre, 'ruta' => Storage::disk('local')->path($rel)];
        $this->nuevoArchivoGenerico = null;
        if (count($this->archivosGenerico) === 2) {
            $this->genericoNombreOriginal = true;  // varios ficheros: por defecto se conserva su nombre detrás
        }
    }

    public function quitarArchivoGenerico(int $i): void
    {
        if (isset($this->archivosGenerico[$i])) {
            @unlink($this->archivosGenerico[$i]['ruta']);
            @rmdir(dirname($this->archivosGenerico[$i]['ruta']));
            array_splice($this->archivosGenerico, $i, 1);
        }
    }

    public function analizarGenerico(): void
    {
        $etiqueta = 'Facturación PDF · Genérico (Analizar)';
        if (! $this->genericoPermitido) {
            $this->salida .= "\n\n===== {$etiqueta} =====\n⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.";
            return;
        }
        $archivos = array_values(array_filter($this->archivosGenerico, fn ($a) => is_file($a['ruta'])));
        if (! $archivos) {
            $this->addError('nuevoArchivoGenerico', 'Sube al menos un PDF o una imagen.');
            return;
        }

        $nombreOriginal = count($archivos) === 1 ? $archivos[0]['nombre'] : count($archivos).' ficheros';
        $id = (string) Str::uuid();
        // PDF de trabajo: el script junta aquí todos los ficheros (las imágenes pasan a PDF)
        $rutaMaster = Storage::disk('local')->path("facturacion-pdf/Generico/{$id}.pdf");
        $entradas = [];
        foreach ($archivos as $a) {
            // Con el nombre original, que es el que aparece en la tabla y en el nombre final
            $dir = Storage::disk('local')->path("facturacion-pdf/Generico/{$id}.orig");
            @mkdir($dir, 0775, true);
            $destino = $dir.'/'.str_replace(['/', '\\'], '-', $a['nombre']);
            @copy($a['ruta'], $destino);
            $entradas[] = $destino;
        }
        $this->generico = array_merge($this->genericoVacio(), [
            'nombreOriginal' => $nombreOriginal,
            'rutaMaster' => $rutaMaster,
            'id' => $id,
        ]);
        $this->genericoPaginas = [];
        $this->resultados['Generico'] = [];

        $this->genericoCliente = trim($this->genericoCliente);
        session(['facturacion-pdf.generico-cliente' => $this->genericoCliente]);
        [$clienteNombre, $clienteNif] = $this->entidadesCliente[$this->genericoCliente]
            ?? [preg_replace('/\s*\([^)]*\)\s*$/', '', $this->genericoCliente), ''];

        $this->salida .= "\n\n===== {$etiqueta} =====\n📄 ".implode(', ', array_column($archivos, 'nombre'))."\n"
            .($clienteNombre !== '' ? "Cliente: {$clienteNombre}".($clienteNif !== '' ? " ({$clienteNif})" : '')."\n" : '');
        $args = [$this->genericoPython(), 'separar_generico.py', 'analizar'];
        foreach ($entradas as $e) {
            array_push($args, '--input', $e);
        }
        array_push($args, '--combinado', $rutaMaster);
        array_push($args, '--miniaturas', self::carpetaMiniaturas($id), '--cliente', $clienteNombre, '--cliente-nif', $clienteNif);
        try {
            $result = Process::path($this->genericoDir())->timeout(900)->env($this->entornoWindows())
                ->run($args);
            $data = json_decode(trim($result->output()), true);
            if (! $result->successful() || ! is_array($data)) {
                $this->salida .= trim($result->errorOutput()."\n".$result->output())
                    ."\n\n⚠️ El proceso terminó con código de salida ".$result->exitCode().'.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error. Mira la caja de Salida.");
                return;
            }
        } catch (\Throwable $e) {
            $this->salida .= '⚠️ '.get_class($e).': '.$e->getMessage();
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida.");
            return;
        }

        $this->genericoPaginas = $data['paginas'] ?? [];
        $this->generico['destinatario'] = $data['destinatario'] ?? '';
        $this->generico['avisos'] = $data['avisos'] ?? [];
        $this->generico['fase'] = 'analizado';
        if (trim($result->errorOutput()) !== '') {
            $this->salida .= trim($result->errorOutput())."\n";
        }
        $this->salida .= count($this->genericoPaginas).' página(s) analizadas. Revisa la tabla y pulsa «Generar PDFs».';
        foreach ($this->generico['avisos'] as $a) {
            $this->salida .= "\n⚠️ {$a}";
        }
        $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nRevisa número y proveedor de cada página.");
    }

    /** Gira 90° (sentido horario) una página: se aplica al generar los PDF. */
    public function girarPagina(int $i): void
    {
        if (isset($this->genericoPaginas[$i])) {
            $this->genericoPaginas[$i]['giro'] = (((int) ($this->genericoPaginas[$i]['giro'] ?? 0)) + 90) % 360;
        }
    }

    /** Copia número/proveedor/tipo de una fila a la siguiente (para unir una página a la factura anterior). */
    public function igualQueAnterior(int $i): void
    {
        if ($i > 0 && isset($this->genericoPaginas[$i], $this->genericoPaginas[$i - 1])) {
            foreach (['numero', 'proveedor', 'tipo'] as $k) {
                $this->genericoPaginas[$i][$k] = $this->genericoPaginas[$i - 1][$k];
            }
        }
    }

    /**
     * Genera los PDF (carpeta + .zip). Devuelve al navegador la lista de lo generado para que, si se
     * eligió una carpeta (File System Access API), los escriba allí: [['fichero', 'archivo', 'intacto',
     * 'url'], ...]. intacto = el PDF de origen sale entero y sin girar: en la carpeta solo se renombra.
     */
    public function generarGenerico(): array
    {
        if (! in_array($this->generico['fase'] ?? '', ['analizado', 'generado'], true) || ! $this->genericoPermitido) {
            return [];
        }
        $master = $this->generico['rutaMaster'] ?? null;
        if (! $master || ! is_file($master)) {
            $this->salida .= "\n\n⚠️ No encuentro el PDF subido (Genérico). Vuelve a subirlo.";
            return [];
        }

        $plan = array_map(fn ($f) => [
            'pagina' => (int) ($f['pagina'] ?? 0),
            'numero' => trim((string) ($f['numero'] ?? '')),
            'proveedor' => trim((string) ($f['proveedor'] ?? '')),
            // el nombre entero: para ver si el fichero ya empieza por el proveedor (basta una palabra suya)
            'proveedor_completo' => trim((string) ($f['proveedor_completo'] ?? '')),
            'tipo' => in_array($f['tipo'] ?? '', ['Fra', 'Abo', 'Pre', 'Prof'], true) ? $f['tipo'] : 'Fra',
            'giro' => ((int) ($f['giro'] ?? 0)) % 360,
            'archivo' => (string) ($f['archivo'] ?? ''),
        ], $this->genericoPaginas);
        $rutaPlan = $master.'.plan.json';
        file_put_contents($rutaPlan, json_encode($plan, JSON_UNESCAPED_UNICODE));

        $etiqueta = 'Facturación PDF · Genérico (Generar PDFs)';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        try {
            $result = Process::path($this->genericoDir())->timeout(300)
                ->run([$this->genericoPython(), 'separar_generico.py', 'generar', '--input', $master, '--plan', $rutaPlan,
                    ...($this->genericoNombreOriginal ? ['--nombre-original'] : [])]);
            $texto = trim($result->output()."\n".$result->errorOutput());
        } catch (\Throwable $e) {
            $this->salida .= '⚠️ '.get_class($e).': '.$e->getMessage();
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida.");
            return [];
        }

        $manifiesto = preg_match('/^RESULT_MANIFEST:\s*(.+?)\s*$/m', $texto, $mj) ? (json_decode($mj[1], true) ?: []) : [];
        $texto = preg_replace('/^RESULT_MANIFEST:.*(\r?\n)?/m', '', $texto);
        $zip = preg_match('/^RESULT_ZIP:\s*(.+?)\s*$/m', $texto, $m) ? $m[1] : null;
        $carpetas = preg_match_all('/^RESULT_FILE:\s*(.+?)\s*$/m', $texto, $mm) ? $mm[1] : [];
        $this->salida .= trim(preg_replace('/^RESULT_(FILE|ZIP):.*(\r?\n)?/m', '', $texto));
        $this->resultados['Generico'] = [];
        if (config('contabilidad.ejecucion_local')) {
            // En local, además del .zip, enlace a la carpeta (en el VPS no sirve de nada)
            $this->anexarResultados('Generico', $carpetas);
        }

        if (! $result->successful()) {
            $this->salida .= "\n\n⚠️ El proceso terminó con código de salida ".$result->exitCode().'.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error. Mira la caja de Salida.");
            return [];
        }
        $this->generico['zip'] = $zip && is_file($zip) ? $zip : null;
        $this->generico['fase'] = 'generado';
        $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");

        // Rutas de lo generado, para servir cada PDF al navegador (ruta contabilidad.facturacion-pdf.generado)
        $id = $this->generico['id'];
        file_put_contents(self::rutaGenerados($id), json_encode(array_column($manifiesto, 'ruta'), JSON_UNESCAPED_UNICODE));
        return array_map(fn ($m, $n) => [
            'fichero' => $m['fichero'], 'archivo' => $m['archivo'], 'intacto' => (bool) $m['intacto'],
            'url' => route('contabilidad.facturacion-pdf.generado', [$id, $n]),
        ], $manifiesto, array_keys($manifiesto));
    }

    public static function rutaGenerados(string $id): string
    {
        return Storage::disk('local')->path("facturacion-pdf/Generico/{$id}.generados.json");
    }

    /** Lo que el navegador ha hecho en la carpeta del usuario (renombrar, escribir, mover a originales), a la Salida. */
    public function anotarCarpeta(string $texto): void
    {
        $this->salida .= "\n\n===== Facturación PDF · Genérico (en tu carpeta) =====\n".mb_substr($texto, 0, 20000);
    }

    /** Antes de subir los ficheros marcados de una carpeta: lote nuevo y nombre original detrás. */
    public function empezarLoteCarpeta(): void
    {
        $this->empezarDeNuevoGenerico();
        $this->genericoNombreOriginal = true;
    }

    public function descargarZipGenerico()
    {
        $zip = $this->generico['zip'] ?? null;
        if (! $zip || ! is_file($zip)) {
            $this->salida .= "\n\n⚠️ El .zip ya no está. Vuelve a pulsar «Generar PDFs».";
            return null;
        }
        $base = pathinfo((string) $this->generico['nombreOriginal'], PATHINFO_FILENAME) ?: 'facturas';
        return response()->download($zip, str_contains($base, 'ficheros') ? 'facturas renombradas.zip' : "{$base} - separado.zip");
    }

    /** Reinicia la pantalla y borra los ficheros subidos del lote (el PDF de trabajo se queda en storage/app/facturacion-pdf/Generico). */
    public function empezarDeNuevoGenerico(): void
    {
        foreach (array_keys($this->archivosGenerico) as $i) {
            @unlink($this->archivosGenerico[$i]['ruta']);
            @rmdir(dirname($this->archivosGenerico[$i]['ruta']));
        }
        $this->archivosGenerico = [];
        $this->genericoNombreOriginal = false;
        $this->generico = $this->genericoVacio();
        $this->genericoPaginas = [];
        $this->nuevoArchivoGenerico = null;
        $this->resultados['Generico'] = [];
    }

    // -- Destinatarios (solo lectura) -------------------------------------

    public function cargarDestinatarios(string $cliente): void
    {
        unset($this->cambios[$cliente]);   // la lista vuelve a ser la del Excel
        if (! isset($this->clientes()[$cliente])) {
            return;
        }
        if ($this->remoto()) {
            $this->lanzarEnCola([['script' => 'herramientas/listar_destinatarios.py', 'args' => ['--client', $cliente], 'timeout' => 60,
                'etiqueta' => "Facturación PDF · {$cliente} · destinatarios"]],
                ['post' => 'postDestinatarios', 'ctx' => ['cliente' => $cliente, 'ini' => strlen($this->salida)]]);
            return;
        }
        if (! config('contabilidad.ejecucion_local')) {
            $this->destinatarios[$cliente] = ['error' => 'Opción no válida. Solo ejecutable desde un terminal autorizado.'];
            return;
        }

        try {
            $result = Process::path($this->scriptDir())->timeout(60)
                ->run([$this->pythonBin(), 'herramientas/listar_destinatarios.py', '--client', $cliente]);

            if (! $result->successful()) {
                $this->destinatarios[$cliente] = ['error' => trim($result->errorOutput()."\n".$result->output())];
                return;
            }

            $data = json_decode(trim($result->output()), true);
            if (! is_array($data) || isset($data['error'])) {
                $this->destinatarios[$cliente] = ['error' => $data['error'] ?? 'Respuesta no reconocida del script.'];
                return;
            }

            $this->destinatarios[$cliente] = [
                'filas' => $data['filas'] ?? [],
                'xlsxPathWindows' => $this->rutaWindows($data['xlsx_path'] ?? ''),
                'xlsxUrl' => $this->fileUrl($data['xlsx_path'] ?? ''),
                'avisos' => $data['avisos'] ?? [],
            'lote' => $data['lote'] ?? null,
                'lote' => $data['lote'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->destinatarios[$cliente] = ['error' => $e->getMessage()];
        }
    }

    /** Fin de «Cargar lista» en el PC: el JSON de listar_destinatarios.py no se enseña en la Salida, se lee como en local. */
    protected function postDestinatarios(array $ctx, int $desde, array $oks): void
    {
        $cliente = $ctx['cliente'];
        $texto = trim(substr($this->salida, $desde));
        $this->salida = substr($this->salida, 0, $ctx['ini']);
        $ini = strpos($texto, '{');
        $data = $ini === false ? null : json_decode(substr($texto, $ini), true);
        if (! ($oks[0] ?? false) || ! is_array($data) || isset($data['error'])) {
            $this->destinatarios[$cliente] = ['error' => is_array($data) && isset($data['error']) ? $data['error'] : ($texto ?: 'Respuesta no reconocida del PC.')];
            return;
        }
        $this->destinatarios[$cliente] = [
            'filas' => $data['filas'] ?? [],
            'xlsxPathWindows' => $this->rutaWindows($data['xlsx_path'] ?? ''),
            'xlsxUrl' => $this->fileUrl($data['xlsx_path'] ?? ''),
            'avisos' => $data['avisos'] ?? [],
            'lote' => $data['lote'] ?? null,
        ];
    }

    /**
     * Cambia 'enviar' o 'mail' de una fila SOLO en pantalla y lo apunta en $cambios; no toca el Excel hasta que se pulsa
     * «Guardar en TODO» (guardarCambios), que aplica todos los cambios de golpe en un PC abriendo el Excel una sola vez.
     */
    public function editarDestinatario(string $cliente, int $fila, string $campo, ?string $valor = ''): void
    {
        if (! isset($this->clientes()[$cliente]) || ! in_array($campo, ['enviar', 'mail', 'estado', 'idioma'], true)) {
            return;
        }
        $valor = trim((string) $valor);   // Laravel convierte '' en null en las peticiones
        foreach (($this->destinatarios[$cliente]['filas'] ?? []) as $i => $f) {
            if ((int) $f['fila'] !== $fila) {
                continue;
            }
            $c = $this->cambios[$cliente][$fila] ?? ['cliente' => $f['cliente'], 'orig' => ['mail' => $f['mail'], 'enviar' => $f['enviar'] ? '1' : '', 'estado' => $f['estado'] ?? '', 'idioma' => $f['idioma'] ?? '']];
            if (in_array($campo, ['mail', 'estado', 'idioma'], true)) {
                $this->destinatarios[$cliente]['filas'][$i][$campo] = $valor;
            } else {
                $this->destinatarios[$cliente]['filas'][$i]['enviar'] = $valor === '1';
                $this->destinatarios[$cliente]['filas'][$i]['enviar_raw'] = $valor;
            }
            if ($valor === $c['orig'][$campo]) {
                unset($c[$campo]);   // vuelve a lo que había: ya no es un cambio
            } else {
                $c[$campo] = $valor;
            }
            if (isset($c['mail']) || isset($c['enviar']) || isset($c['estado']) || isset($c['idioma'])) {
                $this->cambios[$cliente][$fila] = $c;
            } else {
                unset($this->cambios[$cliente][$fila]);
            }
            return;
        }
    }

    /** Cambia el Estado de un cliente (activo, baja, inactivo, liquidada; nunca vacío). Si deja de estar activo, Enviar pasa a no. */
    public function cambiarEstado(string $cliente, int $fila, string $estado): void
    {
        if (! in_array($estado, ['activo', 'baja', 'inactivo', 'liquidada'], true)) {
            return;
        }
        $this->editarDestinatario($cliente, $fila, 'estado', $estado);
        if ($estado !== 'activo') {
            $this->editarDestinatario($cliente, $fila, 'enviar', '');
        }
    }

    public function descartarCambios(string $cliente): void
    {
        unset($this->cambios[$cliente]);
        $this->cargarDestinatarios($cliente);
    }

    /** Un solo trabajo en un PC: abre el Excel (ToDO Alex), aplica todos los cambios apuntados y guarda. */
    public function guardarCambios(string $cliente): void
    {
        $lista = [];
        foreach (($this->cambios[$cliente] ?? []) as $fila => $c) {
            $lista[] = ['fila' => (int) $fila, 'cliente' => $c['cliente']] + array_intersect_key($c, ['mail' => 1, 'enviar' => 1, 'estado' => 1, 'idioma' => 1]);
        }
        if (! isset($this->clientes()[$cliente]) || ! $lista) {
            return;
        }
        $args = ['--client', $cliente, '--cambios', json_encode($lista, JSON_UNESCAPED_UNICODE)];
        $etiqueta = "Facturación PDF · {$cliente} · guardar ".count($lista).' cambio(s) en TODO';
        if ($this->remoto()) {
            $this->lanzarEnCola([['script' => 'herramientas/editar_destinatario.py', 'args' => $args, 'timeout' => 170, 'etiqueta' => $etiqueta]],
                ['post' => 'postGuardarCambios', 'ctx' => ['cliente' => $cliente, 'enviados' => $this->cambios[$cliente]]]);
            return;
        }
        if (! config('contabilidad.ejecucion_local')) {
            return;
        }
        $result = Process::path($this->scriptDir())->timeout(170)->run([$this->pythonBin(), 'herramientas/editar_destinatario.py', ...$args]);
        if ($result->successful()) {
            unset($this->cambios[$cliente]);
        } else {
            $this->salida .= "\n\n⚠️ ".trim($result->output()."\n".$result->errorOutput());
        }
    }

    /** Fin de guardarCambios en el PC: si fue bien se quitan los cambios guardados (los hechos mientras tanto se conservan); si no, quedan apuntados y el motivo está en la Salida. */
    protected function postGuardarCambios(array $ctx, int $desde, array $oks): void
    {
        if (! ($oks[0] ?? false)) {
            return;
        }
        $cliente = $ctx['cliente'];
        foreach ($ctx['enviados'] as $fila => $c) {
            if (($this->cambios[$cliente][$fila] ?? null) === $c) {
                unset($this->cambios[$cliente][$fila]);
            }
        }
        if (empty($this->cambios[$cliente])) {
            unset($this->cambios[$cliente]);
        }
    }

    /** Filas que se enseñan por cliente: solo las activas (Estado vacío) + las que tienen un cambio sin guardar + todas si se marca «ver inactivas». */
    public function getDestinatariosVisiblesProperty(): array
    {
        $out = [];
        foreach (array_keys($this->clientes()) as $id) {
            $d = $this->destinatarios[$id] ?? null;
            $filas = (! $d || isset($d['error'])) ? [] : $d['filas'];
            if (empty($this->verInactivas[$id])) {
                $filas = array_values(array_filter($filas, fn ($f) => in_array(mb_strtolower(trim($f['estado'] ?? '')), ['', 'activo', 'activa'], true) || isset($this->cambios[$id][$f['fila']])));
            }
            if (! empty($this->soloLote[$id])) {
                $filas = array_values(array_filter($filas, fn ($f) => ! empty($f['en_lote'])));
            }
            $out[$id] = $filas;
        }
        return $out;
    }

    public function getDestinatariosFiltradosProperty(): array
    {
        $out = [];
        foreach ($this->destinatariosVisibles as $id => $filas) {
            $filtro = $this->filtroEnviar[$id] ?? 'todos';
            if ($filtro === 'si') {
                $filas = array_values(array_filter($filas, fn ($f) => $f['enviar']));
            } elseif ($filtro === 'no') {
                $filas = array_values(array_filter($filas, fn ($f) => ! $f['enviar']));
            }
            $out[$id] = $filas;
        }
        return $out;
    }

    /** /mnt/e/Foo/Bar  ->  E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    /** /mnt/e/Foo/Bar  ->  file:///E:/Foo/Bar */
    protected function fileUrl(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return 'file:///'.strtoupper($m[1]).':/'.str_replace('%2F', '/', rawurlencode($m[2]));
        }
        return 'file://'.$p;
    }

    /**
     * Igual que Contabilidad\Procesos::ejecutarScript: cualquier fallo (el
     * proceso, el propio report() si el log tampoco fuera escribible, o
     * cualquier otra excepción) se convierte en un aviso en pantalla, nunca
     * en un error que rompa la página.
     */
    /**
     * Devuelve ['ok' => bool, 'archivos' => rutas absolutas marcadas por el
     * script con líneas "RESULT_FILE: <ruta>" (no se muestran en la caja de
     * Salida; se enseñan como enlace aparte -- mismo mecanismo que
     * Contabilidad\Procesos).
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): array
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return ['ok' => false, 'archivos' => []];
        }

        try {
            $result = Process::path($this->scriptDir())->timeout($timeout)->run($args);
            $texto = trim($result->output()."\n".$result->errorOutput());
            $archivos = [];
            if (preg_match_all('/^RESULT_FILE:\s*(.+?)\s*$/m', $texto, $m)) {
                $archivos = array_map('trim', $m[1]);
                $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $texto));
            }
            $this->salida .= $texto;
            if ($result->successful()) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
                return ['ok' => true, 'archivos' => $archivos];
            }
            $this->salida .= "\n\n⚠️ El proceso terminó con código de salida ".$result->exitCode().'.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código ".$result->exitCode().'). Mira la caja de Salida para el detalle.');
            return ['ok' => false, 'archivos' => $archivos];
        } catch (\Throwable $e) {
            $this->salida .= "\n\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n"
                .get_class($e).': '.$e->getMessage()."\n"
                .'en '.$e->getFile().':'.$e->getLine()."\n"
                .'comando: '.implode(' ', array_map(fn ($a) => "'".$a."'", $args))."\n"
                ."traza:\n".implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8));
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida para el detalle.");
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Si ni siquiera se puede registrar el error, no debe romper la pantalla por eso.
            }
            return ['ok' => false, 'archivos' => []];
        }
    }

    /** Añade rutas RESULT_FILE a $resultados[$cliente] (ruta Windows + copiar), sin duplicar. */
    protected function anexarResultados(string $cliente, array $rutas): void
    {
        $yaEstan = array_column($this->resultados[$cliente] ?? [], 'ruta');
        foreach ($rutas as $ruta) {
            $win = $this->rutaWindows($ruta);
            if (in_array($win, $yaEstan, true)) {
                continue;
            }
            $yaEstan[] = $win;
            $this->resultados[$cliente][] = ['ruta' => $win, 'url' => $this->fileUrl($ruta)];
        }
    }

    public function render()
    {
        return view('livewire.contabilidad.facturacion-pdf');
    }
}
