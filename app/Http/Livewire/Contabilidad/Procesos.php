<?php

namespace App\Http\Livewire\Contabilidad;

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
    public bool $modoReal = true; // marcado por defecto (pedido del usuario 2026-09-07)
    public string $salida = '';

    // RentasVariables (formularios aparte, no encajan en el check general)
    //
    // Tres acciones con alcances distintos (ver PROCESO_GENERAL.md en
    // Contabilidad/monthlyFIQ):
    //  - Cálculos    -> SIEMPRE las 4 tiendas con renta variable (La Roca,
    //                   Las Rozas, Málaga, Barcelona). No depende de $rvTiendas.
    //  - Declaración -> solo BCN / MAL (únicas con arrendador externo).
    //  - Envío       -> solo BCN / MAL, un envío por tienda con sus destinatarios.
    /** Tiendas marcadas para "Declaración a arrendador". Valores: 'BCN', 'MAL'. */
    public array $rvTiendas = ['BCN', 'MAL'];
    public int $rvMesInicio;
    public int $rvMesFin;
    public bool $rvReal = true; // marcado por defecto (pedido del usuario 2026-09-07), igual que $modoReal

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
        $this->mes = (int) date('n');
        $this->rvMesInicio = $this->mes;
        $this->rvMesFin = $this->mes;
        $this->rvEnvio = $this->rvDestinatariosPorDefecto();
    }

    public function getRvTiendasArrendadorProperty(): array
    {
        return $this->rvTiendasArrendador();
    }

    protected function scriptDir(): string
    {
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
    protected function nodeCmd(string $script, array $rest = []): array
    {
        return ['node', '--jitless', $script, ...$rest];
    }

    /**
     * Orden fijo (es el orden en que se ejecutan si se marcan varios a la vez).
     * "Monthly sales" va PRIMERO (pedido del usuario 2026-09-09: se lo saltó
     * por tenerlo el último). No depende de la salida de los demás -- lee
     * SyS MM.xlsx, Ctrol Dinamico y Laboral directamente.
     */
    protected function procesos(): array
    {
        return [
            'monthly_sales' => [
                'label' => 'Monthly sales',
                'script' => 'monthlyFIQ.js',
                'soportaReal' => true,
                'siempreReal' => true,
                'ayuda' => '⚠️ SIEMPRE escribe sobre el fichero real de Monthly y sobre Ctrol Dinamico (con backup automático). No tiene modo de prueba.',
            ],
            'anaplan_split' => [
                'label' => 'Anaplan · separar por canal',
                'script' => 'sysSplit.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Real: guarda Anaplan/SyS MM Split.xlsx (backup de la versión anterior si ya existía). Prueba: _test_output_sysSplit_MM.xlsx.',
            ],
            'anaplan_consolida' => [
                'label' => 'Anaplan · consolidar en SyS 2026',
                'script' => 'anaplanConsolida.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Vuelca el SaldoP de cada cuenta en Anaplan/SyS 2026.xlsx.',
            ],
            'anaplan_desviaciones' => [
                'label' => 'Anaplan · informe de desviaciones',
                'script' => 'anaplanDesviaciones.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Compara el mes con el anterior (o la media, cuentas prioritarias) y resalta en amarillo.',
            ],
            'laboral' => [
                'label' => 'Laboral · imputación de costes',
                'script' => 'imputacionCostes.js',
                'soportaReal' => true,
                'siempreReal' => false,
                'ayuda' => 'Real: escribe sobre el fichero de imputación de costes original de Laboral 2026/MM/ (con backup). Prueba: _test_output_imputacionCostes_MM.xlsx.',
            ],
        ];
    }

    public function getProcesosProperty(): array
    {
        return $this->procesos();
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
     */
    protected function ejecutarScript(array $args, int $timeout, string $etiqueta): void
    {
        // Pedido explícito del usuario (2026-09-07): estos scripts solo tienen
        // sentido en un PC con los ficheros de OneDrive de verdad (AlexMiniPC,
        // PortalExomen...). En el VPS de producción (app-mos.com) el código se
        // despliega igual (para que la pantalla se vea), pero bloqueado -- ver
        // config/contabilidad.php.
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida .= '⚠️ Opción no válida. Solo ejecutable desde un terminal autorizado.';
            $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nOpción no válida. Solo ejecutable desde un terminal autorizado.");
            return;
        }

        try {
            $result = Process::path($this->scriptDir())->timeout($timeout)->run($args);
            $this->salida .= trim($result->output() . "\n" . $result->errorOutput());
            if ($result->successful()) {
                $this->dispatch('proceso-terminado', mensaje: "✅ {$etiqueta}\nTerminado correctamente.");
            } else {
                $this->salida .= "\n\n⚠️ El proceso terminó con código de salida " . $result->exitCode() . '.';
                $this->dispatch('proceso-terminado', mensaje: "⚠️ {$etiqueta}\nTerminó con error (código " . $result->exitCode() . "). Mira la caja de Salida para el detalle.");
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
    }

    protected function ejecutarUno(string $id): void
    {
        $procesos = $this->procesos();
        if (! isset($procesos[$id])) {
            return;
        }
        $p = $procesos[$id];
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        $args = $this->nodeCmd($p['script'], [$mm]);
        $usaReal = $p['siempreReal'] || ($p['soportaReal'] && $this->modoReal);
        if ($p['soportaReal'] && $this->modoReal) {
            $args[] = '--real';
        }

        $etiquetaModo = $p['siempreReal'] ? 'SIEMPRE REAL' : ($usaReal ? 'REAL' : 'prueba');
        $etiqueta = "{$p['label']} (mes {$mm}, {$etiquetaModo})";
        $this->salida .= "\n\n===== {$etiqueta} =====\n";

        $this->ejecutarScript($args, 180, $etiqueta);
    }

    // -- RentasVariables (formularios aparte) -------------------------------

    public function ejecutarRvCalculos(): void
    {
        $mm = str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);
        $args = $this->nodeCmd('calculosRentasVariables.js', [$mm, '--no-open']);
        if ($this->rvReal) {
            $args[] = '--real';
        }
        $etiqueta = 'RentasVariables · Cálculos (mes ' . $mm . ', ' . ($this->rvReal ? 'REAL' : 'prueba') . ')';
        $this->salida .= "\n\n===== {$etiqueta} =====\n";
        $this->ejecutarScript($args, 180, $etiqueta);
    }

    public function ejecutarRvDeclaracion(): void
    {
        $mi = str_pad((string) $this->rvMesInicio, 2, '0', STR_PAD_LEFT);
        $mf = str_pad((string) $this->rvMesFin, 2, '0', STR_PAD_LEFT);

        $tiendas = array_values(array_intersect(
            array_keys($this->rvTiendasArrendador()),
            $this->rvTiendas
        ));
        if (empty($tiendas)) {
            $this->salida .= "\n\n===== RentasVariables · Declaración =====\nERROR: no hay ninguna tienda marcada (Barcelona / Málaga).\n";
            $this->dispatch('proceso-terminado', mensaje: "⚠️ RentasVariables · Declaración\nNo hay ninguna tienda marcada.");
            return;
        }

        foreach ($tiendas as $tienda) {
            $args = $this->nodeCmd('rentasVariablesDeclaracion.js', [$tienda, $mi]);
            if ($mf !== $mi) {
                $args[] = $mf;
            }
            if ($this->rvReal) {
                $args[] = '--real';
            }
            $etiqueta = "RentasVariables · Declaración {$tienda} ({$mi}-{$mf}, " . ($this->rvReal ? 'REAL' : 'prueba') . ')';
            $this->salida .= "\n\n===== {$etiqueta} =====\n";
            $this->ejecutarScript($args, 180, $etiqueta);
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

    public function render()
    {
        return view('livewire.contabilidad.procesos');
    }
}
