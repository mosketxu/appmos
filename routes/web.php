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

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/', function () {return view('entidades');})->name('entidades');
    Route::get('/dashboard', function () {return view('entidades');})->name('dashboard');

    // entidades
    // Route::get('/entidad/facturacionconceptos/{entidad}', [EntidadController::class, 'facturacionconceptos'])->name('entidad.facturacionconceptos');
    Route::get('/entidad/pu/{entidad}', [EntidadController::class, 'pus'])->name('entidad.pu');
    Route::get('/entidad/contacto/{entidad}', [EntidadController::class, 'contactos'])->name('entidad.contacto');
    Route::get('/entidad/nueva/{ruta}', [EntidadController::class, 'create'])->name('entidad.nueva');
    Route::get('/entidad/nuevocontacto/{entidad}', [EntidadController::class, 'createcontacto'])->name('entidad.createcontacto');
    Route::get('/entidad/planfacturacion/{entidad}', [EntidadController::class, 'planfacturacion'])->name('entidad.planfacturacion');
    Route::resource('entidad', EntidadController::class)->only('edit','create');

    //Facturacion
    Route::get('facturacion/import', [FacturacionController::class,'import'])->name('facturacion.import');
    Route::get('facturacion/{factura}/prefactura', [FacturacionController::class,'editprefactura'])->name('facturacion.editprefactura');
    Route::get('facturacion/prefactura/create/{entidad?}', [FacturacionController::class,'createprefactura'])->name('facturacion.createprefactura');
    // Route::get('facturacion/{factura}/imprimirfactura', [FacturacionController::class,'imprimirfactura'])->name('facturacion.imprimirfactura');
    Route::get('facturacion/{factura}/pdf', [FacturacionController::class,'pdffactura'])->name('facturacion.pdffactura');
    Route::get('facturacion/{factura}/pdfsimple', [FacturacionController::class,'pdffacturalineasimple'])->name('facturacion.pdffacturalineasimple');
    Route::get('facturacion/{factura}/downfacturapdf', [FacturacionController::class,'downfacturapdf'])->name('facturacion.downfactura');
    Route::get('facturacion/downfacturas', [FacturacionController::class,'downfacturas'])->name('facturacion.downfacturas');
    Route::get('facturacion/zip', [FacturacionController::class,'downloadZip'])->name('facturacion.zip');
    Route::get('facturacion/prefacturas', [FacturacionController::class,'prefacturas'])->name('facturacion.prefacturas');
    Route::get('facturacion/prefacturas/{entidad}/entidad', [FacturacionController::class,'prefacturasentidad'])->name('facturacion.prefacturasentidad');
    Route::resource('facturacion', FacturacionController::class);

    //Facturacion detalle
    Route::resource('facturaciondetalle', FacturacionDetalleController::class);

    //Facturacion detalle concepto
    Route::resource('facturaciondetalleconcepto', FacturacionDetalleConceptoController::class);

    //Facturacion Conceptos
    Route::get('facturacionconcepto/{entidad}',[FacturacionConceptoController::class,'conceptosentidad'])->name('facturacionconcepto.entidad');
    // Route::post('facturacionconcepto/newdetalle',[FacturacionConceptoController::class,'newdetalle'])->name('facturacionconceptos.newdetalle');
    Route::resource('facturacionconcepto', FacturacionConceptoController::class);

    //Facturacion Conceptos detalles

    Route::resource('facturacionconceptodetalle', FacturacionConceptoDetalleController::class);

    // Route::get('conceptosentidad/{entidad}', [FacturacionConceptoController::class,'conceptosentidad]')->name('facturacionconceptos.entidad');
    // Route::resource('facturacionconceptos', FacturacionConceptoController::class);


});

//  Route::any('{query}',function(){
//      return redirect('/login');
//     })
//     ->where('query','.*');
