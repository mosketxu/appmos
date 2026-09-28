<?php

namespace App\Http\Livewire\Contabilidad;

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
    use WithFileUploads;

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
     * Carpeta de resultados de la última ejecución de cada cliente, para
     * enseñar el enlace igual que en Procesos FIQ (ver
     * Contabilidad\Procesos::$resultados). Forma:
     * ['Suma' => [['ruta' => 'E:\\...\\2026-09', 'url' => 'file:///E:/...'], ...]].
     */
    public array $resultados = [];

    /** Destinatarios cargados por cliente (ver cargarDestinatarios). */
    public array $destinatarios = [];

    /** Filtro de la lista de destinatarios por cliente: 'todos' | 'si' | 'no'. */
    public array $filtroEnviar = [];

    /**
     * Separador genérico (separar_generico.py): PDF de facturas de cualquier
     * proveedor, normalmente escaneado/fotografiado (OCR). Sin correo y sin
     * OneDrive, así que también funciona en el VPS (ver
     * config/contabilidad.php 'generico_*'). Fases: 'vacio' -> 'analizado'
     * (tabla editable página -> número/proveedor) -> 'generado' (.zip
     * descargable).
     */
    public $archivoGenerico = null;

    /** Rutas del PDF subido y del .zip: bloqueado para que el navegador no pueda cambiarlas. */
    #[Locked]
    public array $generico = [];

    /** Tabla editable: una fila por página [pagina, numero, proveedor, tipo Fra|Abo|Pre]. */
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
            $this->estado[$id] = ['fase' => 'vacio', 'nombreOriginal' => null, 'rutaMaster' => null];
            $this->filtroEnviar[$id] = 'todos';
        }
        $this->generico = $this->genericoVacio();
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
    }

    // -- Fase 2: enviar correos ------------------------------------------

    public function enviarCorreos(string $cliente): void
    {
        if (($this->estado[$cliente]['fase'] ?? '') !== 'separado') {
            return;
        }
        $ok = $this->ejecutarConCopiaFresca($cliente, enviar: true, etiquetaSufijo: 'Fase 2 · Enviar correos (REAL)');
        if ($ok) {
            $this->estado[$cliente]['fase'] = 'enviado';
        }
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

        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $res = $this->ejecutarScript($args, 180, $etiqueta);
        $this->anexarResultados($cliente, $res['archivos']);
        return $res['ok'];
    }

    // -- Genérico: cualquier proveedor ------------------------------------

    protected function genericoVacio(): array
    {
        return ['fase' => 'vacio', 'nombreOriginal' => null, 'rutaMaster' => null,
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

    public function getGenericoPermitidoProperty(): bool
    {
        return (bool) (config('contabilidad.ejecucion_local') || config('contabilidad.generico_ejecucion'));
    }

    /** Bajo Apache falta WSL_INTEROP y powershell.exe (OCR de Windows) falla en silencio: igual que FacturasOcr. */
    protected function entornoWindows(): array
    {
        return getenv('WSL_INTEROP') || ! is_dir('/run/WSL') ? [] : ['WSL_INTEROP' => '/run/WSL/1_interop'];
    }

    public function analizarGenerico(): void
    {
        $etiqueta = 'Facturación PDF · Genérico (Analizar)';
        if (! $this->genericoPermitido) {
            $this->salida .= "\n\n===== {$etiqueta} =====\n⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.";
            return;
        }
        $this->validate(['archivoGenerico' => 'required|file|mimes:pdf|max:51200']);

        $nombreOriginal = $this->archivoGenerico->getClientOriginalName();
        $rutaRelativa = $this->archivoGenerico->storeAs('facturacion-pdf/Generico', Str::uuid().'.pdf', 'local');
        $this->generico = array_merge($this->genericoVacio(), [
            'nombreOriginal' => $nombreOriginal,
            'rutaMaster' => Storage::disk('local')->path($rutaRelativa),
        ]);
        $this->genericoPaginas = [];
        $this->archivoGenerico = null;
        $this->resultados['Generico'] = [];

        $this->salida .= "\n\n===== {$etiqueta} =====\n📄 {$nombreOriginal}\n";
        try {
            $result = Process::path($this->genericoDir())->timeout(900)->env($this->entornoWindows())
                ->run([$this->genericoPython(), 'separar_generico.py', 'analizar', '--input', $this->generico['rutaMaster']]);
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

    /** Copia número/proveedor/tipo de una fila a la siguiente (para unir una página a la factura anterior). */
    public function igualQueAnterior(int $i): void
    {
        if ($i > 0 && isset($this->genericoPaginas[$i], $this->genericoPaginas[$i - 1])) {
            foreach (['numero', 'proveedor', 'tipo'] as $k) {
                $this->genericoPaginas[$i][$k] = $this->genericoPaginas[$i - 1][$k];
            }
        }
    }

    public function generarGenerico(): void
    {
        if (! in_array($this->generico['fase'] ?? '', ['analizado', 'generado'], true) || ! $this->genericoPermitido) {
            return;
        }
        $master = $this->generico['rutaMaster'] ?? null;
        if (! $master || ! is_file($master)) {
            $this->salida .= "\n\n⚠️ No encuentro el PDF subido (Genérico). Vuelve a subirlo.";
            return;
        }

        $plan = array_map(fn ($f) => [
            'pagina' => (int) ($f['pagina'] ?? 0),
            'numero' => trim((string) ($f['numero'] ?? '')),
            'proveedor' => trim((string) ($f['proveedor'] ?? '')),
            'tipo' => in_array($f['tipo'] ?? '', ['Fra', 'Abo', 'Pre'], true) ? $f['tipo'] : 'Fra',
        ], $this->genericoPaginas);
        $rutaPlan = $master.'.plan.json';
        file_put_contents($rutaPlan, json_encode($plan, JSON_UNESCAPED_UNICODE));

        $etiqueta = 'Facturación PDF · Genérico (Generar PDFs)';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        try {
            $result = Process::path($this->genericoDir())->timeout(300)
                ->run([$this->genericoPython(), 'separar_generico.py', 'generar', '--input', $master, '--plan', $rutaPlan]);
            $texto = trim($result->output()."\n".$result->errorOutput());
        } catch (\Throwable $e) {
            $this->salida .= '⚠️ '.get_class($e).': '.$e->getMessage();
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida.");
            return;
        }

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
            return;
        }
        $this->generico['zip'] = $zip && is_file($zip) ? $zip : null;
        $this->generico['fase'] = 'generado';
        $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
    }

    public function descargarZipGenerico()
    {
        $zip = $this->generico['zip'] ?? null;
        if (! $zip || ! is_file($zip)) {
            $this->salida .= "\n\n⚠️ El .zip ya no está. Vuelve a pulsar «Generar PDFs».";
            return null;
        }
        $base = pathinfo((string) $this->generico['nombreOriginal'], PATHINFO_FILENAME) ?: 'facturas';
        return response()->download($zip, "{$base} - separado.zip");
    }

    /** Solo reinicia la pantalla; el PDF subido se queda en storage/app/facturacion-pdf/Generico. */
    public function empezarDeNuevoGenerico(): void
    {
        $this->generico = $this->genericoVacio();
        $this->genericoPaginas = [];
        $this->archivoGenerico = null;
        $this->resultados['Generico'] = [];
    }

    // -- Destinatarios (solo lectura) -------------------------------------

    public function cargarDestinatarios(string $cliente): void
    {
        if (! isset($this->clientes()[$cliente])) {
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
            ];
        } catch (\Throwable $e) {
            $this->destinatarios[$cliente] = ['error' => $e->getMessage()];
        }
    }

    public function getDestinatariosFiltradosProperty(): array
    {
        $out = [];
        foreach (array_keys($this->clientes()) as $id) {
            $d = $this->destinatarios[$id] ?? null;
            $filas = (! $d || isset($d['error'])) ? [] : $d['filas'];
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
