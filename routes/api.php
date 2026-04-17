<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CampoController;
use App\Http\Controllers\CategoriaController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Esta es la ruta que recibirá la imagen desde React
Route::post('/process-document', [ReceiptController::class, 'process']);

// Rutas para Inmuebles
Route::apiResource('inmuebles', InmuebleController::class);
Route::post('/inmuebles/{id}/restore', [InmuebleController::class, 'restore']);

// Rutas para Campos
Route::apiResource('campos', CampoController::class);
Route::post('/campos/{id}/restore', [CampoController::class, 'restore']);

// Rutas para Categorías
Route::apiResource('categorias', CategoriaController::class);
Route::post('/categorias/{id}/restore', [CategoriaController::class, 'restore']);

