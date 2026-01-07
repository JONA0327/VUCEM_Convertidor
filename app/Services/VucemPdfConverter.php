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
     */
    public function convertToVucem(string $inputPath, string $outputPath): void
    {
        // Aumentar límite de tiempo y memoria de ejecución
        set_time_limit(600); // 10 minutos
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '2048M'); // 2GB de memoria
        
        if (!file_exists($inputPath)) {
            throw new RuntimeException("El archivo de entrada no existe: {$inputPath}");
        }

        if (!$this->ghostscriptPath) {
            throw new RuntimeException('Ghostscript no está disponible en el sistema.');
        }

        $tempDir = $this->createTempDirectory();

        try {
            // MÉTODO DE RASTERIZACIÓN COMPLETA:
            // Convertir cada página del PDF a imagen PNG a 300 DPI, 
            // luego reconstruir el PDF desde las imágenes.
            // Esto garantiza que TODO (texto e imágenes) esté a exactamente 300 DPI.
            
            Log::info('VucemConverter: Iniciando rasterización completa a 300 DPI', [
                'input' => basename($inputPath),
                'temp_dir' => $tempDir
            ]);
            
            // Paso 1: Convertir cada página del PDF a PNG a 300 DPI en escala de grises
            $pngPattern = $tempDir . '/page_%03d.png';
            
            $gsRasterArgs = [
                '-sDEVICE=pnggray',                      // PNG en escala de grises
                '-r300',                                  // 300 DPI exactos
                '-dNOPAUSE',
                '-dBATCH',
                '-dSAFER',
                '-sOutputFile=' . $pngPattern,
                $inputPath,
            ];
            
            Log::info('VucemConverter: Rasterizando páginas a PNG 300 DPI...');
            $rasterResult = $this->executeGhostscript($gsRasterArgs);
            
            // Contar PNGs generados
            $pngFiles = glob($tempDir . '/page_*.png');
            sort($pngFiles, SORT_NATURAL);
            $pageCount = count($pngFiles);
            
            if ($pageCount === 0) {
                throw new RuntimeException('No se generaron páginas PNG. Error: ' . ($rasterResult['error'] ?? 'desconocido'));
            }
            
            Log::info('VucemConverter: PNGs generados exitosamente', ['count' => $pageCount]);
            
            // Paso 2: Usar ImageMagick si está disponible (MUCHO más rápido)
            if ($this->imageMagickPath) {
                Log::info('VucemConverter: Usando ImageMagick para conversión rápida...', [
                    'path' => $this->imageMagickPath
                ]);
                
                // Intentar primero con calidad media-alta (70)
                // VUCEM requiere máximo 3 MB
                $quality = 70;
                $maxAttempts = 3;
                $attempt = 0;
                
                while ($attempt < $maxAttempts) {
                    $attempt++;
                    
                    // Usar ruta completa de ImageMagick
                    $imArgs = [$this->imageMagickPath];
                    
                    // Agregar archivos PNG
                    foreach ($pngFiles as $png) {
                        $imArgs[] = $png;
                    }
                    
                    // Agregar opciones de conversión
                    $imArgs = array_merge($imArgs, [
                        '-colorspace', 'Gray',
                        '-density', '300',
                        '-units', 'PixelsPerInch',
                        '-compress', 'JPEG',
                        '-quality', (string)$quality,
                        $outputPath
                    ]);
                    
                    Log::info('VucemConverter: Ejecutando ImageMagick', [
                        'quality' => $quality,
                        'png_count' => count($pngFiles),
                        'first_png' => basename($pngFiles[0])
                    ]);
                    
                    // Crear proceso con escapado correcto
                    $imProcess = new Process($imArgs);
                    $imProcess->setTimeout(600);
                    
                    try {
                        $imProcess->run();
                    } catch (\Exception $e) {
                        Log::warning('VucemConverter: Excepción al ejecutar ImageMagick', [
                            'exception' => $e->getMessage()
                        ]);
                        break;
                    }
                    
                    if (!$imProcess->isSuccessful() || !file_exists($outputPath)) {
                        Log::warning('VucemConverter: ImageMagick falló', [
                            'error' => $imProcess->getErrorOutput(),
                            'output' => $imProcess->getOutput(),
                            'exit_code' => $imProcess->getExitCode()
                        ]);
                        break;
                    }
                    
                    // Verificar tamaño del archivo
                    $sizeMB = filesize($outputPath) / (1024 * 1024);
                    Log::info('VucemConverter: PDF generado con ImageMagick', [
                        'size_mb' => round($sizeMB, 2),
                        'quality' => $quality,
                        'attempt' => $attempt
                    ]);
                    
                    // Si está bajo 3 MB, perfecto
                    if ($sizeMB <= 3.0) {
                        break;
                    }
                    
                    // Si excede 3 MB, reducir calidad y reintentar
                    if ($attempt < $maxAttempts) {
                        Log::warning('VucemConverter: PDF excede 3 MB, reduciendo calidad...', [
                            'current_size_mb' => round($sizeMB, 2)
                        ]);
                        unlink($outputPath);
                        $quality -= 15; // Reducir calidad (70 -> 55 -> 40)
                    }
                }
            }
            
            // Si ImageMagick no está disponible o falló, usar TCPDF (PHP puro)
            if (!$this->imageMagickPath || !file_exists($outputPath)) {
                Log::info('VucemConverter: Usando TCPDF para crear PDF desde PNGs...');
                
                // Intentar con diferentes niveles de calidad JPEG
                $qualities = [60, 45, 30]; // Calidades para intentar
                $successfullyCreated = false;
                
                foreach ($qualities as $quality) {
                    // Crear PDF con TCPDF
                    $pdf = new \TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('VUCEM Converter');
                    $pdf->SetAuthor('Sistema');
                    $pdf->SetTitle('Documento VUCEM');
                    
                    // Configurar compresión
                    $pdf->setImageScale(1.0);
                    $pdf->setJPEGQuality($quality); // Calidad JPEG
                    $pdf->SetCompression(true);
                    
                    // Quitar header/footer
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetMargins(0, 0, 0);
                    $pdf->SetAutoPageBreak(false, 0);
                    
                    Log::info("VucemConverter: Creando PDF con calidad JPEG {$quality}%...");
                    
                    foreach ($pngFiles as $index => $pngFile) {
                        list($width, $height) = getimagesize($pngFile);
                        
                        // Calcular dimensiones en puntos (1 pulgada = 72 puntos, imagen a 300 DPI)
                        $widthPt = ($width / 300) * 72;
                        $heightPt = ($height / 300) * 72;
                        
                        // Agregar página con tamaño exacto de la imagen
                        $pdf->AddPage('P', [$widthPt, $heightPt]);
                        
                        // Cargar PNG y convertir a JPEG en memoria para reducir tamaño
                        $image = imagecreatefrompng($pngFile);
                        if ($image) {
                            // Convertir a escala de grises
                            imagefilter($image, IMG_FILTER_GRAYSCALE);
                            
                            // Guardar como JPEG temporal
                            $jpegFile = $tempDir . "/temp_page_{$index}.jpg";
                            imagejpeg($image, $jpegFile, $quality);
                            imagedestroy($image);
                            
                            // Insertar JPEG en PDF
                            $pdf->Image($jpegFile, 0, 0, $widthPt, $heightPt, 'JPEG', '', '', false, 300, '', false, false, 0);
                            
                            unlink($jpegFile); // Limpiar temporal
                        }
                        
                        if (($index + 1) % 10 === 0) {
                            Log::info("VucemConverter: Procesadas " . ($index + 1) . "/{$pageCount} páginas");
                        }
                    }
                    
                    // Guardar PDF
                    $pdf->Output($outputPath, 'F');
                    
                    if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
                        throw new RuntimeException('TCPDF no pudo generar el archivo');
                    }
                    
                    $sizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
                    Log::info("VucemConverter: PDF creado con TCPDF (calidad {$quality}%)", [
                        'size_mb' => $sizeMB
                    ]);
                    
                    // Si está bajo 3 MB, éxito
                    if ($sizeMB <= 3.0) {
                        $successfullyCreated = true;
                        break;
                    }
                    
                    // Si excede y hay más intentos, eliminar y reintentar
                    if ($quality !== end($qualities)) {
                        Log::warning("VucemConverter: PDF excede 3 MB ({$sizeMB} MB), reduciendo calidad...");
                        unlink($outputPath);
                    }
                }
                
                if (!$successfullyCreated) {
                    $finalSize = round(filesize($outputPath) / (1024 * 1024), 2);
                    throw new RuntimeException(
                        "No se pudo crear un PDF menor a 3 MB. Tamaño mínimo alcanzado: {$finalSize} MB. " .
                        "El documento tiene demasiadas páginas o imágenes muy grandes."
                    );
                }
            } else {
                // Ya se creó con ImageMagick, solo necesitamos asegurar compatibilidad PDF 1.4
                Log::info('VucemConverter: Asegurando PDF 1.4...');
                $tempOutput = $outputPath . '.tmp';
                rename($outputPath, $tempOutput);
                
                $gsPdfArgs = [
                    '-sDEVICE=pdfwrite',
                    '-dCompatibilityLevel=1.4',
                    '-dNOPAUSE',
                    '-dBATCH',
                    '-dSAFER',
                    '-sColorConversionStrategy=Gray',
                    '-dProcessColorModel=/DeviceGray',
                    '-sOutputFile=' . $outputPath,
                    $tempOutput,
                ];
                
                Log::info('VucemConverter: Convirtiendo a PDF 1.4...');
                $pdfResult = $this->executeGhostscript($gsPdfArgs);
            }
            
            // Verificar resultado final
            if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
                $errorDetail = "No se pudo generar el PDF final. ";
                if (!empty($pdfResult['error'])) {
                    $errorDetail .= "GS Error: " . substr($pdfResult['error'], 0, 300);
                }
                Log::error('VucemConverter: Error en conversión PNG a PDF', [
                    'gs_code' => $pdfResult['code'],
                    'output_exists' => file_exists($outputPath),
                    'output_size' => file_exists($outputPath) ? filesize($outputPath) : 0
                ]);
                throw new RuntimeException($errorDetail);
            }
            
            $outputSizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
            
            // VALIDACIÓN CRÍTICA: VUCEM requiere máximo 3 MB
            if ($outputSizeMB > 3.0) {
                Log::warning('VucemConverter: PDF excede límite de 3 MB, recomprimiendo...', [
                    'current_size_mb' => $outputSizeMB
                ]);
                
                // Recomprimir con mayor compresión
                $tempOutput = $outputPath . '.tmp';
                rename($outputPath, $tempOutput);
                
                $gsCompressArgs = [
                    '-sDEVICE=pdfwrite',
                    '-dCompatibilityLevel=1.4',
                    '-dPDFSETTINGS=/ebook',  // Mayor compresión
                    '-dNOPAUSE',
                    '-dBATCH',
                    '-dSAFER',
                    '-sColorConversionStrategy=Gray',
                    '-dProcessColorModel=/DeviceGray',
                    '-dColorImageResolution=300',
                    '-dGrayImageResolution=300',
                    '-dColorImageDownsampleType=/Bicubic',
                    '-dGrayImageDownsampleType=/Bicubic',
                    '-dJPEGQ=60',  // Calidad JPEG reducida
                    '-sOutputFile=' . $outputPath,
                    $tempOutput,
                ];
                
                $this->executeGhostscript($gsCompressArgs);
                unlink($tempOutput);
                
                $outputSizeMB = round(filesize($outputPath) / (1024 * 1024), 2);
                
                if ($outputSizeMB > 3.0) {
                    throw new RuntimeException(
                        "El PDF convertido excede el límite de 3 MB requerido por VUCEM. " .
                        "Tamaño actual: {$outputSizeMB} MB. " .
                        "Intente con un PDF con menos páginas o imágenes de menor resolución."
                    );
                }
                
                Log::info('VucemConverter: PDF recomprimido exitosamente', [
                    'new_size_mb' => $outputSizeMB
                ]);
            }
            
            Log::info('VucemConverter: Conversión completada exitosamente', [
                'output_size_mb' => $outputSizeMB,
                'pages' => $pageCount
            ]);

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

        $process = new Process([$this->pdfimagesPath, '-list', $pdfPath]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'valid' => false, 
                'error' => 'Error al ejecutar pdfimages: ' . $process->getErrorOutput()
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
}
