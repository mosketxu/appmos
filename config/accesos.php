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
            'contabilidad.is' => 'Impuesto sobre Sociedades (modelo 200)',
        ],
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
