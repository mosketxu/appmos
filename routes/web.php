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

    // Panel de control: solo Admin
    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', function () {return view('admin.usuarios');})->name('usuarios');
        Route::get('/roles', function () {return view('admin.roles');})->name('roles');
    });

    // Contabilidad (Fashion IQ): lanzar los scripts Node/Python de monthlyFIQ
    Route::get('/contabilidad/procesos', function () {return view('contabilidad.procesos');})->name('contabilidad.procesos')->middleware('can:contabilidad.procesos');

    // Contabilidad (Facturación PDF): lanzar procesar_facturas.py de Suma/Balerga
    Route::get('/contabilidad/facturacion-pdf', function () {return view('contabilidad.facturacion-pdf');})->name('contabilidad.facturacion-pdf')->middleware('can:contabilidad.facturacionpdf');

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

    // Entidades: consulta
    Route::middleware('can:entidades.ver')->group(function () {
        Route::get('/entidades', function () {return view('entidades');})->name('entidades');
        Route::get('/entidad/pu/{entidad}', [EntidadController::class, 'pus'])->name('entidad.pu');
        Route::get('/entidad/contacto/{entidad}', [EntidadController::class, 'contactos'])->name('entidad.contacto');
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
