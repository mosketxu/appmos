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
