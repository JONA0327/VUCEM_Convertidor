<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfConverterController;
use App\Http\Controllers\VucemValidatorController;
use App\Services\VucemPdfConverter;

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

// Debug de herramientas (temporal - eliminar en producción)
Route::get('/debug-tools', function () {
    $converter = new VucemPdfConverter();
    return response()->json([
        'tools_info' => $converter->getToolsInfo(),
        'debug_info' => $converter->getDebugInfo(),
    ], 200, [], JSON_PRETTY_PRINT);
})->name('debug.tools');

Route::get('/welcome', function () {
    return view('welcome');
});
