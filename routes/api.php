<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Esta es la ruta que recibirá la imagen desde React
Route::post('/process-document', [ReceiptController::class, 'process']);
