<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pantalla de bancos por cliente (una carpeta por cliente dentro de
 * Contabilidad/Bancos). Ver PLAN.md en Contabilidad/Bancos para el detalle.
 *
 * Al empezar cada proceso se suben (botón o arrastrando, cada uno en su fila) los ficheros base:
 *   - el mayor de SAGE, uno solo con todas las cuentas (30-sep-2026): se guardan las de banco,
 *     572... y las "otras cuentas de banco" marcadas en pantalla (p.ej. 551002 de Pevima),
 *   - el plan de cuentas del cliente.
 * Se guardan tal cual en <Cliente>/Base/Recibidos (con fecha/hora delante) y
 * bancos_base.py añade sus filas nuevas a <Cliente>/Base/Base <Cliente>.xlsx.
 *
 * Luego, para cada extracto del banco: se elige la cuenta (combo con las
 * cuentas 572/551... cargadas en la base), se sube el extracto y
 * bancos_conciliacion.py --partida <cuenta> genera Output/bancos<cuenta>.xlsx
 * buscando la contrapartida en Variables -> Maestro -> Plan cuentas de la base.
 * El extracto se archiva en Input/input_old como siempre.
 *
 * El Maestro se ve y se edita aquí (bancos_maestro.py): las filas de SAGE
 * salen del Maestro de la base y lo que se añade o corrige a mano se guarda
 * en su pestaña Variables, que la conciliación mira antes que el Maestro (el
 * Maestro se rehace entero con cada subida de ficheros base).
 */
class Bancos extends Component
{
    use WithFileUploads;

    public string $cliente = '';
    public string $salida = '';

    /**
     * Ficheros base recién subidos (botón o arrastrando a su fila); al terminar la subida el
     * navegador llama a procesarSubidas() con la fila: 'plan' o 'mayor'.
     */
    public array $subidas = [];

    /**
     * Cuentas del plan de la base creadas fuera de SAGE (aquí o por Facturas OCR): [{cuenta, nombre, cif,
     * cp, origen}] y, por cuenta, la propuesta de CIF/CP buscada en internet (buscar_cif.py).
     */
    public array $cuentasNuevas = [];
    public array $propuestasCif = [];

    /** Fichero de Output en revisión, sus líneas y aviso si no se han podido leer (ver cargarRevision). */
    public string $revisar = '';
    public array $lineasRevisar = [];
    public string $avisoRevisar = '';
    public int $revisionN = 0;

    /** Lo que hay en la base (plan, cada cuenta, último mayor, otras cuentas de banco): bancos_base.py --estado. */
    public array $estadoBase = [];

    /** Cuenta que no empieza por 572 a marcar como de banco (p.ej. 551002). */
    public string $otraCuenta = '';

    /**
     * Extractos del banco a conciliar (varios a la vez, 8-oct-2026): ficheros subidos ($nuevosExtractos es el
     * buzón de la subida; anadirExtractos() los pasa a la lista), la cuenta (partida) de cada uno
     * ($cuentasExtracto, misma posición) y de dónde sale la propuesta ($origenCuenta).
     */
    public array $nuevosExtractos = [];
    public array $extractos = [];
    public array $cuentasExtracto = [];
    public array $origenCuenta = [];
    public string $cuenta = '';

    /** Maestro del cliente (filas SAGE + manuales) y plan de cuentas [codigo => nombre], ver cargarMaestro(). */
    public array $maestro = [];
    public array $planCuentas = [];
    public string $avisoMaestro = '';

    /** Listas comunes de Doc_y_Config/Configuracion.xlsx (bancos_config.py) y la prueba de limpieza. */
    public array $config = ['textos' => [], 'genericas' => []];
    public string $pruebaTexto = '';
    public ?array $pruebaResultado = null;

    /** Aviso junto a la lista tocada tras añadir/quitar: ['clave', 'ok', 'texto', 'n']. */
    public array $avisoConfig = [];

    /** Ficheros resultado de la última ejecución (mismo mecanismo que Contabilidad\Procesos). */
    public array $resultados = [];

    /**
     * Formatos de extracto del cliente (<Cliente>/Base/formatos.json, bancos_formatos.py) y la
     * pantalla para asignar las columnas de un extracto que no se reconoce (o corregir un formato):
     * ['ruta', 'cuenta', 'id', 'nombre', 'cabecera', 'columnas', 'filas', 'fichero', 'motivo', 'error'].
     */
    public array $formatos = [];
    public ?array $mapeo = null;

    public const ROLES = [
        '' => '— no se usa —', 'fecha' => 'Fecha', 'fecha2' => 'Otra fecha (valor…)', 'concepto' => 'Concepto',
        'importe' => 'Importe (con signo)', 'debe' => 'Debe / cargos', 'haber' => 'Haber / abonos', 'saldo' => 'Saldo',
    ];

    public function mount(): void
    {
        $this->cliente = $this->clientes()[0] ?? '';
        $this->sincronizarCentral();
        $this->cargarMaestro();
        $this->cargarConfig();
        $this->cargarFormatos();
    }

    // ------------------------------------------------------------ formatos de extracto

