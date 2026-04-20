<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CampoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\SubcategoriaController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\SoporteGastoController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

// Login
Route::post('/login', [AuthController::class, 'login']);

// Integración n8n (se dejan públicas por ahora)
Route::post('/process-document', [ReceiptController::class, 'process']);
Route::get('/check-n8n-availability', [ReceiptController::class, 'checkN8nAvailability']);


/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren token)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Usuario autenticado
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Usuarios
    Route::apiResource('users', UserController::class);

    // Inmuebles
    Route::apiResource('inmuebles', InmuebleController::class);
    Route::post('/inmuebles/{id}/restore', [InmuebleController::class, 'restore']);

    // Campos
    Route::apiResource('campos', CampoController::class);
    Route::post('/campos/{id}/restore', [CampoController::class, 'restore']);

    // Categorías
    Route::apiResource('categorias', CategoriaController::class);
    Route::post('/categorias/{id}/restore', [CategoriaController::class, 'restore']);

    // Subcategorías
    Route::apiResource('subcategorias', SubcategoriaController::class);
    Route::post('/subcategorias/{id}/restore', [SubcategoriaController::class, 'restore']);

    // Gastos
    Route::apiResource('gastos', GastoController::class);
    Route::get('/gastos-filtrados', [GastoController::class, 'gastosFiltrados']);
    Route::get('/generar-balance', [GastoController::class, 'generarBalance']);
    Route::post('/gastos/{id}/restore', [GastoController::class, 'restore']);

    // Soportes de Gastos
    Route::apiResource('soporte-gastos', SoporteGastoController::class);
    Route::post('/soporte-gastos/{id}/restore', [SoporteGastoController::class, 'restore']);

    // Balances
    Route::apiResource('balances', BalanceController::class);
    Route::post('/balances/{id}/restore', [BalanceController::class, 'restore']);

    Route::post('/soporte-gastos/upload', [SoporteGastoController::class, 'uploadFile']);

});