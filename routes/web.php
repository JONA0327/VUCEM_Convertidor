<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfConverterController;
use App\Http\Controllers\VucemValidatorController;

// Página principal con menú
Route::get('/', function () {
    return view('home');
})->name('home');

// Convertidor (usa VucemPdfConverter con 300 DPI exactos)
Route::get('/convertidor', function () {
    return view('upload');
})->name('convertidor');

Route::post('/convert', [PdfConverterController::class, 'convert'])->name('pdf.convert');

// Validador
Route::get('/validador', [VucemValidatorController::class, 'index'])->name('validador');
Route::post('/validador', [VucemValidatorController::class, 'validatePdf'])->name('validador.validate');

Route::get('/welcome', function () {
    return view('welcome');
});
