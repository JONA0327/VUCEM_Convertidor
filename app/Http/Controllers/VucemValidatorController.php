<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VucemValidatorController extends Controller
{
    public function index()
    {
        return view('vucem.validador');
    }

    public function validatePdf(Request $request)
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'], // hasta 50MB de entrada
        ]);

        $file = $request->file('pdf');

        // Crear directorio tmp/validador si no existe
        if (!Storage::exists('tmp/validador')) {
            Storage::makeDirectory('tmp/validador');
        }

        // Guardar temporalmente
        $path = $file->store('tmp/validador');
        $fullPath = Storage::path($path);

        $checks = [];

        // 1) Tamaño (máximo 3 MB según VUCEM)
        $sizeBytes = filesize($fullPath);
        $sizeMb = round($sizeBytes / (1024 * 1024), 2);
        $sizeOk = $sizeBytes <= 3 * 1024 * 1024;

        $checks['size'] = [
            'label' => 'Tamaño < 3 MB',
            'ok'    => $sizeOk,
            'value' => $sizeMb . ' MB',
        ];

        // 2) Versión PDF (con Ghostscript)
        $version = $this->getPdfVersionWithGs($fullPath);
        $versionOk = $version === '1.4';

        $checks['version'] = [
            'label' => 'Versión PDF 1.4',
            'ok'    => $versionOk,
            'value' => $version ?: 'No detectada',
        ];

        // 3) Escala de grises (usando ink coverage de Ghostscript)
        $grayResult = $this->checkGrayWithInkCov($fullPath);
        $checks['grayscale'] = [
            'label' => 'Contenido en escala de grises (sin color)',
            'ok'    => $grayResult['is_gray'],
            'value' => $grayResult['detail'],
        ];

        // 4) Resolución DPI (debe ser EXACTAMENTE 300 DPI - regla VUCEM estricta)
        $dpiResult = $this->checkDpi($fullPath);
        $checks['dpi'] = [
            'label' => 'Resolución exacta 300 DPI',
            'ok'    => $dpiResult['is_valid'],
            'value' => $dpiResult['detail'],
            'status' => $dpiResult['status'] ?? ($dpiResult['is_valid'] ? 'ok' : 'error'),
            'pages' => $dpiResult['pages'] ?? [],
            'images' => $dpiResult['images'] ?? [],
        ];

        // 5) Encriptado (si tuvieras qpdf, aquí se integra)
        $encryption = $this->checkEncryptionWithQpdf($fullPath);
        $checks['encryption'] = [
            'label' => 'Sin contraseña / sin encriptar',
            'ok'    => $encryption['is_unencrypted'],
            'value' => $encryption['detail'],
        ];

        // 5) Resultado global
        $allOk = collect($checks)->every(fn($c) => $c['ok']);

        // Limpiar archivo temporal
        Storage::delete($path);

        return view('vucem.validador', [
            'checks'  => $checks,
            'allOk'   => $allOk,
            'fileName'=> $file->getClientOriginalName(),
        ]);
    }

    protected function getPdfVersionWithGs(string $path): ?string
    {
        // Buscar Ghostscript
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            // Intentar leer directamente del archivo
            return $this->getPdfVersionFromFile($path);
        }

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        // Ghostscript: obtener versión del PDF
        $code = '(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin pdfdict /Version get == quit';

        $process = new Process([
            $gsPath,
            '-q',
            '-dNODISPLAY',
            '-dNOSAFER',
            '-c',
            $code,
        ], null, $env);

        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            // Limpiar la salida (puede venir con comillas)
            $output = trim($output, "\" \r\n");
            if (preg_match('/^[\d\.]+$/', $output)) {
                return $output;
            }
        }

        // Si Ghostscript falla, intentar leer directamente del archivo
        return $this->getPdfVersionFromFile($path);
    }
    
    /**
     * Leer versión del PDF directamente del archivo
     */
    protected function getPdfVersionFromFile(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return null;
        }
        
        // Leer los primeros bytes del PDF
        $header = fread($handle, 20);
        fclose($handle);
        
        // El header del PDF es algo como: %PDF-1.4
        if (preg_match('/%PDF-(\d+\.\d+)/', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    protected function checkGrayWithInkCov(string $path): array
    {
        // Buscar Ghostscript
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            return [
                'is_gray' => false,
                'detail'  => 'Ghostscript no encontrado',
            ];
        }

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        // Ghostscript ink coverage: revisa si hay C/M/Y distintos de 0
        $process = new Process([
            $gsPath,
            '-o', '-',
            '-sDEVICE=inkcov',
            $path,
        ], null, $env);

        $process->setTimeout(180);
        $process->run();

        // Obtener tanto stdout como stderr
        $output = $process->getOutput() . "\n" . $process->getErrorOutput();
        
        if (empty(trim($output))) {
            return [
                'is_gray' => false,
                'detail'  => 'No se pudo analizar el documento',
            ];
        }

        // Formato típico por página: " 0.00000  0.00000  0.00000  0.12345 CMYK OK"
        // O en algunas versiones: "Page 1: 0.00000 0.00000 0.00000 0.12345 CMYK"
        $lines = preg_split('/\r\n|\r|\n/', trim($output));
        $isGray = true;
        $pagesWithColor = 0;
        $totalPages = 0;

        foreach ($lines as $line) {
            // Buscar patrón de cobertura de tinta CMYK
            // Formato: C M Y K (valores decimales)
            if (preg_match('/(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+\.?\d*)\s*(?:CMYK)?/i', $line, $m)) {
                $totalPages++;
                $c = floatval($m[1]);
                $magenta = floatval($m[2]);
                $y = floatval($m[3]);
                // $k = floatval($m[4]); // K (negro) no importa para detectar color

                // Si hay cualquier cantidad de C, M o Y, hay color
                if ($c > 0.0001 || $magenta > 0.0001 || $y > 0.0001) {
                    $isGray = false;
                    $pagesWithColor++;
                }
            }
        }

        // Si no se detectaron páginas, intentar método alternativo
        if ($totalPages === 0) {
            return $this->checkColorAlternative($path);
        }

        if ($isGray) {
            return [
                'is_gray' => true,
                'detail'  => "Analizado: {$totalPages} página(s) - Sin cobertura de color (solo K → escala de grises).",
            ];
        }

        return [
            'is_gray' => false,
            'detail'  => "Se detectó color en {$pagesWithColor} de {$totalPages} página(s).",
        ];
    }
    
    /**
     * Método alternativo para detectar color usando análisis de color space
     */
    protected function checkColorAlternative(string $path): array
    {
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            return [
                'is_gray' => false,
                'detail'  => 'No se pudo verificar (Ghostscript no disponible)',
            ];
        }

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        // Intentar renderizar una página y verificar si hay color
        // Usar pdfinfo-style analysis
        $tempImage = sys_get_temp_dir() . '/gs_color_check_' . uniqid() . '.ppm';
        
        $process = new Process([
            $gsPath,
            '-q',
            '-dNOPAUSE',
            '-dBATCH',
            '-dFirstPage=1',
            '-dLastPage=1',
            '-sDEVICE=ppmraw',
            '-r72',
            '-sOutputFile=' . $tempImage,
            $path,
        ], null, $env);

        $process->setTimeout(60);
        $process->run();

        if (!file_exists($tempImage)) {
            return [
                'is_gray' => false,
                'detail'  => 'No se pudo analizar - asuma que tiene color por seguridad',
            ];
        }

        // Analizar la imagen PPM para detectar color
        $hasColor = $this->checkPpmForColor($tempImage);
        @unlink($tempImage);

        if ($hasColor) {
            return [
                'is_gray' => false,
                'detail'  => 'Se detectó contenido a color en el documento.',
            ];
        }

        return [
            'is_gray' => true,
            'detail'  => 'El documento parece estar en escala de grises.',
        ];
    }
    
    /**
     * Verificar si una imagen PPM tiene color
     */
    protected function checkPpmForColor(string $ppmPath): bool
    {
        $handle = fopen($ppmPath, 'rb');
        if (!$handle) {
            return true; // Asumir color si no se puede leer
        }

        // Leer header PPM
        $header = fgets($handle);
        if (strpos($header, 'P6') === false && strpos($header, 'P3') === false) {
            fclose($handle);
            return true;
        }

        // Saltar comentarios y leer dimensiones
        do {
            $line = fgets($handle);
        } while ($line !== false && $line[0] === '#');
        
        // Leer max value
        $maxVal = fgets($handle);

        // Leer datos de píxeles y verificar si R=G=B para todos
        $sampleSize = 10000; // Revisar primeros 10000 píxeles
        $colorPixels = 0;
        
        for ($i = 0; $i < $sampleSize; $i++) {
            $rgb = fread($handle, 3);
            if (strlen($rgb) < 3) break;
            
            $r = ord($rgb[0]);
            $g = ord($rgb[1]);
            $b = ord($rgb[2]);
            
            // Si R, G y B no son iguales (con tolerancia), hay color
            $tolerance = 5;
            if (abs($r - $g) > $tolerance || abs($r - $b) > $tolerance || abs($g - $b) > $tolerance) {
                $colorPixels++;
            }
        }

        fclose($handle);

        // Si más del 1% de los píxeles tienen color, el documento tiene color
        return ($colorPixels > ($sampleSize * 0.01));
    }

    /**
     * Verificar la resolución DPI de las imágenes en el PDF
     * Método mejorado para detección precisa
     */
    protected function checkDpi(string $path): array
    {
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            return [
                'is_valid' => false,
                'detail'   => 'No se pudo verificar (Ghostscript no disponible)',
            ];
        }

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        // Primero intentar con pdfimages (de poppler-utils) si está disponible
        $pdfimagesResult = $this->checkDpiWithPdfimages($path);
        if ($pdfimagesResult !== null) {
            return $pdfimagesResult;
        }

        // Si no hay pdfimages, usar método con Ghostscript
        return $this->checkDpiWithGhostscript($path, $gsPath, $env);
    }

    /**
     * Buscar pdfimages en PATH o en ruta específica (multiplataforma)
     */
    protected function findPdfimages(): ?string
    {
        // Primero verificar si está configurado en .env
        $configPath = $this->getConfiguredPath('pdfimages');
        if ($configPath) {
            return $configPath;
        }
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Windows: buscar en rutas conocidas
            $windowsPaths = [
                'C:\\Poppler\\Release-25.12.0-0\\poppler-25.12.0\\Library\\bin\\pdfimages.exe',
                'C:\\Poppler\\Library\\bin\\pdfimages.exe',
                'C:\\Program Files\\poppler\\bin\\pdfimages.exe',
            ];
            
            foreach ($windowsPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            
            // Buscar con glob
            $popplerFolders = glob('C:\\Poppler\\Release-*\\poppler-*\\Library\\bin\\pdfimages.exe');
            if ($popplerFolders) {
                rsort($popplerFolders, SORT_NATURAL);
                return $popplerFolders[0];
            }
        } else {
            // Linux/Unix - Primero intentar ejecutar directamente
            $process = Process::fromShellCommandline('pdfimages -v 2>&1');
            $process->run();
            $output = $process->getOutput() . $process->getErrorOutput();
            if (str_contains($output, 'pdfimages') || str_contains($output, 'poppler')) {
                return 'pdfimages';
            }
            
            // Rutas comunes de Linux
            $linuxPaths = [
                '/usr/bin/pdfimages',
                '/usr/local/bin/pdfimages',
                '/opt/local/bin/pdfimages',
                '/snap/bin/pdfimages',
            ];
            
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
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
     * Verificar DPI usando pdfimages (validación estricta VUCEM)
     * Regla: TODAS las imágenes deben tener EXACTAMENTE 300 DPI
     * Si cualquier imagen ≠ 300 DPI → documento NO válido
     */
    protected function checkDpiWithPdfimages(string $path): ?array
    {
        $pdfimages = $this->findPdfimages();
        if (!$pdfimages) {
            return null; // pdfimages no disponible
        }

        // Ejecutar pdfimages -list para obtener TODAS las imágenes embebidas
        $process = new Process([$pdfimages, '-list', $path]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        
        $images = [];
        $pages = [];
        $hasImages = false;
        $isValid = true;
        $detailLines = [];
        $invalidImages = [];

        foreach ($lines as $line) {
            // Formato pdfimages -list:
            // page  num  type  width height color comp bpc  enc interp  object ID x-ppi y-ppi   size ratio
            // Ejemplo: 1    0 image   2550  3300  gray    1   8 jpeg   no      10  0   300   300 1200K  15%
            
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                $hasImages = true;
                
                $pageNum = intval($matches[1]);
                $imgNum = intval($matches[2]);
                $imgType = $matches[3];
                $imgWidth = intval($matches[4]);
                $imgHeight = intval($matches[5]);
                $colorSpace = $matches[6];
                $xPpi = intval($matches[13]);
                $yPpi = intval($matches[14]);
                
                // Calcular DPI promedio de esta imagen
                $dpi = intval(round(($xPpi + $yPpi) / 2));
                
                // Validación ESTRICTA: debe ser EXACTAMENTE 300 DPI
                $imageValid = ($dpi === 300);
                
                if (!$imageValid) {
                    $isValid = false;
                    $invalidImages[] = [
                        'page' => $pageNum,
                        'image' => $imgNum,
                        'dpi' => $dpi,
                    ];
                }
                
                // Guardar información detallada de cada imagen
                $images[] = [
                    'page' => $pageNum,
                    'image_num' => $imgNum,
                    'type' => $imgType,
                    'width' => $imgWidth,
                    'height' => $imgHeight,
                    'color' => $colorSpace,
                    'x_ppi' => $xPpi,
                    'y_ppi' => $yPpi,
                    'dpi' => $dpi,
                    'valid' => $imageValid,
                ];
                
                // Agrupar por página para calcular min/max DPI por página
                if (!isset($pages[$pageNum])) {
                    $pages[$pageNum] = [
                        'min_dpi' => $dpi,
                        'max_dpi' => $dpi,
                        'images' => [],
                        'valid' => true,
                    ];
                }
                
                if ($dpi < $pages[$pageNum]['min_dpi']) {
                    $pages[$pageNum]['min_dpi'] = $dpi;
                }
                if ($dpi > $pages[$pageNum]['max_dpi']) {
                    $pages[$pageNum]['max_dpi'] = $dpi;
                }
                if (!$imageValid) {
                    $pages[$pageNum]['valid'] = false;
                }
                $pages[$pageNum]['images'][] = $dpi;
            }
        }

        // Si no hay imágenes, el documento es válido (solo texto/vectores)
        if (!$hasImages) {
            return [
                'is_valid' => true,
                'detail'   => 'Documento sin imágenes rasterizadas (solo texto/vectores) ✓',
                'status'   => 'ok',
                'pages'    => [],
                'images'   => [],
            ];
        }

        // Construir mensaje de detalle estilo VUCEM
        ksort($pages); // Ordenar por número de página
        foreach ($pages as $pageNum => $pageData) {
            $pageDpi = $pageData['min_dpi'];
            if ($pageData['min_dpi'] !== $pageData['max_dpi']) {
                $pageDpi = $pageData['min_dpi'] . '-' . $pageData['max_dpi'];
            }
            
            if ($pageData['valid']) {
                $detailLines[] = "Página {$pageNum} → {$pageDpi} DPI ✓";
            } else {
                $detailLines[] = "Página {$pageNum} → {$pageDpi} DPI (inválido)";
            }
        }

        $detail = implode("\n", $detailLines);

        // Si hay imágenes inválidas, agregar resumen al final
        if (!$isValid) {
            $invalidCount = count($invalidImages);
            $detail .= "\n\n⚠️ {$invalidCount} imagen(es) con DPI ≠ 300. Se requiere exactamente 300 DPI.";
        }

        return [
            'is_valid' => $isValid,
            'detail'   => $detail,
            'status'   => $isValid ? 'ok' : 'error',
            'pages'    => $pages,
            'images'   => $images,
        ];
    }

    /**
     * Obtener dimensiones de página de forma simple (para análisis de cobertura)
     */
    protected function getPageDimensionsSimple(string $path): ?array
    {
        $gsPath = $this->findGhostscript();
        if (!$gsPath) return null;

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        $process = new Process([
            $gsPath, '-q', '-dNODISPLAY', '-dNOSAFER', '-c',
            '(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin 1 pdfgetpage /MediaBox pget pop == quit',
        ], null, $env);
        $process->setTimeout(30);
        $process->run();

        $mediaBox = trim($process->getOutput());
        
        if (preg_match('/\[([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\]/', $mediaBox, $matches)) {
            $widthPt = abs(floatval($matches[3]) - floatval($matches[1]));
            $heightPt = abs(floatval($matches[4]) - floatval($matches[2]));
            return [
                'width_pt' => $widthPt,
                'height_pt' => $heightPt,
                'width_in' => $widthPt / 72,
                'height_in' => $heightPt / 72,
            ];
        }
        return null;
    }

    /**
     * Obtener número de páginas del PDF
     */
    protected function getPdfPageCount(string $path, string $gsPath, array $env): int
    {
        $process = new Process([
            $gsPath,
            '-q',
            '-dNODISPLAY',
            '-dNOSAFER',
            '-c',
            '(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin pdfpagecount = quit',
        ], null, $env);

        $process->setTimeout(30);
        $process->run();

        $output = trim($process->getOutput());
        return intval($output);
    }

    /**
     * Verificar DPI usando Ghostscript (método alternativo)
     * Regla VUCEM estricta: EXACTAMENTE 300 DPI
     */
    protected function checkDpiWithGhostscript(string $path, string $gsPath, array $env): array
    {
        // Método: Extraer información de imágenes embebidas usando PostScript
        $imageInfo = $this->extractEmbeddedImageInfo($path, $gsPath, $env);
        
        if (!empty($imageInfo['images'])) {
            $minDpi = $imageInfo['min_dpi'];
            $maxDpi = $imageInfo['max_dpi'];
            
            // Validación ESTRICTA: debe ser EXACTAMENTE 300 DPI
            $isValid = ($minDpi === 300 && $maxDpi === 300);
            
            if ($isValid) {
                return [
                    'is_valid' => true,
                    'detail'   => "Resolución: 300 DPI ✓",
                    'status'   => 'ok',
                    'pages'    => [],
                    'images'   => $imageInfo['images'],
                ];
            }
            
            // Si hay diferentes DPIs, mostrar el rango
            $dpiDisplay = ($minDpi === $maxDpi) ? "{$minDpi}" : "{$minDpi} - {$maxDpi}";
            
            return [
                'is_valid' => false,
                'detail'   => "Resolución: {$dpiDisplay} DPI (inválido)\n⚠️ Se requiere exactamente 300 DPI.",
                'status'   => 'error',
                'pages'    => [],
                'images'   => $imageInfo['images'],
            ];
        }
        
        // Si no se detectaron imágenes embebidas
        return [
            'is_valid' => true,
            'detail'   => 'Documento sin imágenes rasterizadas (solo texto/vectores) ✓',
            'status'   => 'ok',
            'pages'    => [],
            'images'   => [],
        ];
    }

    /**
     * Extraer información de imágenes embebidas del PDF
     * Obtiene dimensiones en píxeles y tamaño de visualización para calcular DPI real
     */
    protected function extractEmbeddedImageInfo(string $path, string $gsPath, array $env): array
    {
        $result = [
            'images' => [],
            'min_dpi' => PHP_INT_MAX,
            'max_dpi' => 0,
        ];

        // Obtener número de páginas
        $pageCount = $this->getPdfPageCount($path, $gsPath, $env);
        if ($pageCount <= 0) {
            return $result;
        }

        $pagesToCheck = min($pageCount, 10);
        
        for ($page = 1; $page <= $pagesToCheck; $page++) {
            // Obtener dimensiones de la página
            $pageInfo = $this->getPageDimensions($path, $page, $gsPath, $env);
            if (!$pageInfo) continue;

            // Script PostScript mejorado para extraer información completa de imágenes
            // incluyendo la matriz de transformación (CTM) para calcular el tamaño de visualización
            $psScript = '
(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin
' . $page . ' pdfgetpage
dup /Resources pget {
    /XObject pget {
        {
            exch pop
            dup type /dicttype eq {
                dup /Subtype pget {
                    /Image eq {
                        (===IMAGE===) =
                        dup /Width pget { (W:) print = } if
                        dup /Height pget { (H:) print = } if
                    } if
                } if
            } if
            pop
        } forall
    } if
} if
quit
            ';

            $process = new Process([
                $gsPath, '-q', '-dNODISPLAY', '-dNOSAFER', '-dBATCH', '-c', $psScript,
            ], null, $env);
            $process->setTimeout(60);
            $process->run();

            $output = $process->getOutput();
            
            // Parsear las imágenes encontradas
            $imageBlocks = explode('===IMAGE===', $output);
            
            foreach ($imageBlocks as $block) {
                $width = 0;
                $height = 0;
                
                if (preg_match('/W:\s*(\d+)/', $block, $m)) {
                    $width = intval($m[1]);
                }
                if (preg_match('/H:\s*(\d+)/', $block, $m)) {
                    $height = intval($m[1]);
                }
                
                if ($width > 50 && $height > 50) { // Ignorar imágenes muy pequeñas (iconos)
                    // Calcular DPI: La imagen se muestra en el tamaño de la página
                    // DPI = píxeles de la imagen / tamaño de visualización en pulgadas
                    // Para páginas escaneadas, la imagen típicamente ocupa toda la página
                    
                    $dpiX = $width / $pageInfo['width_in'];
                    $dpiY = $height / $pageInfo['height_in'];
                    
                    // Usar el promedio de ambas direcciones
                    $dpi = round(($dpiX + $dpiY) / 2);
                    
                    // Para documentos convertidos a 300 DPI con página carta (8.5x11"):
                    // Una imagen de 2550x3300 px en página de 612x792 pt (8.5x11") = 300 DPI
                    
                    $result['images'][] = [
                        'width' => $width,
                        'height' => $height,
                        'dpi' => $dpi,
                        'page' => $page,
                    ];
                    
                    if ($dpi < $result['min_dpi']) $result['min_dpi'] = $dpi;
                    if ($dpi > $result['max_dpi']) $result['max_dpi'] = $dpi;
                }
            }
        }

        if (empty($result['images'])) {
            $result['min_dpi'] = 0;
            $result['max_dpi'] = 0;
        }

        return $result;
    }

    /**
     * Obtener dimensiones de una página específica
     */
    protected function getPageDimensions(string $path, int $page, string $gsPath, array $env): ?array
    {
        $process = new Process([
            $gsPath, '-q', '-dNODISPLAY', '-dNOSAFER', '-c',
            '(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin ' . $page . ' pdfgetpage /MediaBox pget pop == quit',
        ], null, $env);
        $process->setTimeout(30);
        $process->run();

        $mediaBox = trim($process->getOutput());
        
        if (preg_match('/\[([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\]/', $mediaBox, $matches)) {
            $widthPt = abs(floatval($matches[3]) - floatval($matches[1]));
            $heightPt = abs(floatval($matches[4]) - floatval($matches[2]));
            return [
                'width_pt' => $widthPt,
                'height_pt' => $heightPt,
                'width_in' => $widthPt / 72,
                'height_in' => $heightPt / 72,
            ];
        }
        return null;
    }

    protected function checkEncryptionWithQpdf(string $path): array
    {
        // Buscar qpdf según el sistema operativo
        $qpdfPath = $this->findQpdf();
        
        if (!$qpdfPath) {
            // Intentar verificar con Ghostscript si qpdf no está disponible
            return $this->checkEncryptionWithGs($path);
        }
        
        $process = new Process([
            $qpdfPath,
            '--show-encryption',
            $path,
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            // Intentar verificar con Ghostscript si qpdf falla
            return $this->checkEncryptionWithGs($path);
        }

        $output = $process->getOutput();

        if (stripos($output, 'File is not encrypted') !== false) {
            return [
                'is_unencrypted' => true,
                'detail'         => 'El archivo no está encriptado.',
            ];
        }

        return [
            'is_unencrypted' => false,
            'detail'         => 'El archivo está encriptado o protegido.',
        ];
    }
    
    /**
     * Buscar qpdf en el sistema (multiplataforma)
     */
    protected function findQpdf(): ?string
    {
        // Primero verificar si está configurado en .env
        $configPath = $this->getConfiguredPath('qpdf');
        if ($configPath) {
            return $configPath;
        }
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            $windowsPaths = [
                'C:\\Program Files\\qpdf\\bin\\qpdf.exe',
                'C:\\qpdf\\bin\\qpdf.exe',
            ];
            
            foreach ($windowsPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            
            // Intentar en PATH
            $process = new Process(['qpdf', '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                return 'qpdf';
            }
        } else {
            // Linux: intentar ejecutar directamente
            $process = Process::fromShellCommandline('qpdf --version 2>/dev/null');
            $process->run();
            if ($process->isSuccessful()) {
                return 'qpdf';
            }
            
            // Rutas comunes
            $linuxPaths = ['/usr/bin/qpdf', '/usr/local/bin/qpdf'];
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        return null;
    }

    protected function checkEncryptionWithGs(string $path): array
    {
        // Intentar abrir el PDF con Ghostscript para ver si está encriptado
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            return [
                'is_unencrypted' => true,
                'detail'         => 'No se pudo verificar (qpdf/Ghostscript no disponibles). Se asume sin encriptar.',
            ];
        }

        $env = array_merge($_SERVER, $_ENV, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        $process = new Process([
            $gsPath,
            '-q',
            '-dNODISPLAY',
            '-dBATCH',
            '-dNOPAUSE',
            '-c',
            '(' . $path . ') (r) file runpdfbegin quit',
        ], null, $env);

        $process->run();

        // Si Ghostscript puede abrir el archivo, no está encriptado (o no tiene password de usuario)
        if ($process->isSuccessful()) {
            return [
                'is_unencrypted' => true,
                'detail'         => 'El archivo no está encriptado (verificado con Ghostscript).',
            ];
        }

        $errorOutput = $process->getErrorOutput();
        if (stripos($errorOutput, 'password') !== false || stripos($errorOutput, 'encrypt') !== false) {
            return [
                'is_unencrypted' => false,
                'detail'         => 'El archivo está encriptado o protegido con contraseña.',
            ];
        }

        return [
            'is_unencrypted' => true,
            'detail'         => 'El archivo parece no estar encriptado.',
        ];
    }

    protected function findGhostscript(): ?string
    {
        // Primero verificar si está configurado en .env
        $configPath = $this->getConfiguredPath('ghostscript');
        if ($configPath) {
            return $configPath;
        }
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Windows: buscar en Program Files
            $gsFolders = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
            if ($gsFolders) {
                rsort($gsFolders, SORT_NATURAL);
                foreach ($gsFolders as $path) {
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }
            
            // Intentar en PATH
            $process = new Process(['gswin64c', '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                return 'gswin64c';
            }
        } else {
            // Linux/Unix - Primero intentar ejecutar directamente 'gs'
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
}
