<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convertidor VUCEM - Carga de Documentos</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/upload.css', 'resources/js/upload.js'])
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
            <h1>📄 Convertidor VUCEM</h1>
            <p>Convierte tus documentos PDF al formato permitido por VUCEM de manera rápida y sencilla</p>
        </header>

        <!-- Main Upload Card -->
        <div class="upload-card">
            <form id="uploadForm" enctype="multipart/form-data">
                @csrf

                <!-- Drop Zone -->
                <div class="drop-zone" id="dropZone">
                    <div class="upload-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 15L12 3M12 3L16 7M12 3L8 7" stroke="white" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19L22 17"
                                stroke="white" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3>Arrastra y suelta tus archivos PDF aquí</h3>
                    <p>Solo se aceptan archivos en formato PDF</p>
                    <input type="file" id="fileInput" multiple accept=".pdf,application/pdf" hidden>
                    <button type="button" class="btn-select" id="btnSelectFiles">
                        Seleccionar PDFs
                    </button>
                </div>

                <!-- File List -->
                <div class="file-list" id="fileList"></div>

                <!-- Convert Button -->
                <button type="submit" class="btn-convert" id="btnConvert" disabled>
                    Convertir a formato VUCEM
                </button>
            </form>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <h3>📄 Formato Soportado</h3>
                <ul>
                    <li>PDF (.pdf) únicamente</li>
                    <li>Múltiples archivos</li>
                    <li>Conversión a formato VUCEM</li>
                    <li>Validación automática</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>⚡ Cola de Conversión</h3>
                <ul>
                    <li>Procesamiento ordenado</li>
                    <li>Estado en tiempo real</li>
                    <li>Descarga individual</li>
                    <li>Conversión en segundo plano</li>
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