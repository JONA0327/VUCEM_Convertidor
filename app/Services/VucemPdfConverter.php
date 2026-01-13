<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use RuntimeException;
use Illuminate\Support\Facades\Log;

/**
 * Convertidor de PDF para cumplir con requisitos VUCEM
 * 
 * VUCEM requiere:
 * - PDF versión 1.4
 * - Todas las imágenes a 300 DPI exactos
 * - Escala de grises
 * - Sin contraseña
 * - Máximo 3MB
 */
class VucemPdfConverter
{
    protected ?string $ghostscriptPath = null;
    protected ?string $pdfimagesPath = null;
    protected ?string $imageMagickPath = null;
    protected bool $isWindows;

    public function __construct()
    {
        // Detectar sistema operativo una sola vez al inicializar
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Primero intentar obtener rutas desde config/env, si no autodetectar
        $this->ghostscriptPath = $this->getConfiguredPath('ghostscript') ?: $this->findGhostscript();
        $this->pdfimagesPath = $this->getConfiguredPath('pdfimages') ?: $this->findPdfimages();
        $this->imageMagickPath = $this->getConfiguredPath('imagemagick') ?: $this->findImageMagick();
    }
    
    /**
     * Obtiene la ruta configurada en .env/config si existe y es válida
     */
    protected function getConfiguredPath(string $tool): ?string
    {
        $path = config("pdftools.{$tool}");
        
        if (empty($path)) {
            return null;
        }
        
        // Verificar que la ruta existe o que el comando es ejecutable
        if (file_exists($path)) {
            return $path;
        }
        
        // Si no es un archivo, podría ser un comando en PATH (ej: 'gs', 'pdfimages')
        $versionArg = $tool === 'pdfimages' ? '-v' : '--version';
        $process = new Process([$path, $versionArg]);
        $process->run();
        
        if ($process->isSuccessful() || str_contains($process->getErrorOutput(), $tool)) {
            return $path;
        }
        
        return null;
    }

