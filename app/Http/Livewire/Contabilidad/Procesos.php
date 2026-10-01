<?php

namespace App\Http\Livewire\Contabilidad;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Pantalla para lanzar, desde Appmos, los scripts Node/Python de
 * Contabilidad/monthlyFIQ (Anaplan, Laboral, Monthly sales, RentasVariables)
 * sin tener que entrar por consola. Pedido explícito del usuario
 * (2026-09-06): "podrías poner botones para ejecutarlos independientemente
 * o marcar un check para ejecutar todos los que estén marcados seguidos."
 *
 * Como Appmos corre en la misma máquina que los ficheros de OneDrive, los
 * scripts se ejecutan tal cual (sin API de Graph/OneDrive) -- ver
 * PROCESO_GENERAL.md en Contabilidad/monthlyFIQ para el detalle de cada uno.
 */
class Procesos extends Component
{
    public int $mes;
    public string $salida = '';
    // Ya no hay check "Modo real" (pedido del usuario 2026-09-09: "siempre va a
    // ser real"). Todos los procesos que soportan --real lo pasan siempre.

    /**
     * Ficheros resultado de la última ejecución de cada proceso, para enseñar
     * un enlace en la pantalla (pedido del usuario 2026-09-09). Forma:
     * ['monthly_sales' => [['ruta' => 'E:\\...\\x.xlsx', 'url' => 'file:///E:/.../x.xlsx'], ...]].
     * Los scripts los marcan con una línea "RESULT_FILE: <ruta absoluta>".
     */
    public array $resultados = [];

    /** ¿Terminó bien el último ejecutarScript()? (para marcar el checklist) */
    protected bool $ultimoOk = false;

    // RentasVariables (formularios aparte, no encajan en el check general).
    // Dos acciones (ver PROCESO_GENERAL.md en Contabilidad/monthlyFIQ):
    //  - Cálculos + Declaración (un solo botón, pedido del usuario 2026-09-09):
    //      * calculosRentasVariables.js -> SIEMPRE las 4 tiendas con renta
    //        variable (La Roca, Las Rozas, Málaga, Barcelona). No depende de
    //        $rvTiendas.
    //      * rentasVariablesDeclaracion.js -> solo las tiendas marcadas de
    //        $rvTiendas (BCN / MAL, únicas con arrendador externo).
    //  - Envío -> solo BCN / MAL, un envío por tienda con sus destinatarios.
    // Siempre real: ya no hay check "Proceso real" (pedido 2026-09-09).
    /** Tiendas marcadas para la Declaración a arrendador. Valores: 'BCN', 'MAL'. */
    public array $rvTiendas = ['BCN', 'MAL'];
    // RentasVariables usa el mismo "$mes" que el resto (un solo desplegable en
    // el título, pedido del usuario 2026-09-10).

    // Envío del correo: estado por tienda con destinatarios EDITABLES en pantalla
    // (pedido del usuario 2026-09-09). Precargados en mount() con los mismos
    // valores por defecto que enviarRentasVariables.py::TIENDAS -- ese script
    // sigue siendo la autoridad del envío real y su red de seguridad si aquí
    // se dejan vacíos. Forma: ['BCN' => ['to'=>..,'cc'=>..,'correccion'=>false], ...].
    //
    // Sin casilla "enviar de verdad" (pedido del usuario 2026-09-09): el botón
    // "Enviar" de cada tienda manda YA a sus destinatarios reales (único gate:
    // el confirm()). Aparte, un botón "Enviar a correo de prueba" manda los DOS
    // ficheros (Barcelona + Málaga) a $rvEmailPrueba.
    public array $rvEnvio = [];
    public string $rvEmailPrueba = '';

    // Correo mensual "End and begining of month payments <Month>." a Plein
    // (pedido del usuario 2026-09-25: "este proceso lo repetiremos todos los
    // meses"). Solo cambian estos datos, en K; el resto de filas (alquileres,
    // suministros...) sale de monthlyFIQ/pagosFinMes.json. Mes propio: es el
    // mes de los cargos (el actual), no el mes cerrado del desplegable.
    public int $pfMes;
    public string $pfSaldo = '';
    public string $pfIva = '';
    public string $pfSs = '';
    public string $pfNominas = ''; // vacío = total de la remesa Laboral 2026/<MM>/RM*.xml
    public string $pfCargo = '';   // vacío = último día hábil del mes ("Wednesday 30th")
    public string $pfEmailPrueba = 'alex.arregui@sumaempresa.com';
    // Texto, frase en negrita (opcional) y destinatarios: se precargan de
    // pagosFinMes.json, que el script reescribe al enviar a Plein -> lo que sale
    // un mes queda de base para el siguiente (pedido del usuario 2026-09-25).
    public string $pfTexto = '';
    public string $pfDestacado = '';
    public bool $pfIncluirDestacado = false;
    public string $pfTo = '';
    public string $pfCc = '';
    // Recordatorio "Kindly reminder and update" (pedido 2026-09-28): parte del
    // correo ya enviado ese mes (Enviados de Outlook), cada fila con su importe
    // retocable y Paid/Pending, y se prepara como "Responder a todos" en
    // Borradores de Outlook. No guarda nada: el mes siguiente sale como siempre.
    public array $pfRecFilas = [];   // [[c1, c2, importe K, c4, 'pending'|'paid'], ...]
    public string $pfRecOriginal = '';
    public string $pfRecSaldo = '';
    public string $pfRecTexto = '';

    /**
     * Destinatarios por defecto por tienda. Duplicado a propósito de
     * enviarRentasVariables.py::TIENDAS (mismo criterio "procesos aislados" del
     * resto del código); si cambian los reales, se tocan LOS DOS sitios.
     */
    protected function rvDestinatariosPorDefecto(): array
    {
        return [
            'BCN' => [
                'to' => 'ahernandez@mohg.com',
                'cc' => 'xruano@mohg.com, mfernandez@mohg.com',
                'correccion' => false,
            ],
            'MAL' => [
                'to' => 'Turnover.Malaga@mcarthurglen.com',
                'cc' => 'Stefania.Nicolai@mcarthurglen.com',
                'correccion' => false,
            ],
        ];
    }

    /** Tiendas de RentasVariables con arrendador externo (Declaración + Envío). */
    protected function rvTiendasArrendador(): array
    {
        return ['BCN' => 'Barcelona', 'MAL' => 'Málaga'];
    }

