<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Cuentas nuevas compartidas entre Bancos (VPS) y Facturas OCR (PCs). Clave en X-Token.
Route::get('/bancos/{cliente}/cuentas-nuevas', [\App\Http\Controllers\BancosCuentasController::class, 'listar']);
Route::post('/bancos/{cliente}/cuentas-nuevas', [\App\Http\Controllers\BancosCuentasController::class, 'alta']);
// Configuracion.xlsx de Bancos (Textos a quitar...), para Neteges en los PCs (misma lista que la web)
Route::get('/bancos-configuracion', [\App\Http\Controllers\BancosCuentasController::class, 'configuracion']);

Route::post('/certificados/escaneo', [\App\Http\Controllers\CertificadosEscaneoController::class, 'guardar']);

// PCs trabajadores (cola de tareas de la web). Autenticación por X-Token propio de cada PC.
Route::prefix('trabajador')->group(function () {
    Route::post('/siguiente', [\App\Http\Controllers\TrabajadorApiController::class, 'siguiente']);
    Route::post('/latido', [\App\Http\Controllers\TrabajadorApiController::class, 'latido']);
    Route::post('/tareas/{id}/log', [\App\Http\Controllers\TrabajadorApiController::class, 'log']);
    Route::post('/tareas/{id}/todo', [\App\Http\Controllers\TrabajadorApiController::class, 'todo']);
    Route::post('/tareas/{id}/todo-resultado', [\App\Http\Controllers\TrabajadorApiController::class, 'todoResultado']);
    Route::post('/claude-uso', [\App\Http\Controllers\TrabajadorApiController::class, 'claudeUso']);
    Route::get('/tareas/{id}/entrada/{nombre}', [\App\Http\Controllers\TrabajadorApiController::class, 'entrada']);
    Route::get('/facturasocr/{cliente}/manifest', [\App\Http\Controllers\FacturasOcrSyncController::class, 'manifest']);
    Route::get('/facturasocr/{cliente}/archivo', [\App\Http\Controllers\FacturasOcrSyncController::class, 'archivo'])->withoutMiddleware('throttle:api');   // el trabajador baja un PDF por petición (decenas seguidas): sin el límite de 60/min
    Route::get('/impuestos/raices', [\App\Http\Controllers\ImpuestosSyncController::class, 'raices']);
    Route::post('/impuestos/manifest', [\App\Http\Controllers\ImpuestosSyncController::class, 'manifest']);
    // Subida masiva de PDF (cientos seguidos): sin el límite de 60 peticiones/minuto del grupo api (el X-Token ya autentica)
    Route::post('/impuestos/subir', [\App\Http\Controllers\ImpuestosSyncController::class, 'subir'])->withoutMiddleware('throttle:api');
    Route::get('/impuestos/libros', [\App\Http\Controllers\ImpuestosSyncController::class, 'libros']);
    Route::post('/impuestos/libro-subir', [\App\Http\Controllers\ImpuestosSyncController::class, 'libroSubir'])->withoutMiddleware('throttle:api');
    Route::post('/tareas/{id}/fichero', [\App\Http\Controllers\TrabajadorApiController::class, 'fichero']);
    Route::post('/tareas/{id}/fin', [\App\Http\Controllers\TrabajadorApiController::class, 'fin']);
});
Route::post('/certificados/envio', [\App\Http\Controllers\CertificadosEscaneoController::class, 'envio']);