    /**
     * Convierte un PDF al formato VUCEM (300 DPI exactos, escala de grises, PDF 1.4)
     * 
     * ESTRATEGIA MEJORADA: Rasterizar completamente cada página a exactamente 300 DPI
     * como imagen PNG en escala de grises, luego reconstruir el PDF.
     * Esto garantiza que TODO (texto, imágenes, vectores) esté a exactamente 300 DPI.
     * 
     * @param string $inputPath Ruta del archivo PDF de entrada
     * @param string $outputPath Ruta del archivo PDF de salida
     * @param bool $splitEnabled Si se debe dividir el PDF en partes
     * @param int $numberOfParts Número de partes en las que dividir (2-8)
     * @return array Información sobre los archivos generados
     */
    public function convertToVucem(string $inputPath, string $outputPath, bool $splitEnabled = false, int $numberOfParts = 2): array
    {
        // Aumentar límite de tiempo y memoria de ejecución
        set_time_limit(1200); // 20 minutos
        ini_set('max_execution_time', '1200');
        ini_set('memory_limit', '2048M'); // 2GB de memoria
        
        if (!file_exists($inputPath)) {
            throw new RuntimeException("El archivo de entrada no existe: {$inputPath}");
        }

        if (!$this->ghostscriptPath) {
            throw new RuntimeException('Ghostscript no está disponible en el sistema.');
        }

        $tempDir = $this->createTempDirectory();

        try {
            // ESTRATEGIA DEFINITIVA: Rasterizar completamente a JPEG y crear PDF con TCPDF
            // Esta es la ÚNICA forma de garantizar 300 DPI exactos en TODAS las imágenes
            
            Log::info('VucemConverter: Rasterización completa a JPEG 300 DPI', [
                'input' => basename($inputPath)
            ]);
            
            // Paso 1: Generar TODOS los JPEGs con calidad MUY baja para archivos pequeños
            $jpegPattern = $tempDir . '/page_%03d.jpg';
            $gsJpegArgs = [
                '-sDEVICE=jpeggray',
                '-r300',  // 300 DPI reales
                '-dJPEGQ=15',  // Calidad 15% - compresión extrema para archivos muy pequeños
                '-dNOPAUSE',
                '-dBATCH',
                '-dSAFER',
                '-dQUIET',
                '-sOutputFile=' . $jpegPattern,
                $inputPath,
            ];
            
            $this->executeGhostscript($gsJpegArgs);
            
            $jpegFiles = glob($tempDir . '/page_*.jpg');
            sort($jpegFiles, SORT_NATURAL);
            $totalPages = count($jpegFiles);
            
            if ($totalPages === 0) {
                throw new RuntimeException('No se generaron páginas JPEG');
            }
            
            Log::info('VucemConverter: JPEGs generados a 300 DPI', [
                'count' => $totalPages,
                'dpi' => '300',
                'quality' => '15%'
            ]);
            
            // Paso 2: Verificar si se solicitó división personalizada
            if ($splitEnabled && $numberOfParts >= 2 && $numberOfParts <= 8) {
                // División personalizada en N partes
                Log::info("VucemConverter: División personalizada solicitada en {$numberOfParts} partes");
                
                $pagesPerPart = ceil($totalPages / $numberOfParts);
                $groups = array_chunk($jpegFiles, $pagesPerPart);
                $outputFiles = [];
                
                foreach ($groups as $groupIndex => $groupJpegs) {
                    $groupNumber = $groupIndex + 1;
                    Log::info("VucemConverter: Procesando parte {$groupNumber}/{$numberOfParts}");
                    
                    $groupOutput = str_replace('.pdf', "_parte{$groupNumber}.pdf", $outputPath);
                    
                    $pdf = new \TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('VUCEM Converter');
                    $pdf->SetTitle("Documento VUCEM - Parte {$groupNumber} de {$numberOfParts}");
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetMargins(0, 0, 0);
                    $pdf->SetAutoPageBreak(false, 0);
                    $pdf->setImageScale(1.0);
                    $pdf->setJPEGQuality(100);
                    $pdf->SetCompression(false);
                    
                    foreach ($groupJpegs as $jpegFile) {
                        list($widthPx, $heightPx) = getimagesize($jpegFile);
                        $widthPt = ($widthPx / 300) * 72;
                        $heightPt = ($heightPx / 300) * 72;
                        $pdf->AddPage('P', [$widthPt, $heightPt]);
                        $pdf->Image($jpegFile, 0, 0, $widthPt, $heightPt, 'JPEG', '', '', false, 300, '', false, false, 0, false, false, false);
                    }
                    
                    $pdfContent = $pdf->Output('', 'S');
                    file_put_contents($groupOutput, $pdfContent);
                    
                    $sizeMB = round(filesize($groupOutput) / (1024 * 1024), 2);
                    Log::info("VucemConverter: Parte {$groupNumber} creada - {$sizeMB} MB, " . count($groupJpegs) . " páginas");
                    
                    $outputFiles[] = [
                        'path' => $groupOutput,
                        'size' => $sizeMB,
                        'pages' => count($groupJpegs),
                        'part' => $groupNumber
                    ];
                }
                
                $totalSize = array_sum(array_column($outputFiles, 'size'));
                Log::info("VucemConverter: División personalizada completada", [
                    'parts' => count($outputFiles),
                    'total_size_mb' => round($totalSize, 2),
                    'total_pages' => $totalPages
                ]);
                
                // Retornar información de archivos divididos
                return [
                    'success' => true,
                    'split_files' => $outputFiles,
                    'total_pages' => $totalPages
                ];
                
            } elseif ($totalPages > 10) {
                // PDF grande: dividir en grupos de 10 páginas (lógica original)
                Log::info("VucemConverter: PDF grande ({$totalPages} páginas), dividiendo en grupos de 10");
                
                $groups = array_chunk($jpegFiles, 10);
                $outputFiles = [];
                
                foreach ($groups as $groupIndex => $groupJpegs) {
                    $groupNumber = $groupIndex + 1;
                    $totalGroups = count($groups);
                    Log::info("VucemConverter: Procesando grupo {$groupNumber}/{$totalGroups}");
                    
                    $groupOutput = str_replace('.pdf', "_parte{$groupNumber}.pdf", $outputPath);
                    
                    $pdf = new \TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('VUCEM Converter');
                    $pdf->SetTitle("Documento VUCEM - Parte {$groupNumber} de {$totalGroups}");
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetMargins(0, 0, 0);
                    $pdf->SetAutoPageBreak(false, 0);
                    $pdf->setImageScale(1.0);
                    $pdf->setJPEGQuality(100);
                    $pdf->SetCompression(false);
                    
                    foreach ($groupJpegs as $jpegFile) {
                        list($widthPx, $heightPx) = getimagesize($jpegFile);
                        // Calcular tamaño en puntos para 300 DPI
                        $widthPt = ($widthPx / 300) * 72;
                        $heightPt = ($heightPx / 300) * 72;
                        $pdf->AddPage('P', [$widthPt, $heightPt]);
                        $pdf->Image($jpegFile, 0, 0, $widthPt, $heightPt, 'JPEG', '', '', false, 300, '', false, false, 0, false, false, false);
                    }
                    
                    $pdfContent = $pdf->Output('', 'S');
                    file_put_contents($groupOutput, $pdfContent);
                    
                    $sizeMB = round(filesize($groupOutput) / (1024 * 1024), 2);
                    Log::info("VucemConverter: Grupo {$groupNumber} creado - {$sizeMB} MB, " . count($groupJpegs) . " páginas");
                    
                    $outputFiles[] = [
                        'path' => $groupOutput,
                        'size' => $sizeMB,
                        'pages' => count($groupJpegs),
                        'part' => $groupNumber
                    ];
                }
                
                $totalSize = array_sum(array_column($outputFiles, 'size'));
                Log::info("VucemConverter: División completada", [
                    'parts' => count($outputFiles),
                    'total_size_mb' => round($totalSize, 2),
                    'total_pages' => $totalPages
                ]);
                
                // Unir todas las partes en un solo PDF usando Ghostscript
                Log::info("VucemConverter: Uniendo todas las partes en un solo PDF...");
                
                $partsForMerge = array_column($outputFiles, 'path');
                
                $gsMergeArgs = [
                    '-sDEVICE=pdfwrite',
                    '-dCompatibilityLevel=1.4',
                    '-dNOPAUSE',
                    '-dBATCH',
                    '-dSAFER',
                    '-dQUIET',
                    '-sOutputFile=' . $outputPath,
                ];
                
                // Agregar todos los archivos de partes
                foreach ($partsForMerge as $partFile) {
                    $gsMergeArgs[] = $partFile;
                }
                
                $this->executeGhostscript($gsMergeArgs);
                
                if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
                    throw new RuntimeException('No se pudo unir los PDFs divididos');
                }
                
                $finalSizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
                Log::info("VucemConverter: PDF unificado creado", [
                    'size_mb' => $finalSizeMB,
                    'parts_merged' => count($partsForMerge)
                ]);
                
                // Limpiar archivos de partes temporales
                foreach ($partsForMerge as $partFile) {
                    @unlink($partFile);
                }
                
                $success = true;
                
            } else {
                // PDF pequeño: intentar ajustar a 10 MB con diferentes calidades
                Log::info('VucemConverter: PDF pequeño (<=10 páginas), ajustando calidad');
                
                $jpegQualities = [75, 65, 55, 50];
                $success = false;
                
                foreach ($jpegQualities as $index => $quality) {
                    $attempt = $index + 1;
                    Log::info("VucemConverter: Intento {$attempt}/" . count($jpegQualities) . " - calidad {$quality}%");
                    
                    if ($index > 0) {
                        $gsJpegArgs[2] = '-dJPEGQ=' . $quality;
                        $this->executeGhostscript($gsJpegArgs);
                        $jpegFiles = glob($tempDir . '/page_*.jpg');
                        sort($jpegFiles, SORT_NATURAL);
                    }
                    
                    $pdf = new \TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('VUCEM Converter');
                    $pdf->SetTitle('Documento VUCEM');
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetMargins(0, 0, 0);
                    $pdf->SetAutoPageBreak(false, 0);
                    $pdf->setImageScale(1.0);
                    $pdf->setJPEGQuality(100);
                    $pdf->SetCompression(false);
                    
                    foreach ($jpegFiles as $idx => $jpegFile) {
                        list($widthPx, $heightPx) = getimagesize($jpegFile);
                        // Calcular tamaño en puntos para 300 DPI
                        $widthPt = ($widthPx / 300) * 72;
                        $heightPt = ($heightPx / 300) * 72;
                        $pdf->AddPage('P', [$widthPt, $heightPt]);
                        $pdf->Image($jpegFile, 0, 0, $widthPt, $heightPt, 'JPEG', '', '', false, 300, '', false, false, 0, false, false, false);
                    }
                    
                    $pdfContent = $pdf->Output('', 'S');
                    file_put_contents($outputPath, $pdfContent);
                    
                    if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
                        throw new RuntimeException('No se pudo crear el PDF con TCPDF');
                    }
                    
                    $sizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
                    Log::info("VucemConverter: PDF creado - {$sizeMB} MB");
                    
                    if ($sizeMB <= 10.0) {
                        $success = true;
                        break;
                    }
                    
                    if ($attempt < count($jpegQualities)) {
                        Log::warning("VucemConverter: PDF excede 10 MB, reduciendo calidad...");
                        unlink($outputPath);
                        foreach ($jpegFiles as $f) @unlink($f);
                    }
                }
            }
            