    public function mount(): void
    {
        // Por defecto, el mes ANTERIOR al actual (pedido del usuario 2026-09-09:
        // "que por defecto el mes sea el de la fecha actual menos 1").
        $m = (int) date('n') - 1;
        $this->mes = $m < 1 ? 12 : $m;
        $this->rvEnvio = $this->rvDestinatariosPorDefecto();
        $this->pfMes = (int) date('n');
        $this->cargarBasePagosFinMes();
        $this->cargarCashInStore();
    }

    protected function confPagosFinMes(): array
    {
        try {
            return json_decode((string) @file_get_contents($this->scriptDir() . '/pagosFinMes.json'), true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function cargarBasePagosFinMes(): void
    {
        $conf = $this->confPagosFinMes();
        $this->pfTexto = (string) ($conf['texto'] ?? '');
        $this->pfDestacado = (string) ($conf['destacado'] ?? '');
        $this->pfIncluirDestacado = (bool) ($conf['incluir_destacado'] ?? false);
        $this->pfTo = implode(', ', $conf['to'] ?? []);
        $this->pfCc = implode(', ', $conf['cc'] ?? []);
    }

    public function getRvTiendasArrendadorProperty(): array
    {
        return $this->rvTiendasArrendador();
    }

    /**
     * 2026-09-20: la carpeta Claude vive en una unidad distinta según el PC
     * (`/mnt/e/Claude` en uno, `/mnt/f/Claude` en otro -- letra de unidad de
     * Windows, no algo que controlemos). Se prueban las dos, igual que
     * monthlyFIQ.js hace con Clientes/_Clientes; si aparece una tercera
     * variante, añadirla aquí sin quitar las anteriores.
     */
    protected function scriptDir(): string
    {
        foreach (['/mnt/e/Claude/Contabilidad/monthlyFIQ', '/mnt/f/Claude/Contabilidad/monthlyFIQ'] as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return '/mnt/e/Claude/Contabilidad/monthlyFIQ';
    }

    /**
     * `node --jitless ...` en vez de `node ...`. Encontrado 2026-09-07: el
     * servicio systemd de apache2 tiene `MemoryDenyWriteExecute=yes`
     * (hardening por defecto de Ubuntu), que bloquea el JIT de V8 heredado
     * por cualquier hijo de Apache -- Node moría con
     * `ProcessSignaledException: signal "5"` (SIGTRAP) nada más arrancar en
     * CUALQUIER script. Mismo síntoma que el aviso ya presente desde hace
     * meses en /appmos-error.log sobre el JIT de PCRE de PHP fallando por
     * "security restrictions". `--jitless` desactiva el JIT de V8 (solo
     * intérprete) y evita la mprotect(PROT_EXEC) que el sandbox bloquea; para
     * estos scripts (E/S y regex sobre ficheros pequeños, nada de bucles
     * pesados) el coste de rendimiento es despreciable. Alternativa
     * descartada: quitar `MemoryDenyWriteExecute` del servicio de Apache
     * entero solo para esto debilitaría el sandbox de TODO Apache, no merece
     * la pena.
     */
    /**
     * 2026-09-20: `node` de este PC viene de nvm
     * (`~/.nvm/versions/node/*\/bin/node`), que no está en el PATH mínimo que
     * ve Apache bajo systemd ("sh: 1: exec: node: not found") -- mismo tipo
     * de gotcha que `pythonBin()` en FacturacionPdf.php. Si no se encuentra
     * ninguna instalación de nvm (otra máquina con node de sistema), se cae
     * al `node` del PATH tal cual.
     */
    /**
     * 2026-09-20, segunda vuelta: Apache corre estos scripts como `www-data`
     * (no como `mosketxu`), y el home de `www-data` es `/var/www` -- así que
     * ni `getenv('HOME')` ni `posix_getpwuid(posix_geteuid())` (probado y
     * descartado: devuelve el usuario del PROCESO, no el dueño de nvm) sirven
     * para encontrar el nvm de `mosketxu`. Se prueban ambos por si algún día
     * se lanza desde un shell de `mosketxu` con HOME real, pero la ruta fija
     * es la que de verdad hace falta aquí.
     */
    protected function nodeBin(): string
    {
        $candidatos = array_merge(
            glob(getenv('HOME').'/.nvm/versions/node/*/bin/node') ?: [],
            glob('/home/mosketxu/.nvm/versions/node/*/bin/node') ?: [],
            ['/usr/local/bin/node', '/usr/bin/node']
        );
        foreach ($candidatos as $c) {
            if (is_file($c) && is_executable($c)) {
                return $c;
            }
        }

        return 'node';
    }

    protected function nodeCmd(string $script, array $rest = []): array
    {
        return [$this->nodeBin(), '--jitless', $script, ...$rest];
    }

    /**
     * 2026-09-23: `node` de WINDOWS vía cmd.exe (interop de WSL), para scripts
     * que controlan el Chrome de Windows (anaplanWeb/subirAnaplan.js: se conecta
     * a localhost:9222 y usa el portapapeles de Windows, inalcanzables desde el
     * node de WSL). Bajo Apache el interop falla en silencio (rc=1, sin salida)
     * porque falta WSL_INTEROP: ver windowsEnv(). Se ejecuta con cwd = carpeta
     * del script (cmd.exe traduce /mnt/e/... a E:\...).
     */
    protected function windowsCmd(string $script, array $rest = []): array
    {
        return ['/mnt/c/Windows/System32/cmd.exe', '/c', 'node', basename($script), ...$rest];
    }

    /** `/run/WSL/1_interop` es el enlace estable al socket de interop del init de WSL. */
    protected function windowsEnv(): array
    {
        return ['WSL_INTEROP' => '/run/WSL/1_interop'];
    }

    /**
     * Orden fijo (es el orden en que se ejecutan si se marcan varios a la vez).
     * "Monthly sales" va PRIMERO (pedido del usuario 2026-09-09: se lo saltó
     * por tenerlo el último). No depende de la salida de los demás -- lee
     * SyS MM.xlsx, Ctrol Dinamico y Laboral directamente.
     */
    protected function procesos(): array
    {
        // Todos escriben sobre los ficheros reales (ya no hay check "Modo real"
        // ni copias de seguridad -- pedido del usuario 2026-09-09).
        return [
            'monthly_sales' => [
                'label' => 'Monthly sales',
                'script' => 'monthlyFIQ.js',
                'soportaReal' => true,
                'ayuda' => 'Prepara Monthly Sales y sincroniza con Ctrol Dinamico.',
            ],
            'anaplan' => [
                'label' => 'Anaplan',
                // Un solo botón = los 3 pasos seguidos, en este orden (pedido del
                // usuario 2026-09-09: siempre se lanzan juntos). "desviaciones"
                // lee el SyS 2026.xlsx que "consolida" acaba de escribir, así que
                // el orden importa.
                'scripts' => ['sysSplit.js', 'anaplanConsolida.js', 'anaplanDesviaciones.js'],
                'soportaReal' => true,
                'ayuda' => 'Separa por canal y consolida en SyS 2026 indicando desviaciones.',
            ],
            // 2026-09-23: pega las pestañas *_Anaplan de SyS MM.xlsx en la web de
            // Anaplan controlando el Chrome abierto con abrirChromeAnaplan.bat
            // (Playwright). Corre con el node de WINDOWS (ver windowsCmd()).
            'anaplan_web' => [
                'label' => 'Anaplan · subir a la web',
                'script' => 'anaplanWeb/subirAnaplan.js',
                'windows' => true,
                'timeout' => 900,
                'soportaReal' => true,
                'ayuda' => 'Pega U_Anaplan y las tiendas en la web de Anaplan, comprueba fila a fila y revisa "All Check Reports". Abre él solo Chrome, las pestañas y el mes; si Anaplan pide login, entra en esa ventana y sigue solo (espera 5 min). Mientras corre, no tocar esa ventana.',
            ],
            'laboral' => [
                'label' => 'Laboral',
                'script' => 'imputacionCostes.js',
                'soportaReal' => true,
                'ayuda' => 'Prepara asiento nóminas y personal de Anaplan.',
            ],
            'adyen' => [
                'label' => 'Adyen',
                'script' => 'adyenReparto.js',
                'soportaReal' => true,
                'ayuda' => 'Lee la factura de Adyen y reparte el coste entre las tiendas a partir del reporte de la web de Adyen.',
            ],
        ];
    }

    public function getProcesosProperty(): array
    {
        return $this->procesos();
    }

    /** /mnt/e/Foo/Bar  ->  E:\Foo\Bar  (para enseñar/copiar la ruta en Windows). */
    protected function rutaWindows(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return strtoupper($m[1]) . ':\\' . str_replace('/', '\\', $m[2]);
        }
        return $p;
    }

    /** /mnt/e/Foo/Bar  ->  file:///E:/Foo/Bar  (enlace del navegador). */
    protected function fileUrl(string $p): string
    {
        if (preg_match('#^/mnt/([a-z])/(.*)$#i', $p, $m)) {
            return 'file:///' . strtoupper($m[1]) . ':/' . str_replace('%2F', '/', rawurlencode($m[2]));
        }
        return 'file://' . $p;
    }

    public function ejecutar(string $id): void
    {
        $this->ejecutarUno($id);
    }

    public function limpiarSalida(): void
    {
        $this->salida = '';
    }

    /**
     * Ejecuta `$args` en `scriptDir()` y añade la salida a `$this->salida`.
     * Pedido explícito del usuario (2026-09-07, tras toparse con una página
     * rota tal cual porque el log de Laravel no era escribible): CUALQUIER
     * fallo -- el proceso, el propio `report()` del error si el log también
     * fallara, o cualquier otra excepción -- se convierte en un aviso dentro
     * de la salida en pantalla, nunca en un error que rompa la página.
     *
     * `$etiqueta` identifica el proceso en el aviso modal de fin (pedido
     * explícito 2026-09-07: "que salga una ventana avisando que ha acabado,
     * una por cada proceso, que la tenga que cerrar yo" -- `alert()` de JS,
     * que bloquea hasta que el usuario le da a OK, evento
     * `proceso-terminado` escuchado en la vista).
     *
     * Devuelve las rutas absolutas que el script haya marcado con líneas
     * "RESULT_FILE: <ruta>" (esas líneas NO se muestran en la caja de Salida;
     * se enseñan como enlace al lado del botón -- pedido del usuario 2026-09-09).
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta, ?string $cwd = null, array $env = []): array
    {
        // Pedido explícito del usuario (2026-09-07): estos scripts solo tienen
        // sentido en un PC con los ficheros de OneDrive de verdad (AlexMiniPC,
        // PortalExomen...). En el VPS de producción (app-mos.com) el código se
        // despliega igual (para que la pantalla se vea), pero bloqueado -- ver
        // config/contabilidad.php.
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return [];
        }

        $resultFiles = [];
        $this->ultimoOk = false;
        try {
            $result = Process::path($cwd ?? $this->scriptDir())->env($env)->input('')->timeout($timeout)->run($args);
            $texto = trim($result->output() . "\n" . $result->errorOutput());
            // Ruido del interop de WSL al lanzar cmd.exe sin consola: no es un error.
            $texto = trim(preg_replace('/^Exception: ios_base::clear.*(\r?\n)?/m', '', $texto));
            if (preg_match_all('/^RESULT_FILE:\s*(.+?)\s*$/m', $texto, $m)) {
                $resultFiles = array_map('trim', $m[1]);
                $texto = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', $texto));
            }
            $this->salida .= $texto;
            if ($result->successful()) {
                $this->ultimoOk = true;
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida " . $result->exitCode() . '.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código " . $result->exitCode() . "). Mira la caja de Salida: cada ⚠️ dice qué hacer (👉) si el proceso lo sabe.");
                // 2026-09-20: pedido del usuario -- que quede en storage/logs/laravel.log
                // (Log::warning, no report()) para poder leerlo directamente en vez de
                // depender de que se pegue la caja de Salida cada vez.
                Log::warning("Contabilidad/Procesos: {$etiqueta} salió con código {$result->exitCode()}", [
                    'comando' => $args,
                    'salida' => $texto,
                ]);
            }
        } catch (\Throwable $e) {
            // Pedido explícito del usuario (2026-09-07): nada de mensaje genérico --
            // que salga tal cual (clase, mensaje, fichero:línea, comando exacto y las
            // primeras líneas de la traza) para poder copiarlo y pegarlo aquí.
            $this->salida .= "\n\n⚠️ EXCEPCIÓN AL EJECUTAR (cópialo tal cual):\n"
                . get_class($e) . ': ' . $e->getMessage() . "\n"
                . 'en ' . $e->getFile() . ':' . $e->getLine() . "\n"
                . 'comando: ' . implode(' ', array_map(fn ($a) => "'" . $a . "'", $args)) . "\n"
                . "traza:\n" . implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8));
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nExcepción al ejecutar. Mira la caja de Salida para el detalle.");
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Si ni siquiera se puede registrar el error (p.ej. el propio log
                // sin permisos de escritura), no debe romper la pantalla por eso.
            }
        }

        return $resultFiles;
    }

    protected function ejecutarUno(string $id): void
    {
        $procesos = $this->procesos();
        if (! isset($procesos[$id])) {
            return;
        }
        $p = $procesos[$id];
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        // Un proceso puede lanzar varios scripts seguidos ('scripts' => [...]);
        // 'script' => '...' es el caso de uno solo.
        $scripts = $p['scripts'] ?? [$p['script']];

        $this->resultados[$id] = []; // se refresca en cada ejecución
        $todoOk = true;
        foreach ($scripts as $script) {
            $windows = ! empty($p['windows']);
            $args = $windows ? $this->windowsCmd($script, [$mm]) : $this->nodeCmd($script, [$mm]);
            if ($p['soportaReal']) {
                $args[] = '--real'; // siempre real (ya no hay check "Modo real")
            }
            $sufijo = count($scripts) > 1 ? " · {$script}" : '';
            $etiqueta = "{$p['label']}{$sufijo} (mes {$mm}, REAL)";
            $this->salida .= "\n\n===== {$etiqueta} =====\n";
            if ($windows && ! is_dir($this->scriptDir() . '/' . dirname($script) . '/node_modules/playwright-core')) {
                $dirWin = $this->rutaWindows($this->scriptDir() . '/' . dirname($script));
                $this->salida .= "⚠️ Falta playwright-core en este PC (se instala una sola vez).\n"
                    . "   👉 Qué hacer: abre una consola de Windows (cmd) y ejecuta:\n"
                    . "      cd /d \"{$dirWin}\"\n"
                    . "      npm install\n"
                    . '   y vuelve a pulsar el botón.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nFalta instalar playwright-core en este PC. En la caja de Salida tienes los comandos.");
                continue;
            }
            $this->anexarResultados($id, $this->ejecutarScript(
                $args,
                $p['timeout'] ?? 180,
                $etiqueta,
                $windows ? $this->scriptDir() . '/' . dirname($script) : null,
                $windows ? $this->windowsEnv() : []
            ));
            $todoOk = $todoOk && $this->ultimoOk;
        }
        if ($todoOk) {
            $this->marcarChecklist($id, $this->mes);
        }
    }

    /** Añade rutas RESULT_FILE a $resultados[$key] (ruta Windows + file:// url), sin duplicar. */
    protected function anexarResultados(string $key, array $rutas): void
    {
        $yaEstan = array_column($this->resultados[$key] ?? [], 'ruta');
        foreach ($rutas as $ruta) {
            $win = $this->rutaWindows($ruta);
            if (in_array($win, $yaEstan, true)) {
                continue;
            }
            $yaEstan[] = $win;
            $this->resultados[$key][] = ['ruta' => $win, 'url' => $this->fileUrl($ruta)];
        }
    }

    // -- RentasVariables (formularios aparte) -------------------------------

    /**
     * Cálculos + Turnover en un solo botón (pedido del usuario 2026-09-09:
     * "unifica cálculos y declaración"). Usa el "$mes" del título, siempre real:
     *  1. calculosRentasVariables.js <mes> (rellena CalculosRentasVbles2026.xlsx
     *     -- las 4 tiendas).
     *  2. rentasVariablesDeclaracion.js <tienda> <mes> por cada tienda marcada
     *     (BCN/MAL) -- rellena el fichero del arrendador (turnover).
     */
    public function ejecutarRvCalculosYDeclaracion(): void
    {
        $this->resultados['rv'] = [];
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        $args = $this->nodeCmd('calculosRentasVariables.js', [$mm, '--no-open', '--real']);
        $etiqueta = "RentasVariables · Cálculos (mes {$mm}, REAL)";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->anexarResultados('rv', $this->ejecutarScript($args, 180, $etiqueta));
        if ($this->ultimoOk) {
            $this->marcarChecklist('rv_calculos', $this->mes);
        }

        $tiendas = array_values(array_intersect(
            array_keys($this->rvTiendasArrendador()),
            $this->rvTiendas
        ));
        if (empty($tiendas)) {
            $this->salida .= "\n\n(Declaración a arrendador: no hay ninguna tienda marcada -- Barcelona / Málaga -- así que solo se han hecho los Cálculos.)\n";
            return;
        }

        foreach ($tiendas as $tienda) {
            $args = $this->nodeCmd('rentasVariablesDeclaracion.js', [$tienda, $mm, '--real']);
            $etiqueta = "RentasVariables · Declaración {$tienda} (mes {$mm}, REAL)";
            $this->salida .= "\n\n===== {$etiqueta} =====\n";
            $this->anexarResultados('rv', $this->ejecutarScript($args, 180, $etiqueta));
            if ($this->ultimoOk) {
                $this->marcarChecklist('rv_certificacion', $this->mes);
            }
        }
    }

    /** Envío REAL a los destinatarios de UNA tienda (botón "Enviar" de su fila). */
    public function ejecutarRvEnvio(string $tienda): void
    {
        if (! isset($this->rvTiendasArrendador()[$tienda])) {
            return;
        }
        $cfg = $this->rvEnvio[$tienda] ?? [];
        $to = trim($cfg['to'] ?? '');
        $cc = trim($cfg['cc'] ?? '');

        if ($to === '') {
            $this->salida .= "\n\n===== RentasVariables · Envío {$tienda} =====\nERROR: el 'Para' está vacío.\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ RentasVariables · Envío {$tienda}\nEl 'Para' está vacío.");
            return;
        }

        $args = ['python3', 'enviarRentasVariables.py', $tienda];
        if (! empty($cfg['correccion'])) {
            $args[] = '--correction';
        }
        $args[] = '--real';
        $args[] = '--to';
        $args[] = $to;
        $args[] = '--cc';
        $args[] = $cc;

        $etiqueta = "RentasVariables · Envío {$tienda} (REAL, a {$to})";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 120, $etiqueta);
        if ($this->ultimoOk) {
            $this->marcarChecklist('rv_envio', $this->mes);
        }
    }

    /**
     * Envío de PRUEBA: manda los DOS ficheros (Barcelona + Málaga) al correo de
     * prueba. Una llamada al script por tienda, en modo --test (todo va a esa
     * dirección; los To/CC editados solo salen en la vista previa del script).
     */
    public function ejecutarRvEnvioPrueba(): void
    {
        $email = trim($this->rvEmailPrueba);
        if ($email === '') {
            $this->salida .= "\n\n===== RentasVariables · Envío a correo de prueba =====\nERROR: pon un correo de prueba.\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ RentasVariables · Envío a correo de prueba\nPon un correo de prueba.");
            return;
        }

        foreach (array_keys($this->rvTiendasArrendador()) as $tienda) {
            $cfg = $this->rvEnvio[$tienda] ?? [];
            $args = ['python3', 'enviarRentasVariables.py', $tienda, '--test', $email];
            if (! empty($cfg['correccion'])) {
                $args[] = '--correction';
            }
            $args[] = '--to';
            $args[] = trim($cfg['to'] ?? '');
            $args[] = '--cc';
            $args[] = trim($cfg['cc'] ?? '');

            $etiqueta = "RentasVariables · Envío PRUEBA {$tienda} (a {$email})";
            $this->salida .= "\n\n===== {$etiqueta} =====\n";
            $this->ejecutarScript($args, 120, $etiqueta);
        }
    }

