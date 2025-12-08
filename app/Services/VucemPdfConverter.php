<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use RuntimeException;

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
    protected bool $isWindows;

    public function __construct()
    {
        // Detectar sistema operativo una sola vez al inicializar
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Buscar herramientas según el SO
        $this->ghostscriptPath = $this->findGhostscript();
        $this->pdfimagesPath = $this->findPdfimages();
    }

    /**
     * Convierte un PDF al formato VUCEM (300 DPI exactos, escala de grises, PDF 1.4)
     */
    public function convertToVucem(string $inputPath, string $outputPath): void
    {
        if (!file_exists($inputPath)) {
            throw new RuntimeException("El archivo de entrada no existe: {$inputPath}");
        }

        if (!$this->ghostscriptPath) {
            throw new RuntimeException('Ghostscript no está disponible en el sistema.');
        }

        $tempDir = $this->createTempDirectory();

        try {
            // PASO 1: Rasterizar PDF a páginas individuales con pdfimage8
            $pagePattern = $tempDir . DIRECTORY_SEPARATOR . 'page_%d.pdf';
            
            $result = $this->executeGhostscript([
                '-sDEVICE=pdfimage8',
                '-r300',
                '-dCompatibilityLevel=1.4',
                '-sOutputFile=' . $pagePattern,
                $inputPath,
            ]);

            // Obtener los PDFs de páginas generados
            $pagePdfs = glob($tempDir . DIRECTORY_SEPARATOR . 'page_*.pdf');
            
            if (empty($pagePdfs)) {
                throw new RuntimeException('No se generaron páginas PDF. Error: ' . $result['error']);
            }

            // Ordenar por número de página
            usort($pagePdfs, function($a, $b) {
                preg_match('/page_(\d+)\.pdf$/', $a, $ma);
                preg_match('/page_(\d+)\.pdf$/', $b, $mb);
                return intval($ma[1] ?? 0) - intval($mb[1] ?? 0);
            });

            // PASO 2: Combinar todos los PDFs en uno solo con PDF 1.4
            $this->mergePdfs($pagePdfs, $outputPath, $tempDir);

            // Verificar que se creó el archivo
            if (!file_exists($outputPath) || filesize($outputPath) < 100) {
                throw new RuntimeException('No se pudo generar el archivo PDF de salida.');
            }

        } finally {
            $this->cleanupDirectory($tempDir);
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
     * Valida que el PDF resultante tenga 300 DPI
     */
    public function validateDpi(string $pdfPath): array
    {
        if (!$this->pdfimagesPath || !file_exists($pdfPath)) {
            return ['valid' => false, 'error' => 'No se puede validar'];
        }

        $process = new Process([$this->pdfimagesPath, '-list', $pdfPath]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['valid' => false, 'error' => 'Error al ejecutar pdfimages'];
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $images = [];
        $allValid = true;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s+\S+\s+\S+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/', $line, $m)) {
                $xPpi = intval($m[9]);
                $yPpi = intval($m[10]);
                $avgDpi = intval(round(($xPpi + $yPpi) / 2));
                
                $images[] = [
                    'page' => intval($m[1]),
                    'x_dpi' => $xPpi,
                    'y_dpi' => $yPpi,
                    'avg_dpi' => $avgDpi,
                    'valid' => ($avgDpi === 300),
                ];

                if ($avgDpi !== 300) {
                    $allValid = false;
                }
            }
        }

        return [
            'valid' => $allValid,
            'images' => $images,
            'count' => count($images),
        ];
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
