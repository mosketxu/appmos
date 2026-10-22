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
        'Verifactu' => [
            'facturacion.ver' => 'Verifactu: ver facturas y conceptos (sin activar todavía)',
            'facturacion.editar' => 'Verifactu: crear, modificar y borrar facturas y conceptos',
        ],
        'TO-DO' => [
            'todo.ver' => 'TO-DO: ver y usar la pestaña de tareas (también «Pedir mejora»)',
        ],
        'Impuestos' => [
            'impuestos.ver' => 'Pestaña Impuestos: Seguimiento, IVA M303, IRPF M111, M349, M216 e Intrastat (ver y marcar los suyos; Admin y Suma ven todos)',
            'contabilidad.is' => 'IS M200 y Pago Cuenta M202 (subpestañas de Impuestos)',
        ],
        'Contabilidad' => [
            'contabilidad.procesos' => 'Procesos FIQ',
            'contabilidad.facturacionpdf' => 'Facturación PDF',
            'contabilidad.durcal' => 'Durcal',
            'contabilidad.bancos' => 'Bancos',
            'contabilidad.facturasocr' => 'Facturas OCR',
            'contabilidad.neteges' => 'Neteges',
            'contabilidad.leoybra' => 'LeoyBra',
            'contabilidad.whatsapp' => 'WhatsApp (chats de clientes)',
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
        // Subpestañas de Impuestos (22-oct-2026): un permiso por impuesto; el global (impuestos.ver / contabilidad.is) los da todos.
        'impuestos.ver' => ['prefijo' => 'proceso.imp.', 'lista' => [
            'seguimiento' => 'Seguimiento de impuestos',
            'iva' => 'IVA M303 (libros de IVA)',
            'm111' => 'IRPF M111',
            'm349' => 'M349',
            'm216' => 'M216',
            'intrastat' => 'Intrastat',
        ]],
        'contabilidad.is' => ['prefijo' => 'proceso.is.', 'lista' => [
            'm200' => 'IS M200',
            'm202' => 'Pago Cuenta M202',
        ]],
        'contabilidad.procesosmensuales' => ['prefijo' => 'proceso.pm.', 'lista' => [
            'certificados' => 'Certificados por caducar',
            'limpieza' => 'Limpieza de OneDrive',
            'whatsapp' => 'Importar WhatsApp de clientes',
            'prefacturacion' => 'Notas de cargo',
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
        \App\Models\EntidadImpuesto::class => 'entidades.editar',
        \App\Models\ContactoEntidad::class => 'entidades.editar',
        \App\Models\Pu::class => 'entidades.editar',
        \App\Models\Facturacion::class => 'facturacion.editar',
        \App\Models\FacturacionDetalle::class => 'facturacion.editar',
        \App\Models\FacturacionDetalleConcepto::class => 'facturacion.editar',
        \App\Models\FacturacionConcepto::class => 'facturacion.editar',
        \App\Models\FacturacionConceptodetalle::class => 'facturacion.editar',
    ],
];
