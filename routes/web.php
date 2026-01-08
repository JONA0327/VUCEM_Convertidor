<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfConverterController;
use App\Http\Controllers\VucemValidatorController;
use App\Http\Controllers\VucemImageExtractorController;
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

// Extractor de imágenes
Route::get('/extraer-imagenes', function () {
    return view('extract_images');
})->name('extraer.imagenes');

Route::post('/extract-images', [VucemImageExtractorController::class, 'convert'])->name('images.extract');

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

// Página de diagnóstico
Route::get('/diagnostico', function () {
    return view('diagnostico');
})->name('diagnostico');

// Test de Ghostscript
Route::get('/test-ghostscript', function () {
    $converter = new VucemPdfConverter();
    $gs = $converter->getToolsInfo()['ghostscript'];
    
    if (!$gs['available']) {
        return response()->json([
            'success' => false,
            'error' => 'Ghostscript no está disponible'
        ]);
    }
    
    // Ejecutar comando simple de prueba
    $process = new \Symfony\Component\Process\Process([
        $gs['path'],
        '--version'
    ]);
    $process->run();
    
    return response()->json([
        'success' => $process->isSuccessful(),
        'exit_code' => $process->getExitCode(),
        'output' => $process->getOutput(),
        'error' => $process->getErrorOutput(),
        'path' => $gs['path']
    ]);
})->name('test.ghostscript');

Route::get('/welcome', function () {
    return view('welcome');
});
