<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CampoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\SubcategoriaController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Esta es la ruta que recibirá la imagen desde React
Route::post('/process-document', [ReceiptController::class, 'process']);

// Ruta para verificar disponibilidad del servicio n8n
Route::get('/check-n8n-availability', [ReceiptController::class, 'checkN8nAvailability']);

// Ruta para inmuebles
Route::apiResource('inmuebles', InmuebleController::class);
Route::post('/inmuebles/{id}/restore', [InmuebleController::class, 'restore']);

// Rutas para Campos
Route::apiResource('campos', CampoController::class);
Route::post('/campos/{id}/restore', [CampoController::class, 'restore']);

// Rutas para Categorías
Route::apiResource('categorias', CategoriaController::class);
Route::post('/categorias/{id}/restore', [CategoriaController::class, 'restore']);

// Rutas para Subcategorías
Route::apiResource('subcategorias', SubcategoriaController::class);
Route::post('/subcategorias/{id}/restore', [SubcategoriaController::class, 'restore']);

// Rutas para Gastos
Route::apiResource('gastos', GastoController::class);
Route::post('/gastos/{id}/restore', [GastoController::class, 'restore']);

// Rutas para Usuarios
Route::apiResource('users', UserController::class);

