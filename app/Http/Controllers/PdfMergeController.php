<?php

namespace App\Http\Controllers;

use App\Services\VucemPdfConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PdfMergeController extends Controller
{
    protected VucemPdfConverter $converter;

    public function __construct(VucemPdfConverter $converter)
    {
        $this->converter = $converter;
    }

    /**
     * Mostrar vista de combinación
     */
    public function index()
    {
        return view('merge');
    }

    /**
     * Combinar múltiples PDFs sin rasterizar
     */
    public function merge(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:2|max:50',
            'files.*' => 'required|file|mimes:pdf|max:51200', // Max 50MB por archivo
            'outputName' => 'nullable|string|max:200',
        ]);

        $files = $request->file('files');
        $outputName = $request->input('outputName', 'documento_combinado');
        
        // Sanitizar nombre
        $outputName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $outputName);
        
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $uniqueId = uniqid();
        $inputPaths = [];
        $totalSize = 0;
        
        // Guardar todos los archivos temporalmente
        foreach ($files as $index => $file) {
            $inputFileName = $uniqueId . '_input_' . $index . '.pdf';
            $inputPath = $tempDir . DIRECTORY_SEPARATOR . $inputFileName;
            $file->move($tempDir, $inputFileName);
            $inputPaths[] = $inputPath;
            $totalSize += filesize($inputPath);
        }
        
        $outputFileName = $uniqueId . '_merged.pdf';
        $outputPath = $tempDir . DIRECTORY_SEPARATOR . $outputFileName;
        
        try {
            Log::info('PdfMerge: Iniciando combinación', [
                'files_count' => count($files),
                'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                'output_name' => $outputName
            ]);
            
            $result = $this->converter->mergePdfsKeepDpi($inputPaths, $outputPath);
            
            if (!file_exists($outputPath)) {
                throw new \Exception('Error al combinar los archivos.');
            }
            
            $outputSize = filesize($outputPath);
            $sizeMB = round($outputSize / (1024 * 1024), 2);
            
            Log::info('PdfMerge: Combinación completada', [
                'output_size_mb' => $sizeMB,
                'files_merged' => count($files)
            ]);
            
            $convertedContent = file_get_contents($outputPath);
            
            // Limpiar todos los archivos temporales
            $this->cleanupFiles(array_merge($inputPaths, [$outputPath]));
            
            $downloadName = $outputName . '_combinado.pdf';
            
            return response($convertedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', $outputSize)
                ->header('X-File-Name', $downloadName)
                ->header('X-File-Size-MB', $sizeMB)
                ->header('X-Files-Merged', count($files))
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('Pragma', 'public');
                
        } catch (\Exception $e) {
            $this->cleanupFiles(array_merge($inputPaths, [$outputPath]));
            
            return response()->json([
                'success' => false,
                'error' => 'Error durante la combinación: ' . $e->getMessage()
            ], 500);
        }
    }

    private function cleanupFiles(array $files)
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
