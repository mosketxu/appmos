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
    public array $marcados = [];
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

    public function ejecutarMarcados(): void
    {
        foreach (array_keys($this->procesos()) as $id) {
            if (in_array($id, $this->marcados, true)) {
                $this->ejecutarUno($id);
            }
        }
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

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
