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
    | En el VPS (app-mos.com, producción) el código está desplegado para que
    | la pantalla se vea (menú, tabla de procesos), pero NO hay ficheros de
    | OneDrive ni Node -- este flag debe quedar en false ahí (por defecto,
    | si no está en el .env) y en true SOLO en el .env de los PCs
    | autorizados. Ver app/Http/Livewire/Contabilidad/Procesos.php.
    |
    */

    'ejecucion_local' => env('CONTABILIDAD_EJECUCION_LOCAL', false),

];
