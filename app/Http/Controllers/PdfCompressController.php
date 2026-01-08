<?php

namespace App\Http\Controllers;

use App\Services\VucemPdfConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PdfCompressController extends Controller
{
    protected VucemPdfConverter $converter;

    public function __construct(VucemPdfConverter $converter)
    {
        $this->converter = $converter;
    }

    /**
     * Mostrar vista de compresión
     */
    public function index()
    {
        return view('compress');
    }

    /**
     * Comprimir un PDF sin rasterizar
     */
    public function compress(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:102400', // Max 100MB
            'compressionLevel' => 'required|in:screen,ebook,printer,prepress',
        ]);

        $file = $request->file('file');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $compressionLevel = $request->input('compressionLevel', 'printer');
        
        $originalSize = $file->getSize();
        
        $uniqueId = uniqid();
        $inputFileName = $uniqueId . '_input.pdf';
        $outputFileName = $uniqueId . '_compressed.pdf';
        
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $inputPath = $tempDir . DIRECTORY_SEPARATOR . $inputFileName;
        $outputPath = $tempDir . DIRECTORY_SEPARATOR . $outputFileName;
        
        $file->move($tempDir, $inputFileName);
        
        try {
            Log::info('PdfCompress: Iniciando compresión', [
                'original_name' => $originalName,
                'size_mb' => round($originalSize / (1024 * 1024), 2),
                'level' => $compressionLevel
            ]);
            
            $result = $this->converter->compressPdf($inputPath, $outputPath, $compressionLevel);
            
            if (!file_exists($outputPath)) {
                throw new \Exception('Error al comprimir el archivo.');
            }
            
            $outputSize = filesize($outputPath);
            $sizeMB = round($outputSize / (1024 * 1024), 2);
            $inputSizeMB = round($originalSize / (1024 * 1024), 2);
            $reductionPercent = round((($originalSize - $outputSize) / $originalSize) * 100, 2);
            
            Log::info('PdfCompress: Compresión completada', [
                'output_size_mb' => $sizeMB,
                'reduction_percent' => $reductionPercent
            ]);
            
            $convertedContent = file_get_contents($outputPath);
            
            $this->cleanupFiles([$inputPath, $outputPath]);
            
            $downloadName = $originalName . '_compressed.pdf';
            
            return response($convertedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', $outputSize)
                ->header('X-File-Name', $downloadName)
                ->header('X-File-Size-MB', $sizeMB)
                ->header('X-Input-Size-MB', $inputSizeMB)
                ->header('X-Reduction-Percent', $reductionPercent)
                ->header('X-Compression-Level', $compressionLevel)
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('Pragma', 'public');
                
        } catch (\Exception $e) {
            $this->cleanupFiles([$inputPath, $outputPath]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error durante la compresión: ' . $e->getMessage()
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
