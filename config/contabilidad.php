<?php

// Credenciales de Graph de los PCs: si no están en el .env, las de Contabilidad/FacturacionPDFyMail/config.json
// (que ya hay en los dos PCs y no va por git). En el VPS van en el .env.
$graphPc = (function () {
    foreach (['e', 'f', 'd'] as $u) {
        $f = "/mnt/{$u}/Claude/Contabilidad/FacturacionPDFyMail/config.json";
        if (is_file($f)) {
            return json_decode((string) @file_get_contents($f), true)['email']['graph'] ?? [];
        }
    }
    return [];
})();

return [

    /*
    |--------------------------------------------------------------------------
    | Ejecución local de los scripts de monthlyFIQ
    |--------------------------------------------------------------------------
    |
    | Los scripts de Contabilidad/monthlyFIQ (Anaplan, Laboral, Monthly sales,
    | RentasVariables) leen y escriben directamente en los ficheros de
    | OneDrive de este PC -- solo tiene sentido lanzarlos desde una máquina
    | donde esos ficheros existen de verdad (AlexMiniPC, PortalExomen...).
    | En el VPS (appmos.sumaempresa.com, producción) el código está desplegado para que
    | la pantalla se vea (menú, tabla de procesos), pero NO hay ficheros de
    | OneDrive ni Node -- este flag debe quedar en false ahí (por defecto,
    | si no está en el .env) y en true SOLO en el .env de los PCs
    | autorizados. Ver app/Http/Livewire/Contabilidad/Procesos.php.
    |
    */

    'ejecucion_local' => env('CONTABILIDAD_EJECUCION_LOCAL', false),

    /*
    |--------------------------------------------------------------------------
    | Bancos (24-sep-2026): se usa desde la web (appmos.sumaempresa.com)
    |--------------------------------------------------------------------------
    |
    | Bancos no toca OneDrive: todo se sube y se descarga por el navegador, así
    | que funciona en el VPS aunque el resto de Contabilidad esté bloqueado.
    | La copia buena de sus datos (bases, Maestro, configuración, ficheros
    | generados) es la del VPS; para no tener dos bases distintas:
    |   - VPS:   BANCOS_EJECUCION=true, BANCOS_DIR=/var/www/bancos
    |   - Local: BANCOS_URL=https://appmos.sumaempresa.com/contabilidad/bancos
    |            -> la pestaña Bancos lleva a la web y la pantalla local no ejecuta.
    */

    'bancos_ejecucion' => env('BANCOS_EJECUCION', false),
    'bancos_dir' => env('BANCOS_DIR', '/mnt/e/Claude/Contabilidad/Bancos'),
    'bancos_url' => env('BANCOS_URL'),

    // Clave compartida con Facturas OCR (PCs) para /api/bancos/{cliente}/cuentas-nuevas
    // (cuentas de proveedor creadas en Bancos o en Facturas OCR que aún no están en SAGE).
    'bancos_sync_token' => env('BANCOS_SYNC_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Impuesto sobre Sociedades (25-sep-2026): se usa desde la web, como Bancos
    |--------------------------------------------------------------------------
    |
    | Genera el .200 para importar en Sociedades WEB (Contabilidad/IS/motor).
    | No toca OneDrive ni presenta nada: los ficheros se suben por el navegador
    | y el .200 se descarga. Datos de los clientes en <IS_DIR>/clientes/<NIF>.
    |   - VPS:   IS_EJECUCION=true, IS_DIR=/var/www/is
    |   - Local: IS_URL=https://appmos.sumaempresa.com/contabilidad/is
    | IS_PYTHON (opcional): python3 con openpyxl, si el del sistema no lo tiene.
    */

    'is_ejecucion' => env('IS_EJECUCION', false),
    'is_dir' => env('IS_DIR', '/mnt/e/Claude/Contabilidad/IS'),
    'is_url' => env('IS_URL'),
    'is_python' => env('IS_PYTHON'),

    /*
    | Proc.Mensuales: sus datos son los de la base de datos de Appmos (no toca OneDrive),
    | así que se usa en la web (VPS: PROCESOSMENSUALES_EJECUCION=true). En los PCs,
    | PROCESOSMENSUALES_URL hace que la pestaña redirija a la web (como Bancos e IS).
    */
    'procesosmensuales_ejecucion' => env('PROCESOSMENSUALES_EJECUCION', false),
    'procesosmensuales_url' => env('PROCESOSMENSUALES_URL'),
    // Certificados por caducar: se ejecuta en LOCAL (los certificados están en los PCs); el Seguimiento de la web enlaza aquí.
    'certificados_local_url' => env('CERTIFICADOS_LOCAL_URL', 'http://localhost:8000/contabilidad/certificados'),
    // El escaneo de cada PC se sube a la web (POST con X-Token): URL de la API y token (el mismo valor en el VPS y en los PCs)
    'certificados_sync_url' => env('CERTIFICADOS_SYNC_URL', 'https://appmos.sumaempresa.com/api/certificados/escaneo'),
    'certificados_sync_token' => env('CERTIFICADOS_SYNC_TOKEN'),

    // Microsoft Graph (Mail.Send) para los correos de Proc.Mensuales. GRAPH_SENDER = remitente si el
    // usuario no tiene correo @sumaempresa.com; GRAPH_REDIRECT = mandar todo a esa dirección (pruebas).
    'graph' => [
        'tenant_id' => env('GRAPH_TENANT_ID') ?: ($graphPc['tenant_id'] ?? null),
        'client_id' => env('GRAPH_CLIENT_ID') ?: ($graphPc['client_id'] ?? null),
        'client_secret' => env('GRAPH_CLIENT_SECRET') ?: ($graphPc['client_secret'] ?? null),
        'sender' => env('GRAPH_SENDER', 'alex.arregui@sumaempresa.com'),
        'redirect' => env('GRAPH_REDIRECT'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Facturas OCR (25-sep-2026): facturas recibidas en PDF -> PluginFacturas.xlsx
    |--------------------------------------------------------------------------
    |
    | Lee los PDF de una carpeta de OneDrive, los renombra y al validarlos los
    | mueve al mes de registro: como Procesos FIQ, solo se ejecuta donde
    | contabilidad.ejecucion_local está a true. Código y datos por cliente en
    | Contabilidad/FacturasOcr; python del .venv de esa carpeta (pymupdf, openpyxl).
    */

    // La carpeta Claude no está en la misma unidad en todos los PCs (AlexMiniPC E:, PortalExomen F:)
    'facturasocr_dir' => env('FACTURASOCR_DIR') ?: (collect(['e', 'f', 'd'])
        ->map(fn ($u) => "/mnt/{$u}/Claude/Contabilidad/FacturasOcr")
        ->first(fn ($d) => is_dir($d)) ?? '/mnt/e/Claude/Contabilidad/FacturasOcr'),
    'facturasocr_python' => env('FACTURASOCR_PYTHON'),

    /*
    |--------------------------------------------------------------------------
    | Neteges (30-sep-2026): conciliación bancaria como Bancos, con más ficheros
    |--------------------------------------------------------------------------
    |
    | Mismo esquema que Bancos (plan de cuentas + mayores de SAGE + extracto) y
    | además el fichero de Ventas. De momento se ejecuta solo en local, como
    | Procesos FIQ (contabilidad.ejecucion_local). Datos en Contabilidad/Neteges.
    */

    'neteges_dir' => env('NETEGES_DIR') ?: (collect(['e', 'f', 'd'])
        ->map(fn ($u) => "/mnt/{$u}/Claude/Contabilidad/Neteges")
        ->first(fn ($d) => is_dir($d)) ?? '/mnt/e/Claude/Contabilidad/Neteges'),

    /*
    |--------------------------------------------------------------------------
    | Facturación PDF · Genérico (28-sep-2026): partir un PDF de facturas de
    | cualquier proveedor (FacturacionPDFyMail/separar_generico.py)
    |--------------------------------------------------------------------------
    |
    | No toca OneDrive ni manda correos: el PDF se sube por el navegador y los
    | PDF salen en un .zip descargable, así que puede ir también en el VPS.
    | Se ejecuta si ejecucion_local o FACTURACION_GENERICO_EJECUCION=true.
    |   - VPS: FACTURACION_GENERICO_EJECUCION=true,
    |          FACTURACION_GENERICO_DIR=<carpeta con separar_generico.py>,
    |          FACTURACION_GENERICO_PYTHON=<python con pymupdf> (opcional);
    |          OCR con tesseract (apt install tesseract-ocr tesseract-ocr-spa).
    |   - Local: la carpeta de FacturacionPDFyMail y el OCR de Windows.
    */

    'generico_ejecucion' => env('FACTURACION_GENERICO_EJECUCION', false),
    'generico_dir' => env('FACTURACION_GENERICO_DIR'),
    'generico_python' => env('FACTURACION_GENERICO_PYTHON'),


    /*
    |--------------------------------------------------------------------------
    | Facturas OCR en la web (VPS, 3-oct-2026)
    |--------------------------------------------------------------------------
    | Con FACTURASOCR_WEB=true el propio servidor ejecuta facturas_ocr.py sobre su copia de trabajo de OneDrive
    | (FACTURASOCR_ONEDRIVE: carpeta que hace de «{OneDrive}», con _Clientes/_FacturasOCR y _Clientes/2026/...).
    | Las facturas se SUBEN a la web (huella SHA-1, solo lo que no se conoce); el OCR es Tesseract. Un trabajador
    | deja después lo validado en el OneDrive de un PC (sincronización con comprobación de huellas).
    */
    'facturasocr_web' => env('FACTURASOCR_WEB', false),
    'facturasocr_onedrive' => env('FACTURASOCR_ONEDRIVE'),
    'facturasocr_pc' => env('FACTURASOCR_PC'),   // PC (nombre del trabajador) cuyo OneDrive recibe lo validado; vacío = cualquiera

    /*
    |--------------------------------------------------------------------------
    | Cola de tareas para los PCs trabajadores (2-oct-2026)
    |--------------------------------------------------------------------------
    | Lista CERRADA de procesos que la web puede pedir y los PCs ejecutar (clave => descripción).
    | El trabajador solo ejecuta lo que conoce; nunca comandos ni rutas que vengan de la web.
    */
    'tareas_procesos' => [
        'certificados.escanear' => 'Escanear los certificados digitales de este PC',
        // Procesos desde la web (3-oct-2026): el PC ejecuta uno o varios scripts de la lista cerrada de un grupo
        // ('pc_grupos'; nunca rutas ni comandos libres), con ficheros de entrada que sube la web, y devuelve salida,
        // ficheros y una copia del estado. Ver Contabilidad/TrabajadorWeb/DIAGNOSTICO.md.
        'claude.todo' => 'Claude hace una tarea del TO-DO que se le ha asignado',
        'pc.script' => 'Ejecutar scripts de Contabilidad',
        'pc.estado' => 'Subir el estado de un grupo de procesos (la web no ve OneDrive)',
        'pc.fichero' => 'Subir un fichero del PC para descargarlo desde la web',
        'pc.facturasocr' => 'Llevar al OneDrive de este PC lo validado en Facturas OCR (con comprobación de huellas)',
        'fiq.checklist' => 'Aplicar un cambio del checklist de cierre de FIQ en OneDrive',
    ],

    // Scripts que el trabajador puede ejecutar, por grupo (ruta relativa a la carpeta del grupo en Contabilidad/).
    // El tipo (node, python, powershell...) y la carpeta los decide el trabajador, no la web. Si se añade uno aquí,
    // también en Contabilidad/TrabajadorWeb/trabajador.py (GRUPOS).
    'pc_grupos' => [
        'fiq' => ['scripts' => [
            'monthlyFIQ.js', 'sysSplit.js', 'anaplanConsolida.js', 'anaplanDesviaciones.js', 'anaplanWeb/subirAnaplan.js',
            'imputacionCostes.js', 'adyenReparto.js', 'calculosRentasVariables.js', 'rentasVariablesDeclaracion.js',
            'enviarRentasVariables.py', 'pagosFinMes.py', 'FacturasEmitidas/facturasEmitidas.py', 'CashFlow/cashflow.py',
            'CashInStore/cashInStore.py',
        ]],
        // Facturación PDF (Suma/Balerga): el PDF subido viaja como entrada y el PC lo procesa con su OneDrive.
        'facturacion' => ['scripts' => ['procesar_facturas.py', 'herramientas/listar_destinatarios.py']],
        // Facturas OCR (web): OCR de Windows por adelantado de las facturas escaneadas (ocr_previo.py)
        'facturasocr' => ['scripts' => ['ocr_previo.py']],
        // Neteges: la base (xlsx) va por git, así que solo un PC debe modificarla: NETEGES_PC=AlexMiniPC en el .env del VPS.
        'neteges' => ['pc' => env('NETEGES_PC'), 'scripts' => [
            'neteges_base.py', 'neteges_cobros.py', 'neteges_conciliar.py', 'neteges_estado.py', 'neteges_extractos.py',
            'neteges_plugin.py', 'neteges_ventas.py', 'bajarAdjuntosNeteges.ps1',
        ]],
    ],


    // Tareas del TO-DO para Claude: el PC principal las coge enseguida; el secundario solo si el principal no da señales
    // o la tarea lleva esperando más de 10 minutos. Pasadas cada hora (minutos) salvo «Ejecutar ya».
    'claude_todo_primario' => env('CLAUDE_TODO_PRIMARIO', 'AlexMiniPC'),
    // ENTRADA ÚNICA (3-oct-2026): solo se entra por la web. En un PC, ENTRADA_WEB_URL=https://appmos.sumaempresa.com hace que todas las
    // páginas redirijan a la web (nunca ponerla en el VPS). Excepciones = pantallas que aún solo funcionan en el PC; la lista debe quedar vacía.
    'entrada_web_url' => env('ENTRADA_WEB_URL'),
    'entrada_web_excepciones' => ['contabilidad/durcal*'],

    // En un PC (localhost) el TO-DO no tiene datos propios: los trabajadores, el uso del plan y las tareas de verdad están en la BD del VPS.
    // Con TODO_URL puesta, la pestaña TO-DO (y su campana y la barra de Claude) llevan a la web, como Bancos e IS.
    'todo_url' => env('TODO_URL'),

    // Solo ellos pausan a Claude y dan el visto bueno a lo que le asignan otros usuarios (correos separados por comas)
    'claude_todo_gestores' => array_filter(array_map('trim', explode(',', env('CLAUDE_TODO_GESTORES', 'alex.arregui@sumaempresa.com')))),
    'claude_todo_max_uso' => (int) env('CLAUDE_TODO_MAX_USO', 80),   // % del plan (sesión o semana) a partir del cual Claude no empieza tareas solo
    'claude_todo_max_dia' => (int) env('CLAUDE_TODO_MAX_DIA', 30),   // red de seguridad: ejecuciones de Claude al día (el freno de verdad es el % del plan, claude_todo_max_uso)
    'claude_todo_cada_minutos' => (int) env('CLAUDE_TODO_CADA_MINUTOS', 60),

];
