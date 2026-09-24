<?php

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

];