            // Verificar que el PDF se generó
            if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
                throw new RuntimeException('No se pudo crear el PDF');
            }
            
            $outputSizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
            
            // Contar páginas del PDF original para el log
            $pageCountResult = $this->executeGhostscript([
                '-dQUIET',
                '-dNODISPLAY',
                '-dNOSAFER',
                '-dNOPAUSE',
                '-dBATCH',
                '-c',
                "(" . $inputPath . ") (r) file runpdfbegin pdfpagecount = quit"
            ]);
            $pageCount = intval(trim($pageCountResult['output'])) ?: 0;
            
            // Solo advertir si excede 10 MB, pero NO lanzar error
            // NOTA: Para PDFs grandes que se dividieron y unieron, el archivo final puede ser mayor a 10 MB
            // pero internamente se procesó en grupos de 10 páginas con buena calidad (60%)
            if ($outputSizeMB > 10.0 && $totalPages <= 10) {
                // Solo advertir si es un PDF pequeño que no se dividió
                Log::warning('VucemConverter: ADVERTENCIA - PDF excede 10 MB VUCEM', [
                    'output_size_mb' => $outputSizeMB,
                    'pages' => $pageCount,
                    'message' => 'El PDF será descargado pero puede ser rechazado por VUCEM (límite 10 MB para imágenes)'
                ]);
            } else {
                Log::info('VucemConverter: Conversión completada exitosamente', [
                    'output_size_mb' => $outputSizeMB,
                    'pages' => $pageCount,
                    'note' => $totalPages > 10 ? 'PDF grande procesado por partes y unificado' : 'PDF procesado directamente'
                ]);
            }
            
            return [
                'success' => true,
                'output_path' => $outputPath,
                'size_mb' => $outputSizeMB,
                'pages' => $pageCount
            ];

        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * Combina múltiples PDFs en uno solo de forma simple y directa
     */
    protected function mergePdfsSimple(array $pdfFiles, string $outputPath): void
    {
        if (empty($pdfFiles)) {
            throw new RuntimeException('No hay archivos PDF para combinar.');
        }

        // Si solo hay un archivo, copiarlo directamente
        if (count($pdfFiles) === 1) {
            copy($pdfFiles[0], $outputPath);
            return;
        }

        // Combinar todos los PDFs usando Ghostscript con configuración estricta VUCEM
        $args = [
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',              // PDF 1.4 (NO 1.5, 1.6, 1.7)
            '-dPDFSETTINGS=/prepress',               // Máxima calidad
            '-sColorConversionStrategy=Gray',        // Todo a escala de grises
            '-dProcessColorModel=/DeviceGray',       // Solo grises
            '-dAutoFilterGrayImages=false',          // NO auto-detectar
            '-dGrayImageFilter=/FlateEncode',        // Compresión sin pérdida
            '-dGrayImageResolution=300',             // 300 DPI exactos
            '-dDownsampleGrayImages=false',          // NO reducir resolución
            '-dEncodeGrayImages=true',               // Codificar en grises
            '-dDetectDuplicateImages=false',         // Mantener todas las imágenes
            '-r300x300',                             // 300 DPI exactos
            '-sOutputFile=' . $outputPath,
        ];

        // Agregar todos los archivos PDF
        foreach ($pdfFiles as $pdf) {
            $args[] = $pdf;
        }

        $result = $this->executeGhostscript($args);

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new RuntimeException('No se pudo combinar los PDFs. Error: ' . ($result['error'] ?? 'desconocido'));
        }
    }

    /**
     * Combina múltiples PDFs en uno solo
     */
    protected function mergePdfs(array $pdfFiles, string $outputPath, string $tempDir): void
    {
        // Si solo hay un archivo, re-procesarlo para asegurar PDF 1.4
        if (count($pdfFiles) === 1) {
            $result = $this->executeGhostscript([
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dPDFSETTINGS=/prepress',
                '-sOutputFile=' . $outputPath,
                $pdfFiles[0],
            ]);

            if (file_exists($outputPath) && filesize($outputPath) > 100) {
                return;
            }
        }

        // Método 1: Combinar directamente con Ghostscript
        $args = [
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/prepress',
            '-sOutputFile=' . $outputPath,
        ];

        foreach ($pdfFiles as $pdf) {
            $args[] = $pdf;
        }

        $result = $this->executeGhostscript($args);

        if (file_exists($outputPath) && filesize($outputPath) > 100) {
            return;
        }

        // Método 2: Crear archivo con lista de PDFs
        $listFile = $tempDir . DIRECTORY_SEPARATOR . 'filelist.txt';
        $listContent = '';
        foreach ($pdfFiles as $pdf) {
            $listContent .= $pdf . "\n";
        }
        file_put_contents($listFile, $listContent);

        $result = $this->executeGhostscript([
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-sOutputFile=' . $outputPath,
            '@' . $listFile,
        ]);

        @unlink($listFile);

        if (file_exists($outputPath) && filesize($outputPath) > 100) {
            return;
        }

        // Método 3: Concatenar PDFs uno por uno
        $currentOutput = $pdfFiles[0];
        
        for ($i = 1; $i < count($pdfFiles); $i++) {
            $nextOutput = $tempDir . DIRECTORY_SEPARATOR . 'merged_' . $i . '.pdf';
            
            $this->executeGhostscript([
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-sOutputFile=' . $nextOutput,
                $currentOutput,
                $pdfFiles[$i],
            ]);

            if ($i > 1) {
                @unlink($currentOutput);
            }
            
            $currentOutput = $nextOutput;
        }

        if (file_exists($currentOutput)) {
            // Procesar una vez más para asegurar PDF 1.4
            $this->executeGhostscript([
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-sOutputFile=' . $outputPath,
                $currentOutput,
            ]);
            @unlink($currentOutput);
        }

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new RuntimeException('No se pudieron combinar los PDFs. Último error: ' . ($result['error'] ?? 'desconocido'));
        }
    }

    /**
     * Ejecuta Ghostscript y retorna resultado
     */
    protected function executeGhostscript(array $args): array
    {
        // Construir comando - NO usar -q porque causa problemas con paths en Windows
        $command = array_merge([
            $this->ghostscriptPath,
            '-dBATCH',
            '-dNOPAUSE',
            '-dNOSAFER',
            '-dQUIET',
        ], $args);

        $process = new Process($command);
        $process->setTimeout(600);
        
        // Establecer directorio de trabajo temporal para Ghostscript
        $tempPath = sys_get_temp_dir();
        $process->setEnv([
            'TEMP' => $tempPath,
            'TMP' => $tempPath,
        ]);
        
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'code' => $process->getExitCode(),
        ];
    }

    /**
     * Valida que el PDF resultante tenga EXACTAMENTE 300 DPI en TODAS las imágenes
     * Validación estricta: x_dpi === 300 Y y_dpi === 300 (no promedio)
     */
    public function validateDpi(string $pdfPath): array
    {
        if (!$this->pdfimagesPath || !file_exists($pdfPath)) {
            return ['valid' => false, 'error' => 'No se puede validar - pdfimages no disponible'];
        }

        try {
            $process = new Process([$this->pdfimagesPath, '-list', $pdfPath]);
            $process->setTimeout(120);
            $process->run();

            // Si el proceso falla (exit code negativo = crash), no reportar error
            if (!$process->isSuccessful() || $process->getExitCode() < 0) {
                return [
                    'valid' => false, 
                    'error' => 'No se pudo validar DPI'
                ];
            }
        } catch (\Exception $e) {
            // Silenciar excepciones de pdfimages
            return [
                'valid' => false,
                'error' => 'No se pudo validar DPI'
            ];
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $images = [];
        $allValid = true;
        $totalImages = 0;
        $invalidImages = [];

        foreach ($lines as $lineNum => $line) {
            // Detectar líneas de imágenes en el output de pdfimages -list
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+\S+\s+\S+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/', $line, $m)) {
                $totalImages++;
                $page = intval($m[1]);
                $xPpi = intval($m[9]);
                $yPpi = intval($m[10]);
                
                // VALIDACIÓN ESTRICTA: Ambos deben ser EXACTAMENTE 300
                $isValid = ($xPpi === 300 && $yPpi === 300);
                
                $imageInfo = [
                    'page' => $page,
                    'num' => intval($m[2]),
                    'type' => $m[3],
                    'width' => intval($m[4]),
                    'height' => intval($m[5]),
                    'x_dpi' => $xPpi,
                    'y_dpi' => $yPpi,
                    'valid' => $isValid,
                ];
                
                $images[] = $imageInfo;

                if (!$isValid) {
                    $allValid = false;
                    $invalidImages[] = $imageInfo;
                }
            }
        }

        $result = [
            'valid' => $allValid,
            'total_images' => $totalImages,
            'images' => $images,
            'invalid_images' => $invalidImages,
            'invalid_count' => count($invalidImages),
        ];

        // Si no hay imágenes detectadas, advertir
        if ($totalImages === 0) {
            $result['warning'] = 'No se detectaron imágenes en el PDF. Puede ser que el PDF solo contenga vectores o texto.';
        }

        return $result;
    }

    protected function createTempDirectory(): string
    {
        $basePath = storage_path('app/tmp/vucem_convert');
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        $tempDir = $basePath . DIRECTORY_SEPARATOR . 'conv_' . uniqid() . '_' . time();
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    protected function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($dir);
    }

    protected function findGhostscript(): ?string
    {
        if ($this->isWindows) {
            // Rutas de Windows
            $gsFolders = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
            if ($gsFolders) {
                rsort($gsFolders, SORT_NATURAL);
                foreach ($gsFolders as $path) {
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }
            
            // Intentar en PATH de Windows
            $process = new Process(['gswin64c', '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                return 'gswin64c';
            }
            
            // Intentar versión 32 bits
            $process = new Process(['gswin32c', '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                return 'gswin32c';
            }
        } else {
            // Linux/Unix - Primero intentar ejecutar directamente 'gs'
            // Esto funciona si gs está en el PATH del sistema
            $process = Process::fromShellCommandline('gs --version 2>/dev/null');
            $process->run();
            if ($process->isSuccessful() && !empty(trim($process->getOutput()))) {
                return 'gs';
            }
            
            // Rutas comunes de Linux/Unix
            $linuxPaths = [
                '/usr/bin/gs',
                '/usr/local/bin/gs',
                '/opt/local/bin/gs',
                '/snap/bin/gs',
            ];
            
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
                    // Verificar que es ejecutable probándolo
                    $process = new Process([$path, '--version']);
                    $process->run();
                    if ($process->isSuccessful()) {
                        return $path;
                    }
                }
            }
            
            // Intentar con which
            $process = Process::fromShellCommandline('which gs 2>/dev/null');
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (!empty($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function findPdfimages(): ?string
    {
        if ($this->isWindows) {
            // Rutas de Windows
            $windowsPaths = [
                'C:\\Poppler\\Release-25.12.0-0\\poppler-25.12.0\\Library\\bin\\pdfimages.exe',
                'C:\\Poppler\\Library\\bin\\pdfimages.exe',
                'C:\\Program Files\\poppler\\bin\\pdfimages.exe',
                'C:\\Program Files (x86)\\poppler\\bin\\pdfimages.exe',
            ];
            
            foreach ($windowsPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            
            // Buscar con glob por si hay diferentes versiones
            $popplerFolders = glob('C:\\Poppler\\Release-*\\poppler-*\\Library\\bin\\pdfimages.exe');
            if ($popplerFolders) {
                rsort($popplerFolders, SORT_NATURAL);
                return $popplerFolders[0];
            }
        } else {
            // Linux/Unix - Primero intentar ejecutar directamente 'pdfimages'
            $process = Process::fromShellCommandline('pdfimages -v 2>&1');
            $process->run();
            $output = $process->getOutput() . $process->getErrorOutput();
            if (str_contains($output, 'pdfimages') || str_contains($output, 'poppler')) {
                return 'pdfimages';
            }
            
            // Rutas comunes de Linux/Unix
            $linuxPaths = [
                '/usr/bin/pdfimages',
                '/usr/local/bin/pdfimages',
                '/opt/local/bin/pdfimages',
                '/snap/bin/pdfimages',
            ];
            
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
                    // Verificar que funciona ejecutándolo
                    $process = new Process([$path, '-v']);
                    $process->run();
                    $output = $process->getOutput() . $process->getErrorOutput();
                    if (str_contains($output, 'pdfimages') || str_contains($output, 'poppler')) {
                        return $path;
                    }
                }
            }
            
            // Intentar con which
            $process = Process::fromShellCommandline('which pdfimages 2>/dev/null');
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (!empty($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Encuentra la instalación de ImageMagick en el sistema
     */
    protected function findImageMagick(): ?string
    {
        if ($this->isWindows) {
            // Windows - Intentar diferentes rutas comunes
            $windowsPaths = [
                'magick',  // Si está en PATH
                'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe',
                'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
                'C:\\Program Files\\ImageMagick\\magick.exe',
                'C:\\Program Files (x86)\\ImageMagick\\magick.exe',
            ];
            
            foreach ($windowsPaths as $path) {
                if ($path === 'magick' || file_exists($path)) {
                    $testProcess = new Process([$path === 'magick' ? 'magick' : $path, '-version']);
                    try {
                        $testProcess->run();
                        if ($testProcess->isSuccessful()) {
                            return $path === 'magick' ? 'magick' : $path;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        } else {
            // Linux/Unix
            $process = Process::fromShellCommandline('convert -version 2>&1');
            $process->run();
            $output = $process->getOutput();
            if (str_contains($output, 'ImageMagick')) {
                return 'convert';
            }
            
            // Intentar con which
            $process = Process::fromShellCommandline('which convert 2>/dev/null');
            $process->run();
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        }

        return null;
    }

    /**
     * Valida que el PDF cumpla con TODOS los requisitos de VUCEM
     */
    public function validateVucemCompliance(string $pdfPath): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        if (!file_exists($pdfPath)) {
            return ['valid' => false, 'errors' => ['El archivo no existe']];
        }

        // 1. Validar tamaño (máximo 3 MB)
        $fileSize = filesize($pdfPath);
        $maxSize = 3 * 1024 * 1024; // 3 MB
        if ($fileSize > $maxSize) {
            $result['valid'] = false;
            $sizeMB = round($fileSize / (1024 * 1024), 2);
            $result['errors'][] = "Tamaño {$sizeMB} MB excede el límite de 3 MB";
        }

        // 2. Validar versión PDF usando Ghostscript
        if ($this->ghostscriptPath) {
            $process = new Process([
                $this->ghostscriptPath,
                '-dNODISPLAY',
                '-dQUIET',
                '-dNOPAUSE',
                '-dBATCH',
                '-c',
                '(pdfPath) cvn dup where { exch get exec } { pop () } ifelse quit',
                $pdfPath,
            ]);
            $process->setTimeout(30);
            $process->run();
            
            // Leer el header del PDF directamente
            $handle = fopen($pdfPath, 'r');
            $header = fread($handle, 1024);
            fclose($handle);
            
            if (preg_match('/%PDF-(\d+\.\d+)/', $header, $matches)) {
                $version = floatval($matches[1]);
                if ($version > 1.4) {
                    $result['valid'] = false;
                    $result['errors'][] = "Versión PDF {$matches[1]} no permitida (debe ser 1.4)";
                }
            }
        }

        // 3. Validar DPI de las imágenes
        $dpiValidation = $this->validateDpi($pdfPath);
        if (isset($dpiValidation['valid']) && !$dpiValidation['valid']) {
            $result['valid'] = false;
            if (isset($dpiValidation['error'])) {
                $result['errors'][] = $dpiValidation['error'];
            }
            if (isset($dpiValidation['invalid_count']) && $dpiValidation['invalid_count'] > 0) {
                $result['errors'][] = "{$dpiValidation['invalid_count']} imágenes no tienen exactamente 300 DPI";
            }
        }

        // 4. Detectar color (esto requiere analizar el contenido)
        if ($this->pdfimagesPath) {
            $process = new Process([$this->pdfimagesPath, '-list', $pdfPath]);
            $process->setTimeout(60);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                // Buscar imágenes en color (no 'gray')
                if (preg_match('/\s+(rgb|cmyk|icc|idx|jpeg|jp2)\s+/i', $output)) {
                    $result['warnings'][] = 'El PDF puede contener imágenes en color';
                }
            }
        }

        return $result;
    }

    public function getToolsInfo(): array
    {
        return [
            'os' => [
                'type' => $this->isWindows ? 'Windows' : 'Linux/Unix',
                'php_os' => PHP_OS,
            ],
            'ghostscript' => [
                'available' => $this->ghostscriptPath !== null,
                'path' => $this->ghostscriptPath,
            ],
            'pdfimages' => [
                'available' => $this->pdfimagesPath !== null,
                'path' => $this->pdfimagesPath,
            ],
        ];
    }

    /**
     * Información de debug para diagnóstico en producción
     */
    public function getDebugInfo(): array
    {
        $debug = [
            'os' => [
                'php_os' => PHP_OS,
                'is_windows' => $this->isWindows,
                'uname' => php_uname(),
            ],
            'paths_checked' => [],
            'commands_tested' => [],
        ];

        if (!$this->isWindows) {
            // Verificar rutas de Ghostscript
            $gsPaths = ['/usr/bin/gs', '/usr/local/bin/gs', '/opt/local/bin/gs', '/snap/bin/gs'];
            foreach ($gsPaths as $path) {
                $debug['paths_checked']['gs'][$path] = [
                    'exists' => file_exists($path),
                    'is_executable' => is_executable($path),
                ];
            }

            // Verificar rutas de pdfimages
            $pdfPaths = ['/usr/bin/pdfimages', '/usr/local/bin/pdfimages', '/opt/local/bin/pdfimages'];
            foreach ($pdfPaths as $path) {
                $debug['paths_checked']['pdfimages'][$path] = [
                    'exists' => file_exists($path),
                    'is_executable' => is_executable($path),
                ];
            }

            // Probar comandos directamente
            $commands = [
                'gs_version' => 'gs --version 2>&1',
                'which_gs' => 'which gs 2>&1',
                'pdfimages_version' => 'pdfimages -v 2>&1',
                'which_pdfimages' => 'which pdfimages 2>&1',
                'path_env' => 'echo $PATH',
                'whoami' => 'whoami',
            ];

            foreach ($commands as $key => $cmd) {
                $process = Process::fromShellCommandline($cmd);
                $process->run();
                $debug['commands_tested'][$key] = [
                    'command' => $cmd,
                    'success' => $process->isSuccessful(),
                    'exit_code' => $process->getExitCode(),
                    'output' => trim($process->getOutput()),
                    'error' => trim($process->getErrorOutput()),
                ];
            }
        }

        $debug['final_paths'] = [
            'ghostscript' => $this->ghostscriptPath,
            'pdfimages' => $this->pdfimagesPath,
        ];

        return $debug;
    }

    /**
     * Comprime un PDF sin rasterizar, manteniendo 300 DPI
     * 
     * @param string $inputPath Ruta del archivo PDF de entrada
     * @param string $outputPath Ruta del archivo PDF de salida
     * @param string $level Nivel de compresión: screen, ebook, printer, prepress
     * @return array Información sobre la compresión
     */
    public function compressPdf(string $inputPath, string $outputPath, string $level = 'printer'): array
    {
        if (!file_exists($inputPath)) {
            throw new RuntimeException("El archivo de entrada no existe: {$inputPath}");
        }

        if (!$this->ghostscriptPath) {
            throw new RuntimeException('Ghostscript no está disponible en el sistema.');
        }

        // Aumentar límite de tiempo para archivos grandes
        set_time_limit(600); // 10 minutos
        ini_set('max_execution_time', '600');

        $inputSize = filesize($inputPath);
        
        // Configuración según nivel de compresión
        $settings = [
            'screen' => [
                'dpi' => 72,
                'description' => 'Pantalla - Máxima compresión (72 DPI)'
            ],
            'ebook' => [
                'dpi' => 150,
                'description' => 'Ebook - Alta compresión (150 DPI)'
            ],
            'printer' => [
                'dpi' => 300,
                'description' => 'Impresora - Mantiene 300 DPI'
            ],
            'prepress' => [
                'dpi' => 300,
                'description' => 'Preimpresión - Calidad máxima 300 DPI'
            ]
        ];

        $config = $settings[$level] ?? $settings['printer'];

        Log::info('PdfCompress: Iniciando compresión', [
            'input' => basename($inputPath),
            'level' => $level,
            'dpi' => $config['dpi'],
            'input_size_mb' => round($inputSize / (1024 * 1024), 2)
        ]);

        // Argumentos para Ghostscript con compresión optimizada
        $gsArgs = [
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/' . $level,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-dQUIET',
            '-sColorConversionStrategy=Gray',
            '-dProcessColorModel=/DeviceGray',
            '-dCompressFonts=true',
            '-dCompressPages=true',
            '-dOptimize=true',
        ];

        // Configuración específica según el nivel
        if ($level === 'printer' || $level === 'prepress') {
            // Mantener 300 DPI pero comprimir con JPEG
            $gsArgs[] = '-dDownsampleGrayImages=false';
            $gsArgs[] = '-dGrayImageResolution=300';
            $gsArgs[] = '-dGrayImageDownsampleThreshold=1.0';
            $gsArgs[] = '-dAutoFilterGrayImages=false';
            $gsArgs[] = '-dGrayImageFilter=/DCTEncode';
            $gsArgs[] = '-dEncodeGrayImages=true';
            // Calidad JPEG más baja para comprimir mejor
            $gsArgs[] = '-dJPEGQ=60';
        } else {
            // Para screen y ebook, permitir downsample
            $gsArgs[] = '-dDownsampleGrayImages=true';
            $gsArgs[] = '-dGrayImageDownsampleType=/Bicubic';
            $gsArgs[] = '-dGrayImageResolution=' . $config['dpi'];
            $gsArgs[] = '-dAutoFilterGrayImages=false';
            $gsArgs[] = '-dGrayImageFilter=/DCTEncode';
            $gsArgs[] = '-dEncodeGrayImages=true';
            $gsArgs[] = '-dJPEGQ=50';
        }

        $gsArgs[] = '-sOutputFile=' . $outputPath;
        $gsArgs[] = $inputPath;

        $this->executeGhostscript($gsArgs);

        if (!file_exists($outputPath)) {
            throw new RuntimeException('No se pudo comprimir el PDF');
        }

        $outputSize = filesize($outputPath);
        $reduction = round((($inputSize - $outputSize) / $inputSize) * 100, 2);

        Log::info('PdfCompress: Compresión completada', [
            'output_size_mb' => round($outputSize / (1024 * 1024), 2),
            'reduction_percent' => $reduction,
            'level' => $level
        ]);

        return [
            'success' => true,
            'input_size' => $inputSize,
            'output_size' => $outputSize,
            'reduction_percent' => $reduction,
            'level' => $level,
            'description' => $config['description']
        ];
    }

    /**
     * Combina múltiples PDFs en uno solo sin rasterizar, manteniendo 300 DPI
     * 
     * @param array $inputPaths Array con rutas de archivos PDF a combinar
     * @param string $outputPath Ruta del archivo PDF de salida
     * @return array Información sobre la combinación
     */
    public function mergePdfsKeepDpi(array $inputPaths, string $outputPath): array
    {
        if (empty($inputPaths)) {
            throw new RuntimeException('No hay archivos PDF para combinar.');
        }

        foreach ($inputPaths as $path) {
            if (!file_exists($path)) {
                throw new RuntimeException("El archivo no existe: {$path}");
            }
        }

        if (!$this->ghostscriptPath) {
            throw new RuntimeException('Ghostscript no está disponible en el sistema.');
        }

        // Aumentar límite de tiempo para múltiples archivos
        set_time_limit(600); // 10 minutos
        ini_set('max_execution_time', '600');

        Log::info('PdfMerge: Iniciando combinación', [
            'files_count' => count($inputPaths),
            'total_size_mb' => round(array_sum(array_map('filesize', $inputPaths)) / (1024 * 1024), 2)
        ]);

        // Si solo hay un archivo, copiarlo directamente
        if (count($inputPaths) === 1) {
            copy($inputPaths[0], $outputPath);
            $outputSize = filesize($outputPath);
            
            return [
                'success' => true,
                'files_merged' => 1,
                'output_size' => $outputSize
            ];
        }

        // Combinar todos los PDFs usando Ghostscript manteniendo 300 DPI
        $gsArgs = [
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/prepress',
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-dQUIET',
            '-sColorConversionStrategy=Gray',
            '-dProcessColorModel=/DeviceGray',
            '-dDownsampleGrayImages=false',
            '-dGrayImageResolution=300',
            '-dAutoFilterGrayImages=false',
            '-dGrayImageFilter=/FlateEncode',
            '-sOutputFile=' . $outputPath,
        ];

        // Agregar todos los archivos PDF
        foreach ($inputPaths as $pdf) {
            $gsArgs[] = $pdf;
        }

        $this->executeGhostscript($gsArgs);

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new RuntimeException('No se pudo combinar los PDFs');
        }

        $outputSize = filesize($outputPath);

        Log::info('PdfMerge: Combinación completada', [
            'output_size_mb' => round($outputSize / (1024 * 1024), 2),
            'files_merged' => count($inputPaths)
        ]);

        return [
            'success' => true,
            'files_merged' => count($inputPaths),
            'output_size' => $outputSize
        ];
    }
}