    /** Ejecuta bancos_formatos.py y devuelve [ok, datos JSON]. */
    protected function formatosPy(array $args): array
    {
        try {
            $r = Process::path($this->baseDir())->timeout(90)->run(array_merge([$this->pythonBin(), 'bancos_formatos.py', $this->cliente], $args));
            $datos = json_decode(trim($r->output()), true);
            if (! is_array($datos)) {
                $datos = ['error' => trim($r->output()."\n".$r->errorOutput())];
            }
            return [$r->successful() && empty($datos['error']), $datos];
        } catch (\Throwable $e) {
            return [false, ['error' => $e->getMessage()]];
        }
    }

    public function cargarFormatos(): void
    {
        $this->formatos = [];
        if (! $this->clienteValido()) {
            return;
        }
        [$ok, $d] = $this->formatosPy(['listar']);
        $this->formatos = $ok ? ($d['formatos'] ?? []) : [];
    }

    /** Abre la pantalla de columnas con un extracto (ruta absoluta dentro de la carpeta del cliente). */
    protected function abrirMapeo(string $ruta, string $cuenta = '', string $motivo = ''): void
    {
        [$ok, $d] = $this->formatosPy(['vista', $ruta]);
        if (! $ok) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ No se puede abrir el extracto\n".($d['error'] ?? ''));
            return;
        }
        $this->mapeo = [
            'ruta' => $ruta, 'cuenta' => $cuenta, 'id' => $d['id'] ?? '', 'nombre' => $d['nombre'] ?? '',
            'cabecera' => (int) ($d['cabecera'] ?? 0), 'columnas' => $d['columnas'] ?? [], 'filas' => $d['filas'] ?? [],
            'fichero' => $d['fichero'] ?? basename($ruta), 'motivo' => $motivo, 'error' => '',
        ];
    }

    /** "Asignar columnas" de un extracto que está en Input (p.ej. el que no se reconoció). */
    public function mapearPendiente(string $nombre): void
    {
        $ruta = $this->rutaCliente('Input/'.$nombre);
        if (! $ruta) {
            return;
        }
        $cuenta = preg_match('/^(\d{6,})/', $nombre, $m) && array_key_exists($m[1], $this->cuentasBanco()) ? $m[1] : $this->cuenta;
        $this->abrirMapeo($ruta, $cuenta);
    }

    /** Corregir un formato guardado (con la muestra de filas que se guardó con él). */
    public function editarFormato(string $id): void
    {
        foreach ($this->formatos as $f) {
            if (($f['id'] ?? '') === $id) {
                $filas = $f['muestra'] ?? [];
                $ancho = max(count($filas[0] ?? []), count($f['columnas'] ?? []));
                $this->mapeo = [
                    'ruta' => '', 'cuenta' => '', 'id' => $id, 'nombre' => $f['nombre'] ?? '', 'cabecera' => (int) ($f['cabecera'] ?? 0),
                    'columnas' => array_pad($f['columnas'] ?? [], $ancho, ''),
                    'filas' => array_map(fn ($r) => array_pad($r, $ancho, ''), $filas),
                    'fichero' => $f['fichero'] ?? '', 'motivo' => '', 'error' => '',
                ];
                return;
            }
        }
    }

    public function cerrarMapeo(): void
    {
        $this->mapeo = null;
    }

    public function guardarMapeo(): void
    {
        if (! $this->mapeo) {
            return;
        }
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · Formato de extracto');
            return;
        }
        $m = $this->mapeo;
        $tmp = tempnam(sys_get_temp_dir(), 'bancos-formato-');
        file_put_contents($tmp, json_encode([
            'id' => $m['id'], 'nombre' => $m['nombre'], 'cabecera' => (int) $m['cabecera'],
            'columnas' => array_values(array_map(fn ($c) => (string) $c, $m['columnas'])), 'ruta' => $m['ruta'],
        ], JSON_UNESCAPED_UNICODE));
        [$ok, $d] = $this->formatosPy(['guardar', $tmp]);
        @unlink($tmp);
        if (! $ok) {
            $this->mapeo['error'] = $d['error'] ?? 'No se ha podido guardar.';
            return;
        }
        $this->mapeo = null;
        $this->cargarFormatos();
        $texto = "Formato «{$d['nombre']}» guardado: salen {$d['movimientos']} movimientos.";
        // Venía de un extracto que no se pudo procesar: se procesa ya con el formato nuevo
        if ($m['ruta'] !== '' && is_file($m['ruta']) && str_contains($m['ruta'], '/Input/') && array_key_exists($m['cuenta'], $this->cuentasBanco())) {
            $this->cuenta = $m['cuenta'];
            $this->salida .= "\n{$texto}";
            $this->procesar($m['ruta']);
            return;
        }
        $this->dispatch('proceso-terminado', mensaje: "✅ {$texto}".($m['ruta'] !== '' && str_contains($m['ruta'], '/Input/')
            ? "\nElige la cuenta y vuelve a subir el extracto para procesarlo." : ''));
    }

    public function borrarFormato(string $id): void
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · Formato de extracto');
            return;
        }
        [$ok, $d] = $this->formatosPy(['borrar', $id]);
        $this->dispatch('proceso-terminado', mensaje: $ok ? '✅ Formato borrado: la próxima vez ese extracto se detectará solo (o se preguntará).' : '⚠️ '.($d['error'] ?? ''));
        $this->cargarFormatos();
    }

    /** Ruta absoluta de un fichero dentro de la carpeta del cliente, o null si se sale de ella / no existe. */
    protected function rutaCliente(string $relativa): ?string
    {
        if (! $this->clienteValido()) {
            return null;
        }
        $raiz = realpath($this->baseDir().'/'.$this->cliente);
        $ruta = realpath($raiz.'/'.$relativa);
        return $raiz && $ruta && str_starts_with($ruta, $raiz.'/') && is_file($ruta) ? $ruta : null;
    }

    /** Ejecuta bancos_config.py y devuelve [ok, salida]. */
    protected function config_py(array $args): array
    {
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run(array_merge([$this->pythonBin(), 'bancos_config.py'], $args));
            return [$r->successful(), trim($r->output()."\n".$r->errorOutput())];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    public function cargarConfig(): void
    {
        [$ok, $out] = $this->config_py(['listar']);
        $datos = $ok ? json_decode($out, true) : null;
        $this->config = is_array($datos) ? $datos : ['textos' => [], 'genericas' => [], 'abreviaturas' => []];
    }

    public function anadirConfig(string $clave, string $texto, string $comentario = ''): void
    {
        $this->editarConfig(['anadir', $clave, $texto, $comentario]);
    }

    public function anadirAbreviatura(string $texto, string $abreviatura, string $comentario = ''): void
    {
        $this->editarConfig(['anadir', 'abreviaturas', $texto, $abreviatura, $comentario]);
    }

    public function borrarConfig(string $clave, string $texto): void
    {
        $this->editarConfig(['borrar', $clave, $texto]);
    }

    protected function editarConfig(array $args): void
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · Configuración');
            return;
        }
        [$ok, $out] = $this->config_py($args);
        // "Textos a quitar" cambia el concepto limpio: se rehace el Maestro de
        // todos los clientes con base para que la tabla y la conciliación lo usen ya.
        if ($ok && ($args[1] ?? '') === 'textos') {
            $rehechos = [];
            foreach ($this->clientes() as $c) {
                if (is_file($this->baseDir()."/{$c}/Base/Base {$c}.xlsx")) {
                    $r = Process::path($this->baseDir())->timeout(120)->run([$this->pythonBin(), 'bancos_base.py', $c, '--rehacer']);
                    $rehechos[] = $c.($r->successful() ? '' : ' (⚠️ '.trim($r->output().' '.$r->errorOutput()).')');
                }
            }
            if ($rehechos) {
                $out .= "\nMaestro rehecho: ".implode(', ', $rehechos);
            }
            $this->cargarMaestro();
        }
        $this->avisoConfig = ['clave' => $args[1] ?? '', 'ok' => $ok, 'texto' => $out, 'n' => ($this->avisoConfig['n'] ?? 0) + 1];
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '✅ ' : '⚠️ ')."Configuración común\n{$out}");
        $this->cargarConfig();
        if ($this->pruebaTexto !== '') {
            $this->probarConcepto();
        }
    }

    /** Cómo queda un concepto con las listas actuales (limpio + palabras que cuentan). */
    public function probarConcepto(): void
    {
        $this->pruebaResultado = null;
        if (trim($this->pruebaTexto) === '') {
            return;
        }
        [$ok, $out] = $this->config_py(['probar', $this->pruebaTexto]);
        $datos = $ok ? json_decode($out, true) : null;
        $this->pruebaResultado = is_array($datos) ? $datos : ['limpio' => '⚠️ '.$out, 'palabras' => []];
    }

    protected function baseDir(): string
    {
        return rtrim(config('contabilidad.bancos_dir'), '/');
    }

    /**
     * Subcarpetas de Bancos que son clientes (todas menos Doc_y_Config y las
     * ocultas) y que el usuario puede ver: cada carpeta se enlaza con su
     * entidad de Appmos en <Cliente>/cliente.json ({"entidad_id": ...}); un
     * usuario sin "entidades.todas" solo ve las de sus entidades, y las
     * carpetas sin enlazar solo las ve quien puede ver todas.
     */
    protected function clientes(): array
    {
        $permitidas = \App\Support\Accesos::entidadesPermitidas();
        $dirs = [];
        foreach (glob($this->baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $nombre = basename($d);
            if (in_array($nombre, ['Doc_y_Config', 'plantillas'], true) || str_starts_with($nombre, '.') || str_starts_with($nombre, '_')) {
                continue;
            }
            if ($permitidas !== null) {
                $cfg = json_decode((string) @file_get_contents($d.'/cliente.json'), true);
                if (! in_array((int) ($cfg['entidad_id'] ?? 0), $permitidas, true)) {
                    continue;
                }
            }
            $dirs[] = $nombre;
        }
        natcasesort($dirs);
        return array_values($dirs);
    }

    protected function clienteValido(): bool
    {
        return in_array($this->cliente, $this->clientes(), true);
    }

    /** Ficheros sueltos (no carpetas) de <cliente>/<sub>, más recientes primero si $recientes. */
    protected function ficheros(string $sub, bool $recientes = false): array
    {
        if (! $this->clienteValido()) {
            return [];
        }
        $dir = $this->baseDir().'/'.$this->cliente.'/'.$sub;
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $f) {
            if (is_file($f) && ! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        natcasesort($out);
        $out = array_values($out);
        return $recientes ? array_reverse($out) : $out;
    }

    public function updatedCliente(): void
    {
        $this->sincronizarCentral();
        $this->resultados = [];
        $this->cuenta = '';
        $this->extractos = $this->cuentasExtracto = $this->origenCuenta = [];
        $this->mapeo = null;
        $this->revisar = '';
        $this->cargarMaestro();
        $this->cargarFormatos();
        $this->cargarRevision();
    }

    protected function basePath(): string
    {
        return $this->baseDir().'/'.$this->cliente.'/Base/Base '.$this->cliente.'.xlsx';
    }

    /**
     * Cuentas de banco cargadas en la base: las pestañas con código de cuenta
     * (572003, 551002...), con su nombre sacado de la pestaña "Plan cuentas".
     * [codigo => nombre]
     */
    protected function cuentasBanco(): array
    {
        if (! $this->clienteValido() || ! is_file($this->basePath())) {
            return [];
        }
        try {
            $hojas = IOFactory::createReader('Xlsx')->listWorksheetNames($this->basePath());
        } catch (\Throwable $e) {
            return [];
        }
        $codigos = array_values(array_filter($hojas, fn ($n) => preg_match('/^\d{6,}$/', $n)));
        sort($codigos);
        $out = [];
        foreach ($codigos as $c) {
            $out[$c] = $this->planCuentas[$c] ?? '';
        }
        return $out;
    }

    /** Lee Maestro + Variables + plan de cuentas de la base (bancos_maestro.py listar). */
    public function cargarMaestro(): void
    {
        $this->maestro = [];
        $this->planCuentas = [];
        $this->avisoMaestro = '';
        $this->estadoBase = [];
        $this->cuentasNuevas = [];
        if (! $this->clienteValido()) {
            return;
        }
        try {
            // También sin base: las otras cuentas de banco se pueden marcar antes del primer mayor
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'bancos_base.py', $this->cliente, '--estado']);
            $this->estadoBase = $r->successful() ? (json_decode($r->output(), true) ?: []) : [];
        } catch (\Throwable $e) {
            // Sin el estado solo se pierde el resumen de cada fila; no debe romper la pantalla.
        }
        if (! is_file($this->basePath())) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'cuentas_nuevas']);
            $this->cuentasNuevas = $r->successful() ? (json_decode($r->output(), true)['cuentas'] ?? []) : [];
        } catch (\Throwable $e) {
            $this->cuentasNuevas = [];
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'listar']);
            $datos = json_decode($r->output(), true);
            if (! $r->successful() || ! is_array($datos)) {
                $this->avisoMaestro = 'No se ha podido leer el Maestro: '.trim($r->output()."\n".$r->errorOutput());
                return;
            }
            $this->maestro = $datos['filas'] ?? [];
            $this->planCuentas = $datos['cuentas'] ?? [];
        } catch (\Throwable $e) {
            $this->avisoMaestro = 'No se ha podido leer el Maestro: '.$e->getMessage();
        }
    }

    /**
     * Alta o cambio de una fila manual del Maestro (se guarda en Variables). $signo: '+' solo
     * cobros, '-' solo pagos, '' los dos; una fila manual es concepto + signo.
     */
    public function guardarMaestro(string $concepto, string $cuenta, string $anterior = '', string $signo = '', string $signoAnterior = '', string $nombreNueva = ''): void
    {
        $this->editarMaestro([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'guardar', $concepto, trim($cuenta), $anterior, $signo, $signoAnterior, $nombreNueva]);
    }

    public function borrarMaestro(string $concepto, string $signo = ''): void
    {
        $this->editarMaestro([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'borrar', $concepto, $signo]);
    }

    protected function editarMaestro(array $args): void
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · Maestro');
            return;
        }
        if (! $this->clienteValido()) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run($args);
            $texto = trim($r->output()."\n".$r->errorOutput());
        } catch (\Throwable $e) {
            $r = null;
            $texto = $e->getMessage();
        }
        $ok = $r && $r->successful();
        $this->dispatch('proceso-terminado', mensaje: ($ok ? '✅ ' : '⚠️ ')."Maestro {$this->cliente}\n{$texto}");
        $this->cargarMaestro();
    }

    /**
     * Revisión en pantalla de un Output/bancos<cuenta>.xlsx (bancos_maestro.py lineas): se rellenan
     * las contrapartidas vacías (y, si se quiere, "Concepto para el Maestro" y "Vale para") y
     * generarBancos() escribe el fichero con la estructura de siempre y el programa aprende.
     */
    public function updatedRevisar(): void
    {
        $this->cargarRevision();
    }

    public function cargarRevision(): void
    {
        $this->lineasRevisar = [];
        $this->avisoRevisar = '';
        $this->revisionN++;
        if ($this->revisar === '' || ! $this->clienteValido()) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(60)->run([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'lineas', $this->revisar]);
            $datos = json_decode($r->output(), true);
            if (! is_array($datos) || isset($datos['error'])) {
                $this->avisoRevisar = $datos['error'] ?? trim($r->output()."\n".$r->errorOutput());
                return;
            }
            $this->lineasRevisar = $datos['lineas'] ?? [];
        } catch (\Throwable $e) {
            $this->avisoRevisar = $e->getMessage();
        }
    }

    /** $lineas: [{numero, contrapartida, concepto_maestro, vale}]; $nuevas: {codigo: nombre} a crear en el plan. */
    public function generarBancos(array $lineas, array $nuevas = [])
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · generar '.$this->revisar);
            return null;
        }
        if ($this->revisar === '' || ! $this->clienteValido()) {
            return null;
        }
        $limpias = array_map(fn ($l) => [
            'numero' => (int) ($l['numero'] ?? 0),
            'contrapartida' => trim((string) ($l['contrapartida'] ?? '')),
            'concepto_maestro' => trim((string) ($l['concepto_maestro'] ?? '')),
            'vale' => in_array($l['vale'] ?? '', ['+', '-'], true) ? $l['vale'] : '',
        ], $lineas);
        $json = tempnam(sys_get_temp_dir(), 'bancos');
        file_put_contents($json, json_encode(['lineas' => $limpias, 'nuevas' => (object) $nuevas], JSON_UNESCAPED_UNICODE));
        $etiqueta = "Bancos · {$this->cliente} · generar {$this->revisar}";
        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'aplicar', $this->revisar, $json], 120, $etiqueta);
        @unlink($json);
        $this->anexarResultados($archivos);
        $this->cargarMaestro();
        $this->cargarRevision();
        return $archivos ? $this->descargar('Output/'.$this->revisar) : null;
    }

    /** Busca en internet el CIF y el CP de una cuenta creada aquí (propuesta, no guarda). */
    public function buscarDatosCuenta(string $cuenta): void
    {
        if (! config('contabilidad.bancos_ejecucion') || ! $this->clienteValido()) {
            return;
        }
        try {
            $r = Process::path($this->baseDir())->timeout(200)->run([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'buscar_datos', $cuenta]);
            $datos = json_decode($r->output(), true);
            $this->propuestasCif[$cuenta] = is_array($datos) ? $datos : ['error' => trim($r->output()."\n".$r->errorOutput())];
        } catch (\Throwable $e) {
            $this->propuestasCif[$cuenta] = ['error' => $e->getMessage()];
        }
    }

    public function guardarDatosCuenta(string $cuenta, string $cif, string $cp): void
    {
        unset($this->propuestasCif[$cuenta]);
        $this->editarMaestro([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'guardar_datos', $cuenta, trim($cif), trim($cp)]);
    }

    /** Varios bancos<cuenta>.xlsx en uno para importarlo en SAGE de una vez (bancos_maestro.py juntar). */
    public function juntarBancos(array $ficheros)
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · juntar ficheros');
            return null;
        }
        $ficheros = array_values(array_filter($ficheros, fn ($f) => is_string($f) && preg_match('/^bancos[\w\- ]*\.xlsx$/i', $f)));
        if (! $this->clienteValido() || count($ficheros) < 2) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ Marca al menos dos ficheros para juntarlos.');
            return null;
        }
        $etiqueta = "Bancos · {$this->cliente} · juntar ".implode(' + ', $ficheros);
        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript(array_merge([$this->pythonBin(), 'bancos_maestro.py', $this->cliente, 'juntar'], $ficheros), 120, $etiqueta);
        $this->anexarResultados($archivos);

        return $archivos ? $this->descargar('Output/'.basename($archivos[0])) : null;
    }

    /**
     * Propone la cuenta de un extracto por su nombre: empieza por el código de cuenta (572003...) o lleva
     * el nº de cuenta del banco (0077994) que ya se vio en un extracto anterior de esa cuenta (nombres
     * de Input/input_old y lo aprendido en Base/cuentas_extracto.json). Devuelve [cuenta, motivo].
     */
    protected function proponerCuenta(string $nombre): array
    {
        $cuentas = $this->cuentasBanco();
        if (preg_match('/^(\d{6,})/', $nombre, $m) && array_key_exists($m[1], $cuentas)) {
            return [$m[1], 'por el nombre del fichero'];
        }
        preg_match_all('/(?<!\d)\d{5,}(?!\d)/', $nombre, $nums);
        $mapa = $this->cuentasPorNumeroBanco();
        foreach ($nums[0] as $n) {
            $clave = ltrim($n, '0');
            if ($clave !== '' && isset($mapa[$clave]) && array_key_exists($mapa[$clave], $cuentas)) {
                return [$mapa[$clave], "porque el nº {$n} ya se usó en extractos de la {$mapa[$clave]}"];
            }
        }
        return ['', ''];
    }

    /** [nº de cuenta del banco sin ceros => cuenta 572...] aprendido de los extractos ya procesados. */
    protected function cuentasPorNumeroBanco(): array
    {
        $mapa = [];
        $json = $this->baseDir().'/'.$this->cliente.'/Base/cuentas_extracto.json';
        if (is_file($json)) {
            $mapa = array_filter((array) json_decode((string) file_get_contents($json), true), 'is_string');
        }
        foreach (['Input', 'Input/input_old'] as $sub) {
            foreach (glob($this->baseDir().'/'.$this->cliente.'/'.$sub.'/*') ?: [] as $f) {
                $n = basename($f);
                if (preg_match('/(?<!\d)(5\d{5})(?!\d)[ _-]+0*(\d{5,})(?!\d)/', $n, $m)) {
                    $mapa[$m[2]] ??= $m[1];
                }
            }
        }
        return $mapa;
    }

    /** Recuerda que el nº de cuenta del banco que lleva el nombre de este extracto es de esta cuenta 572. */
    protected function aprenderCuentaExtracto(string $nombre, string $cuenta): void
    {
        if (preg_match('/^\d{6,}/', $nombre)) {
            return; // ya lleva la cuenta delante: no hace falta aprender nada
        }
        preg_match_all('/(?<!\d)\d{5,}(?!\d)/', $nombre, $nums);
        if (empty($nums[0])) {
            return;
        }
        $json = $this->baseDir().'/'.$this->cliente.'/Base/cuentas_extracto.json';
        $mapa = is_file($json) ? (array) json_decode((string) file_get_contents($json), true) : [];
        foreach ($nums[0] as $n) {
            $mapa[ltrim($n, '0')] = $cuenta;
        }
        @file_put_contents($json, json_encode($mapa, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Los ficheros recién subidos pasan a la lista, cada uno con su cuenta propuesta (se puede cambiar). */
    public function anadirExtractos(): void
    {
        $this->resetErrorBag(['extractos', 'cuenta']);
        $nuevos = array_values(array_filter($this->nuevosExtractos, fn ($f) => $f instanceof UploadedFile));
        $this->nuevosExtractos = [];
        $malos = [];
        foreach ($nuevos as $f) {
            $nombre = $f->getClientOriginalName();
            if (! in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
                $malos[] = $nombre;
                continue;
            }
            [$cuenta, $motivo] = $this->proponerCuenta($nombre);
            $this->extractos[] = $f;
            $this->cuentasExtracto[] = $cuenta;
            $this->origenCuenta[] = $motivo;
        }
        if ($malos) {
            $this->addError('extractos', 'Solo Excel (.xlsx / .xls). No se han añadido: '.implode(', ', $malos));
        }
    }

    public function quitarExtracto(int $i): void
    {
        unset($this->extractos[$i], $this->cuentasExtracto[$i], $this->origenCuenta[$i]);
        $this->extractos = array_values($this->extractos);
        $this->cuentasExtracto = array_values($this->cuentasExtracto);
        $this->origenCuenta = array_values($this->origenCuenta);
    }

    public function updatedCuentasExtracto(): void
    {
        $this->origenCuenta = array_map(fn ($o, $i) => ($this->cuentasExtracto[$i] ?? '') === '' ? '' : $o, $this->origenCuenta, array_keys($this->origenCuenta));
    }

    /** Concilia, uno tras otro, todos los extractos de la lista contra la cuenta elegida para cada uno. */
    public function conciliar(): void
    {
        $this->resetErrorBag(['extractos', 'cuenta']);
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado('Bancos · extractos');
            return;
        }
        if (! $this->clienteValido()) {
            $this->addError('extractos', 'Elige primero un cliente.');
            return;
        }
        if (! $this->extractos) {
            $this->addError('extractos', 'Sube al menos un extracto del banco.');
            return;
        }
        $cuentas = $this->cuentasBanco();
        foreach ($this->extractos as $i => $f) {
            if (! array_key_exists($this->cuentasExtracto[$i] ?? '', $cuentas)) {
                $this->addError('extractos', 'Falta la cuenta de «'.$f->getClientOriginalName().'»: elígela en su fila.');
                return;
            }
        }

        $dir = $this->baseDir().'/'.$this->cliente.'/Input';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('extractos', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }
        $this->salida = '';
        $hechos = [];
        foreach ($this->extractos as $i => $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            $destino = "{$dir}/{$nombre}";
            if (file_exists($destino)) {
                $destino = "{$dir}/".date('Ymd-His')." {$nombre}";
            }
            if (! @copy($f->getRealPath(), $destino)) {
                $this->addError('extractos', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                break;
            }
            $this->cuenta = $this->cuentasExtracto[$i];
            $this->aprenderCuentaExtracto($nombre, $this->cuenta);
            $hechos[] = $i;
            if (! $this->procesar($destino)) {
                break; // formato desconocido: se abre la pantalla de columnas y el resto queda en la lista
            }
        }
        foreach (array_reverse($hechos) as $i) {
            $this->quitarExtracto($i);
        }
        // un extracto con formato desconocido se vuelve a procesar desde la pantalla de columnas, no desde la lista
    }

    /** bancos_conciliacion.py con un extracto ya guardado en Input; si no sabe leerlo, pide asignar las columnas. */
    protected function procesar(string $destino): bool
    {
        $etiqueta = "Bancos · {$this->cliente} · bancos{$this->cuenta}";
        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $inicio = strlen($this->salida);
        $archivos = $this->ejecutarScript([$this->pythonBin(), 'bancos_conciliacion.py', $this->cliente, '--partida', $this->cuenta, $destino], 180, $etiqueta);
        $this->anexarResultados($archivos);
        foreach ($archivos as $a) {
            if (preg_match('/^bancos.*\.xlsx$/i', basename($a))) {
                $this->revisar = basename($a); // se abre directamente para revisar
            }
        }
        $this->cargarMaestro(); // la conciliación añade a Variables lo que no encuentra
        $this->cargarFormatos(); // y guarda el formato si lo ha detectado solo
        $this->cargarRevision();
        $nueva = substr($this->salida, $inicio);
        if (preg_match('/^FORMATO_DESCONOCIDO:\s*(.+?)\s*$/m', $nueva, $m)) {
            $this->salida = substr($this->salida, 0, $inicio).preg_replace('/^FORMATO_DESCONOCIDO:.*(\r?\n)?/m', '', $nueva)
                ."\n→ Asigna las columnas del extracto en la ventana que se ha abierto.";
            preg_match('/^AVISO:\s*(.+?) -- se omite/m', $nueva, $motivo);
            $this->abrirMapeo($m[1], $this->cuenta, $motivo[1] ?? '');
            return false;
        }
        return true;
    }

    /** Descarga un fichero de la carpeta del cliente (Output/..., Base/...). */
    public function descargar(string $relativa)
    {
        $ruta = $this->rutaCliente($relativa);
        if (! $ruta) {
            $this->addError('extracto', "No se encuentra {$relativa}.");
            return null;
        }
        return response()->download($ruta, basename($ruta));
    }

    public function procesarSubidas(string $fila): void
    {
        $this->resetErrorBag('subidas');
        $ficheros = array_values(array_filter($this->subidas, fn ($f) => $f instanceof UploadedFile));
        $this->subidas = [];
        if (! $ficheros) {
            return;
        }

        $etiqueta = "Bancos · {$this->cliente} · ".($fila === 'plan' ? 'plan de cuentas' : 'mayor');
        if (! in_array($fila, ['plan', 'mayor'], true)) {
            $this->addError('subidas', 'Fila desconocida.');
            return;
        }
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado($etiqueta);
            return;
        }
        if (! $this->clienteValido()) {
            $this->addError('subidas', 'Elige primero un cliente.');
            return;
        }

        $malos = [];
        foreach ($ficheros as $f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
                $malos[] = $f->getClientOriginalName();
            }
        }
        if ($malos) {
            $this->addError('subidas', 'Solo Excel (.xlsx / .xls). No se ha subido nada; sobran: '.implode(', ', $malos));
            return;
        }

        $dir = $this->baseDir().'/'.$this->cliente.'/Base/Recibidos';
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
            $this->addError('subidas', "No se ha podido crear la carpeta {$this->rutaWindows($dir)}.");
            return;
        }

        $sello = date('Ymd-His');
        $rutas = [];
        foreach ($ficheros as $f) {
            $nombre = str_replace(['/', '\\'], '_', $f->getClientOriginalName());
            $destino = "{$dir}/{$sello} {$nombre}";
            if (! @copy($f->getRealPath(), $destino)) {
                $this->addError('subidas', "No se ha podido guardar {$nombre} en {$this->rutaWindows($dir)}.");
                return;
            }
            $rutas[] = $destino;
            // Los ficheros base valen para todos los procesos de la empresa: se copian también a los centrales
            if (($eid = $this->entidadIdCliente()) && ! \App\Support\FicherosBase::existeContenido($eid, $fila, $destino)) {
                \App\Support\FicherosBase::guardar($eid, $fila, $destino, $nombre, 'Bancos');
            }
        }

        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $archivos = $this->ejecutarScript(array_merge([$this->pythonBin(), 'bancos_base.py', $this->cliente, '--espera', $fila], $rutas), 120, $etiqueta);
        $this->anexarResultados($archivos);
        $this->cargarMaestro();
    }

    protected function entidadIdCliente(): int
    {
        $cfg = json_decode((string) @file_get_contents($this->baseDir().'/'.$this->cliente.'/cliente.json'), true);

        return (int) ($cfg['entidad_id'] ?? 0);
    }

    /**
     * Ficheros base CENTRALES de la empresa (mayor y plan de cuentas, App\Support\FicherosBase): si se subieron en otro proceso (Facturas OCR...) y aquí aún no, se
     * pasan por el mismo bancos_base.py que una subida de esta pantalla (solo añade filas nuevas; antes se guarda una copia de la base). Se recuerda lo ya visto por su huella.
     */
    protected function sincronizarCentral(): void
    {
        if ($this->cliente === '' || ! $this->clienteValido() || ! config('contabilidad.bancos_ejecucion') || ! ($eid = $this->entidadIdCliente())) {
            return;
        }
        try {
            $baseCli = $this->baseDir().'/'.$this->cliente.'/Base';
            $marca = $baseCli.'/central_visto.json';
            $visto = is_file($marca) ? (json_decode((string) file_get_contents($marca), true) ?: []) : [];
            foreach (['plan', 'mayor'] as $tipo) {
                $c = \App\Support\FicherosBase::ultimo($eid, $tipo);
                if (! $c) {
                    continue;
                }
                $sha = hash_file('sha256', $c['ruta']);
                if (($visto[$tipo] ?? null) === $sha) {
                    continue;
                }
                $yaRecibido = false;   // mismo contenido ya subido aquí (a mano o desde otro cliente de pantalla)
                foreach (glob($baseCli.'/Recibidos/*') ?: [] as $f) {
                    $yaRecibido = $yaRecibido || (is_file($f) && filesize($f) === filesize($c['ruta']) && hash_file('sha256', $f) === $sha);
                }
                if (! $yaRecibido) {
                    foreach (glob($baseCli.'/Base *.xlsx') ?: [] as $b) {   // copia de seguridad de la base antes de tocarla (se guardan las 5 últimas)
                        @mkdir($baseCli.'/copias', 0775, true);
                        @copy($b, $baseCli.'/copias/'.pathinfo($b, PATHINFO_FILENAME).' '.date('Ymd-His').'.xlsx');
                    }
                    $cop = glob($baseCli.'/copias/*.xlsx') ?: [];
                    rsort($cop);
                    foreach (array_slice($cop, 5) as $viejo) {
                        @unlink($viejo);
                    }
                    @mkdir($baseCli.'/Recibidos', 0775, true);
                    $destino = $baseCli.'/Recibidos/'.date('Ymd-His').' '.str_replace(['/', '\\'], '_', $c['nombre']);
                    if (@copy($c['ruta'], $destino)) {
                        $this->salida .= "\n===== Bancos · {$this->cliente} · fichero base central ({$tipo}: {$c['nombre']}) =====\n";
                        $this->ejecutarScript([$this->pythonBin(), 'bancos_base.py', $this->cliente, '--espera', $tipo, $destino], 300, "Bancos · {$this->cliente} · {$tipo} del central");
                    }
                }
                $visto[$tipo] = $sha;
            }
            @file_put_contents($marca, json_encode((object) $visto));
        } catch (\Throwable $e) {
            report($e);   // no impide abrir la pantalla
        }
    }

    /** Marca (o desmarca) como de banco una cuenta que no empieza por 572; al marcarla se saca de los mayores ya subidos. */
    public function anadirOtraCuenta(): void
    {
        $cuenta = trim($this->otraCuenta);
        if (! preg_match('/^\d{6,}$/', $cuenta)) {
            $this->addError('otraCuenta', 'Escribe la cuenta completa (6 cifras o más).');
            return;
        }
        $this->otraCuenta = '';
        $this->editarOtrasCuentas('anadir', $cuenta);
    }

    public function quitarOtraCuenta(string $cuenta): void
    {
        $this->editarOtrasCuentas('quitar', $cuenta);
    }

    protected function editarOtrasCuentas(string $accion, string $cuenta): void
    {
        $this->resetErrorBag('otraCuenta');
        if (! $this->clienteValido()) {
            return;
        }
        $etiqueta = "Bancos · {$this->cliente} · ".($accion === 'anadir' ? 'añadir' : 'quitar')." cuenta de banco {$cuenta}";
        $this->resultados = [];
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript([$this->pythonBin(), 'bancos_base.py', $this->cliente, '--otras-cuentas', $accion, $cuenta], 120, $etiqueta);
        $this->cargarMaestro();
    }

    /** Igual que Durcal::pythonBin() -- venv propio si existe, si no el python3 del sistema. */
    protected function pythonBin(): string
    {
        $venvPython = $this->baseDir().'/.venv/bin/python3';
        return is_file($venvPython) ? $venvPython : 'python3';
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    /** /mnt/e/Foo/Bar -> E:\Foo\Bar */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]).':\\'.str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    /** /mnt/e/Foo/Bar -> file:///E:/Foo/Bar */
    protected function fileUrl(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return 'file:///'.strtoupper($m[1]).':/'.str_replace('%2F', '/', rawurlencode($m[2]));
        }
        return 'file://'.$p;
    }

    /** Añade rutas RESULT_FILE a $resultados, sin duplicar (mismo mecanismo que Contabilidad\Procesos). */
    protected function anexarResultados(array $rutas): void
    {
        $yaEstan = array_column($this->resultados, 'ruta');
        foreach ($rutas as $ruta) {
            $win = $this->rutaWindows($ruta);
            if (in_array($win, $yaEstan, true)) {
                continue;
            }
            $yaEstan[] = $win;
            $raiz = $this->baseDir().'/'.$this->cliente.'/';
            $relativa = str_starts_with($ruta, $raiz) ? substr($ruta, strlen($raiz)) : basename($ruta);
            $this->resultados[] = ['ruta' => $win, 'url' => $this->fileUrl($ruta), 'relativa' => $relativa];
        }
    }

    protected function avisarNoAutorizado(string $etiqueta): void
    {
        $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
        $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
    }

    /**
     * Igual que Durcal::ejecutarScript: cualquier fallo se convierte en un
     * aviso en pantalla, nunca en un error que rompa la página. Devuelve las
     * rutas absolutas marcadas por el script con líneas "RESULT_FILE: <ruta>".
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): array
    {
        if (! config('contabilidad.bancos_ejecucion')) {
            $this->avisarNoAutorizado($etiqueta);
            return [];
        }

        $resultFiles = [];
        try {
            $result = Process::path($this->baseDir())->timeout($timeout)->run($args);
            $texto = trim($result->output()."\n".$result->errorOutput());
            if (preg_match_all('/^RESULT_FILE:\s*(.+?)\s*$/m', $texto, $m)) {
                $resultFiles = array_map('trim', $m[1]);
                $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $texto));
            }
            $this->salida .= $texto;
            if ($result->successful()) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida ".$result->exitCode().'.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con avisos o errores (código ".$result->exitCode().'). Mira la caja de Salida para el detalle.');
            }
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
        }

        return $resultFiles;
    }

    public function render()
    {
        return view('livewire.contabilidad.bancos', [
            'clientes' => $this->clientes(),
            'recibidos' => array_slice($this->ficheros('Base/Recibidos', true), 0, 15),
            'cuentas' => $this->cuentasBanco(),
            'hayBase' => $this->clienteValido() && is_file($this->basePath()),
            'pendientes' => $this->ficheros('Input'),
            'generados' => $this->ficheros('Output'),
        ]);
    }
}
