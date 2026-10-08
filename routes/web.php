<?php

use App\Http\Controllers\{
    EntidadController,
    FacturacionController,
    FacturacionConceptoController,
    FacturacionConceptoDetalleController,
    FacturacionDetalleConceptoController,
    FacturacionDetalleController
};
use App\Models\FacturacionConceptodetalle;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('entidades');
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Acceso por roles y permisos: ver config/accesos.php. Además, lo que se guarda o
// borra lo controla el modelo (AppServiceProvider) y cada usuario sin
// "entidades.todas" solo ve sus entidades (App\Models\Concerns\SoloEntidadesPermitidas).
Route::middleware(['auth:sanctum', 'verified', 'activo'])->group(function () {
    Route::get('/dashboard', function () {return redirect()->route('entidades');})->name('dashboard');

    // Contraseña nueva obligatoria (users.debe_cambiar_password, lo comprueba el middleware activo)
    Route::get('/cambiar-password', [\App\Http\Controllers\CambiarPasswordController::class, 'show'])->name('password.cambiar.form');
    Route::post('/cambiar-password', [\App\Http\Controllers\CambiarPasswordController::class, 'update'])->name('password.cambiar');

    // Panel de control: solo Admin
    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', function () {return view('admin.usuarios');})->name('usuarios');
        Route::get('/roles', function () {return view('admin.roles');})->name('roles');
    });

    // TO-DO: tareas tipo ticket; cada usuario ve las suyas (creadas o asignadas), el Admin las de cualquiera
    Route::get('/todo', function () {
        if (config('contabilidad.todo_url')) {   // en un PC: los datos buenos están en la web
            return redirect()->away(config('contabilidad.todo_url').(request('t') ? '?t='.(int) request('t') : ''));
        }
        return view('todo.index');
    })->name('todo');

    // Impuestos (pestaña del TO-DO): qué tiene pendiente cada cliente; datos en la BD de Appmos (en un PC redirige a la web)
    Route::get('/impuestos', function () {
        if (config('contabilidad.todo_url')) {
            return redirect()->away(preg_replace('#/todo$#', '/impuestos', rtrim(config('contabilidad.todo_url'), '/')));
        }
        return view('impuestos.index');
    })->name('impuestos')->middleware('can:impuestos.ver');
    Route::get('/impuestos/adjunto/{comentario}', [\App\Http\Controllers\ImpuestosAdjuntoController::class, 'ver'])->name('impuestos.adjunto')->middleware('can:impuestos.ver');
    Route::get('/impuestos/documento/{documento}', [\App\Http\Controllers\ImpuestosDocumentoController::class, 'ver'])->name('impuestos.documento')->middleware('can:impuestos.ver');

    // Contabilidad (Fashion IQ): lanzar los scripts Node/Python de monthlyFIQ
    Route::get('/contabilidad/procesos', function () {return view('contabilidad.procesos');})->name('contabilidad.procesos')->middleware('can:contabilidad.procesos');

    // Contabilidad (Facturación PDF): lanzar procesar_facturas.py de Suma/Balerga
    Route::get('/contabilidad/facturacion-pdf', function () {return view('contabilidad.facturacion-pdf');})->name('contabilidad.facturacion-pdf')->middleware('can:contabilidad.facturacionpdf');
    Route::get('/contabilidad/facturacion-pdf/miniatura/{id}/{pagina}', function (string $id, int $pagina) {
        $f = \App\Http\Livewire\Contabilidad\FacturacionPdf::carpetaMiniaturas($id)."/p{$pagina}.jpg";
        abort_unless(is_file($f), 404);
        return response()->file($f, ['Cache-Control' => 'private, max-age=86400']);
    })->whereUuid('id')->whereNumber('pagina')->name('contabilidad.facturacion-pdf.miniatura')->middleware('can:contabilidad.facturacionpdf');
    Route::get('/contabilidad/facturacion-pdf/generado/{id}/{n}', function (string $id, int $n) {
        $rutas = json_decode((string) @file_get_contents(\App\Http\Livewire\Contabilidad\FacturacionPdf::rutaGenerados($id)), true) ?: [];
        abort_unless(isset($rutas[$n]) && is_file($rutas[$n]), 404);
        return response()->file($rutas[$n], ['Content-Type' => 'application/pdf']);
    })->whereUuid('id')->whereNumber('n')->name('contabilidad.facturacion-pdf.generado')->middleware('can:contabilidad.facturacionpdf');

    // Contabilidad (Durcal): activación de sueldos/SS por proyecto y amortización
    Route::get('/contabilidad/durcal', function () {return view('contabilidad.durcal');})->name('contabilidad.durcal')->middleware('can:contabilidad.durcal');

    // Contabilidad (Bancos): conciliación de extractos bancarios -> bancos.xlsx para SAGE
    // En local con BANCOS_URL (y sin BANCOS_EJECUCION) lleva directamente a la web, donde está la copia buena
    Route::get('/contabilidad/bancos', function () {
        if (config('contabilidad.bancos_url') && ! config('contabilidad.bancos_ejecucion')) {
            return redirect()->away(config('contabilidad.bancos_url'));
        }
        return view('contabilidad.bancos');
    })->name('contabilidad.bancos')->middleware('can:contabilidad.bancos');

    // Contabilidad (Facturas OCR): facturas recibidas en PDF -> PluginFacturas.xlsx de SAGE (toca OneDrive: solo PCs autorizados)
    Route::get('/contabilidad/facturas-ocr', function () {return view('contabilidad.facturas-ocr');})->name('contabilidad.facturas-ocr')->middleware('can:contabilidad.facturasocr');
    Route::get('/contabilidad/facturas-ocr/pdf/{cliente}/{id}', [\App\Http\Controllers\FacturasOcrController::class, 'pdf'])->name('contabilidad.facturas-ocr.pdf')->middleware('can:contabilidad.facturasocr');
    Route::get('/contabilidad/facturas-ocr/excel/{cliente}/{archivo}', [\App\Http\Controllers\FacturasOcrController::class, 'excel'])->name('contabilidad.facturas-ocr.excel')->middleware('signed');
    Route::get('/contabilidad/facturas-ocr/archivo/{cliente}/{carpeta}/{nombre}', [\App\Http\Controllers\FacturasOcrController::class, 'archivo'])->name('contabilidad.facturas-ocr.archivo')->middleware('can:contabilidad.facturasocr');
    Route::get('/contabilidad/facturas-ocr/miniatura/{cliente}/{id}', [\App\Http\Controllers\FacturasOcrController::class, 'miniatura'])->name('contabilidad.facturas-ocr.miniatura')->middleware('can:contabilidad.facturasocr');

    // Contabilidad (IS): fichero del modelo 200 para importar en Sociedades WEB. Igual que Bancos: se usa desde la web
    Route::get('/contabilidad/is', function () {
        if (config('contabilidad.is_url') && ! config('contabilidad.is_ejecucion')) {
            return redirect()->away(config('contabilidad.is_url'));
        }
        return view('contabilidad.is');
    })->name('contabilidad.is')->middleware('can:contabilidad.is');

    // Contabilidad (Neteges): como Bancos pero con más ficheros de consulta (Ventas...). Se ejecuta en local (ejecucion_local)
    // Fichero que un PC trabajador subió a Appmos para descargarlo (URL firmada que crea la pantalla, trait EjecutaEnPcs)
    Route::get('/contabilidad/tarea-fichero/{id}/{nombre}', function (int $id, string $nombre) {
        $ruta = \App\Support\ColaTareas::carpetaFicheros($id).'/'.basename($nombre);
        abort_unless(is_file($ruta), 404);
        return response()->download($ruta, basename($nombre));
    })->whereNumber('id')->name('contabilidad.tarea-fichero')->middleware('signed');
    Route::get('/contabilidad/neteges', function () {return view('contabilidad.neteges');})->name('contabilidad.neteges')->middleware('can:contabilidad.neteges');
    // Contabilidad (LeoyBra, 4-oct-2026): Grupo Leoybra; genera PluginFacturas y PluginBancos. Se usa en la web (datos en el VPS)
    Route::get('/contabilidad/leoybra/descargar/{periodo}/{nombre}', function (string $periodo, string $nombre) {
        abort_unless(preg_match('/^\d{4}-[1-4]T$/', $periodo), 404);
        $ruta = rtrim(config('contabilidad.leoybra_dir'), '/').'/Output/'.$periodo.'/'.basename($nombre);
        abort_unless(is_file($ruta), 404);
        return response()->download($ruta, basename($ruta));
    })->name('contabilidad.leoybra.descargar')->middleware('can:contabilidad.leoybra');
    Route::get('/contabilidad/leoybra', function () {return view('contabilidad.leoybra');})->name('contabilidad.leoybra')->middleware('can:contabilidad.leoybra');

    // Contabilidad (Proc.Mensuales): agrupa varios procesos mensuales. Se usa en la web (datos de la BD del VPS)
    Route::get('/contabilidad/procesos-mensuales', function () {
        if (config('contabilidad.procesosmensuales_url') && ! config('contabilidad.procesosmensuales_ejecucion')) {
            return redirect()->away(config('contabilidad.procesosmensuales_url'));
        }
        return view('contabilidad.procesos-mensuales');
    })->name('contabilidad.procesos-mensuales')->middleware('can:contabilidad.procesosmensuales');
    // Seguimiento mensual: checklist de todos los procesos mensuales (datos en la BD de Appmos: se usa en la web, como Proc.Mensuales)
    Route::get('/contabilidad/seguimiento-mensual', function () {
        if (config('contabilidad.procesosmensuales_url') && ! config('contabilidad.procesosmensuales_ejecucion')) {
            return redirect()->away(str_replace('procesos-mensuales', 'seguimiento-mensual', config('contabilidad.procesosmensuales_url')));
        }
        return view('contabilidad.seguimiento-mensual');
    })->name('contabilidad.seguimiento-mensual')->middleware(['can:contabilidad.procesosmensuales', 'can:proceso.pm.seguimiento']);
    // Certificados por caducar: se ejecuta en LOCAL (los certificados están en los PCs)
    Route::get('/contabilidad/certificados', function () {
        return view('contabilidad.certificados');
    })->name('contabilidad.certificados')->middleware(['can:contabilidad.procesosmensuales', 'can:proceso.pm.certificados']);

    // Entidades: consulta
    Route::middleware('can:entidades.ver')->group(function () {
        Route::get('/entidades', function () {return view('entidades');})->name('entidades');
        Route::get('/entidad/pu/{entidad}', [EntidadController::class, 'pus'])->name('entidad.pu');
        Route::get('/entidad/contacto/{entidad}', [EntidadController::class, 'contactos'])->name('entidad.contacto');
        Route::get('/entidad/historial/{entidad}', [EntidadController::class, 'historial'])->name('entidad.historial');
        Route::get('/entidad/planfacturacion/{entidad}', [EntidadController::class, 'planfacturacion'])->name('entidad.planfacturacion');
        Route::resource('entidad', EntidadController::class)->only('edit');
    });
    // Entidades: altas
    Route::middleware('can:entidades.editar')->group(function () {
        Route::get('/entidad/nueva/{ruta}', [EntidadController::class, 'create'])->name('entidad.nueva');
        Route::get('/entidad/nuevocontacto/{entidad}', [EntidadController::class, 'createcontacto'])->name('entidad.createcontacto');
        Route::resource('entidad', EntidadController::class)->only('create');
    });

    // Facturación: altas
    Route::middleware('can:facturacion.editar')->group(function () {
        Route::get('facturacion/import', [FacturacionController::class,'import'])->name('facturacion.import');
        Route::get('facturacion/prefactura/create/{entidad?}', [FacturacionController::class,'createprefactura'])->name('facturacion.createprefactura');
    });
    // Facturación: consulta (lo que se guarda lo frena el modelo si no hay facturacion.editar)
    Route::middleware('can:facturacion.ver')->group(function () {
        Route::get('facturacion/{factura}/prefactura', [FacturacionController::class,'editprefactura'])->name('facturacion.editprefactura');
        Route::get('facturacion/{factura}/pdf', [FacturacionController::class,'pdffactura'])->name('facturacion.pdffactura');
        Route::get('facturacion/{factura}/pdfsimple', [FacturacionController::class,'pdffacturalineasimple'])->name('facturacion.pdffacturalineasimple');
        Route::get('facturacion/{factura}/downfacturapdf', [FacturacionController::class,'downfacturapdf'])->name('facturacion.downfactura');
        Route::get('facturacion/downfacturas', [FacturacionController::class,'downfacturas'])->name('facturacion.downfacturas');
        Route::get('facturacion/zip', [FacturacionController::class,'downloadZip'])->name('facturacion.zip');
        Route::get('facturacion/prefacturas', [FacturacionController::class,'prefacturas'])->name('facturacion.prefacturas');
        Route::get('facturacion/prefacturas/{entidad}/entidad', [FacturacionController::class,'prefacturasentidad'])->name('facturacion.prefacturasentidad');
        Route::resource('facturacion', FacturacionController::class);
        Route::resource('facturaciondetalle', FacturacionDetalleController::class);
        Route::resource('facturaciondetalleconcepto', FacturacionDetalleConceptoController::class);
        Route::get('facturacionconcepto/{entidad}',[FacturacionConceptoController::class,'conceptosentidad'])->name('facturacionconcepto.entidad');
        Route::resource('facturacionconcepto', FacturacionConceptoController::class);
        Route::resource('facturacionconceptodetalle', FacturacionConceptoDetalleController::class);
    });
});

//  Route::any('{query}',function(){
//      return redirect('/login');
//     })
//     ->where('query','.*');
