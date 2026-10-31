<?php

namespace App\Http\Livewire\Contabilidad;

use App\Http\Livewire\Concerns\EjecutaEnPcs;
use App\Support\ColaTareas;
use App\Support\FicherosBase;
use App\Support\IntrastatMotor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    // En la web los scripts los hacen los PCs trabajadores por la cola de tareas (ver el trait).
    use EjecutaEnPcs;
    use WithFileUploads;

    /** Adyen: settlement elegido a mano (opcional; si no, el script lo busca en la carpeta del mes). */
    public $adyenSettlement = null;
    /** Ya se avisó de que no hay settlement subido (el segundo «Ejecutar» sigue adelante y lo busca en la carpeta Adyen del mes). */
    public bool $adyenAvisado = false;
    /** Batches Adyen (25-oct-2026): los settlement_detail_report_batch_NNN.xlsx tal como bajan de Adyen (uno o varios). */
    public $adyenBatchFicheros = [];
    /** Resultado de «Batches Adyen» en una ventana (25-oct-2026, Alex): el texto de la ejecución y si fue bien. */
    public string $resultadoModal = '';
    public bool $resultadoModalOk = true;
    public string $resultadoModalTitulo = '';
    /** Historial de resultados de los procesos (25-oct-2026): ventana con filtro por fechas. */
    public bool $histAbierto = false;
    public string $histDesde = '';
    public string $histHasta = '';
    public string $histTexto = '';
    /**
     * EDENRED (27-oct-2026): Imputación de costes del mes (la última versión, subida a mano). «Ejecutar» primero ANALIZA (diferencias con la copia
     * de Carme, trabajadores nuevos) y abre una ventana; ahí se decide cada diferencia y «Generar Recargas» hace los 5 ficheros.
     */
    public $edenredFichero = null;
    public $ubsFichero = null;   // extracto de UBS elegido a mano (27-oct-2026)
    public ?array $edenredAnalisis = null;
    public array $edenredDec = [];
    public bool $edenredAbierto = false;
    public bool $edenredUsuariosAbierto = false;
    public string $edenredMsg = '';
    /** Envío real a Jordi en dos pasos, comprobado en el servidor (wire:confirm no frena Livewire: incidente 5-oct-2026). */
    public bool $edenredConfirmaJordi = false;
    public bool $edenredConfirmaAlta = false;      // doble paso antes de crear tarjetas en el portal de Edenred (envío real)
    public string $edenredConfirmaSubir = '';      // tienda (siglas) cuyo pedido de Recargas se va a confirmar: doble paso
    /** Laboral (nóminas): fichero «imputacion costes» elegido a mano (opcional; si no, se usa el de versión más alta de la carpeta del mes). */
    public $laboralFichero = null;
    /**
     * Balance de sumas y saldos del mes elegido a mano (16-oct-2026: "quiero un input para ir a buscarlo; tú lo renombras y lo
     * llevas a la carpeta"). Lo usan Monthly sales y Anaplan: antes de su script, ponerSyS.js lo comprueba y lo deja como
     * Anaplan/SyS MM.xlsx. $sysForzar: el check «Usarlo igualmente» se quitó el 17-oct-2026 (Alex: «si el fichero no te vale ya me lo dices»); queda siempre false.
     */
    public $sysFichero = null;
    public bool $sysForzar = false;
    /**
     * Anaplan · personal (16-oct-2026): «Bruto sin Bonus» y «Bonus + Incentivo» de cada empleado (imputación de costes del mes) a
     * «Input Staff Data for AC» (Salary / Bonus Value MTD). Propuesta de qué empleado de Anaplan es cada uno de las nóminas, en un modal;
     * lo que se confirma queda como histórico (estado fiq.anaplan_staff_asoc) y la vez siguiente lo nuevo sale en amarillo.
     * $staff = [tienda => [[excel, bruto, bonus, anaplan, origen: 'historico'|'propuesta'|'', nota], ...]], $staffAnaplan = [tienda => [nombres]].
     */
    public array $staff = [];
    public array $staffAnaplan = [];
    public array $staffAvisos = [];
    public string $staffMesAnaplan = '';
    public string $staffFichero = '';
    public bool $staffAbierto = false;

    /** Cash flow: mayor de SAGE elegido a mano (opcional; si no, el último de los ficheros base de Fashion IQ, 16-oct-2026). */
    public $cashflowMayor = null;
    /** Open Sold Items: mayor de SAGE elegido a mano (opcional; si no, el más reciente entre el de SageLauncher del PC y los ficheros base). */
    public $openItemsMayor = null;

    protected string $grupoPc = 'fiq';

    // -- Fiscal Report / Cashier día a día (18-oct-2026) ------------------------------------------------
    /** Mes ('2026-10') cuyo modal de estado por días está abierto; null = cerrado. */
    public ?string $fiscalDiasMes = null;
    /** Último aviso del modal (qué se ha pedido a miniServ). */
    public string $fiscalMsg = '';

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
    // suministros...) sale de monthlyFIQ/pagosFinMes.json. Mes = el del título
    // (desde 2026-10-01; antes tenía desplegable propio).
    public int $pfMes;
    /** Año de trabajo (27-oct-2026): por defecto el del mes anterior al actual; selector junto al del mes. */
    public int $anio;
    public string $pfSaldo = '';
    public string $pfIva = '';
    public string $pfSs = '';
    public string $pfNominas = ''; // vacío = total de la remesa Laboral AAAA/<MM>/RM*.xml
    public string $pfCargo = '';   // vacío = último día hábil del mes ("Wednesday 30th")
    /** Observaciones por fila del correo (VAT, SS, Payrolls, fijas...); vacío = la de siempre. */
    public array $pfObs = [];
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
        // Desde el día 20 se trabaja ya el mes corriente (pedido 2026-10-29: los pagos de
        // fin de mes y el cierre salían en el mes anterior cuando ya tocaba el actual).
        if ((int) date('j') >= 20 && \App\Support\Anio::forzado() === null) {
            $this->mes = (int) date('n');
            $this->anio = (int) date('Y');
        } else {
            $m = (int) date('n') - 1;
            $this->mes = $m < 1 ? 12 : $m;
            $this->anio = \App\Support\Anio::defecto();
        }
        $this->rvEnvio = $this->rvDestinatariosPorDefecto();
        $this->cargarBasePagosFinMes();
        $this->updatedPfMes();
        $this->sincronizarEstado('fiq.checklist_def');
        $this->retomarTareas();
        $this->cargarCashInStore();
        $this->cargarIntrastat();
        $this->cargarComunicarImpuestos();
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
                'ayuda' => 'Separa por canal y consolida en SyS del año indicando desviaciones.',
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
            // 2026-10-16: Excel Open Items (OpenItems_FIQ_CL_AAAA_MM_DD.xlsx) = el último + lo que falta del mayor de las
            // cuentas 4000001, 4000003-7 y 4030000-4, con el cuadre por cuenta. Escribe con Excel por COM (el PC tiene que tener Excel).
            'open_items' => [
                'label' => 'Open Sold Items',
                'script' => 'OpenItems/openItems.py',
                'python' => true,
                'soportaReal' => true,
                'timeout' => 900,
                'ayuda' => 'Copia el último Open Items con la fecha de hoy, añade lo que falta del mayor y lo cuadra cuenta a cuenta.',
            ],
            'adyen' => [
                'label' => 'Adyen',
                'script' => 'adyenReparto.js',
                'soportaReal' => true,
                'ayuda' => 'Lee la factura de Adyen y reparte el coste entre las tiendas a partir del reporte de la web de Adyen.',
            ],
            // 2026-10-25: settlement detail de Adyen (un fichero por batch) -> Bat MM DD importe N.xlsx + pestaña Batch de Totals Batch Adyen.xlsx
            // (conciliación de Adyen). Escribe con Excel por COM (el PC tiene que tener Excel) y refresca las dinámicas.
            'adyen_batch' => [
                'label' => 'Batches Adyen',
                'script' => 'AdyenBatch/adyenBatch.py',
                'python' => true,
                'soportaReal' => true,
                'timeout' => 1500,
                'ayuda' => 'Pasa los settlement de Adyen a Bat MM DD importe N.xlsx y los añade a Totals Batch Adyen.xlsx.',
            ],
            // 2026-10-27: Recargas de Ticket Restaurant (Edenred) desde la Imputación de costes. Excel por COM; trabajadores nuevos desde el Outlook de Alex.
            'edenred' => [
                'label' => 'EDENRED',
                'script' => 'Edenred/edenred.py',
                'python' => true,
                'soportaReal' => true,
                'timeout' => 900,
                'ayuda' => 'Sube la Imputación de costes del mes: enseña las diferencias con lo que se preparó el mes anterior y los trabajadores nuevos, y deja los RecargasRestaurantCards de BCN, LRV, ROZ, MAL y MAR.',
            ],
            // 2026-10-27: extracto semanal de UBS. Normalmente es automático (trabajador de miniServ); aquí se puede lanzar a mano con un Excel.
            'ubs' => [
                'label' => 'Contabilizar UBS',
                'script' => 'UBS/ubs.py',
                'python' => true,
                'soportaReal' => true,
                'timeout' => 600,
                'ayuda' => 'Procesa a mano un extracto de UBS: lo añade a UBS BANCOS.xlsx y crea el plugin de bancos (5720001) con lo que aún no se había contabilizado.',
            ],
        ];
    }

    public function getProcesosProperty(): array
    {
        return $this->procesos();
    }

    /**
     * Ficheros que necesitan Monthly sales y Anaplan para el mes del título y si están (16-oct-2026: "pones si me falta
     * subir algo en el mes seleccionado"). Lo calcula monthlyFIQ/entradasMes.js: en la web, la copia que sube el PC con
     * el estado (fiq.entradas, al terminar cada tarea o con «↻ Sincronizar»); en un PC, ejecutándolo aquí.
     * Forma: ['monthly_sales' => [['que','donde','ok','opcional'?,'nota'?], ...], 'anaplan' => [...], 'origen' => texto].
     */
    public function getEntradasProperty(): array
    {
        $mm = sprintf('%02d', $this->mes);
        if ($this->remoto()) {
            $todo = $this->estadoRemoto('fiq.entradas');
            $cuando = $this->colaLista() ? \Illuminate\Support\Facades\DB::table('estado_procesos')->where('clave', 'fiq.entradas')->first(['updated_at', 'origen']) : null;
            $origen = $cuando ? 'comprobado '.\Carbon\Carbon::parse($cuando->updated_at)->format('d/m H:i').($cuando->origen ? ' en '.$cuando->origen : '') : '';
        } else {
            $todo = $this->entradasLocales;
            $origen = 'comprobado ahora en este PC';
        }

        $r = $todo && isset($todo[$mm]) ? $todo[$mm] + ['origen' => $origen] : [];
        // El SyS del mes en los ficheros base (subido en cualquier pantalla) cuenta como «está»: se deja en la carpeta al ejecutar.
        $c = $this->fiqEntidad() ? FicherosBase::ultimo($this->fiqEntidad(), 'sys', "{$this->anio}-{$mm}") : null;
        if ($c) {
            $nuevo = ($this->sysPuestos()[$mm] ?? null) !== FicherosBase::sha($c['ruta']);
            $r['sys_central'] = ['nombre' => $c['nombre'], 'fecha' => date('d/m/Y H:i', $c['fecha']), 'origen' => $c['origen'], 'nuevo' => $nuevo];
            foreach (['monthly_sales', 'anaplan'] as $k) {
                foreach ($r[$k] ?? [] as $i => $e) {
                    if ($e['que'] === "SyS {$mm}.xlsx" && ! $e['ok']) {
                        $r[$k][$i]['ok'] = true;
                    }
                }
            }
        }

        return $r;
    }

    /** En un PC: entradasMes.js ejecutado aquí (una vez por petición). */
    public function getEntradasLocalesProperty(): ?array
    {
        try {
            $r = Process::path($this->scriptDir())->timeout(60)->run($this->nodeCmd('entradasMes.js'));
            $d = $r->successful() ? json_decode($r->output(), true) : null;
        } catch (\Throwable $e) {
            $d = null;
        }

        return is_array($d) ? $d : null;
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

    /** ¿Puede este usuario este proceso del checklist? (permiso `proceso.fiq.<id>`; sin permiso propio vale el de la pestaña). */
    protected function permitido(string $id): bool
    {
        $u = auth()->user();
        return ! $u || ! \Illuminate\Support\Facades\Schema::hasTable('permissions') || $u->can('proceso.fiq.'.$id);
    }

    /** Si no puede, lo dice y devuelve false. */
    protected function autorizar(string $id): bool
    {
        if ($this->permitido($id)) {
            return true;
        }
        $this->dispatch('proceso-terminado', mensaje: '⚠️ No tienes acceso a este proceso. Pídeselo a un administrador.');
        return false;
    }

    /** Las tareas del grupo «fiscal» van siempre a su PC (miniServ), nunca al elegido en «Ejecutar en»: es el que mueve los correos y escribe Ctrol. */
    protected function destinoPc(): ?string
    {
        if ($this->grupoPc === 'fiscal') {
            return config('contabilidad.pc_grupos.fiscal.pc') ?: null;
        }

        return ColaTareas::pcElegido() ?: (config('contabilidad.pc_grupos.'.$this->grupoPc.'.pc') ?: null);
    }

    /** Abre el modal con el estado por días del Fiscal Report y del Cashier de un mes ('2026-10'). */
    public function abrirFiscalDias(string $mes): void
    {
        if (! $this->autorizar('fiscal_report') || ! preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $mes)) {
            return;
        }
        $this->fiscalDiasMes = $mes;
        $this->fiscalMsg = '';
        $this->pedirEstadoFiscal(false);
    }

    public function cerrarFiscalDias(): void
    {
        $this->fiscalDiasMes = null;
    }

    /** Estado por días que subió miniServ (null si aún no hay). */
    public function getFiscalDiasProperty(): ?array
    {
        return $this->fiscalDiasMes ? $this->estadoRemoto('fiscal.dias.'.substr($this->fiscalDiasMes, 5)) : null;
    }

    /** «↻ Actualizar estado»: pide a miniServ que lo recalcule (solo si el que hay tiene más de 10 min, o si se fuerza). */
    public function pedirEstadoFiscal(bool $forzar = true): void
    {
        $this->grupoPc = 'fiscal';
        try {
            $this->sincronizarEstado('fiscal.dias.'.substr((string) $this->fiscalDiasMes, 5), $forzar, 10);
            if ($forzar) {
                $this->fiscalMsg = '⏳ Pedido a miniServ el estado al día; se actualiza solo en cuanto lo tenga.';
            }
        } finally {
            $this->grupoPc = 'fiq';
        }
    }

    /** Lanza a mano un paso del Fiscal Report / Cashier en miniServ: correos | ctrol_fiscal | ctrol_cash | reclamar (con fecha). */
    public function lanzarFiscal(string $que, ?string $fecha = null): void
    {
        if (! $this->autorizar('fiscal_report')) {
            return;
        }
        $pasos = [
            'correos' => ['Leer los correos de las tiendas y de Plein ahora', ['--correo'], 300],
            'ctrol_fiscal' => ['Pasar los Fiscal Report a Ctrol Dinamico ahora', ['--ctrol', '--real', '--forzar', '--solo', 'fiscal'], 1500],
            'ctrol_cash' => ['Pasar Cash (X-out y semanal) a Ctrol Dinamico ahora', ['--ctrol', '--real', '--forzar', '--solo', 'cash'], 1500],
            'reclamar' => ['Reclamar a las tiendas lo que falta del '.$fecha, ['--reclamar', '--fecha', (string) $fecha], 300],
        ];
        if (! isset($pasos[$que]) || ($que === 'reclamar' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha))) {
            return;
        }
        [$etiqueta, $args, $timeout] = $pasos[$que];
        $this->grupoPc = 'fiscal';
        try {
            $tid = $this->lanzarEnCola([['script' => 'FiscalReport/fiscalReport.py', 'args' => $args, 'timeout' => $timeout, 'etiqueta' => $etiqueta]],
                ['post' => 'finFiscal', 'ctx' => ['etiqueta' => $etiqueta, 'ini' => 0]]);
        } finally {
            $this->grupoPc = 'fiq';
        }
        $this->fiscalMsg = $tid ? "⏳ {$etiqueta}: pedido a miniServ (tarea #{$tid}). El resultado sale en «Salida» y el estado se actualiza solo."
            : '⚠️ No se ha podido pedir (¿ya está pedido? mira «Tareas en los PCs»).';
    }

    /** Fin de una tarea del Fiscal Report: el estado ya viene con ella; se enseña el aviso en el modal. */
    protected function finFiscal(array $ctx, int $desde, array $oks): void
    {
        $this->fiscalMsg = (! in_array(false, $oks, true) ? '✅ ' : '⚠️ ').($ctx['etiqueta'] ?? 'Fiscal Report').(! in_array(false, $oks, true) ? ': hecho.' : ': ha terminado con avisos (mira «Salida»).');
    }

    public function ejecutar(string $id): void
    {
        $this->ejecutarUno($id);
    }

    /**
     * «🔄 Reiniciar» de «Anaplan · subir a la web» (16-oct-2026, Alex: «he abierto otra ventana mientras trabajaba y ahora
     * no abre Anaplan; ¿cómo lo reseteo?»). Cancela las subidas pedidas o en marcha (el PC corta la que esté haciendo en
     * unos segundos) y pide al PC anaplanWeb/resetAnaplan.js: para el node que quede, cierra SOLO el Chrome de Anaplan
     * y lo vuelve a abrir limpio en el modelo. No toca Anaplan ni ficheros.
     */
    public function reiniciarAnaplan(): void
    {
        if (! $this->autorizar('anaplan_web')) {
            return;
        }
        $ids = [];
        if ($this->remoto()) {
            $ids = DB::table('tareas')->where('proceso', 'pc.script')->whereIn('estado', ['preparando', 'pendiente', 'en_curso'])
                ->where('parametros', 'like', '%subirAnaplan%')->pluck('id')->all();
            if ($ids) {
                DB::table('tareas')->whereIn('id', $ids)->update(['estado' => 'cancelada', 'terminada_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->lanzar([[
            'script' => 'anaplanWeb/resetAnaplan.js', 'args' => [], 'timeout' => 120,
            'etiqueta' => 'Reiniciar Chrome de Anaplan', 'windows' => true,
        ]], ['resultados' => 'anaplan_web']);
        if (! empty($ids)) {
            $this->salida .= "\n🚫 Subida a Anaplan cancelada (tarea #".implode(', #', $ids).'): el PC la corta en unos segundos.';
        }
    }

    /** Quita la línea EDENRED_JSON de la Salida y la devuelve. */
    protected function jsonEdenred(int $desde): ?array
    {
        $nuevo = substr($this->salida, $desde);
        if (! preg_match('/^EDENRED_JSON: (.*)$/m', $nuevo, $m)) {
            return null;
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^EDENRED_JSON: .*(\r?\n)?/m', '', $nuevo));

        return json_decode($m[1], true) ?: null;
    }

    /** Fin de EDENRED. Devuelve true si era la fase de análisis (abre la ventana de decisiones y no marca el check). */
    protected function postEdenred(array $ctx, int $desde, bool $ok): bool
    {
        $d = $this->jsonEdenred($desde);
        if (($ctx['fase'] ?? '') === 'analizar') {
            if ($ok && $d) {
                $this->edenredAnalisis = $d;
                $this->edenredDec = [];
                foreach ($d['diferencias'] ?? [] as $x) {
                    $this->edenredDec[$x['key']] = ['accion' => 'aplicar', 'importe' => $x['dif'], 'nota' => ''];
                }
                $this->edenredAbierto = true;
            } else {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ EDENRED: no he podido analizar la Imputación. Mira la Salida.');
            }

            return true;
        }
        if ($d && Schema::hasTable('estado_procesos')) {
            ColaTareas::guardarEstado('fiq.edenred_historial', (array) ($d['historial'] ?? []), 'Procesos FIQ');
            ColaTareas::guardarEstado('fiq.edenred_usuarios', ['por_alta' => $d['usuarios_por_alta'] ?? [], 'pendientes' => $d['pendientes'] ?? [], 'tiendas' => $d['tiendas'] ?? [], 'cuando' => now()->format('d/m H:i')], 'Procesos FIQ');
        }
        $this->resultadoModal = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', substr($this->salida, $desde)));
        $this->resultadoModalOk = $ok;
        $this->resultadoModalTitulo = $ok ? 'EDENRED: Recargas preparados' : 'EDENRED: ha habido un problema';
        if ($ok) {
            $this->edenredFichero = null;
            $this->edenredAnalisis = null;
        }

        return false;
    }

    /** Fase 2 de EDENRED: con las decisiones de la ventana, genera los Recargas (y los ajustes). */
    public function generarEdenred(): void
    {
        if (! $this->autorizar('edenred') || ! $this->edenredFichero) {
            return;
        }
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $json = tempnam(sys_get_temp_dir(), 'edenred');
        file_put_contents($json, json_encode(['decisiones' => (object) $this->edenredDec], JSON_UNESCAPED_UNICODE));
        $f = $this->edenredFichero;
        $remoto = $this->remoto();
        $pasos = [[
            'script' => 'Edenred/edenred.py',
            'args' => [$mm, '--real', '--fichero', $remoto ? '{E0}' : $f->getRealPath(), '--ajustes', $remoto ? '{E1}' : $json],
            'timeout' => 900,
            'etiqueta' => "EDENRED · Recargas (mes {$mm}, REAL)",
            'windows' => false,
        ]];
        $opc = ['resultados' => 'edenred', 'post' => 'postProceso', 'ctx' => ['id' => 'edenred', 'mes' => $this->mes, 'fase' => 'generar']];
        if ($remoto) {
            $opc['entradas'] = [
                ['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/edenred_in'],
                ['ruta' => $json, 'nombre' => 'edenred_ajustes.json', 'dir' => '_tmp/edenred_in'],
            ];
        }
        $this->edenredAbierto = false;
        $this->lanzar($pasos, $opc);
    }

    public function cerrarEdenred(): void
    {
        $this->edenredAbierto = false;
    }

    /** Imputación con la que ya se prepararon los Recargas del mes (copia en la BD que deja el proceso): evita el «Falta subir la Imputación» cuando ya se hizo. */
    public function getEdenredUsadaProperty(): ?array
    {
        if (! Schema::hasTable('estado_procesos')) {
            return null;
        }
        $h = (array) $this->estadoRemoto('fiq.edenred_historial');

        return $h[$this->anio.'-'.str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT)] ?? null;
    }

    /** Usuarios de Edenred por dar de alta (copia en la BD del VPS que deja cada ejecución). */
    public function getEdenredUsuariosProperty(): array
    {
        return Schema::hasTable('estado_procesos') ? (array) $this->estadoRemoto('fiq.edenred_usuarios') : [];
    }

    /** «Pedir contratos a Jordi»: un correo con todos los trabajadores sin contrato. $modo = borrador | enviar (doble paso en el cliente y aquí). */
    public function correoJordi(string $modo): void
    {
        if (! $this->autorizar('edenred') || ! in_array($modo, ['borrador', 'enviar'], true)) {
            return;
        }
        if ($modo === 'enviar' && ! $this->edenredConfirmaJordi) {
            return;
        }
        $this->edenredConfirmaJordi = false;
        $pend = $this->edenredUsuarios['pendientes'] ?? [];
        if (! $pend) {
            $this->edenredMsg = 'No hay trabajadores pendientes de contrato.';
            return;
        }
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->lanzar([[
            'script' => 'Edenred/edenred.py',
            'args' => [$mm, '--correo-jordi', $modo, '--remitente', (string) auth()->user()?->email, '--pendientes', json_encode($pend, JSON_UNESCAPED_UNICODE)],
            'timeout' => 120,
            'etiqueta' => 'EDENRED · '.($modo === 'enviar' ? 'enviar a Jordi' : 'borrador para Jordi'),
            'windows' => false,
        ]], ['resultados' => 'edenred_jordi']);
        $this->edenredMsg = $modo === 'enviar' ? '✉ Pedido el envío a Jordi: el resultado sale en «Salida».' : '✉ Pedido el borrador para Jordi: el resultado sale en «Salida».';
    }

    /** Carme ha creado el usuario en Edenred: sale de la lista de pendientes. */
    public function altaEdenred(string $clave): void
    {
        if (! $this->autorizar('edenred')) {
            return;
        }
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->lanzar([['script' => 'Edenred/edenred.py', 'args' => [$mm, '--alta', $clave], 'timeout' => 60, 'etiqueta' => 'EDENRED · usuario dado de alta', 'windows' => false]], ['resultados' => 'edenred_alta']);
        if (Schema::hasTable('estado_procesos')) {
            $e = (array) $this->estadoRemoto('fiq.edenred_usuarios');
            $e['por_alta'] = array_values(array_filter($e['por_alta'] ?? [], fn ($u) => ($u['clave'] ?? '') !== $clave));
            ColaTareas::guardarEstado('fiq.edenred_usuarios', $e, 'Procesos FIQ');
        }
    }

    /** Acciones en el portal de Edenred (Playwright en el PC de «Ejecutar en»; Chrome con ventana, Alex hace el login y los 2 pasos). */
    protected function lanzarEdenred(string $script, array $args, string $etiqueta, int $timeout): void
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->edenredMsg = "⏳ {$etiqueta}: pedido. Si Edenred pide entrar, sale un aviso: escribe usuario, contraseña y valida los 2 pasos en la ventana de Chrome del PC.";
        $this->lanzar([['script' => $script, 'args' => array_merge([$mm], $args), 'timeout' => $timeout, 'etiqueta' => "EDENRED · {$etiqueta}", 'windows' => false]],
            ['resultados' => 'edenred_portal', 'post' => 'finEdenredAccion', 'ctx' => ['etiqueta' => $etiqueta]]);
    }

    protected function finEdenredAccion(array $ctx, int $desde, array $oks): void
    {
        $ok = ! in_array(false, $oks, true);
        $this->edenredMsg = ($ok ? '✅ ' : '⚠️ ').($ctx['etiqueta'] ?? 'Edenred').($ok ? ': terminado. Mira el detalle en «Salida».' : ': ha terminado con avisos. Mira «Salida».');
        if (Schema::hasTable('estado_procesos')) {
            // el script ya ha actualizado la copia de usuarios en la BD (altaUsuarios.py); aquí solo se refresca la pantalla
            $this->dispatch('proceso-terminado', mensaje: $this->edenredMsg);
        }
    }

    /** «Dar de alta en Edenred»: tarjeta virtual a 0 € para los pendientes con datos completos (doble paso: es una acción real en el portal). */
    public function altaUsuariosEdenred(): void
    {
        if (! $this->autorizar('edenred') || ! $this->edenredConfirmaAlta) {
            return;
        }
        $this->edenredConfirmaAlta = false;
        $this->lanzarEdenred('Edenred/altaUsuarios.py', ['--confirmar-pedido'], 'dar de alta a los usuarios pendientes (tarjeta virtual, 0 €)', 1500);
    }

    /** Comprueba en «Usuarios y saldos» quién ya existe (p. ej. si se ha creado a mano) y actualiza la lista y el contador. */
    public function comprobarUsuariosEdenred(): void
    {
        if (! $this->autorizar('edenred')) {
            return;
        }
        $this->lanzarEdenred('Edenred/altaUsuarios.py', ['--comprobar'], 'comprobar usuarios en Edenred', 600);
    }

    /** Estado de los Recargas de cada tienda del mes (lo publica el proceso): pedido / listo / bloqueada / sin fichero. */
    public function getEdenredTiendasProperty(): array
    {
        $t = (array) ($this->edenredUsuarios['tiendas'] ?? []);

        return (array) ($t[$this->anio.'-'.str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT)] ?? []);
    }

    /** Sube el Recargas de UNA tienda a Edenred (decide Alex tienda a tienda). $confirmar=false: sube y calcula y se para antes de confirmar. */
    public function subirRecargasEdenred(string $tienda, bool $confirmar): void
    {
        if (! $this->autorizar('edenred') || ! preg_match('/^([A-Z]{3}|TODAS)$/', $tienda) || ($confirmar && $this->edenredConfirmaSubir !== $tienda)) {
            return;
        }
        $this->edenredConfirmaSubir = '';
        $todas = $tienda === 'TODAS';   // todas las tiendas listas, una tras otra (para si se pide con todos los datos completos)
        $this->lanzarEdenred('Edenred/subirRecargas.py', array_merge($todas ? [] : ['--tienda', $tienda], $confirmar ? ['--confirmar'] : []),
            $todas ? 'subir los Recargas de todas las tiendas listas y confirmar los pedidos' : ($confirmar ? "subir los Recargas de {$tienda} a Edenred y confirmar el pedido" : "subir los Recargas de {$tienda} y calcular (sin confirmar)"), 4800);
    }

    public function cerrarResultadoModal(): void
    {
        $this->resultadoModal = '';
        $this->resultadoModalTitulo = '';
    }

    public function abrirHistorial(string $texto = ''): void
    {
        $this->histAbierto = true;
        $this->histTexto = $texto;
        if ($this->histDesde === '') {
            $this->histDesde = now()->subDays(30)->format('Y-m-d');
        }
    }

    /** Tareas de Procesos FIQ ya hechas, más recientes primero, entre las fechas elegidas (y que contengan el texto, si se escribe uno). */
    public function historialTareas(): array
    {
        if (! $this->histAbierto) {
            return [];
        }
        $q = DB::table('tareas')->whereIn('proceso', ['pc.script'])->where('parametros', 'like', '%"grupo":"fiq"%')->whereIn('estado', ['ok', 'error', 'cancelada'])
            ->orderByDesc('id')->limit(300);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->histDesde)) {
            $q->where('created_at', '>=', $this->histDesde.' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->histHasta)) {
            $q->where('created_at', '<=', $this->histHasta.' 23:59:59');
        }
        $out = [];
        foreach ($q->get(['id', 'estado', 'created_at', 'terminada_at', 'web', 'resultado']) as $t) {
            $web = json_decode((string) $t->web, true) ?: [];
            $res = json_decode((string) $t->resultado, true) ?: [];
            $etq = implode(' + ', $web['etiquetas'] ?? []) ?: 'Proceso';
            if ($this->histTexto !== '' && stripos($etq, $this->histTexto) === false) {
                continue;
            }
            $out[] = ['id' => $t->id, 'fecha' => \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i'), 'etiqueta' => $etq,
                'estado' => $t->estado, 'pc' => $res['pc'] ?? '', 'con_salida' => ! empty($res['pasos'])];
        }

        return $out;
    }

    public function verResultadoTarea(int $id): void
    {
        $t = DB::table('tareas')->where('id', $id)->where('parametros', 'like', '%"grupo":"fiq"%')->first(['id', 'estado', 'created_at', 'web', 'resultado', 'log']);
        if (! $t) {
            return;
        }
        $web = json_decode((string) $t->web, true) ?: [];
        $res = json_decode((string) $t->resultado, true) ?: [];
        $etiquetas = $web['etiquetas'] ?? [];
        $txt = '';
        foreach ((array) ($res['pasos'] ?? []) as $i => $paso) {
            $txt .= ($txt !== '' ? "\n\n" : '').'===== '.($etiquetas[$i] ?? ($paso['script'] ?? 'Proceso')).(! empty($res['pc']) ? ' · en '.$res['pc'] : '')." =====\n"
                .trim(preg_replace(['/^RESULT_FILE:.*(\r?\n)?/m', '/^RESUMEN_WEB:\s*/m'], '', (string) ($paso['salida'] ?? '')));
            if (empty($paso['ok'])) {
                $txt .= "\n\n⚠️ Terminó con código de salida ".($paso['codigo'] ?? '?').'.';
            }
        }
        $this->resultadoModal = $txt !== '' ? $txt : '(sin salida guardada) '.trim((string) $t->log);
        $this->resultadoModalOk = $t->estado === 'ok';
        $this->resultadoModalTitulo = '#'.$t->id.' · '.\Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i').' · '.(implode(' + ', $etiquetas) ?: 'Proceso');
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
            $this->lanzarEnCola($pasos, $opc);   // limpia la Salida
            return;
        }
        $this->salida = '';   // cada proceso empieza con la Salida limpia (16-oct-2026)
        $oks = [];
        $desde = strlen($this->salida);
        foreach ($pasos as $p) {
            if (! empty($p['solo_si_ok']) && in_array(false, $oks, true)) {
                $oks[] = false;   // un paso anterior falló (p. ej. no se ha podido dejar el SyS): este no se hace
                continue;
            }
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

    // -- Estado que dejan los scripts (JSON en los PCs; en la web, copia en BD) -------------------------

    /** Lee un JSON de estado: del fichero en un PC, de la copia que subió el trabajador en la web. */
    protected function estadoFiq(string $clave, ?string $fichero): ?array
    {
        if ($this->remoto()) {
            return $this->estadoRemoto($clave);
        }
        $d = $fichero && is_file($fichero) ? json_decode((string) file_get_contents($fichero), true) : null;

        return is_array($d) ? $d : null;
    }

    /** «↻ Sincronizar estado» del panel de PCs. */
    public function sincronizarAhora(): void
    {
        $this->sincronizarEstado('fiq.checklist_def', true);
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
        if (! array_filter($this->ciImp, fn ($v) => trim((string) $v) !== '')) {
            $this->cargarComunicarImpuestos();
        } else {
            $m = $this->confComunicarImpuestos()['meses'][sprintf('%02d', $this->mes)] ?? [];
            $this->ciEnviado = (string) ($m['enviado'] ?? $this->ciEnviado);
            $this->ciBorrador = (string) ($m['borrador'] ?? $this->ciBorrador);
        }
    }

    protected function ejecutarUno(string $id): void
    {
        if (! $this->autorizar($id)) {
            return;
        }
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
        $opc = ['resultados' => $id, 'post' => 'postProceso', 'ctx' => ['id' => $id, 'mes' => $this->mes]];
        if ($id === 'adyen' && ! $this->adyenSettlement && ! $this->adyenAvisado) {
            // 25-oct-2026 (Alex): sin settlement el reparto no hace nada. Se avisa; si ya está en la carpeta Adyen del mes, el segundo clic sigue.
            $this->adyenAvisado = true;
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No has subido el settlement. Súbelo con el botón «Settlement». Si ya está en la carpeta Adyen del mes (settlement_report_aggregate_*.xlsx), pulsa Ejecutar otra vez.');
            return;
        }
        if ($id === 'adyen' && $this->adyenSettlement) {
            $f = $this->adyenSettlement;
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El settlement tiene que ser un .xlsx.');
                return;
            }
            if ($this->remoto()) {
                $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--settlement', '{E0}']);
                $opc['entradas'] = [['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/adyen_in']];
            } else {
                $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--settlement', $f->getRealPath()]);
            }
        }
        if ($id === 'adyen_batch') {
            $fs = array_values(array_filter((array) $this->adyenBatchFicheros));
            if (! $fs) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ Sube primero los ficheros de Adyen (settlement_detail_report_batch_NNN.xlsx).');
                return;
            }
            foreach ($fs as $f) {
                if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                    $this->dispatch('proceso-terminado', mensaje: '⚠️ «'.$f->getClientOriginalName().'» no es un .xlsx: sube los settlement tal como los baja Adyen.');
                    return;
                }
            }
            if ($email = auth()->user()?->email) {   // el correo-resumen va a quien lanza el proceso (30-oct-2026)
                $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--aviso-a', $email]);
            }
            $pasos[0]['args'][] = '--ficheros';
            foreach ($fs as $i => $f) {
                if ($this->remoto()) {
                    $pasos[0]['args'][] = '{E'.$i.'}';
                    $opc['entradas'][] = ['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/adyen_batch_in'];
                } else {
                    $pasos[0]['args'][] = $f->getRealPath();
                }
            }
        }
        if (in_array($id, ['monthly_sales', 'anaplan'], true) && ! $this->prepararSys($mm, $pasos, $opc)) {
            return;
        }
        if ($id === 'cashflow' && ! $this->prepararMayorCashflow($pasos, $opc)) {
            return;
        }
        if ($id === 'open_items' && ! $this->prepararMayorOpenItems($pasos, $opc)) {
            return;
        }
        if ($id === 'laboral' && $this->laboralFichero) {
            $f = $this->laboralFichero;
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El fichero de imputación de costes tiene que ser un Excel (.xlsx).');
                return;
            }
            if ($this->remoto()) {
                $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--fichero', '{E0}']);
                $opc['entradas'] = [['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/laboral_in']];
            } else {
                $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--fichero', $f->getRealPath()]);
            }
        }
        if ($id === 'laboral') {
            $this->prepararStaffLaboral($pasos, $opc);
        }
        if ($id === 'edenred') {
            // Fase 1: solo analiza (no escribe). La ventana de después lanza la fase 2 (generarEdenred).
            $f = $this->edenredFichero;
            if (! $f) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ Sube primero la Imputación de costes del mes (la última versión).');
                return;
            }
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ La Imputación de costes tiene que ser un .xlsx.');
                return;
            }
            $pasos[0]['args'] = [$mm, '--analizar', '--fichero', $this->remoto() ? '{E0}' : $f->getRealPath()];
            $pasos[0]['etiqueta'] = "EDENRED · análisis (mes {$mm})";
            $opc['ctx']['fase'] = 'analizar';
            if ($this->remoto()) {
                $opc['entradas'] = [['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/edenred_in']];
            }
        }
        if ($id === 'ubs') {
            $f = $this->ubsFichero;
            if (! $f) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ Elige primero el extracto de UBS (FIQ UBS dd.mm - dd.mm.xlsx).');
                return;
            }
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El extracto de UBS tiene que ser un .xlsx.');
                return;
            }
            $pasos[0]['args'] = ['--archivo', $this->remoto() ? '{E0}' : $f->getRealPath(), '--real'];
            $pasos[0]['etiqueta'] = 'UBS · '.$f->getClientOriginalName();
            if ($this->remoto()) {
                $opc['entradas'] = [['ruta' => $f->getRealPath(), 'nombre' => $f->getClientOriginalName(), 'dir' => '_tmp/ubs_in']];
            }
        }
        $this->lanzar($pasos, $opc);
        if ($id === 'ubs') {
            $this->ubsFichero = null;
        }
        if ($id === 'adyen') {
            $this->adyenSettlement = null;
            $this->adyenAvisado = false;
        }
        if ($id === 'adyen_batch') {
            $this->adyenBatchFicheros = [];
        }
        if ($id === 'laboral') {
            $this->laboralFichero = null;
        }
        if (in_array($id, ['monthly_sales', 'anaplan'], true)) {
            $this->sysFichero = null;
            $this->sysForzar = false;
        }
        if ($id === 'cashflow') {
            $this->cashflowMayor = null;
        }
        if ($id === 'open_items') {
            $this->openItemsMayor = null;
        }
    }

    // -- Anaplan · personal (Input Staff Data for AC) -----------------------------------------------------------------

    /** «🔎 Preparar»: un PC lee el Excel de nóminas y la lista de empleados de Anaplan del mes; aquí se arma la propuesta. */
    public function prepararStaff(): void
    {
        if (! $this->autorizar('anaplan_staff')) {
            return;
        }
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $this->lanzar([['script' => 'anaplanWeb/staffAnaplan.js', 'args' => [$mm, '--leer'], 'timeout' => 600, 'windows' => true,
            'etiqueta' => "Anaplan · personal {$mm} · leer nóminas y Anaplan"]], ['post' => 'postStaffLeer', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postStaffLeer(array $ctx, int $desde, array $oks): void
    {
        $nuevo = substr($this->salida, $desde);
        if (! preg_match('/^STAFF_JSON: (.*)$/m', $nuevo, $m) || ! is_array($d = json_decode($m[1], true))) {
            return;
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^STAFF_JSON: .*(\r?\n)?/m', '', $nuevo));
        if ((int) $ctx['mes'] !== $this->mes) {
            return;
        }
        $this->staffAnaplan = (array) ($d['anaplan'] ?? []);
        $this->staffAvisos = (array) ($d['avisos'] ?? []);
        $this->staffMesAnaplan = (string) ($d['mes'] ?? '');
        $this->staffFichero = (string) ($d['fichero'] ?? '');
        // lo que el usuario ya hubiera elegido a mano (p. ej. un empleado que acaba de crear en Anaplan y «↻ Releer») se respeta
        $antes = [];
        foreach ($this->staff as $b) {
            foreach ($b['filas'] as $f) {
                if ($f['origen'] === 'mano') {
                    $antes[$b['tienda']][$f['excel']] = $f['anaplan'];
                }
            }
        }
        $this->staff = [];
        foreach (self::proponerStaff((array) ($d['excel'] ?? []), $this->staffAnaplan, $this->staffHistorico()) as $tienda => $filas) {
            foreach ($filas as $i => $f) {
                if (isset($antes[$tienda][$f['excel']])) {
                    $filas[$i]['anaplan'] = $antes[$tienda][$f['excel']];
                    $filas[$i]['origen'] = 'mano';
                    $filas[$i]['nota'] = '';
                }
            }
            $this->staff[] = ['tienda' => $tienda, 'filas' => $filas];
        }
        if (Schema::hasTable('estado_procesos')) {
            ColaTareas::guardarEstado('fiq.anaplan_staff_lista', ['mes' => $this->staffMesAnaplan, 'cuando' => now()->format('Y-m-d H:i'), 'tiendas' => $this->staffAnaplan], 'Procesos FIQ');
        }
        $this->staffAbierto = true;
    }

    /**
     * Flujo de personal desde el 17-oct-2026 (Alex: «primero rellenas el Excel con lo que ya tienes, si falta alguien me avisas, lo creo
     * en Anaplan y te pongo su nombre, y luego un botón Subir que ya se conecta a Anaplan»):
     *   1. Laboral: imputacionCostes.js recibe el histórico de parejas (y la última lista de Anaplan, si la hay), escribe en el cuadro de
     *      cada tienda el nombre de Anaplan y devuelve STAFF_DATOS = por tienda, nombre de la nómina, nombre de Anaplan, bruto y bonus.
     *      Se guarda en fiq.anaplan_staff_datos (por mes).
     *   2. «✎ Nombres»: el modal enseña esos datos; los que no tienen nombre de Anaplan salen en rojo y Alex escribe el que tiene allí
     *      («💾 Guardar nombres» → histórico).
     *   3. «⬆ Subir a Anaplan»: con esos datos, sin leer nada antes; staffAnaplan.js --subir escribe y relee.
     */
    protected function prepararStaffLaboral(array &$pasos, array &$opc): void
    {
        $lista = $this->staffEstado('fiq.anaplan_staff_lista');
        $json = tempnam(sys_get_temp_dir(), 'staff');
        file_put_contents($json, json_encode(['historico' => (object) $this->staffHistorico(), 'anaplan' => (object) ($lista['tiendas'] ?? []),
            'nuevos' => (object) $this->staffEstado('fiq.anaplan_staff_nuevos'), 'mes' => $lista['mes'] ?? ''], JSON_UNESCAPED_UNICODE));
        if ($this->remoto()) {
            $opc['entradas'] = $opc['entradas'] ?? [];
            $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--staff', '{E'.count($opc['entradas']).'}']);
            $opc['entradas'][] = ['ruta' => $json, 'nombre' => 'staff_laboral.json', 'dir' => '_tmp/staff_in'];
        } else {
            $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--staff', $json]);
        }
    }

    protected function postStaffLaboral(array $ctx, int $desde): void
    {
        $nuevo = substr($this->salida, $desde);
        if (! preg_match('/^STAFF_DATOS: (.*)$/m', $nuevo, $m) || ! is_array($d = json_decode($m[1], true))) {
            return;
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^STAFF_DATOS: .*(\r?\n)?/m', '', $nuevo));
        if (Schema::hasTable('estado_procesos')) {
            // las parejas que Laboral ha encontrado solo (mismo nombre en Anaplan) pasan al histórico y quedan como nuevas de este mes
            $h = $this->staffHistorico();
            $nuevos = $this->staffEstado('fiq.anaplan_staff_nuevos');
            $cambia = false;
            foreach ((array) ($d['tiendas'] ?? []) as $tienda => $emps) {
                foreach ($emps as $e) {
                    if (($e['origen'] ?? '') === 'mismo nombre en Anaplan' && ($e['anaplan'] ?? '') !== '' && ! isset($h[$tienda][$e['nomina']])) {
                        $h[$tienda][$e['nomina']] = $e['anaplan'];
                        $nuevos[$tienda][$e['nomina']] = ['mes' => sprintf('%02d', (int) $ctx['mes']), 'cuando' => now()->format('d/m'), 'como' => 'mismo nombre en Anaplan'];
                        $cambia = true;
                    }
                }
            }
            if ($cambia) {
                ColaTareas::guardarEstado('fiq.anaplan_staff_asoc', $h, 'Procesos FIQ');
                ColaTareas::guardarEstado('fiq.anaplan_staff_nuevos', $nuevos, 'Procesos FIQ');
            }
            $todo = $this->staffEstado('fiq.anaplan_staff_datos');
            $todo[sprintf('%02d', (int) $ctx['mes'])] = ['cuando' => now()->format('d/m H:i'), 'fichero' => (string) ($d['fichero'] ?? ''), 'tiendas' => (array) ($d['tiendas'] ?? [])];
            ColaTareas::guardarEstado('fiq.anaplan_staff_datos', $todo, 'Procesos FIQ');
            unset($this->staffDatosMes);
        }
        if ((int) $ctx['mes'] === $this->mes) {
            $this->cargarStaffDatos();
        }
        $faltan = $this->staffSinNombre();
        if ($faltan) {
            $txt = collect($faltan)->map(fn ($f) => "· {$f['tienda']}: {$f['excel']} → créalo como «{$f['propuesta']}»")->implode("\n");
            $this->dispatch('proceso-terminado', mensaje: '⚠️ '.count($faltan)." empleado(s) sin nombre de Anaplan:\n{$txt}\n\n👉 Créalos en Anaplan y escribe su nombre en ANAPLAN · personal → «✎ Nombres». Después, «⬆ Subir a Anaplan».");
        }
    }

    /** Datos de personal del mes del título (los deja Laboral). */
    public function getStaffDatosMesProperty(): array
    {
        return $this->staffEstado('fiq.anaplan_staff_datos')[sprintf('%02d', $this->mes)] ?? [];
    }

    /** Para los recuadros de Laboral y ANAPLAN · personal: [['tienda','excel','propuesta'], ...] del mes, con el histórico de ahora. */
    public function getStaffSinNombreMesProperty(): array
    {
        $h = $this->staffHistorico();
        $out = [];
        foreach ((array) ($this->staffDatosMes['tiendas'] ?? []) as $tienda => $emps) {
            foreach ($emps as $e) {
                if (($h[$tienda][$e['nomina']] ?? (string) ($e['anaplan'] ?? '')) === '') {
                    $out[] = ['tienda' => $tienda, 'excel' => $e['nomina'], 'propuesta' => ($e['propuesta'] ?? '') ?: $e['nomina'], 'quizas' => $e['quizas'] ?? ''];
                }
            }
        }

        return $out;
    }

    /** $this->staff a partir de lo que dejó Laboral, con el histórico de ahora (los nombres escritos después de pasar Laboral). */
    protected function cargarStaffDatos(): bool
    {
        unset($this->staffDatosMes);
        $d = $this->staffDatosMes;
        if (empty($d['tiendas'])) {
            $this->staff = [];
            return false;
        }
        $h = $this->staffHistorico();
        $lista = $this->staffEstado('fiq.anaplan_staff_lista');
        $this->staffAnaplan = (array) ($lista['tiendas'] ?? []);
        $this->staffMesAnaplan = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][$this->mes - 1].'-26';
        $this->staffFichero = (string) ($d['fichero'] ?? '').' · Laboral del '.($d['cuando'] ?? '');
        $this->staffAvisos = [];
        $this->staff = [];
        foreach ($d['tiendas'] as $tienda => $emps) {
            $filas = [];
            foreach ($emps as $e) {
                $a = $h[$tienda][$e['nomina']] ?? (string) ($e['anaplan'] ?? '');
                $filas[] = ['excel' => $e['nomina'], 'bruto' => (float) $e['bruto'], 'bonus' => (float) $e['bonus'], 'anaplan' => $a,
                    'origen' => $a === '' ? '' : 'historico', 'propuesta' => (string) (($e['propuesta'] ?? '') ?: $e['nomina']),
                    'nota' => $a === '' ? 'No tiene nombre de Anaplan: créalo allí (propuesta: «'.(($e['propuesta'] ?? '') ?: $e['nomina']).'»'
                        .(! empty($e['quizas']) ? ', ¿o es «'.$e['quizas'].'»?' : '').') y escribe aquí el nombre que tiene en Anaplan.' : ''];
            }
            $this->staff[] = ['tienda' => $tienda, 'filas' => $filas];
        }

        return true;
    }

    /** Filas sin nombre de Anaplan: [['tienda','excel','propuesta'], ...]. */
    protected function staffSinNombre(): array
    {
        $out = [];
        foreach ($this->staff as $b) {
            foreach ($b['filas'] as $f) {
                if (trim((string) $f['anaplan']) === '') {
                    $out[] = ['tienda' => $b['tienda'], 'excel' => $f['excel'], 'propuesta' => $f['propuesta'] ?? $f['excel']];
                }
            }
        }

        return $out;
    }

    /** «✎ Nombres»: abre el modal con los datos de Laboral del mes. */
    public function abrirNombresStaff(): void
    {
        if (! $this->autorizar('anaplan_staff')) {
            return;
        }
        if (! $this->cargarStaffDatos()) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No tengo los datos de personal del mes '.sprintf('%02d', $this->mes).'. 👉 Pasa antes «Laboral» (Nóminas (Imputación de costes)).');
            return;
        }
        $this->staffAbierto = true;
    }

    /** «💾 Guardar nombres»: lo escrito a mano en el modal pasa al histórico de parejas (nómina → nombre en Anaplan). */
    public function guardarNombresStaff(bool $avisar = true): void
    {
        if (! $this->autorizar('anaplan_staff') || ! $this->staff || ! Schema::hasTable('estado_procesos')) {
            return;
        }
        $h = $this->staffHistorico();
        $nuevos = $this->staffEstado('fiq.anaplan_staff_nuevos');
        $n = 0;
        foreach ($this->staff as $bi => $b) {
            foreach ($b['filas'] as $fi => $f) {
                $a = trim((string) $f['anaplan']);
                if ($f['origen'] === 'mano' && $a !== '' && ($h[$b['tienda']][$f['excel']] ?? null) !== $a) {
                    $h[$b['tienda']][$f['excel']] = $a;
                    $nuevos[$b['tienda']][$f['excel']] = ['mes' => sprintf('%02d', $this->mes), 'cuando' => now()->format('d/m'), 'como' => 'a mano'];
                    $n++;
                }
                if ($f['origen'] === 'mano') {
                    $this->staff[$bi]['filas'][$fi]['origen'] = $a === '' ? '' : 'historico';
                }
            }
        }
        if ($n) {
            ColaTareas::guardarEstado('fiq.anaplan_staff_asoc', $h, 'Procesos FIQ');
            ColaTareas::guardarEstado('fiq.anaplan_staff_nuevos', $nuevos, 'Procesos FIQ');   // en el Excel, en negrita verde («NUEVO»)
            unset($this->staffSinNombreMes);
        }
        if ($avisar) {
            // «💾 Guardar y actualizar el Excel»: Laboral otra vez, que escribe los nombres de Anaplan en el cuadro (y quita el rojo)
            $faltan = count($this->staffSinNombre());
            $this->staffAbierto = false;
            $this->ejecutarUno('laboral');
            $this->dispatch('proceso-terminado', mensaje: '💾 Nombres guardados. Actualizo el Excel de imputación de costes con «Laboral» (mira la Salida).'
                .($faltan ? " Quedan {$faltan} sin nombre de Anaplan (no se subirán)." : ' Ya están todos: después puedes pulsar «⬆ Subir a Anaplan».'));
        }
    }

    protected function staffEstado(string $clave): array
    {
        try {
            return Schema::hasTable('estado_procesos') ? (array) ColaTareas::estado($clave) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function staffHistorico(): array
    {
        try {
            return Schema::hasTable('estado_procesos') ? (array) ColaTareas::estado('fiq.anaplan_staff_asoc') : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Nombre → palabras en mayúsculas sin acentos (Ñ → N), sin comas ni puntos. */
    public static function palabrasNombre(string $n): array
    {
        $n = strtoupper(strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'ç' => 'c', 'Ç' => 'C']));

        return array_values(array_unique(array_filter(preg_split('/[^A-Z]+/', $n), fn ($w) => strlen($w) > 0)));
    }

    /**
     * Cuántas palabras coinciden (en cualquier orden; una palabra cortada vale si es el principio de la otra y tiene 3 letras o más,
     * p. ej. «HUG» = «HUGO»; una inicial suelta vale contra su palabra). Devuelve [coincidencias, máximo de palabras de los dos].
     */
    public static function parecidoNombres(string $a, string $b): array
    {
        $pa = self::palabrasNombre($a);
        $pb = self::palabrasNombre($b);
        $libres = $pb;
        $n = 0;
        foreach ($pa as $w) {
            foreach ($libres as $i => $v) {
                if ($w === $v || (min(strlen($w), strlen($v)) >= 3 && (str_starts_with($w, $v) || str_starts_with($v, $w)))
                    || (min(strlen($w), strlen($v)) === 1 && $w[0] === $v[0])) {
                    $n++;
                    unset($libres[$i]);
                    break;
                }
            }
        }

        return [$n, max(count($pa), count($pb), 1)];
    }

    /** Propuesta: primero el histórico; después, por tienda, las parejas más parecidas (≥ 2 palabras, o 1 si alguno tiene una sola). */
    public static function proponerStaff(array $excel, array $anaplan, array $historico): array
    {
        $out = [];
        foreach ($excel as $tienda => $emps) {
            $lista = array_values((array) ($anaplan[$tienda] ?? []));
            $usados = [];
            $filas = [];
            foreach ($emps as $i => $e) {
                // sin histórico, el nombre de Anaplan que ya escribió Laboral en el Excel (columna D, 17-oct-2026)
                $h = $historico[$tienda][$e['nombre']] ?? ($e['anaplan'] ?? null);
                $filas[$i] = ['excel' => $e['nombre'], 'bruto' => $e['bruto'], 'bonus' => $e['bonus'], 'anaplan' => '', 'origen' => '', 'nota' => ''];
                if ($h !== null && in_array($h, $lista, true)) {
                    $filas[$i]['anaplan'] = $h;
                    $filas[$i]['origen'] = 'historico';
                    $usados[$h] = true;
                } elseif ($h !== null) {
                    $filas[$i]['nota'] = "En el histórico era «{$h}», que ya no está en Anaplan.";
                }
            }
            $pares = [];
            foreach ($filas as $i => $f) {
                if ($f['anaplan'] !== '') {
                    continue;
                }
                foreach ($lista as $a) {
                    if (isset($usados[$a])) {
                        continue;
                    }
                    [$n, $max] = self::parecidoNombres($f['excel'], $a);
                    $min = min(count(self::palabrasNombre($f['excel'])), count(self::palabrasNombre($a)));
                    if ($n >= 2 || ($n >= 1 && $min === 1)) {
                        $pares[] = [$n, $n / $max, $i, $a];
                    }
                }
            }
            usort($pares, fn ($x, $y) => [$y[0], $y[1]] <=> [$x[0], $x[1]]);
            foreach ($pares as [$n, $ratio, $i, $a]) {
                if ($filas[$i]['anaplan'] !== '' || isset($usados[$a])) {
                    continue;
                }
                $filas[$i]['anaplan'] = $a;
                $filas[$i]['origen'] = 'propuesta';
                $filas[$i]['nota'] = $ratio < 1 ? 'Parecido, no igual: revísalo.' : '';
                $usados[$a] = true;
            }
            foreach ($filas as $i => $f) {
                if ($f['anaplan'] === '' && $f['nota'] === '') {
                    $filas[$i]['nota'] = 'No lo encuentro en Anaplan: créalo allí y elígelo (o escribe su nombre), o déjalo vacío para no subirlo.';
                }
            }
            $out[$tienda] = array_values($filas);
        }

        return $out;
    }

    /** «⬆ Subir a Anaplan» desde el modal: solo las filas con empleado de Anaplan. */
    public function subirStaff(): void
    {
        if (! $this->autorizar('anaplan_staff')) {
            return;
        }
        if (! $this->staffAbierto && ! $this->cargarStaffDatos()) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No tengo los datos de personal del mes '.sprintf('%02d', $this->mes).'. 👉 Pasa antes «Laboral» (Nóminas (Imputación de costes)).');
            return;
        }
        $this->guardarNombresStaff(false);
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $filas = [];
        $repes = [];
        foreach ($this->staff as $bloque) {
            $tienda = $bloque['tienda'];
            $vistos = [];
            foreach ($bloque['filas'] as $f) {
                $a = trim((string) $f['anaplan']);
                if ($a === '') {
                    continue;
                }
                if (isset($vistos[$a])) {
                    $repes[] = "{$tienda}: «{$a}» está elegido para «{$vistos[$a]}» y para «{$f['excel']}»";
                }
                $vistos[$a] = $f['excel'];
                $filas[] = ['tienda' => $tienda, 'anaplan' => $a, 'excel' => $f['excel'], 'bruto' => (float) $f['bruto'], 'bonus' => (float) $f['bonus']];
            }
        }
        if ($repes) {
            $this->dispatch('proceso-terminado', mensaje: "⚠️ Hay empleados de Anaplan elegidos dos veces:\n".implode("\n", $repes));
            return;
        }
        if (! $filas) {
            $this->dispatch('proceso-terminado', mensaje: '⚠️ No hay ninguna fila con empleado de Anaplan.');
            return;
        }
        $json = tempnam(sys_get_temp_dir(), 'staff');
        file_put_contents($json, json_encode(['mes' => $this->staffMesAnaplan, 'filas' => $filas], JSON_UNESCAPED_UNICODE));
        $paso = ['script' => 'anaplanWeb/staffAnaplan.js', 'timeout' => 900, 'windows' => true, 'etiqueta' => "Anaplan · personal {$mm} · subir"];
        $opc = ['post' => 'postStaffSubir', 'ctx' => ['mes' => $this->mes, 'asoc' => array_map(fn ($f) => [$f['tienda'], $f['excel'], $f['anaplan']], $filas)]];
        if ($this->remoto()) {
            $paso['args'] = [$mm, '--subir', '{E0WIN}', '--real'];
            $opc['entradas'] = [['ruta' => $json, 'nombre' => 'staff.json', 'dir' => '_tmp/staff_in']];
        } else {
            $paso['args'] = [$mm, '--subir', $this->rutaWindows($json), '--real'];
        }
        $this->lanzar([$paso], $opc);
        $this->staffAbierto = false;
    }

    /** Al cambiar a mano el empleado de Anaplan de una fila del modal. */
    public function updatedStaff($valor, $clave): void
    {
        if (preg_match('/^(\d+)\.filas\.(\d+)\.anaplan$/', (string) $clave, $m) && isset($this->staff[$m[1]]['filas'][$m[2]])) {
            $f = &$this->staff[$m[1]]['filas'][$m[2]];
            $f['origen'] = 'mano';
            $lista = $this->staffAnaplan[$this->staff[$m[1]]['tienda']] ?? [];
            $f['nota'] = trim((string) $valor) === '' ? 'No se sube.' : (! $lista || in_array(trim((string) $valor), $lista, true) ? 'Guardado.' : 'Guardado (no está en la última lista leída de Anaplan: si lo acabas de crear, está bien; al subir lo busco).');
            unset($f);
            // 17-oct-2026: se guarda al momento (Alex escribió el nombre y cerró sin pulsar «Guardar»: se perdía)
            $this->guardarNombresStaff(false);
        }
    }

    protected function postStaffSubir(array $ctx, int $desde, array $oks): void
    {
        $nuevo = substr($this->salida, $desde);
        $res = preg_match('/^STAFF_RES: (.*)$/m', $nuevo, $m) ? (json_decode($m[1], true) ?: []) : [];
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^STAFF_RES: .*(\r?\n)?/m', '', $nuevo));
        // al histórico, las parejas que han quedado bien en Anaplan, con el nombre tal cual está allí (3.º elemento; puede diferir
        // en mayúsculas o acentos del escrito a mano en el modal)
        $bien = [];
        foreach ((array) ($res['ok'] ?? []) as $x) {
            $bien[$x[0].'|'.$x[1]] = $x[2] ?? $x[1];
        }
        $h = $this->staffHistorico();
        foreach ($ctx['asoc'] ?? [] as [$tienda, $excel, $anaplan]) {
            if (isset($bien[$tienda.'|'.$anaplan])) {
                $h[$tienda][$excel] = $bien[$tienda.'|'.$anaplan];
            }
        }
        if ($bien && Schema::hasTable('estado_procesos')) {
            ColaTareas::guardarEstado('fiq.anaplan_staff_asoc', $h, 'Procesos FIQ');
        }
        if (! in_array(false, $oks, true) && empty($res['fallos'])) {
            $this->marcarChecklist('anaplan_staff', $ctx['mes']);
        }
    }

    // -- Ficheros base de Fashion IQ (SyS y mayor), comunes a todos los procesos (App\Support\FicherosBase, 16-oct-2026) --------------

    protected function fiqEntidad(): int
    {
        return (int) config('contabilidad.fiq_entidad');
    }

    /** [MM => sha256 del SyS de los ficheros base que ya se dejó en la carpeta Anaplan]: así solo se vuelve a poner si llega otro. */
    protected function sysPuestos(): array
    {
        try {
            return Schema::hasTable('estado_procesos') ? ((array) ColaTareas::estado('fiq.sys_puesto')) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Antes del proceso, deja como Anaplan/SyS MM.xlsx (ponerSyS.js) el sumas y saldos que toca: el que se acaba de elegir en la fila o,
     * si no, el último del mes subido en CUALQUIER pantalla (ficheros base de Fashion IQ: Procesos FIQ, 202, IS...) si aún no se puso.
     * Lo elegido aquí pasa a los ficheros base cuando ponerSyS lo da por bueno (postSys). false = no seguir.
     */
    protected function prepararSys(string $mm, array &$pasos, array &$opc): bool
    {
        $eid = $this->fiqEntidad();
        $args = [];
        if ($f = $this->sysFichero) {
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El balance de sumas y saldos tiene que ser un .xlsx.');
                return false;
            }
            $sha = hash_file('sha256', $f->getRealPath());
            $ruta = FicherosBase::raiz().'/_pendientes/'.$sha.'.xlsx';   // a los ficheros base, cuando ponerSyS lo dé por bueno
            @mkdir(dirname($ruta), 0775, true);
            if (! @copy($f->getRealPath(), $ruta)) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ No he podido guardar el sumas y saldos subido.');
                return false;
            }
            $nombre = $f->getClientOriginalName();
            $opc['ctx']['sysNuevo'] = ['ruta' => $ruta, 'nombre' => $nombre];
            if ($this->sysForzar) {
                $args[] = '--forzar';
            }
        } else {
            $c = $eid ? FicherosBase::ultimo($eid, 'sys', "{$this->anio}-{$mm}") : null;
            if (! $c || ($this->sysPuestos()[$mm] ?? null) === ($sha = FicherosBase::sha($c['ruta']))) {
                return true;   // no hay ninguno en los ficheros base, o ya es el que está en la carpeta
            }
            [$ruta, $nombre] = [$c['ruta'], $c['nombre']];
            // si no vale (sin desglose por canal, otro mes...), se avisa y se sigue con el SyS que ya hubiera en la carpeta
            $args[] = '--opcional';
            $this->salida .= "\n\nℹ️ Sumas y saldos {$mm}: uso el último subido («{$nombre}», ".date('d/m/Y H:i', $c['fecha']).($c['origen'] ? ", desde {$c['origen']}" : '').').';
        }
        $poner = ['script' => 'ponerSyS.js', 'timeout' => 120, 'etiqueta' => "Dejar SyS {$mm}.xlsx en Anaplan"];
        if ($this->remoto()) {
            $poner['args'] = [$mm, '{E0}', ...$args];
            $opc['entradas'] = [['ruta' => $ruta, 'nombre' => $nombre, 'dir' => '_tmp/sys_in', 'sello' => true]];
        } else {
            $poner['args'] = [$mm, $ruta, ...$args];
        }
        foreach ($pasos as $i => $paso) {
            $pasos[$i]['solo_si_ok'] = true;   // si no se ha podido dejar el SyS, no se sigue con el proceso
        }
        array_unshift($pasos, $poner);
        $opc['ctx'] += ['pasoSys' => $mm, 'sysSha' => $sha];

        return true;
    }

    /** Tras ponerSyS: apunta qué SyS hay en la carpeta y pasa a los ficheros base el que se eligió en la fila. Devuelve $oks sin ese paso. */
    protected function postSys(array $ctx, array $oks): array
    {
        if (empty($ctx['pasoSys'])) {
            return $oks;
        }
        $ok = (bool) array_shift($oks);
        $nuevo = $ctx['sysNuevo'] ?? null;
        if ($ok && Schema::hasTable('estado_procesos')) {
            ColaTareas::guardarEstado('fiq.sys_puesto', [$ctx['pasoSys'] => $ctx['sysSha']] + $this->sysPuestos(), 'Procesos FIQ');
        }
        if ($nuevo && is_file($nuevo['ruta'])) {
            $eid = $this->fiqEntidad();
            if ($ok && $eid && ! FicherosBase::existeContenido($eid, 'sys', $nuevo['ruta'])) {
                FicherosBase::guardar($eid, 'sys', $nuevo['ruta'], $nuevo['nombre'], 'Procesos FIQ'.(auth()->user() ? ' · '.auth()->user()->name : ''), $this->anio.'-'.$ctx['pasoSys']);
            }
            @unlink($nuevo['ruta']);
        }
        unset($this->entradasLocales);

        return $oks;
    }

    /**
     * Cash flow: el mayor elegido en la fila (pasa a los ficheros base para todos los procesos si es el listado de SAGE que usan los
     * demás) o, si no, el último mayor de 2026 de los ficheros base de Fashion IQ. Sin ninguno, el script lo busca en Descargas como siempre.
     */
    protected function prepararMayorCashflow(array &$pasos, array &$opc): bool
    {
        $eid = $this->fiqEntidad();
        if ($f = $this->cashflowMayor) {
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El mayor tiene que ser un Excel (.xlsx) de SAGE.');
                return false;
            }
            [$ruta, $nombre] = [$f->getRealPath(), $f->getClientOriginalName()];
            if ($eid && ! FicherosBase::existeContenido($eid, 'mayor', $ruta)) {
                if (FicherosBase::esMayorLineal($ruta)) {
                    FicherosBase::guardar($eid, 'mayor', $ruta, $nombre, 'Procesos FIQ · Cash flow'.(auth()->user() ? ' · '.auth()->user()->name : ''));
                } else {
                    $this->salida .= "\n\n⚠️ «{$nombre}» es el mayor en formato «fichas» (una cuenta debajo de otra): lo uso para el Cash flow, pero no lo guardo para los demás procesos, que leen el listado en columnas (Código cuenta, Fecha asiento, Debe, Haber...).";
                }
            }
        } else {
            $c = $eid ? FicherosBase::ultimo($eid, 'mayor', null, $this->anio) : null;
            if (! $c) {
                return true;
            }
            [$ruta, $nombre] = [$c['ruta'], 'mayor_central.xlsx'];
            $this->salida .= "\n\nℹ️ Cash flow: uso el último mayor subido («{$c['nombre']}», ".date('d/m/Y H:i', $c['fecha']).($c['origen'] ? ", desde {$c['origen']}" : '').').';
        }
        if ($this->remoto()) {
            $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--mayor', '{E0}']);
            $opc['entradas'] = [['ruta' => $ruta, 'nombre' => 'mayor_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre), 'dir' => '_tmp/mayor_in']];
        } else {
            $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--mayor', $ruta]);
        }

        return true;
    }

    /**
     * Open Sold Items: el mayor elegido en la fila (a los ficheros base si es el listado en columnas) o, si no, el último de 2026 de los
     * ficheros base, que openItems.py solo usa si es más reciente que el de SageLauncher del PC (--mayor-fecha).
     */
    protected function prepararMayorOpenItems(array &$pasos, array &$opc): bool
    {
        $eid = $this->fiqEntidad();
        if ($f = $this->openItemsMayor) {
            if (strtolower($f->getClientOriginalExtension()) !== 'xlsx') {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ El mayor tiene que ser un Excel (.xlsx) de SAGE.');
                return false;
            }
            [$ruta, $nombre] = [$f->getRealPath(), $f->getClientOriginalName()];
            if (! FicherosBase::esMayorLineal($ruta)) {
                $this->dispatch('proceso-terminado', mensaje: '⚠️ Ese mayor está en formato «fichas»: Open Sold Items necesita el listado en columnas (el «FIQ MAyor.xlsx» de SageLauncher).');
                return false;
            }
            if ($eid && ! FicherosBase::existeContenido($eid, 'mayor', $ruta)) {
                FicherosBase::guardar($eid, 'mayor', $ruta, $nombre, 'Procesos FIQ · Open Sold Items'.(auth()->user() ? ' · '.auth()->user()->name : ''));
            }
            $flag = '--mayor';
        } else {
            $c = $eid ? FicherosBase::ultimo($eid, 'mayor', null, $this->anio) : null;
            if (! $c || ! FicherosBase::esMayorLineal($c['ruta'])) {
                return true;   // el script usa el de SageLauncher (o Descargas) del PC
            }
            [$ruta, $nombre, $flag] = [$c['ruta'], $c['nombre'], '--mayor-central'];
            $pasos[0]['args'] = array_merge($pasos[0]['args'], ['--mayor-fecha', date('Y-m-d H:i', $c['fecha'])]);
        }
        if ($this->remoto()) {
            $pasos[0]['args'] = array_merge($pasos[0]['args'], [$flag, '{E0}']);
            $opc['entradas'] = [['ruta' => $ruta, 'nombre' => 'mayor_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre), 'dir' => '_tmp/mayor_in']];
        } else {
            $pasos[0]['args'] = array_merge($pasos[0]['args'], [$flag, $ruta]);
        }

        return true;
    }

    /** Lo que se enseña en la fila de Cash flow: el mayor que se usará. */
    public function getCashflowMayorCentralProperty(): ?array
    {
        $c = $this->fiqEntidad() ? FicherosBase::ultimo($this->fiqEntidad(), 'mayor', null, $this->anio) : null;

        return $c ? ['nombre' => $c['nombre'], 'fecha' => date('d/m/Y H:i', $c['fecha']), 'origen' => $c['origen']] : null;
    }

    /** Fin de «Contabilizar UBS» a mano: guarda lo que dijo el script (última vez, plugin) y, si el PC subió el plugin, su descarga. */
    protected function postUbs(array $ctx, int $desde, bool $ok): void
    {
        $nuevo = substr($this->salida, $desde);
        if (! preg_match('/^UBS_JSON: (.*)$/m', $nuevo, $m)) {
            return;
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^UBS_JSON: .*(\r?\n)?/m', '', $nuevo));
        $d = json_decode($m[1], true);
        if (! $ok || ! is_array($d)) {
            return;
        }
        $tarea = null;
        foreach ($this->resultados['ubs'] ?? [] as $r) {
            if (! empty($r['tarea'])) {
                $tarea = (int) $r['tarea'];
            }
        }
        ColaTareas::guardarEstado('fiq.ubs', $d, 'Procesos FIQ');
        ColaTareas::guardarEstado('fiq.ubs_web', ['cuando' => $d['cuando'] ?? '', 'plugin_nombre' => $d['plugin_nombre'] ?? null, 'tarea' => $tarea], 'Procesos FIQ');
    }

    /** Última vez que se procesó un extracto de UBS (automático o a mano), con la descarga del plugin si se hizo desde la web. */
    public function getUbsUltimoProperty(): array
    {
        $u = (array) $this->estadoRemoto('fiq.ubs');
        $w = (array) $this->estadoRemoto('fiq.ubs_web');
        if ($u && ! empty($w['plugin_nombre']) && $w['plugin_nombre'] === ($u['plugin_nombre'] ?? null)) {
            $u['tarea'] = $w['tarea'] ?? null;
        }

        return $u;
    }

    /** Fin de un proceso de la tabla: si todo fue bien, marca el checklist (con envío aparte: «procesado», no «hecho»). */
    protected function postProceso(array $ctx, int $desde, array $oks): void
    {
        $ok = ! in_array(false, $oks, true);
        $this->postSys($ctx, $oks);
        if (($ctx['id'] ?? '') === 'ubs') {
            $this->postUbs($ctx, $desde, $ok);
            return;   // no marca el check del mes: el extracto no es de un mes concreto
        }
        if (($ctx['id'] ?? '') === 'adyen_batch') {
            $this->resultadoModal = trim(preg_replace('/^RESULT_FILE:.*(\r?\n)?/m', '', substr($this->salida, $desde)));
            $this->resultadoModalOk = $ok;
        }
        if (($ctx['id'] ?? '') === 'laboral') {
            $this->postStaffLaboral($ctx, $desde);
        }
        if (($ctx['id'] ?? '') === 'edenred') {
            if ($this->postEdenred($ctx, $desde, $ok)) {
                return;   // fase de análisis: no se marca el check
            }
        }
        if ($ok) {
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
    public function ejecutarRvCalculosYDeclaracion(bool $calculos = true, bool $declaracion = true): void
    {
        if (! $this->autorizar($calculos ? 'rv_calculos' : 'rv_certificacion')) {
            return;
        }
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $pasos = $calculos ? [[
            'script' => 'calculosRentasVariables.js', 'args' => [$mm, '--no-open', '--real'], 'timeout' => 180,
            'etiqueta' => "RentasVariables · Cálculos (mes {$mm}, REAL)",
        ]] : [];
        if (! $declaracion) {
            $this->rvSoloPasos($pasos, 'calculos');
            return;
        }

        $tiendas = array_values(array_intersect(
            array_keys($this->rvTiendasArrendador()),
            $this->rvTiendas
        ));
        if (empty($tiendas)) {
            $this->salida .= "\n\n(Declaración a arrendador: no hay ninguna tienda marcada -- Barcelona / Málaga -- así que solo se hacen los Cálculos.)\n";
            if (! $calculos) {
                return;
            }
        }
        foreach ($tiendas as $tienda) {
            $pasos[] = [
                'script' => 'rentasVariablesDeclaracion.js', 'args' => [$tienda, $mm, '--real'], 'timeout' => 180,
                'etiqueta' => "RentasVariables · Declaración {$tienda} (mes {$mm}, REAL)",
            ];
        }
        if (! $calculos) {
            $this->rvSoloPasos($pasos, 'declaracion');
            return;
        }
        $opc = ['resultados' => 'rv', 'post' => 'postRvCalculos', 'ctx' => ['mes' => $this->mes]];
        $sysElegido = $this->sysFichero;
        $this->sysFichero = null;   // aquí no hay selector: solo el último de los ficheros base, si aún no está en la carpeta
        $this->prepararSys($mm, $pasos, $opc);
        $this->sysFichero = $sysElegido;
        $this->lanzar($pasos, $opc);
    }

    /** Fila «Alquiler» del checklist (17-oct-2026): «▶ Provisión» = solo Cálculos; «▶ Certificación» = solo el Turnover de BCN/MAL. */
    public function ejecutarRvCalculos(): void
    {
        $this->ejecutarRvCalculosYDeclaracion(true, false);
    }

    public function ejecutarRvDeclaracion(): void
    {
        $this->ejecutarRvCalculosYDeclaracion(false, true);
    }

    /** Lanza solo una de las dos partes de Rentas Variables y marca su check. */
    protected function rvSoloPasos(array $pasos, string $parte): void
    {
        $opc = ['resultados' => 'rv', 'post' => 'postRvParte', 'ctx' => ['mes' => $this->mes, 'parte' => $parte]];
        if ($parte === 'calculos') {
            $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
            $sysElegido = $this->sysFichero;
            $this->sysFichero = null;
            $this->prepararSys($mm, $pasos, $opc);
            $this->sysFichero = $sysElegido;
        }
        $this->lanzar($pasos, $opc);
    }

    protected function postRvParte(array $ctx, int $desde, array $oks): void
    {
        if ($ctx['parte'] === 'calculos') {
            $oks = $this->postSys($ctx, $oks);
            if ($oks[0] ?? false) {
                $this->marcarChecklist('rv_calculos', $ctx['mes']);
            }
        } elseif (in_array(true, $oks, true)) {
            $this->marcarChecklist('rv_certificacion', $ctx['mes']);
        }
    }

    /** $oks[0] = Cálculos; el resto, una Declaración por tienda marcada. */
    protected function postRvCalculos(array $ctx, int $desde, array $oks): void
    {
        $oks = $this->postSys($ctx, $oks);
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
        if (! $this->autorizar('rv_envio')) {
            return;
        }
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
        if (! $this->autorizar('rv_envio')) {
            return;
        }
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
        if (! $this->autorizar('pagos_fin_mes')) {
            return;
        }
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
        array_push($args, '--obs', json_encode(array_map(fn ($o) => trim((string) $o), array_values($this->pfObs)), JSON_UNESCAPED_UNICODE));
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
    protected function pfCargoTexto(): string
    {
        $cargo = trim($this->pfCargo);
        if ($cargo === '') {
            $d = \Carbon\Carbon::create($this->anio, $this->pfMes, 1)->endOfMonth()->startOfDay();
            while ($d->isWeekend()) {
                $d->subDay();
            }
            $cargo = $d->locale('en')->isoFormat('dddd Do');
        }
        return $cargo;
    }

    protected function pfObsPorDefecto(): array
    {
        $t = 'Will be charged on ' . $this->pfCargoTexto();
        return [$t, $t];
    }

    /** Si cambia el día de cargo, las observaciones de VAT/SS que seguían la frase de siempre la siguen. */
    public function updatedPfCargo(): void
    {
        $t = 'Will be charged on ' . $this->pfCargoTexto();
        foreach ([0, 1] as $i) {
            if (preg_match('/^Will be charged on /', (string) ($this->pfObs[$i] ?? ''))) {
                $this->pfObs[$i] = $t;
            }
        }
    }

    public function getPfLineasProperty(): array
    {
        $saldo = $this->pfNum($this->pfSaldo);
        $iva = $this->pfNum($this->pfIva);
        $ss = $this->pfNum($this->pfSs);
        $nom = $this->pfNum($this->pfNominas);
        $fijas = $this->confPagosFinMes()['fijas'] ?? [];

        $completo = $iva !== null && $ss !== null && $nom !== null;
        $impNom = $completo ? $iva + $ss + $nom : null;
        $total = $completo ? $impNom + array_sum(array_column($fijas, 2)) : null;

        $filas = [
            ['VAT TAX', '', $this->pfK($iva), ''],
            ['Social Security', '', $this->pfK($ss), ''],
            ['Payrolls', '', $this->pfK($nom), ''],
        ];
        foreach ($fijas as [$a, $b, $c]) {
            $filas[] = [$a, $b, $this->pfK((float) $c), ''];
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
        if (! $this->autorizar('pagos_fin_mes')) {
            return;
        }
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
        // Observaciones: las guardadas de ese mes o, si no hay, VAT/SS con el día de cargo.
        // Una vez en pantalla se respetan tal cual (vacías = vacías).
        $this->pfObs = isset($m['obs']) ? array_values($m['obs']) : $this->pfObsPorDefecto();
        $this->pfRecFilas = [];
        $this->pfRecOriginal = '';
        $this->pfRecSaldo = '';
        unset($this->resultados['pf'], $this->resultados['pfRec']);
    }

    /** Lee de Outlook el correo de pagos de ese mes (y el último de su hilo) y carga sus filas. */
    public function leerEnviadoPagosFinMes(): void
    {
        if (! $this->autorizar('pagos_fin_mes')) {
            return;
        }
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
        if (! $this->autorizar('pagos_fin_mes')) {
            return;
        }
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
    public function updatedAnio(): void
    {
        $this->anio = max(2020, min(2100, $this->anio));
        unset($this->checklist, $this->checklistMarcas);
        $this->updatedMes();
    }

    public function updatedMes(): void
    {
        $this->resultados = [];
        $this->staff = $this->staffAnaplan = $this->staffAvisos = [];
        $this->staffAbierto = false;
        $this->updatedPfMes();
        $this->cargarCashInStore();
        $this->cargarIntrastat();
        $this->cargarComunicarImpuestos();
        $this->intrastatFichero = null;
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
        if (! $this->autorizar('cash_in_store')) {
            return;
        }
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
        if (! $this->autorizar('cash_in_store')) {
            return;
        }
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
        if (! $this->autorizar('cashflow')) {
            return;
        }
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
        if (! $this->autorizar('cash_in_store')) {
            return;
        }
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
        if (! $this->autorizar('cash_in_store')) {
            return;
        }
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

    // ---- Intrastat (14-oct-2026) ----------------------------------------------
    // "Que pueda elegir el fichero en la tarea Intrastat y que cuando lo procese se marque como hecho": el Excel
    // «Outbound Fashion IQ PPI & PS - MM.AAAA.xlsx» pasa por el motor de Impuestos → Intrastat (en el servidor,
    // sin PC trabajador; cliente «fashioniq», periodo 2026-MM) y deja el CSV para la Sede.

    public $intrastatFichero = null;
    /** resumen.json del mes del título (partidas, avisos, fichero…) o null. */
    public ?array $intrastatRes = null;

    protected function intrastatPeriodo(): string
    {
        return sprintf('%d-%02d', $this->anio, $this->mes);
    }

    protected function cargarIntrastat(): void
    {
        try {
            $this->intrastatRes = IntrastatMotor::resumen('fashioniq', $this->intrastatPeriodo());
        } catch (\Throwable $e) {
            $this->intrastatRes = null;
        }
    }

    public function procesarIntrastat(): void
    {
        if (! $this->autorizar('intrastat')) {
            return;
        }
        $periodo = $this->intrastatPeriodo();
        $etiqueta = "Intrastat {$periodo}";
        $f = $this->intrastatFichero;
        if ($f) {
            if (! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xlsm'], true)) {
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTiene que ser el Excel (.xlsx) de Outbound.");
                return;
            }
            $entrada = IntrastatMotor::carpeta('fashioniq', $periodo, 'entrada');
            foreach (glob($entrada.'/*') ?: [] as $viejo) {
                @unlink($viejo); // el fichero elegido sustituye a lo que hubiera de ese mes
            }
            copy($f->getRealPath(), $entrada.'/'.IntrastatMotor::nombreEntrada($f->getClientOriginalName()));
            $this->intrastatFichero = null;
        }
        $error = IntrastatMotor::generar('fashioniq', $periodo);
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        if ($error) {
            $this->salida .= "⚠️ {$error}\n   👉 Qué hacer: elige el Excel «Outbound Fashion IQ PPI & PS - ".substr($periodo, 5).".".$this->anio.".xlsx» y vuelve a procesar.";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\n{$error}");
            return;
        }
        $this->cargarIntrastat();
        $r = $this->intrastatRes ?? [];
        $avisos = $r['avisos'] ?? [];
        $this->salida .= ($r['partidas'] ?? 0).' partidas · '.number_format((float) ($r['valor'] ?? 0), 2, ',', '.').' € · '
            .number_format((float) ($r['uds'] ?? 0), 0, ',', '.').' unidades · de: '.implode(', ', $r['entradas'] ?? [])
            .($avisos ? "\n⚠️ ".count($avisos)." avisos (revísalos antes de subirlo):\n  • ".implode("\n  • ", $avisos) : "\nSin avisos.")
            ."\nCSV para la Sede: {$r['fichero']} (⬇ en la fila Intrastat).";
        $this->marcarChecklist('intrastat', $this->mes);
        $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\n".($r['partidas'] ?? 0).' partidas'.($avisos ? ' y '.count($avisos).' avisos para revisar.' : '.'));
    }

    public function descargarIntrastat()
    {
        if (! $this->autorizar('intrastat') || empty($this->intrastatRes['fichero'])) {
            return null;
        }
        $ruta = IntrastatMotor::datosDir().'/fashioniq/'.$this->intrastatPeriodo().'/salida/'.basename($this->intrastatRes['fichero']);
        return is_file($ruta) ? response()->download($ruta, basename($ruta)) : null;
    }

    // ---- Comunicar impuestos a Plein (14-oct-2026) ----------------------------
    // Correo «<Month> Taxes» con lo que se va a presentar y el día del cargo en BBVA (como «August Taxes»).
    // M303 lo busca el script en el Excel de IVA del mes; M111/M115/M216 los escribe Alex. Script:
    // monthlyFIQ/ComunicarImpuestos/comunicarImpuestos.py (destinatarios y lo de cada mes en su .json).

    /** Orden del correo: clave => [modelo, texto del correo, (sin uso: el día lo da PlazosAeat)]. */
    public const CI_FILAS = ['m111' => ['M111', 'Withholding payrolls', 20], 'm115' => ['M115', 'Withholding rent', 20],
        'm216' => ['M216', 'Withholding non-residents', 20], 'm303' => ['M303', 'VAT', 0]];

    public array $ciImp = ['m111' => '', 'm115' => '', 'm216' => '', 'm303' => ''];
    public array $ciFecha = ['m111' => '', 'm115' => '', 'm216' => '', 'm303' => ''];
    public string $ciTo = '';
    public string $ciCc = '';
    public string $ciEnviado = '';
    public string $ciBorrador = '';
    public string $ciEmailPrueba = 'alex.arregui@sumaempresa.com';
    public bool $ciAbierto = false;
    /** Primer paso del envío real (el segundo es «Confirmar envío REAL»). */
    public bool $ciArmado = false;

    protected function confComunicarImpuestos(): array
    {
        return $this->estadoFiq('fiq.comunicarImpuestos', $this->scriptDir().'/ComunicarImpuestos/comunicarImpuestos.json') ?? [];
    }

    /** Último día de plazo (= cargo domiciliado), como el script: App\Support\PlazosAeat (calendario AEAT). */
    protected function ciFechaDefecto(string $clave): string
    {
        $extra = array_map(fn ($f) => \Carbon\Carbon::createFromFormat('d/m/Y', $f)->format('Y-m-d'),
            $this->confComunicarImpuestos()['festivos_extra'] ?? []);
        return \App\Support\PlazosAeat::finPlazo($clave, $this->anio, $this->mes, false, $extra)->format('d/m/Y');
    }

    protected function cargarComunicarImpuestos(): void
    {
        $conf = $this->confComunicarImpuestos();
        $m = $conf['meses'][sprintf('%02d', $this->mes)] ?? [];
        foreach (self::CI_FILAS as $k => $_) {
            $this->ciImp[$k] = (string) ($m[$k] ?? '');
            $this->ciFecha[$k] = (string) ($m['f'.substr($k, 1)] ?? $this->ciFechaDefecto($k));
        }
        $this->ciTo = implode(', ', $conf['to'] ?? []);
        $this->ciCc = implode(', ', $conf['cc'] ?? []);
        $this->ciEnviado = (string) ($m['enviado'] ?? '');
        $this->ciBorrador = (string) ($m['borrador'] ?? '');
        $this->ciArmado = false;
    }

    /** Filas tal como saldrán en el correo (solo las que tienen importe). */
    public function getCiLineasProperty(): array
    {
        $out = [];
        foreach (self::CI_FILAS as $k => [$modelo, $texto]) {
            $v = $this->cisNum((string) $this->ciImp[$k]);
            if ($v !== null) {
                $out[] = [$texto, number_format($v, 2, ',', '.'), $this->ciFecha[$k] ?: $this->ciFechaDefecto($k)];
            }
        }
        return $out;
    }

    public function buscar303(): void
    {
        if (! $this->autorizar('comunicar_impuestos')) {
            return;
        }
        $mm = $this->cisMm();
        $this->lanzar([['script' => 'ComunicarImpuestos/comunicarImpuestos.py', 'args' => [(string) $this->mes, '--buscar'], 'timeout' => 120,
            'etiqueta' => "Comunicar impuestos {$mm} · buscar 303"]], ['post' => 'postBuscar303', 'ctx' => ['mes' => $this->mes]]);
    }

    protected function postBuscar303(array $ctx, int $desde, array $oks): void
    {
        $nuevo = substr($this->salida, $desde);
        if ($ctx['mes'] === $this->mes && preg_match('/^DATO m303=(-?[\d.]+)\s*$/m', $nuevo, $m)) {
            $this->ciImp['m303'] = number_format((float) $m[1], 2, ',', '.');
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^DATO .*(\r?\n)?/m', '', $nuevo));
    }

    /** Primer paso del envío real: enseña «Confirmar envío REAL». */
    public function armarComunicarImpuestos(): void
    {
        if ($this->autorizar('comunicar_impuestos')) {
            $this->ciArmado = ! $this->ciArmado;
        }
    }

    /** $modo: 'vista', 'prueba', 'borrador' (Borradores de Outlook, no se envía) o 'real' (solo tras armar). */
    public function comunicarImpuestos(string $modo): void
    {
        if (! $this->autorizar('comunicar_impuestos') || ! in_array($modo, ['vista', 'prueba', 'borrador', 'real'], true)) {
            return;
        }
        $mm = $this->cisMm();
        $etiqueta = "Comunicar impuestos {$mm} · ".['vista' => 'vista previa', 'prueba' => 'prueba a '.trim($this->ciEmailPrueba),
            'borrador' => 'borrador en Outlook', 'real' => 'ENVÍO REAL a Plein'][$modo];
        if ($modo === 'real' && ! $this->ciArmado) {
            return; // sin el primer paso no se envía nada
        }
        $this->ciArmado = false;
        $error = match (true) {
            ! array_filter($this->ciImp, fn ($v) => trim((string) $v) !== '') => 'no hay ningún importe.',
            in_array($modo, ['borrador', 'real'], true) && trim($this->ciTo) === '' => 'el «Para» está vacío.',
            $modo === 'prueba' && trim($this->ciEmailPrueba) === '' => 'pon un correo de prueba.',
            default => null,
        };
        if ($error) {
            $this->salida .= "\n\n===== {$etiqueta} =====\n⚠️ ".ucfirst($error);
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\n".ucfirst($error));
            return;
        }
        $args = [(string) $this->mes];
        foreach (self::CI_FILAS as $k => $_) {
            // con «=»: un importe negativo no se confunde con una opción
            array_push($args, "--{$k}=".trim((string) $this->ciImp[$k]), '--f'.substr($k, 1).'='.trim((string) $this->ciFecha[$k]));
        }
        array_push($args, '--to', $this->ciTo, '--cc', $this->ciCc);
        $args = array_merge($args, match ($modo) {
            'real' => ['--real'],
            'borrador' => ['--borrador'],
            'prueba' => ['--test', trim($this->ciEmailPrueba)],
            default => ['--sin-enviar'],
        });
        $this->lanzar([['script' => 'ComunicarImpuestos/comunicarImpuestos.py', 'args' => $args, 'timeout' => 240, 'etiqueta' => $etiqueta]],
            ['resultados' => 'ci', 'post' => 'postComunicarImpuestos', 'ctx' => ['mes' => $this->mes, 'modo' => $modo]]);
    }

    protected function postComunicarImpuestos(array $ctx, int $desde, array $oks): void
    {
        $nuevo = substr($this->salida, $desde);
        if (! in_array(false, $oks, true)) {
            if ($ctx['modo'] === 'real' && str_contains($nuevo, 'ENVIADO_REAL')) {
                $this->marcarChecklist('comunicar_impuestos', $ctx['mes'], 'ok');
                $this->ciEnviado = date('Y-m-d H:i');
            } elseif ($ctx['modo'] === 'borrador' && str_contains($nuevo, 'BORRADOR_OK')) {
                $this->marcarChecklist('comunicar_impuestos', $ctx['mes'], 'proc');
                $this->ciBorrador = date('Y-m-d H:i');
            }
        }
        $this->salida = substr($this->salida, 0, $desde).trim(preg_replace('/^(ENVIADO_REAL|BORRADOR_OK)\s*$/m', '', $nuevo));
    }


    // ---- Checklist de cierre (pedido 2026-10-01) -----------------------------
    // "Guardar un registro de que se ha hecho cada mes para no volverme loco":
    // un check por proceso y mes. La lista de procesos (con su detalle, el ⓘ)
    // está en monthlyFIQ/checklist.json; las marcas, en OneDrive
    // (Fashion AAAA/checklist FIQ AAAA.estado.json) para que los dos PCs vean
    // lo mismo. Los procesos que se lanzan desde aquí se marcan solos al
    // terminar bien (marcarChecklist); el resto, con un clic.

    /** Columnas: dic-2025 (el checklist empezó ahí) y los 12 meses del año. */
    public function getChecklistMesesProperty(): array
    {
        $meses = [($this->anio - 1) . '-12' => 'dic ' . substr((string) ($this->anio - 1), 2)];
        foreach (['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as $i => $n) {
            $meses[sprintf('%d-%02d', $this->anio, $i + 1)] = $n;
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
        // Solo los procesos que este usuario puede ver (permisos de proceso del panel de control)
        return array_values(array_filter(array_column($conPos, 1), fn ($p) => $this->permitido((string) $p['id'])));
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
                $dir = "/mnt/{$u}/OneDrive/{$c}/{$this->anio}/Fashion {$this->anio}";
                if (is_dir($dir)) {
                    return "{$dir}/checklist FIQ {$this->anio}.estado.json";
                }
            }
        }
        return null;
    }

    protected function leerChecklistEstado(): array
    {
        $d = $this->estadoFiq('fiq.checklist_estado.'.$this->anio, $this->checklistEstadoPath());
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
            ColaTareas::guardarEstado('fiq.checklist_estado.'.$this->anio, $cambio($this->leerChecklistEstado()), 'web');
            ColaTareas::crear('fiq.checklist', $op, ColaTareas::pcElegido(), auth()->id(), null, ColaTareas::preferido());
            unset($this->checklist, $this->checklistMarcas);
            return;
        }
        $f = $this->checklistEstadoPath();
        if (! $f) {
            $this->salida .= "\n\n⚠️ Checklist: no encuentro la carpeta Fashion {$this->anio} de OneDrive; no se ha guardado.";
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

    /** Lo llaman los procesos al terminar bien. $mes = número de mes del año de trabajo. */
    protected function marcarChecklist(string $id, int $mes, string $estado = 'ok'): void
    {
        try {
            $clave = sprintf('%d-%02d', $this->anio, $mes);
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
        if (! $this->autorizar($id)) {
            return;
        }
        if (! in_array($id, array_column($this->checklist, 'id'), true)) {
            return;
        }
        $mes = sprintf('%d-%02d', $this->anio, $this->mes);
        $hecho = ($this->checklistMarcas[$mes][$id]['estado'] ?? '') === 'ok';
        $this->guardarChecklist($id, $mes, $hecho ? null : ['estado' => 'ok', 'cuando' => date('Y-m-d H:i'), 'como' => 'manual']);
    }

    /** Clic en un check: vacío → ✓ → «no toca» → vacío. */
    public function alternarChecklist(string $id, string $mes): void
    {
        if (! $this->autorizar($id)) {
            return;
        }
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
