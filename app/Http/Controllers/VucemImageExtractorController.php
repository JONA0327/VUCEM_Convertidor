<?php

namespace App\Http\Controllers;

use App\Services\VucemImageExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VucemImageExtractorController extends Controller
{
    protected VucemImageExtractor $extractor;

    public function __construct(VucemImageExtractor $extractor)
    {
        $this->extractor = $extractor;
    }

    public function convert(Request $request)
    {
        // Aumentar tiempo de ejecución para PDFs grandes
        set_time_limit(1200); // 20 minutos
        
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:102400'], // máximo 100 MB
        ]);

        $file = $request->file('pdf');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sizeMb = round($file->getSize() / (1024 * 1024), 2);

        Log::info('VucemImageExtractor Controller: Iniciando extracción', [
            'original_name' => $originalName,
            'size_mb' => $sizeMb,
        ]);

        try {
            // Guardar archivo temporal
            $uniqueId = uniqid() . '_input.pdf';
            $inputPath = Storage::path('temp/' . $uniqueId);
            
            if (!Storage::exists('temp')) {
                Storage::makeDirectory('temp');
            }
            
            $file->move(dirname($inputPath), basename($inputPath));

            // Crear nombre para el ZIP de salida
            $outputZipName = 'imagenes_' . $originalName . '_' . uniqid() . '.zip';
            $outputZipPath = Storage::path('temp/' . $outputZipName);

            // Extraer imágenes
            $result = $this->extractor->extractImagesToZip($inputPath, $outputZipPath);

            if (!$result['success']) {
                throw new \Exception('Error al extraer imágenes');
            }

            Log::info('VucemImageExtractor Controller: Extracción completada', [
                'zip_size_mb' => $result['zip_size_mb'],
            ]);

            // Preparar respuesta para descarga
            $zipSizeMb = $result['zip_size_mb'];
            $downloadName = 'imagenes_' . $originalName . '.zip';

            return response()->download($outputZipPath, $downloadName, [
                'Content-Type' => 'application/zip',
                'X-File-Name' => $downloadName,
                'X-File-Size-MB' => $zipSizeMb,
                'X-Images-Count' => $result['images_count'],
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('VucemImageExtractor Controller: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al extraer imágenes: ' . $e->getMessage(),
            ], 500);
        } finally {
            // Limpiar archivo de entrada
            if (isset($inputPath) && file_exists($inputPath)) {
                @unlink($inputPath);
            }
        }
    }
}