    // -- Pagos fin de mes (correo a Plein) ------------------------------------

    /** $modo: 'vista' (solo genera la vista previa), 'prueba' o 'real'. */
    public function ejecutarPagosFinMes(string $modo): void
    {
        // VAT TAX / Social Security vacíos -> el script los busca solo (PDF del 303
        // del mes anterior / correo de Jordi en Outlook); a mano siempre se puede.
        foreach (['pfSaldo' => 'Saldo BBVA'] as $campo => $nombre) {
            if (trim($this->{$campo}) === '') {
                $this->salida .= "\n\n===== Pagos fin de mes =====\nERROR: falta '{$nombre}'.\n";
                $this->dispatch('proceso-terminado', mensaje: "⚠️ Pagos fin de mes\nFalta '{$nombre}'.");
                return;
            }
        }
        if ($modo === 'real' && trim($this->pfTo) === '') {
            $this->salida .= "\n\n===== Pagos fin de mes =====\nERROR: el 'Para' está vacío.\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ Pagos fin de mes\nEl 'Para' está vacío.");
            return;
        }
        if ($modo === 'prueba' && trim($this->pfEmailPrueba) === '') {
            $this->salida .= "\n\n===== Pagos fin de mes =====\nERROR: pon un correo de prueba.\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ Pagos fin de mes\nPon un correo de prueba.");
            return;
        }

        $args = ['python3', 'pagosFinMes.py', (string) $this->pfMes, '--saldo', trim($this->pfSaldo)];
        foreach (['--iva' => $this->pfIva, '--ss' => $this->pfSs] as $opt => $v) {
            if (trim($v) !== '') {
                array_push($args, $opt, trim($v));
            }
        }
        if (trim($this->pfNominas) !== '') {
            array_push($args, '--nominas', trim($this->pfNominas));
        }
        if (trim($this->pfCargo) !== '') {
            array_push($args, '--cargo', trim($this->pfCargo));
        }
        array_push($args, '--texto', $this->pfTexto, '--destacado', $this->pfDestacado,
            $this->pfIncluirDestacado ? '--con-destacado' : '--sin-destacado',
            '--to', $this->pfTo, '--cc', $this->pfCc);
        $args = array_merge($args, match ($modo) {
            'real' => ['--real'],
            'prueba' => ['--test', trim($this->pfEmailPrueba)],
            default => ['--sin-enviar'],
        });

        $mm = str_pad((string) $this->pfMes, 2, '0', STR_PAD_LEFT);
        $etiqueta = 'Pagos fin de mes ' . $mm . ' · ' . match ($modo) {
            'real' => 'ENVÍO REAL a Plein',
            'prueba' => 'prueba a ' . trim($this->pfEmailPrueba),
            default => 'vista previa',
        };
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->resultados['pf'] = [];
        // windowsEnv(): el script llama a powershell.exe (Outlook) y bajo Apache
        // el interop de WSL necesita WSL_INTEROP (igual que subirAnaplan.js).
        $this->anexarResultados('pf', $this->ejecutarScript($args, 240, $etiqueta, null, $this->windowsEnv()));
        if ($modo === 'real' && $this->ultimoOk) {
            $this->marcarChecklist('pagos_fin_mes', $this->pfMes);
        }
        if ($modo === 'real') {
            $this->cargarBasePagosFinMes(); // ya es la base del mes que viene
        }
    }

    /** Mismo criterio que pagosFinMes.py::num(): '85,9', '85.9' o '85.912,39' (euros → K), 1 decimal. */
    protected function pfNum(string $raw): ?float
    {
        $t = str_replace(' ', '', trim($raw));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace(',', '.', str_replace('.', '', $t));
        }
        if (! is_numeric($t)) {
            return null;
        }
        $v = (float) $t;
        return round(abs($v) >= 1000 ? $v / 1000 : $v, 1);
    }

    /** Importe en K como en el correo: '37', '59,2', '-76,2'. */
    protected function pfK(?float $v): string
    {
        if ($v === null) {
            return '—';
        }
        $v = round($v, 1);
        return str_replace('.', ',', $v == (int) $v ? (string) (int) $v : number_format($v, 1, '.', ''));
    }

    /**
     * Líneas de importes tal como saldrán en el correo, para verlas a la derecha
     * de la tarjeta (pedido 2026-09-25). Mismas cuentas que pagosFinMes.py;
     * VAT/SS/Payrolls vacíos salen como "—" (el script los buscaría al enviar).
     */
    public function getPfLineasProperty(): array
    {
        $saldo = $this->pfNum($this->pfSaldo);
        $iva = $this->pfNum($this->pfIva);
        $ss = $this->pfNum($this->pfSs);
        $nom = $this->pfNum($this->pfNominas);
        $fijas = $this->confPagosFinMes()['fijas'] ?? [];

        $cargo = trim($this->pfCargo);
        if ($cargo === '') {
            $d = \Carbon\Carbon::create(2026, $this->pfMes, 1)->endOfMonth()->startOfDay();
            while ($d->isWeekend()) {
                $d->subDay();
            }
            $cargo = $d->locale('en')->isoFormat('dddd Do');
        }

        $completo = $iva !== null && $ss !== null && $nom !== null;
        $impNom = $completo ? $iva + $ss + $nom : null;
        $total = $completo ? $impNom + array_sum(array_column($fijas, 2)) : null;

        $filas = [
            ['VAT TAX', '', $this->pfK($iva), "K Will be charged on {$cargo}"],
            ['Social Security', '', $this->pfK($ss), "K Will be charged on {$cargo}"],
            ['Payrolls', '', $this->pfK($nom), 'K'],
        ];
        foreach ($fijas as [$a, $b, $c]) {
            $filas[] = [$a, $b, $this->pfK((float) $c), 'K'];
        }

        return [
            'cabecera' => [
                ['Bank balance as of ' . date('Y/m/d'), $this->pfK($saldo), 'K'],
                ['Amount to upload to pay taxes and Payrolls', $this->pfK($saldo !== null && $completo ? $saldo - $impNom : null), 'K'],
                ['Total Amount to upload', $this->pfK($saldo !== null && $completo ? $saldo - $total : null), 'K'],
            ],
            'filas' => $filas,
            'total' => $this->pfK($total),
        ];
    }

    /**
     * "Buscar importes": VAT TAX del PDF del 303 del mes anterior, Social Security
     * del correo de Jordi en Outlook y Payrolls de la remesa. Rellena los campos
     * (se pueden cambiar a mano después); lo que no encuentre queda como estaba.
     */
    public function buscarImportesPagosFinMes(): void
    {
        $mm = str_pad((string) $this->pfMes, 2, '0', STR_PAD_LEFT);
        $etiqueta = "Pagos fin de mes {$mm} · buscar importes";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $desde = strlen($this->salida);
        $this->ejecutarScript(['python3', 'pagosFinMes.py', (string) $this->pfMes, '--buscar'], 240, $etiqueta, null, $this->windowsEnv());
        $nuevo = substr($this->salida, $desde);
        $campos = ['iva' => 'pfIva', 'ss' => 'pfSs', 'nominas' => 'pfNominas'];
        if (preg_match_all('/^DATO (\w+)=([\d.\-]+)\s*$/m', $nuevo, $m, PREG_SET_ORDER)) {
            foreach ($m as [$_, $clave, $valor]) {
                if (isset($campos[$clave])) {
                    $this->{$campos[$clave]} = str_replace('.', ',', $valor);
                }
            }
        }
        $this->salida = substr($this->salida, 0, $desde) . trim(preg_replace('/^DATO .*(\r?\n)?/m', '', $nuevo));
    }

    /** Otro mes: el recordatorio cargado era del anterior. */
    public function updatedPfMes(): void
    {
        // Lo de un mes no vale para otro: importes, recordatorio y enlaces fuera
        // (el saldo de BBVA y el texto/destinatarios no son del mes: se quedan).
        $this->pfIva = $this->pfSs = $this->pfNominas = $this->pfCargo = '';
        $this->pfRecFilas = [];
        $this->pfRecOriginal = '';
        $this->pfRecSaldo = '';
        unset($this->resultados['pf'], $this->resultados['pfRec']);
    }

    /** Lee de Outlook el correo de pagos de ese mes (y el último de su hilo) y carga sus filas. */
    public function leerEnviadoPagosFinMes(): void
    {
        $mm = str_pad((string) $this->pfMes, 2, '0', STR_PAD_LEFT);
        $etiqueta = "Pagos fin de mes {$mm} · cargar correo enviado";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $desde = strlen($this->salida);
        $this->ejecutarScript(['python3', 'pagosFinMes.py', (string) $this->pfMes, '--leer-enviado'], 240, $etiqueta, null, $this->windowsEnv());
        $nuevo = substr($this->salida, $desde);
        $filas = [];
        if (preg_match_all('/^FILA (.*)$/m', $nuevo, $m)) {
            foreach ($m[1] as $linea) {
                // 5ª columna: Paid/Pending del último recordatorio del hilo (si lo hay).
                [$a, $b, $c, $d, $e] = array_pad(explode("\t", rtrim($linea, "\r")), 5, '');
                $filas[] = [$a, $b, $c, $d, $e === 'paid' ? 'paid' : 'pending'];
            }
        }
        if ($filas) {
            $this->pfRecFilas = $filas;
            $this->pfRecTexto = (string) ($this->confPagosFinMes()['texto_recordatorio'] ?? '');
            // Responde al último correo del hilo (recordatorio anterior o respuesta de Plein).
            $this->pfRecOriginal = preg_match('/Último del hilo: (.*) → /u', $nuevo, $o)
                || preg_match('/Correo original: (.*), \d+ filas/', $nuevo, $o) ? $o[1] : '';
        }
        $this->salida = substr($this->salida, 0, $desde) . trim(preg_replace('/^FILA .*(\r?\n)?/m', '', $nuevo));
    }

    /** $modo: 'vista', 'prueba' (Graph al correo de prueba) o 'real' (borrador en Outlook). */
    public function ejecutarRecordatorioPagosFinMes(string $modo): void
    {
        $etiqueta = 'Pagos fin de mes ' . str_pad((string) $this->pfMes, 2, '0', STR_PAD_LEFT) . ' · recordatorio · ' . match ($modo) {
            'real' => 'borrador en Outlook',
            'prueba' => 'prueba a ' . trim($this->pfEmailPrueba),
            default => 'vista previa',
        };
        $error = match (true) {
            ! $this->pfRecFilas => "carga antes el correo enviado.",
            trim($this->pfRecSaldo) === '' => "falta el saldo de BBVA de hoy.",
            $modo === 'prueba' && trim($this->pfEmailPrueba) === '' => 'pon un correo de prueba.',
            default => null,
        };
        if ($error) {
            $this->salida .= "\n\n===== {$etiqueta} =====\nERROR: {$error}\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ Recordatorio\n" . ucfirst($error));
            return;
        }
        $args = ['python3', 'pagosFinMes.py', (string) $this->pfMes, '--recordatorio',
            '--saldo', trim($this->pfRecSaldo), '--texto', $this->pfRecTexto,
            '--filas', json_encode(array_values($this->pfRecFilas), JSON_UNESCAPED_UNICODE)];
        $args = array_merge($args, match ($modo) {
            'real' => ['--real'],
            'prueba' => ['--test', trim($this->pfEmailPrueba)],
            default => ['--sin-enviar'],
        });
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->resultados['pfRec'] = [];
        $this->anexarResultados('pfRec', $this->ejecutarScript($args, 240, $etiqueta, null, $this->windowsEnv()));
    }

    /** Totales del recordatorio para verlos en la tarjeta (mismas cuentas que el script). */
    public function getPfRecTotalesProperty(): array
    {
        $pend = 0.0;
        $impNom = 0.0;
        foreach ($this->pfRecFilas as [$a, $b, $c, $d, $e]) {
            if ($e !== 'paid') {
                $v = $this->pfNum((string) $c) ?? 0.0;
                $pend += $v;
                if (in_array($a, ['VAT TAX', 'Social Security', 'Payrolls'], true)) {
                    $impNom += $v;
                }
            }
        }
        $saldo = $this->pfNum($this->pfRecSaldo);
        return [
            'pendiente' => $this->pfK($pend),
            'subirImp' => $this->pfK($saldo === null ? null : $saldo - $impNom),
            'subirTotal' => $this->pfK($saldo === null ? null : $saldo - $pend),
        ];
    }

    // ---- Cash in store (pedido 2026-10-01) ----------------------------------
    // Efectivo de cada tienda al cierre del último día del mes ($mes), separado
    // en Cash (lo que va a Prosegur) y Petty Cash (se queda para cambio y fondo).
    // "Buscar" lo saca de los correos de las tiendas en Outlook y guarda el .msg
    // en Cash End month\MM; a las que no lo han mandado se les pide; "Grabar"
    // lo escribe en la hoja "Cash End Month" de Ctrol Dinamico. Script:
    // monthlyFIQ/CashInStore/cashInStore.py.

    /** tienda => ['cash','petty','asunto','recibido','texto','encontrado','anterior','msg'] */
    public array $cisFilas = [];

    /** ¿Se ve la tabla de importes debajo de su fila? (botón Plegar / Ver importes) */
    public bool $cisAbierto = true;

    /**
     * Otro mes: fuera todo lo que era del anterior (enlaces a ficheros resultado,
     * importes de Cash in store...) y se carga lo que haya de este (pedido 2026-10-01:
     * "al cambiar el mes no debería ver los importes, corrige esto en cualquier
     * sitio que tenga memoria").
     */
    public function updatedMes(): void
    {
        $this->resultados = array_intersect_key($this->resultados, ['pf' => 1, 'pfRec' => 1]); // Pagos fin de mes tiene su propio mes
        $this->cargarCashInStore();
    }

    protected function cisMm(): string
    {
        return str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
    }

    protected function cisEur(?float $v): string
    {
        return $v === null ? '' : number_format($v, 2, ',', '.');
    }

    /** "2219,80", "2.219,80", "2219.80", "1.080" → float; vacío → null. */
    protected function cisNum(string $raw): ?float
    {
        $t = str_replace([' ', '€'], '', trim($raw));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace(',', '.', str_replace('.', '', $t));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
            $t = str_replace('.', '', $t);
        }
        return is_numeric($t) ? round((float) $t, 2) : null;
    }

    public function buscarCashInStore(): void
    {
        $mm = $this->cisMm();
        $etiqueta = "Cash in store {$mm} · buscar en Outlook";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        // windowsEnv(): el script llama a powershell.exe (Outlook) y bajo Apache necesita WSL_INTEROP.
        $this->ejecutarScript(['python3', 'CashInStore/cashInStore.py', (string) $this->mes, '--buscar'], 300, $etiqueta, null, $this->windowsEnv());
        $this->salida = preg_replace('/^CASH_JSON:.*(\r?\n)?/m', '', $this->salida);
        $this->cargarCashInStore(true);
    }

    /**
     * Lee el resultado de la última búsqueda del mes (CashInStore/_cashInStore_MM.json),
     * sin volver a Outlook: así al entrar en la pantalla ya está «Ver importes».
     */
    protected function cargarCashInStore(bool $abrir = false): void
    {
        $this->cisFilas = [];
        $this->cisAbierto = $abrir;
        $fichero = $this->scriptDir() . '/CashInStore/_cashInStore_' . $this->cisMm() . '.json';
        $datos = is_file($fichero) ? json_decode((string) file_get_contents($fichero), true) : null;
        if (! is_array($datos)) {
            return;
        }
        foreach ($datos as $tienda => $r) {
            $this->cisFilas[$tienda] = [
                'cash' => $this->cisEur($r['cash'] ?? null),
                'petty' => $this->cisEur($r['petty'] ?? null),
                'asunto' => (string) ($r['asunto'] ?? ''),
                'recibido' => (string) ($r['recibido'] ?? ''),
                'texto' => (string) ($r['texto'] ?? ''),
                'encontrado' => ! empty($r['lineas']),
                'anterior' => $this->cisEur($r['anterior'][1] ?? null),
                'msg' => (string) ($r['msg'] ?? ''),
            ];
        }
    }

    public function getCisFaltanProperty(): array
    {
        return array_keys(array_filter($this->cisFilas, fn ($r) => ! $r['encontrado']));
    }

    /** Correo de petición (Graph, envío directo) a las tiendas sin dato. */
    public function pedirCashInStore(): void
    {
        $faltan = $this->cisFaltan;
        $etiqueta = "Cash in store {$this->cisMm()} · pedir a " . implode(', ', $faltan);
        if (! $faltan) {
            $this->salida .= "\n\n⚠️ {$etiqueta}: no falta ninguna tienda (pulsa antes «Buscar»).";
            return;
        }
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript(['python3', 'CashInStore/cashInStore.py', (string) $this->mes, '--pedir', implode(',', $faltan), '--real'], 240, $etiqueta);
    }

    /** Escribe la fila del mes en "Cash End Month" de Ctrol Dinamico. */
    public function grabarCashInStore(): void
    {
        $datos = [];
        foreach ($this->cisFilas as $tienda => $r) {
            $cash = $this->cisNum((string) $r['cash']);
            $petty = $this->cisNum((string) $r['petty']);
            if ($cash !== null || $petty !== null) {
                $datos[$tienda] = [$cash, $petty];
            }
        }
        $etiqueta = "Cash in store {$this->cisMm()} · grabar en Ctrol Dinamico";
        if (! $datos) {
            $this->salida .= "\n\n⚠️ {$etiqueta}: no hay importes que grabar.";
            return;
        }
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->resultados['cis'] = [];
        $this->anexarResultados('cis', $this->ejecutarScript(
            ['python3', 'CashInStore/cashInStore.py', (string) $this->mes, '--grabar', '--datos', json_encode($datos), '--real'], 120, $etiqueta));
        if ($this->ultimoOk) {
            $this->marcarChecklist('cash_in_store', $this->mes);
        }
    }

    // ---- Checklist de cierre (pedido 2026-10-01) -----------------------------
    // "Guardar un registro de que se ha hecho cada mes para no volverme loco":
    // un check por proceso y mes. La lista de procesos (con su detalle, el ⓘ)
    // está en monthlyFIQ/checklist.json; las marcas, en OneDrive
    // (Fashion 2026/checklist FIQ 2026.estado.json) para que los dos PCs vean
    // lo mismo. Los procesos que se lanzan desde aquí se marcan solos al
    // terminar bien (marcarChecklist); el resto, con un clic.

    /** Columnas: dic-2025 (el checklist empezó ahí) y los 12 meses de 2026. */
    public function getChecklistMesesProperty(): array
    {
        $meses = ['2025-12' => 'dic 25'];
        foreach (['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as $i => $n) {
            $meses[sprintf('2026-%02d', $i + 1)] = $n;
        }
        return $meses;
    }

    /**
     * Procesos en el orden de Alex: el que haya dejado arrastrando (⠿), guardado
     * en el estado de OneDrive ('orden', compartido por los dos PCs); los que no
     * estén ahí (procesos nuevos) van al final, en el orden de checklist.json.
     */
    public function getChecklistProperty(): array
    {
        $f = $this->scriptDir() . '/checklist.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        $procesos = $d['procesos'] ?? [];
        $orden = array_flip($this->leerChecklistEstado()['orden'] ?? []);
        $pos = fn ($p, $i) => $orden[$p['id']] ?? (count($orden) + $i);
        $conPos = [];
        foreach ($procesos as $i => $p) {
            $conPos[] = [$pos($p, $i), $p];
        }
        usort($conPos, fn ($a, $b) => $a[0] <=> $b[0]);
        return array_column($conPos, 1);
    }

    /** Arrastrar ⠿: deja $id encima (o debajo) de $destino y guarda el orden. */
    public function moverChecklist(string $id, string $destino, bool $debajo = false): void
    {
        $ids = array_column($this->checklist, 'id');
        if ($id === $destino || ! in_array($id, $ids, true) || ! in_array($destino, $ids, true)) {
            return;
        }
        $ids = array_values(array_diff($ids, [$id]));
        array_splice($ids, array_search($destino, $ids, true) + ($debajo ? 1 : 0), 0, [$id]);
        $this->escribirChecklistEstado(function (array $d) use ($ids) {
            $d['orden'] = $ids;
            return $d;
        });
    }

    protected function checklistEstadoPath(): ?string
    {
        foreach (['e', 'f'] as $u) {
            foreach (['_Clientes', 'Clientes'] as $c) {
                $dir = "/mnt/{$u}/OneDrive/{$c}/2026/Fashion 2026";
                if (is_dir($dir)) {
                    return "{$dir}/checklist FIQ 2026.estado.json";
                }
            }
        }
        return null;
    }

    protected function leerChecklistEstado(): array
    {
        $f = $this->checklistEstadoPath();
        $d = ($f && is_file($f)) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($d) ? $d + ['marcas' => []] : ['marcas' => []];
    }

    /** mes => id => ['estado' => 'ok'|'na', 'cuando', 'como'] */
    public function getChecklistMarcasProperty(): array
    {
        return $this->leerChecklistEstado()['marcas'] ?? [];
    }

    protected function guardarChecklist(string $id, string $mes, ?array $marca): void
    {
        $this->escribirChecklistEstado(function (array $d) use ($id, $mes, $marca) {
            if ($marca) {
                $d['marcas'][$mes][$id] = $marca;
            } else {
                unset($d['marcas'][$mes][$id]);
                if (empty($d['marcas'][$mes])) {
                    unset($d['marcas'][$mes]);
                }
            }
            ksort($d['marcas']);
            return $d;
        });
    }

    /** Lee el estado de OneDrive, le aplica $cambio y lo escribe (atómico). */
    protected function escribirChecklistEstado(callable $cambio): void
    {
        $f = $this->checklistEstadoPath();
        if (! $f) {
            $this->salida .= "\n\n⚠️ Checklist: no encuentro la carpeta Fashion 2026 de OneDrive; no se ha guardado.";
            return;
        }
        $d = $cambio($this->leerChecklistEstado());
        $tmp = $f . '.tmp-' . getmypid();
        if (@file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false || ! @rename($tmp, $f)) {
            $this->salida .= "\n\n⚠️ Checklist: no he podido escribir {$f}.";
        }
        // Las propiedades calculadas se guardan durante la petición: que se relean.
        unset($this->checklist, $this->checklistMarcas);
    }

    /** Lo llaman los procesos al terminar bien. $mes = número de mes de 2026. */
    protected function marcarChecklist(string $id, int $mes): void
    {
        try {
            $this->guardarChecklist($id, sprintf('2026-%02d', $mes),
                ['estado' => 'ok', 'cuando' => date('Y-m-d H:i'), 'como' => 'auto']);
        } catch (\Throwable $e) {
            $this->salida .= "\n\n⚠️ Checklist: no he podido marcar {$id} ({$e->getMessage()}).";
        }
    }

    /** Botón «Marcar» de los procesos manuales: ✓ (o lo quita) en el mes del título. */
    public function marcarMesActual(string $id): void
    {
        if (! in_array($id, array_column($this->checklist, 'id'), true)) {
            return;
        }
        $mes = sprintf('2026-%02d', $this->mes);
        $hecho = ($this->checklistMarcas[$mes][$id]['estado'] ?? '') === 'ok';
        $this->guardarChecklist($id, $mes, $hecho ? null : ['estado' => 'ok', 'cuando' => date('Y-m-d H:i'), 'como' => 'manual']);
    }

    /** Clic en un check: vacío → ✓ → «no toca» → vacío. */
    public function alternarChecklist(string $id, string $mes): void
    {
        if (! array_key_exists($mes, $this->checklistMeses) || ! in_array($id, array_column($this->checklist, 'id'), true)) {
            return;
        }
        $actual = $this->checklistMarcas[$mes][$id]['estado'] ?? null;
        $nuevo = match ($actual) {
            null => 'ok',
            'ok' => 'na',
            default => null,
        };
        $this->guardarChecklist($id, $mes, $nuevo ? ['estado' => $nuevo, 'cuando' => date('Y-m-d H:i'), 'como' => 'manual'] : null);
    }

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
