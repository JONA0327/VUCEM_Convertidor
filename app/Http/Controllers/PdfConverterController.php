<?php

namespace App\Http\Controllers;

use App\Services\VucemPdfConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfConverterController extends Controller
{
    // Tamaño máximo permitido por VUCEM: 3 MB
    const MAX_OUTPUT_SIZE = 3 * 1024 * 1024;

    /**
     * Servicio de conversión VUCEM
     */
    protected VucemPdfConverter $converter;

    /**
     * Constructor
     */
    public function __construct(VucemPdfConverter $converter)
    {
        $this->converter = $converter;
    }
    
    /**
     * Convertir un PDF al formato VUCEM
     * - 300 DPI exactos (validado con pdfimages)
     * - Escala de grises
     * - PDF versión 1.4
     * - Sin contraseña
     */
    public function convert(Request $request)
    {
        // Validar que se haya subido un archivo PDF
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:51200', // Max 50MB de entrada
        ]);

        $file = $request->file('file');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Crear nombres únicos para los archivos
        $uniqueId = uniqid();
        $inputFileName = $uniqueId . '_input.pdf';
        $outputFileName = $uniqueId . '_VUSEM.pdf';
        
        // Crear directorio temp si no existe
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Guardar el archivo de entrada temporalmente
        $inputPath = $tempDir . DIRECTORY_SEPARATOR . $inputFileName;
        $outputPath = $tempDir . DIRECTORY_SEPARATOR . $outputFileName;
        
        // Mover el archivo subido
        $file->move($tempDir, $inputFileName);
        
        try {
            // Convertir usando el servicio VucemPdfConverter (300 DPI exactos)
            $this->converter->convertToVucem($inputPath, $outputPath);
            
            // Verificar que el archivo se creó
            if (!file_exists($outputPath)) {
                throw new \Exception('Error al convertir el archivo.');
            }
            
            // Verificar tamaño del archivo de salida
            $fileSize = filesize($outputPath);
            
            // Advertencia si excede el tamaño
            $sizeWarning = null;
            if ($fileSize > self::MAX_OUTPUT_SIZE) {
                $sizeMB = round($fileSize / (1024 * 1024), 2);
                $sizeWarning = "El archivo resultante ({$sizeMB} MB) excede el límite de 3 MB de VUCEM. Considera dividir el documento.";
            }
            
            // Leer el archivo convertido
            $convertedContent = file_get_contents($outputPath);
            
            // Limpiar archivos temporales
            $this->cleanupFiles([$inputPath, $outputPath]);
            
            // Nombre del archivo de salida
            $downloadName = $originalName . '_VUCEM_300DPI.pdf';
            
            // Devolver el archivo para descarga
            $response = response($convertedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($convertedContent))
                ->header('X-File-Name', $downloadName);
            
            if ($sizeWarning) {
                $response->header('X-Size-Warning', $sizeWarning);
            }
            
            return $response;
                
        } catch (\Exception $e) {
            // Limpiar archivos temporales en caso de error
            $this->cleanupFiles([$inputPath, $outputPath]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error durante la conversión: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Limpiar archivos temporales
     */
    private function cleanupFiles(array $files)
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
