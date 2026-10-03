<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Tareas pedidas a los PCs trabajadores desde la web (3-oct-2026) y aún sin cerrar. En el VPS no hay
     * ficheros de OneDrive: cada botón deja una tarea `fiq.script` en la cola, un PC la ejecuta y al terminar
     * se hace lo mismo que en local (salida, ficheros, checklist...). Forma:
     * [tarea_id => ['tipo' => 'script'|'estado', 'etiquetas' => [...], 'post' => método|null, 'ctx' => [...], 'resultados' => clave|null]].
     */
    public array $pendientes = [];

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
    // suministros...) sale de monthlyFIQ/pagosFinMes.json. Mes = el del título
    // (desde 2026-10-01; antes tenía desplegable propio).
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
    /** Fecha en que se mandó a Plein el de este mes ('' = aún no). */
    public string $pfEnviado = '';
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
        $this->cargarBasePagosFinMes();
        $this->updatedPfMes();
        $this->sincronizarEstado();
        $this->cargarCashInStore();
    }

    protected function confPagosFinMes(): array
    {
        return $this->estadoFiq('fiq.pagosFinMes', $this->scriptDir() . '/pagosFinMes.json') ?? [];
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
            // 2026-10-01: facturas semanales de las tiendas para el plugin de SAGE
            // (pestaña Emitidas), desde las hojas FIS de Ctrol Dinamico. Python.
            'facturas_emitidas' => [
                'label' => 'Facturas emitidas',
                'script' => 'FacturasEmitidas/facturasEmitidas.py',
                'python' => true,
                'soportaReal' => true,
                'ayuda' => 'Facturas semanales de cada tienda para el plugin de SAGE.',
            ],
            // 2026-10-01: Cashflow 2026 MM.xlsx = el del mes anterior + lo que falta
            // de los dos bancos según el mayor de SAGE (mayor*.xlsx de Descargas).
            'cashflow' => [
                'label' => 'Cash flow',
                'script' => 'CashFlow/cashflow.py',
                'python' => true,
                'envio' => true, // al procesar queda «procesado, sin enviar»; «Enviar» lo cierra
                'soportaReal' => true,
                'ayuda' => 'Cashflow del mes a partir del mayor de los bancos.',
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

    // -- Ejecución local (PC) o en cola de trabajadores (web) ----------------------------------------

    /** En el VPS (sin ejecucion_local) los scripts los hacen los PCs trabajadores vía cola de tareas. */
    protected function remoto(): bool
    {
        return ! config('contabilidad.ejecucion_local');
    }

    /** Comando local de un paso ['script', 'args', 'windows'?] → [argv, cwd|null, env]. */
    protected function comando(array $p): array
    {
        $script = $p['script'];
        $args = array_map('strval', $p['args'] ?? []);
        if (! empty($p['windows'])) {
            return [$this->windowsCmd($script, $args), $this->scriptDir() . '/' . dirname($script), $this->windowsEnv()];
        }
        if (str_ends_with($script, '.py')) {
            // windowsEnv(): muchos scripts llaman a powershell.exe (Outlook) y bajo Apache el interop de WSL necesita WSL_INTEROP
            return [['python3', $script, ...$args], null, $this->windowsEnv()];
        }

        return [$this->nodeCmd($script, $args), null, []];
    }

    /**
     * Lanza uno o varios scripts de monthlyFIQ seguidos. $pasos: [['script','args','timeout','etiqueta','windows'?], ...].
     * $opc: 'resultados' => clave de $this->resultados donde van los ficheros; 'post' => método que se llama al
     * terminar con ($ctx, $desde, array $oks) -- $desde = posición de $this->salida donde empieza el texto del
     * último paso, $oks = éxito de cada paso; 'ctx' => datos para el post (p. ej. el mes, que puede cambiar mientras tanto).
     * En local se ejecuta aquí mismo; en la web se deja en la cola y se cierra en revisarTareas().
     */
    protected function lanzar(array $pasos, array $opc = []): void
    {
        $clave = $opc['resultados'] ?? null;
        if ($clave !== null) {
            $this->resultados[$clave] = [];
        }
        if ($this->remoto()) {
            $this->lanzarEnCola($pasos, $opc);
            return;
        }
        $oks = [];
        $desde = strlen($this->salida);
        foreach ($pasos as $p) {
            $this->salida .= "\n\n===== {$p['etiqueta']} =====\n";
            $desde = strlen($this->salida);
            if (! empty($p['windows']) && ! is_dir($this->scriptDir() . '/' . dirname($p['script']) . '/node_modules/playwright-core')) {
                $dirWin = $this->rutaWindows($this->scriptDir() . '/' . dirname($p['script']));
                $this->salida .= "⚠️ Falta playwright-core en este PC (se instala una sola vez).\n"
                    . "   👉 Qué hacer: abre una consola de Windows (cmd) y ejecuta:\n"
                    . "      cd /d \"{$dirWin}\"\n"
                    . "      npm install\n"
                    . '   y vuelve a pulsar el botón.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$p['etiqueta']}\nFalta instalar playwright-core en este PC. En la caja de Salida tienes los comandos.");
                $oks[] = false;
                continue;
            }
            [$args, $cwd, $env] = $this->comando($p);
            $ficheros = $this->ejecutarScript($args, $p['timeout'] ?? 180, $p['etiqueta'], $cwd, $env);
            $oks[] = $this->ultimoOk;
            if ($clave !== null) {
                $this->anexarResultados($clave, $ficheros);
            }
        }
        $this->ultimoOk = ! in_array(false, $oks, true);
        if (! empty($opc['post'])) {
            $this->{$opc['post']}($opc['ctx'] ?? [], $desde, $oks);
        }
    }

    /** Deja los pasos como una tarea para los PCs (la web no ejecuta nada por sí misma). */
    protected function lanzarEnCola(array $pasos, array $opc): void
    {
        $etiquetas = array_column($pasos, 'etiqueta');
        $titulo = implode(' + ', $etiquetas);
        if (! Schema::hasTable('tareas') || ! Schema::hasTable('estado_procesos')) {
            $this->salida .= "\n\n⚠️ {$titulo}: falta hacer la migración de la cola de tareas en este servidor.";
            return;
        }
        $params = ['pasos' => array_map(fn ($p) => [
            'script' => $p['script'], 'args' => array_map('strval', $p['args'] ?? []), 'timeout' => $p['timeout'] ?? 180,
        ], $pasos)];
        // Mismo botón pulsado dos veces: no se duplica (sobre todo importante en los envíos de correo).
        $json = json_encode($params, JSON_UNESCAPED_UNICODE);
        if (DB::table('tareas')->where('proceso', 'fiq.script')->whereIn('estado', ['pendiente', 'en_curso'])->where('parametros', $json)->exists()) {
            $this->salida .= "\n\n⚠️ {$titulo}: ya está pedido y sin terminar (mira «Tareas en los PCs»).";
            return;
        }
        $this->podarFicherosViejos();
        $tid = ColaTareas::crear('fiq.script', $params, null, auth()->id(), ColaTareas::preferido());
        $this->pendientes[$tid] = ['tipo' => 'script', 'etiquetas' => $etiquetas, 'post' => $opc['post'] ?? null,
            'ctx' => $opc['ctx'] ?? [], 'resultados' => $opc['resultados'] ?? null];
        $this->salida .= "\n\n⏳ {$titulo} · pedido a los PCs (tarea #{$tid}); el resultado saldrá aquí en cuanto lo terminen.";
        if ($this->pcsConectados() === 0) {
            $this->salida .= "\n⚠️ Ahora mismo no hay ningún PC conectado: esperará hasta que alguno arranque (puedes cancelarla en «Tareas en los PCs»).";
        }
    }

    protected function pcsConectados(): int
    {
        return DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
    }

    /** Ficheros que subieron los PCs de tareas con más de 30 días. */
    protected function podarFicherosViejos(): void
    {
        $base = storage_path('app/tareas');
        foreach (is_dir($base) ? (glob($base . '/*', GLOB_ONLYDIR) ?: []) : [] as $d) {
            if (filemtime($d) < time() - 30 * 86400) {
                array_map('unlink', glob($d . '/*') ?: []);
                @rmdir($d);
            }
        }
    }

    /** wire:poll mientras haya tareas pedidas: cierra las que ya han terminado (haciendo lo que haría el modo local). */
    public function revisarTareas(): void
    {
        if (! $this->pendientes) {
            return;
        }
        foreach ($this->pendientes as $tid => $p) {
            $t = DB::table('tareas')->find($tid);
            if ($t && in_array($t->estado, ['pendiente', 'en_curso'], true)) {
                continue;
            }
            unset($this->pendientes[$tid]);
            if (! $t || $t->estado === 'cancelada') {
                $this->salida .= "\n\n🚫 " . implode(' + ', $p['etiquetas'] ?? ['Tarea']) . ' · cancelada.';
                continue;
            }
            $this->cerrarTarea($t, $p);
        }
    }

    protected function cerrarTarea(object $t, array $p): void
    {
        $res = json_decode((string) $t->resultado, true) ?: [];
        if (($p['tipo'] ?? 'script') === 'estado') {
            $this->recargarEstado();
            return;
        }
        $etiquetas = $p['etiquetas'] ?? [];
        $pasos = $res['pasos'] ?? [];
        $oks = [];
        $desde = strlen($this->salida);
        if (! $pasos) {
            // el trabajador falló antes de ejecutar nada (script no permitido, falta playwright...)
            $this->salida .= "\n\n===== " . implode(' + ', $etiquetas) . " =====\n⚠️ " . trim((string) $t->log);
            $this->dispatch('proceso-terminado', mensaje: '⚠️ ' . implode(' + ', $etiquetas) . "\nNo se pudo ejecutar en el PC. Mira la caja de Salida.");
            $this->ultimoOk = false;
            return;
        }
        $pc = $res['pc'] ?? ($t->trabajador_id ? DB::table('trabajadores')->where('id', $t->trabajador_id)->value('nombre') : '');
        foreach ($pasos as $i => $paso) {
            $etiqueta = $etiquetas[$i] ?? ($paso['script'] ?? 'Proceso');
            $this->salida .= "\n\n===== {$etiqueta}" . ($pc ? " · en {$pc}" : '') . " =====\n";
            $desde = strlen($this->salida);
            $this->salida .= (string) ($paso['salida'] ?? '');
            $ok = ! empty($paso['ok']);
            $oks[] = $ok;
            if ($ok) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida " . ($paso['codigo'] ?? '?') . '.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código " . ($paso['codigo'] ?? '?') . "). Mira la caja de Salida: cada ⚠️ dice qué hacer (👉) si el proceso lo sabe.");
            }
            if (! empty($p['resultados'])) {
                foreach ($paso['ficheros'] ?? [] as $f) {
                    $this->anexarFicheroRemoto($p['resultados'], $f, (int) $t->id, (string) $pc);
                }
            }
        }
        $this->ultimoOk = ! in_array(false, $oks, true);
        if (! empty($p['post'])) {
            $this->{$p['post']}($p['ctx'] ?? [], $desde, $oks);
        }
    }

    /** Fichero resultado de una tarea de un PC: ruta Windows en ese PC y, si se subió, botón de descarga. */
    protected function anexarFicheroRemoto(string $key, array $f, int $tid, string $pc): void
    {
        $win = $this->rutaWindows((string) $f['ruta']);
        foreach ($this->resultados[$key] ?? [] as $r) {
            if (($r['ruta'] ?? '') === $win) {
                return;
            }
        }
        $this->resultados[$key][] = ['ruta' => $win . ($pc ? " ({$pc})" : ''), 'url' => null, 'local' => null,
            'tarea' => ! empty($f['subido']) ? $tid : null, 'nombre' => (string) ($f['nombre'] ?? basename($win))];
    }

    /** «⬇ Descargar» de un fichero que subió un PC al terminar una tarea (web). */
    public function descargarDeTarea(int $tid, string $nombre)
    {
        $nombre = basename($nombre);
        $ruta = ColaTareas::carpetaFicheros($tid) . '/' . $nombre;
        if ($nombre === '' || ! is_file($ruta)) {
            $this->salida .= "\n\n⚠️ No puedo descargar ese fichero (ya no está en el servidor; se borran a los 30 días).";
            return null;
        }
        return response()->download($ruta, $nombre);
    }

    /** Anula una tarea que aún no ha cogido ningún PC (p. ej. un envío pedido con todos los PCs apagados). */
    public function cancelarTarea(int $tid): void
    {
        $n = DB::table('tareas')->where('id', $tid)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'terminada_at' => now(), 'updated_at' => now()]);
        $this->salida .= $n ? "\n\n🚫 Tarea #{$tid} cancelada." : "\n\n⚠️ La tarea #{$tid} ya la ha cogido un PC (o ya terminó): no se puede cancelar.";
        $this->revisarTareas();
    }

    /** PCs trabajadores y últimas tareas, para el panel «Tareas en los PCs» (solo en la web). */
    public function getPcsProperty(): array
    {
        if (! $this->remoto() || ! Schema::hasTable('trabajadores') || ! Schema::hasTable('tareas')) {
            return ['pcs' => [], 'tareas' => []];
        }
        $limite = now()->subSeconds(ColaTareas::LATIDO_MAX);
        return [
            'pcs' => DB::table('trabajadores')->where('activo', true)->orderBy('nombre')->get()
                ->map(fn ($t) => ['nombre' => $t->nombre, 'conectado' => $t->ultimo_latido && $t->ultimo_latido >= $limite->toDateTimeString(), 'latido' => $t->ultimo_latido])->all(),
            'tareas' => DB::table('tareas')->leftJoin('trabajadores', 'trabajadores.id', '=', 'tareas.trabajador_id')
                ->whereIn('tareas.proceso', ['fiq.script', 'fiq.estado', 'fiq.checklist'])->orderByDesc('tareas.id')->limit(6)
                ->get(['tareas.id', 'tareas.proceso', 'tareas.parametros', 'tareas.estado', 'tareas.created_at', 'trabajadores.nombre as pc'])->all(),
        ];
    }

    // -- Estado que dejan los scripts (JSON en los PCs; en la web, copia en BD) -------------------------

    /** Lee un JSON de estado: del fichero en un PC, de la copia que subió el trabajador en la web. */
    protected function estadoFiq(string $clave, ?string $fichero): ?array
    {
        if ($this->remoto()) {
            try {
                $d = Schema::hasTable('estado_procesos') ? ColaTareas::estado($clave) : null;
            } catch (\Throwable $e) {
                $d = null;
            }
            return is_array($d) ? $d : null;
        }
        $d = $fichero && is_file($fichero) ? json_decode((string) file_get_contents($fichero), true) : null;

        return is_array($d) ? $d : null;
    }

    /** Web: pide a un PC que suba el estado si no hay copia o es vieja (>30 min). Sin esperar: se recarga al llegar. */
    protected function sincronizarEstado(bool $forzar = false): void
    {
        if (! $this->remoto() || ! Schema::hasTable('estado_procesos') || ! Schema::hasTable('tareas')) {
            return;
        }
        $ultima = DB::table('estado_procesos')->where('clave', 'fiq.checklist_def')->value('updated_at');
        if (! $forzar && $ultima && \Carbon\Carbon::parse($ultima)->gt(now()->subMinutes(30))) {
            return;
        }
        if (DB::table('tareas')->where('proceso', 'fiq.estado')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            return;
        }
        $tid = ColaTareas::crear('fiq.estado', [], null, auth()->id(), ColaTareas::preferido());
        $this->pendientes[$tid] = ['tipo' => 'estado', 'etiquetas' => ['Estado de los procesos'], 'post' => null, 'ctx' => [], 'resultados' => null];
    }

    public function sincronizarAhora(): void
    {
        $this->sincronizarEstado(true);
    }

    /** Ha llegado estado nuevo de un PC: vuelve a cargar lo que depende de él (sin tocar lo que el usuario esté escribiendo). */
    protected function recargarEstado(): void
    {
        unset($this->checklist, $this->checklistMarcas);
        $this->cargarBasePagosFinMes();
        if (! $this->pfRecFilas && trim($this->pfSaldo) === '' && trim($this->pfIva) === '') {
            $this->updatedPfMes();
        }
        $this->cargarCashInStore($this->cisAbierto);
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
        $pasos = [];
        foreach ($scripts as $script) {
            $sufijo = count($scripts) > 1 ? " · {$script}" : '';
            $pasos[] = [
                'script' => $script,
                // siempre real (ya no hay check "Modo real")
                'args' => $p['soportaReal'] ? [$mm, '--real'] : [$mm],
                'timeout' => $p['timeout'] ?? 180,
                'etiqueta' => "{$p['label']}{$sufijo} (mes {$mm}, REAL)",
                'windows' => ! empty($p['windows']),
            ];
        }
        $this->lanzar($pasos, ['resultados' => $id, 'post' => 'postProceso', 'ctx' => ['id' => $id, 'mes' => $this->mes]]);
    }

    /** Fin de un proceso de la tabla: si todo fue bien, marca el checklist (con envío aparte: «procesado», no «hecho»). */
    protected function postProceso(array $ctx, int $desde, array $oks): void
    {
        if (! in_array(false, $oks, true)) {
            $p = $this->procesos()[$ctx['id']] ?? [];
            $this->marcarChecklist($ctx['id'], $ctx['mes'], ! empty($p['envio']) ? 'proc' : 'ok');
        }
    }

    /**
     * «⬇ Descargar» de un fichero resultado (pedido 2026-10-02: "al hacer clic que lo
     * pueda descargar"). Solo ficheros que hayan salido como resultado en esta pantalla.
     */
    public function descargarResultado(string $clave)
    {
        $ruta = base64_decode($clave, true);
        $validas = [];
        foreach ($this->resultados as $lista) {
            foreach ((array) $lista as $r) {
                if (! empty($r['local'])) {
                    $validas[] = $r['local'];
                }
            }
        }
        if (! $ruta || ! in_array($ruta, $validas, true) || ! is_file($ruta)) {
            $this->salida .= "\n\n⚠️ No puedo descargar ese fichero (ya no está o no es un resultado de esta pantalla).";
            return null;
        }
        return response()->download($ruta, basename($ruta));
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
            $this->resultados[$key][] = ['ruta' => $win, 'url' => $this->fileUrl($ruta), 'local' => $ruta];
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
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $pasos = [[
            'script' => 'calculosRentasVariables.js', 'args' => [$mm, '--no-open', '--real'], 'timeout' => 180,
            'etiqueta' => "RentasVariables · Cálculos (mes {$mm}, REAL)",
        ]];

        $tiendas = array_values(array_intersect(
            array_keys($this->rvTiendasArrendador()),
            $this->rvTiendas
        ));
        if (empty($tiendas)) {
            $this->salida .= "\n\n(Declaración a arrendador: no hay ninguna tienda marcada -- Barcelona / Málaga -- así que solo se hacen los Cálculos.)\n";
        }
        foreach ($tiendas as $tienda) {
            $pasos[] = [
                'script' => 'rentasVariablesDeclaracion.js', 'args' => [$tienda, $mm, '--real'], 'timeout' => 180,
                'etiqueta' => "RentasVariables · Declaración {$tienda} (mes {$mm}, REAL)",
            ];
        }
        $this->lanzar($pasos, ['resultados' => 'rv', 'post' => 'postRvCalculos', 'ctx' => ['mes' => $this->mes]]);
    }

    /** $oks[0] = Cálculos; el resto, una Declaración por tienda marcada. */
    protected function postRvCalculos(array $ctx, int $desde, array $oks): void
    {
        if ($oks[0] ?? false) {
            $this->marcarChecklist('rv_calculos', $ctx['mes']);
        }
        if (in_array(true, array_slice($oks, 1), true)) {
            $this->marcarChecklist('rv_certificacion', $ctx['mes']);
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

        $args = [$tienda];
        if (! empty($cfg['correccion'])) {
            $args[] = '--correction';
        }
        array_push($args, '--real', '--to', $to, '--cc', $cc);

        $this->lanzar([['script' => 'enviarRentasVariables.py', 'args' => $args, 'timeout' => 120,
            'etiqueta' => "RentasVariables · Envío {$tienda} (REAL, a {$to})"]],
            ['post' => 'postRvEnvio', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postRvEnvio(array $ctx, int $desde, array $oks): void
    {
        if (! in_array(false, $oks, true)) {
            $this->marcarChecklist('rv_envio', $ctx['mes']);
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

        $pasos = [];
        foreach (array_keys($this->rvTiendasArrendador()) as $tienda) {
            $cfg = $this->rvEnvio[$tienda] ?? [];
            $args = [$tienda, '--test', $email];
            if (! empty($cfg['correccion'])) {
                $args[] = '--correction';
            }
            array_push($args, '--to', trim($cfg['to'] ?? ''), '--cc', trim($cfg['cc'] ?? ''));
            $pasos[] = ['script' => 'enviarRentasVariables.py', 'args' => $args, 'timeout' => 120,
                'etiqueta' => "RentasVariables · Envío PRUEBA {$tienda} (a {$email})"];
        }
        $this->lanzar($pasos);
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

        $args = [(string) $this->pfMes, '--saldo', trim($this->pfSaldo)];
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
        $this->lanzar([['script' => 'pagosFinMes.py', 'args' => $args, 'timeout' => 240, 'etiqueta' => $etiqueta]],
            ['resultados' => 'pf', 'post' => 'postPagosFinMes', 'ctx' => ['modo' => $modo, 'mes' => $this->pfMes]]);
    }

    protected function postPagosFinMes(array $ctx, int $desde, array $oks): void
    {
        if ($ctx['modo'] === 'real' && ! in_array(false, $oks, true)) {
            $this->marcarChecklist('pagos_fin_mes', $ctx['mes']);
        }
        if ($ctx['modo'] === 'real') {
            $this->cargarBasePagosFinMes(); // ya es la base del mes que viene
            $this->pfEnviado = (string) ($this->confPagosFinMes()['meses'][sprintf('%02d', $ctx['mes'])]['enviado'] ?? '');
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
        $this->lanzar([['script' => 'pagosFinMes.py', 'args' => [(string) $this->pfMes, '--buscar'], 'timeout' => 240, 'etiqueta' => $etiqueta]],
            ['post' => 'postBuscarImportes']);
    }

    protected function postBuscarImportes(array $ctx, int $desde, array $oks): void
    {
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
        // Pagos fin de mes usa el mismo mes que el título (pedido 2026-10-01: antes
        // tenía su propio desplegable, el mes de los cargos = el actual). Al cambiar
        // de mes: lo que salió ese mes (pagosFinMes.json → meses) o vacío.
        $this->pfMes = $this->mes;
        $m = $this->confPagosFinMes()['meses'][sprintf('%02d', $this->mes)] ?? [];
        $this->pfSaldo = (string) ($m['saldo'] ?? '');
        $this->pfIva = (string) ($m['iva'] ?? '');
        $this->pfSs = (string) ($m['ss'] ?? '');
        $this->pfNominas = (string) ($m['nominas'] ?? '');
        $this->pfCargo = (string) ($m['cargo'] ?? '');
        $this->pfEnviado = (string) ($m['enviado'] ?? '');
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
        $this->lanzar([['script' => 'pagosFinMes.py', 'args' => [(string) $this->pfMes, '--leer-enviado'], 'timeout' => 240, 'etiqueta' => $etiqueta]],
            ['post' => 'postLeerEnviado']);
    }

    protected function postLeerEnviado(array $ctx, int $desde, array $oks): void
    {
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
        $args = [(string) $this->pfMes, '--recordatorio',
            '--saldo', trim($this->pfRecSaldo), '--texto', $this->pfRecTexto,
            '--filas', json_encode(array_values($this->pfRecFilas), JSON_UNESCAPED_UNICODE)];
        $args = array_merge($args, match ($modo) {
            'real' => ['--real'],
            'prueba' => ['--test', trim($this->pfEmailPrueba)],
            default => ['--sin-enviar'],
        });
        $this->lanzar([['script' => 'pagosFinMes.py', 'args' => $args, 'timeout' => 240, 'etiqueta' => $etiqueta]], ['resultados' => 'pfRec']);
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
        $this->resultados = [];
        $this->updatedPfMes();
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
        $this->lanzar([['script' => 'CashInStore/cashInStore.py', 'args' => [(string) $this->mes, '--buscar'], 'timeout' => 300, 'etiqueta' => $etiqueta]],
            ['post' => 'postBuscarCashInStore', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postBuscarCashInStore(array $ctx, int $desde, array $oks): void
    {
        $this->salida = preg_replace('/^CASH_JSON:.*(\r?\n)?/m', '', $this->salida);
        if ($ctx['mes'] === $this->mes) {
            $this->cargarCashInStore(true);
        }
    }

    /**
     * Lee el resultado de la última búsqueda del mes (CashInStore/_cashInStore_MM.json),
     * sin volver a Outlook: así al entrar en la pantalla ya está «Ver importes».
     */
    protected function cargarCashInStore(bool $abrir = false): void
    {
        $this->cisFilas = [];
        $this->cisAbierto = $abrir;
        $datos = $this->estadoFiq('fiq.cashInStore.' . $this->cisMm(), $this->scriptDir() . '/CashInStore/_cashInStore_' . $this->cisMm() . '.json');
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
        $this->lanzar([['script' => 'CashInStore/cashInStore.py', 'args' => [(string) $this->mes, '--pedir', implode(',', $faltan), '--real'], 'timeout' => 600, 'etiqueta' => $etiqueta]]);
    }

    /** Cash flow: manda el Cashflow del mes a Plein (Graph) y lo marca como hecho (enviado). */
    public function enviarCashflow(): void
    {
        $mm = $this->cisMm();
        $etiqueta = "Cash flow {$mm} · envío a Plein";
        $this->lanzar([['script' => 'CashFlow/cashflow.py', 'args' => [(string) $this->mes, '--enviar', '--real'], 'timeout' => 300, 'etiqueta' => $etiqueta]],
            ['post' => 'postEnviarCashflow', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postEnviarCashflow(array $ctx, int $desde, array $oks): void
    {
        if (! in_array(false, $oks, true) && str_contains(substr($this->salida, $desde), 'ENVIADO_REAL')) {
            $this->marcarChecklist('cashflow', $ctx['mes'], 'ok');
        }
        $this->salida = preg_replace('/^ENVIADO_REAL\s*$/m', '', $this->salida);
    }

    /** Recordatorio a las que faltan: «Responder a todos» a la petición ya enviada, en Borradores de Outlook. */
    public function recordarCashInStore(): void
    {
        $faltan = $this->cisFaltan;
        $etiqueta = "Cash in store {$this->cisMm()} · recordatorio a " . implode(', ', $faltan);
        if (! $faltan) {
            $this->salida .= "\n\n⚠️ {$etiqueta}: no falta ninguna tienda (pulsa antes «Buscar»).";
            return;
        }
        $this->lanzar([['script' => 'CashInStore/cashInStore.py', 'args' => [(string) $this->mes, '--recordatorio', implode(',', $faltan)], 'timeout' => 240, 'etiqueta' => $etiqueta]]);
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
        $this->lanzar([['script' => 'CashInStore/cashInStore.py', 'args' => [(string) $this->mes, '--grabar', '--datos', json_encode($datos), '--real'], 'timeout' => 120, 'etiqueta' => $etiqueta]],
            ['resultados' => 'cis', 'post' => 'postGrabarCashInStore', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postGrabarCashInStore(array $ctx, int $desde, array $oks): void
    {
        if (! in_array(false, $oks, true)) {
            $this->marcarChecklist('cash_in_store', $ctx['mes']);
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
        $d = $this->estadoFiq('fiq.checklist_def', $this->scriptDir() . '/checklist.json');
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
        }, ['op' => 'orden', 'ids' => $ids]);
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
        $d = $this->estadoFiq('fiq.checklist_estado', $this->checklistEstadoPath());
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
        }, ['op' => 'marca', 'id' => $id, 'mes' => $mes, 'marca' => $marca]);
    }

    /**
     * Lee el estado de OneDrive, le aplica $cambio y lo escribe (atómico). En la web no hay OneDrive: se aplica
     * a la copia de la BD (para verlo al momento) y se pide a un PC que haga el mismo cambio ($op) en OneDrive.
     */
    protected function escribirChecklistEstado(callable $cambio, array $op): void
    {
        if ($this->remoto()) {
            if (! Schema::hasTable('estado_procesos') || ! Schema::hasTable('tareas')) {
                $this->salida .= "\n\n⚠️ Checklist: falta la migración de la cola de tareas; no se ha guardado.";
                return;
            }
            ColaTareas::guardarEstado('fiq.checklist_estado', $cambio($this->leerChecklistEstado()), 'web');
            ColaTareas::crear('fiq.checklist', $op, null, auth()->id(), ColaTareas::preferido());
            unset($this->checklist, $this->checklistMarcas);
            return;
        }
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
    protected function marcarChecklist(string $id, int $mes, string $estado = 'ok'): void
    {
        try {
            $clave = sprintf('2026-%02d', $mes);
            if ($estado === 'proc' && ($this->checklistMarcas[$clave][$id]['estado'] ?? '') === 'ok') {
                return; // ya estaba enviado: reprocesar no lo devuelve a «sin enviar»
            }
            $this->guardarChecklist($id, $clave,
                ['estado' => $estado, 'cuando' => date('Y-m-d H:i'), 'como' => 'auto']);
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
        $conEnvio = collect($this->checklist)->firstWhere('id', $id)['envio'] ?? false;
        $nuevo = $conEnvio
            ? match ($actual) { null => 'proc', 'proc' => 'ok', 'ok' => 'na', default => null }
            : match ($actual) { null => 'ok', 'ok' => 'na', default => null };
        $this->guardarChecklist($id, $mes, $nuevo ? ['estado' => $nuevo, 'cuando' => date('Y-m-d H:i'), 'como' => 'manual'] : null);
    }

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
