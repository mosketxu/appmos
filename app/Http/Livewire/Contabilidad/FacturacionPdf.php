<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    protected function scriptDir(): string
    {
        return '/mnt/e/Claude/FacturacionPDFyMail';
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

        $args = ['python3', 'procesar_facturas.py', '--client', $cliente, '--input', $rutaCopia];
        $args[] = $enviar ? '--send' : '--no-mail';

        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $res = $this->ejecutarScript($args, 180, $etiqueta);
        $this->anexarResultados($cliente, $res['archivos']);
        return $res['ok'];
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
                ->run(['python3', 'herramientas/listar_destinatarios.py', '--client', $cliente]);

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
