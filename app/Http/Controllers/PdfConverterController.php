<?php

namespace App\Http\Controllers;

use App\Services\VucemPdfConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
            'splitEnabled' => 'nullable|boolean',
            'numberOfParts' => 'nullable|integer|min:2|max:5',
        ]);

        $file = $request->file('file');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $splitEnabled = $request->input('splitEnabled', false);
        $numberOfParts = $request->input('numberOfParts', 2);
        
        // Obtener tamaño ANTES de mover el archivo
        $originalSize = $file->getSize();
        
        // Crear nombres únicos para los archivos
        $uniqueId = uniqid();
        $inputFileName = $uniqueId . '_input.pdf';
        $outputFileName = $uniqueId . '_VUCEM.pdf';
        
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
            Log::info('PdfConverter: Iniciando conversión', [
                'original_name' => $originalName,
                'size_mb' => round($originalSize / (1024 * 1024), 2),
                'split_enabled' => $splitEnabled,
                'number_of_parts' => $numberOfParts
            ]);
            
            // Convertir usando el servicio VucemPdfConverter (300 DPI exactos en TODO)
            $result = $this->converter->convertToVucem($inputPath, $outputPath, $splitEnabled, $numberOfParts);
            
            Log::info('PdfConverter: Conversión completada', [
                'output_exists' => file_exists($outputPath),
                'output_size' => file_exists($outputPath) ? filesize($outputPath) : 0,
                'split_files' => isset($result['split_files']) ? count($result['split_files']) : 0
            ]);
            
            // Si se solicitó dividir en partes, manejar respuesta con múltiples archivos
            if ($splitEnabled && isset($result['split_files']) && count($result['split_files']) > 0) {
                $files = [];
                
                foreach ($result['split_files'] as $partInfo) {
                    $partPath = $partInfo['path'];
                    $partNumber = $partInfo['part'];
                    
                    $validation = $this->converter->validateVucemCompliance($partPath);
                    $fileSize = filesize($partPath);
                    $sizeMB = round($fileSize / (1024 * 1024), 2);
                    
                    $files[] = [
                        'name' => $originalName . '_parte' . $partNumber . '_VUCEM.pdf',
                        'content' => base64_encode(file_get_contents($partPath)),
                        'size' => $fileSize,
                        'size_mb' => $sizeMB,
                        'part' => $partNumber,
                        'pages' => $partInfo['pages']
                    ];
                    
                    // Eliminar archivo temporal de la parte
                    @unlink($partPath);
                }
                
                // Limpiar archivos temporales
                $this->cleanupFiles([$inputPath, $outputPath]);
                
                return response()->json([
                    'success' => true,
                    'split' => true,
                    'files' => $files,
                    'total_parts' => count($files)
                ]);
            }
            
            // Flujo normal: un solo archivo
            // Verificar que el archivo se creó
            if (!file_exists($outputPath)) {
                throw new \Exception('Error al convertir el archivo.');
            }
            
            // VALIDACIÓN ESTRICTA: Verificar que cumple con TODOS los requisitos VUCEM
            $validation = $this->converter->validateVucemCompliance($outputPath);
            
            // Obtener tamaño del archivo
            $fileSize = filesize($outputPath);
            $sizeMB = round($fileSize / (1024 * 1024), 2);
            
            // Construir mensajes de validación
            $validationMessages = [];
            
            if (!$validation['valid']) {
                // Si hay errores críticos, agregar advertencias
                foreach ($validation['errors'] as $error) {
                    $validationMessages[] = "⚠️ " . $error;
                }
            }
            
            // Agregar warnings si existen
            if (!empty($validation['warnings'])) {
                foreach ($validation['warnings'] as $warning) {
                    $validationMessages[] = "ℹ️ " . $warning;
                }
            }
            
            // Validación adicional de DPI
            $dpiValidation = $this->converter->validateDpi($outputPath);
            if (isset($dpiValidation['error'])) {
                // Si hay error en validación de DPI, solo advertir pero no bloquear
                $validationMessages[] = "⚠️ No se pudo validar DPI: " . $dpiValidation['error'];
            } elseif (isset($dpiValidation['total_images']) && $dpiValidation['total_images'] > 0) {
                if ($dpiValidation['valid']) {
                    $validationMessages[] = "✓ Todas las imágenes ({$dpiValidation['total_images']}) están a exactamente 300 DPI";
                } else {
                    $validationMessages[] = "⚠️ {$dpiValidation['invalid_count']} de {$dpiValidation['total_images']} imágenes NO están a 300 DPI exactos";
                }
            }
            
            // Mensaje de tamaño
            if ($fileSize > self::MAX_OUTPUT_SIZE) {
                $validationMessages[] = "⚠️ Archivo {$sizeMB} MB - Para VUCEM subir por partes si excede límites";
            } else {
                $validationMessages[] = "✓ Tamaño: {$sizeMB} MB";
            }
            
            // Leer el archivo convertido
            $convertedContent = file_get_contents($outputPath);
            
            // Limpiar archivos temporales
            $this->cleanupFiles([$inputPath, $outputPath]);
            
            // Nombre del archivo de salida
            $downloadName = $originalName . '_VUCEM_300DPI.pdf';
            
            // Devolver el archivo para descarga con headers de validación
            $response = response($convertedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', $fileSize)
                ->header('X-File-Name', $downloadName)
                ->header('X-File-Size-MB', $sizeMB)
                ->header('X-VUCEM-Valid', $validation['valid'] ? 'true' : 'false')
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('Pragma', 'public');
            
            // Agregar mensajes de validación como headers
            if (!empty($validationMessages)) {
                $response->header('X-Validation-Messages', json_encode($validationMessages));
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
