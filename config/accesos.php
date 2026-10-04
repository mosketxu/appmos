<?php

/*
|--------------------------------------------------------------------------
| Control de acceso de Appmos (24-sep-2026)
|--------------------------------------------------------------------------
|
| Roles (spatie/laravel-permission):
|   - Admin:   todo, siempre (Gate::before en AuthServiceProvider) + panel de control.
|   - Suma:    sin panel de control; por defecto todas las acciones (se pueden
|              quitar desde el panel).
|   - Usuario: solo sus entidades (Responsable Suma o asignadas en el panel), en
|              consulta, y las acciones que se le den.
|
| 'permisos' es la lista única de acciones: el seeder las crea y el panel de
| control las enseña agrupadas. Para añadir una utilidad nueva: añadirla aquí,
| protegerla con can:<permiso> en la ruta / @can en el menú, y volver a pasar
| php artisan db:seed --class=AccesosSeeder.
*/

return [
    'permisos' => [
        'Entidades' => [
            'entidades.ver' => 'Ver entidades (contactos, PU)',
            'entidades.todas' => 'Ver TODAS las entidades (si no, solo las suyas)',
            'entidades.editar' => 'Crear, modificar y borrar entidades, contactos y PU',
        ],
        'Facturación' => [
            'facturacion.ver' => 'Ver facturas, prefacturas y conceptos',
            'facturacion.editar' => 'Crear, modificar y borrar facturas, prefacturas y conceptos',
        ],
        'Contabilidad' => [
            'contabilidad.procesos' => 'Procesos FIQ',
            'contabilidad.facturacionpdf' => 'Facturación PDF',
            'contabilidad.durcal' => 'Durcal',
            'contabilidad.bancos' => 'Bancos',
            'contabilidad.facturasocr' => 'Facturas OCR',
            'contabilidad.is' => 'Impuesto sobre Sociedades (modelo 200)',
            'contabilidad.neteges' => 'Neteges',
            'contabilidad.leoybra' => 'LeoyBra',
            'contabilidad.procesosmensuales' => 'Procesos mensuales',
        ],
    ],

    /*
    | Procesos dentro de una pestaña (4-oct-2026): permiso de la pestaña => sus procesos. Un permiso de proceso
    | (`<prefijo><id>`) AFINA el de la pestaña: hace falta el de la pestaña Y el del proceso. Mientras el permiso de un
    | proceso no exista en la BD vale el de su pestaña (así no cambia nada hasta que el Admin lo toque en el panel);
    | al tocarlo por primera vez se crea y se da a quien ya tenía la pestaña (Accesos::asegurarProceso).
    | 'fuente' => 'fiq': los procesos salen de checklist.json (Procesos FIQ); 'lista' => [id => texto] fija.
    */
    'procesos' => [
        'contabilidad.procesos' => ['prefijo' => 'proceso.fiq.', 'fuente' => 'fiq'],
        'contabilidad.procesosmensuales' => ['prefijo' => 'proceso.pm.', 'lista' => [
            'petdocimpuestos' => 'Pet. Documentación Impuestos',
            'certificados' => 'Certificados por caducar',
            'seguimiento' => 'Seguimiento (checklist mensual)',
            'revisionmayor' => 'Revisión del mayor',
        ]],
    ],

    'roles' => [
        'Admin' => '*',
        'Suma' => '*',
        'Usuario' => ['entidades.ver', 'facturacion.ver'],
    ],

    // Modelo -> permiso necesario para escribir en él (guardar o borrar).
    'escritura' => [
        \App\Models\Entidad::class => 'entidades.editar',
        \App\Models\ContactoEntidad::class => 'entidades.editar',
        \App\Models\Pu::class => 'entidades.editar',
        \App\Models\Facturacion::class => 'facturacion.editar',
        \App\Models\FacturacionDetalle::class => 'facturacion.editar',
        \App\Models\FacturacionDetalleConcepto::class => 'facturacion.editar',
        \App\Models\FacturacionConcepto::class => 'facturacion.editar',
        \App\Models\FacturacionConceptodetalle::class => 'facturacion.editar',
    ],
];
