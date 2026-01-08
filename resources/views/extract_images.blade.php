<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Extraer Imágenes - VUCEM Tools</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/upload.css', 'resources/js/extract_images.js'])
</head>

<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="particle" style="width: 4px; height: 4px; left: 10%; animation-delay: 0s;"></div>
        <div class="particle" style="width: 6px; height: 6px; left: 30%; animation-delay: 2s;"></div>
        <div class="particle" style="width: 3px; height: 3px; left: 50%; animation-delay: 4s;"></div>
        <div class="particle" style="width: 5px; height: 5px; left: 70%; animation-delay: 6s;"></div>
        <div class="particle" style="width: 4px; height: 4px; left: 90%; animation-delay: 8s;"></div>
    </div>

    <div class="container">
        <!-- Header -->
        <header class="header">
            <a href="{{ route('home') }}" class="back-link">← Volver al menú</a>
            <h1>📸 Extractor de Imágenes</h1>
            <p>Extrae todas las páginas como imágenes JPEG a 300 DPI y descárgalas en un archivo ZIP</p>
        </header>

        <!-- Main Upload Card -->
        <div class="upload-card">
            <form id="extractForm" enctype="multipart/form-data">
                @csrf

                <!-- Drop Zone -->
                <div class="drop-zone" id="dropZone">
                    <div class="upload-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke="white" stroke-width="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5" fill="white"/>
                            <path d="M21 15L16 10L5 21" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Arrastra y suelta tu archivo PDF aquí</h3>
                    <p>Solo se aceptan archivos en formato PDF</p>
                    <input type="file" id="fileInput" name="pdf" accept=".pdf,application/pdf" hidden>
                    <button type="button" class="btn-select" id="btnSelectFiles">
                        Seleccionar PDF
                    </button>
                </div>

                <!-- File List -->
                <div class="file-list" id="fileList"></div>

                <!-- Convert Button -->
                <button type="submit" class="btn-convert" id="btnConvert" disabled>
                    Extraer Imágenes a ZIP
                </button>
            </form>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <h3>📸 Extracción</h3>
                <ul>
                    <li>300 DPI por imagen</li>
                    <li>Formato JPEG (25%)</li>
                    <li>Todas las páginas</li>
                    <li>Proceso automático</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>📦 Empaquetado</h3>
                <ul>
                    <li>Archivo ZIP comprimido</li>
                    <li>Nombres descriptivos</li>
                    <li>Descarga instantánea</li>
                    <li>Listo para usar</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>🔒 Seguro y Privado</h3>
                <ul>
                    <li>Conexión encriptada</li>
                    <li>Archivos temporales</li>
                    <li>Sin almacenamiento</li>
                    <li>100% confidencial</li>
                </ul>
            </div>
        </div>
    </div>
</body>

</html>
