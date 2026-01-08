<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class VucemImageExtractor
{
    protected string $ghostscriptPath;

    public function __construct()
    {
        $this->ghostscriptPath = $this->findGhostscript();
    }

    /**
     * Extrae todas las imágenes del PDF como JPEGs a 300 DPI y las empaqueta en un ZIP
     */
    public function extractImagesToZip(string $inputPath, string $outputZipPath): array
    {
        // Aumentar tiempo de ejecución para PDFs grandes
        set_time_limit(1200); // 20 minutos
        
        $tempDir = storage_path('app/tmp/vucem_extract_' . uniqid());
        
        try {
            // Crear directorio temporal
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            Log::info('VucemImageExtractor: Iniciando extracción de imágenes', [
                'input' => basename($inputPath),
            ]);

            // Obtener número de páginas
            $pageCount = $this->getPageCount($inputPath);
            Log::info('VucemImageExtractor: Total de páginas detectadas', ['count' => $pageCount]);

            // Extraer cada página como imagen JPEG a 300 DPI
            $this->extractPagesAsImages($inputPath, $tempDir, $pageCount);

            // Contar imágenes generadas
            $imageFiles = glob($tempDir . '/*.jpg');
            $imageCount = count($imageFiles);
            
            Log::info('VucemImageExtractor: Imágenes extraídas', ['count' => $imageCount]);

            if ($imageCount === 0) {
                throw new \Exception('No se pudieron extraer imágenes del PDF');
            }

            // Crear archivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('No se pudo crear el archivo ZIP');
            }

            // Agregar todas las imágenes al ZIP
            foreach ($imageFiles as $imageFile) {
                $zip->addFile($imageFile, basename($imageFile));
            }

            $zip->close();

            $zipSizeMb = round(filesize($outputZipPath) / (1024 * 1024), 2);
            
            Log::info('VucemImageExtractor: ZIP creado exitosamente', [
                'zip_size_mb' => $zipSizeMb,
                'images_count' => $imageCount,
            ]);

            return [
                'success' => true,
                'zip_path' => $outputZipPath,
                'zip_size_mb' => $zipSizeMb,
                'images_count' => $imageCount,
            ];

        } finally {
            // Limpiar directorio temporal
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * Extrae cada página del PDF como una imagen JPEG a 300 DPI
     */
    protected function extractPagesAsImages(string $inputPath, string $outputDir, int $pageCount): void
    {
        Log::info('VucemImageExtractor: Extrayendo páginas como imágenes a 300 DPI...');

        $outputPattern = $outputDir . '/pagina_%03d_300dpi.jpg';

        $process = new Process([
            $this->ghostscriptPath,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sDEVICE=jpeg',
            '-r300',              // 300 DPI
            '-dJPEGQ=25',         // Calidad JPEG 25% (reducido para cumplir límite 10 MB)
            '-dGraphicsAlphaBits=4',
            '-dTextAlphaBits=4',
            '-sOutputFile=' . $outputPattern,
            $inputPath,
        ]);

        $process->setTimeout(1200); // 20 minutos para PDFs grandes
        $process->setEnv([
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('Error al extraer imágenes: ' . $process->getErrorOutput());
        }
    }

    /**
     * Obtiene el número de páginas del PDF
     */
    protected function getPageCount(string $pdfPath): int
    {
        $process = new Process([
            $this->ghostscriptPath,
            '-dQUIET',
            '-dNODISPLAY',
            '-dNOSAFER',
            '-dNOPAUSE',
            '-dBATCH',
            '-c',
            '(' . str_replace('\\', '/', $pdfPath) . ') (r) file runpdfbegin pdfpagecount = quit',
        ]);

        $process->setTimeout(30);
        $process->setEnv([
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('VucemImageExtractor: No se pudo obtener número de páginas', [
                'error' => $process->getErrorOutput(),
            ]);
            return 1; // Asumir al menos 1 página
        }

        return max(1, intval(trim($process->getOutput())));
    }

    /**
     * Encuentra Ghostscript en el sistema
     */
    protected function findGhostscript(): string
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            $windowsPaths = [
                'C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.03.1\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.03.0\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.02.0\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.01.2\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.01.1\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.01.0\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs10.00.0\\bin\\gswin64c.exe',
            ];
            
            foreach ($windowsPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Buscar con glob
            $gsFolders = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
            if ($gsFolders) {
                rsort($gsFolders, SORT_NATURAL);
                return $gsFolders[0];
            }
        } else {
            // Linux/macOS
            $linuxPaths = ['/usr/bin/gs', '/usr/local/bin/gs', '/opt/local/bin/gs'];
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            return 'gs'; // Asumir que está en PATH
        }

        throw new \Exception('Ghostscript no encontrado');
    }

    /**
     * Limpia un directorio y su contenido
     */
    protected function cleanupDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
