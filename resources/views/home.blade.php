<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VUSEM Tools - Herramientas PDF</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/upload.css'])
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
            <h1>🔧 VUSEM Tools</h1>
            <p>Herramientas para preparar y validar tus documentos PDF según los requisitos de VUSEM</p>
        </header>

        <!-- Menu Cards -->
        <div class="menu-grid">
            <!-- Card Convertidor -->
            <a href="{{ route('convertidor') }}" class="menu-card">
                <div class="menu-card-icon convert-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2V8H20" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 15L12 12L15 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 12V18" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2>🎯 Convertir PDF</h2>
                <p>Convierte tus documentos PDF al formato VUCEM con 300 DPI exactos</p>
                <div class="menu-card-features">
                    <span>✓ 300 DPI EXACTOS</span>
                    <span>✓ PDF versión 1.4</span>
                    <span>✓ Escala de grises</span>
                    <span>✓ Validación automática</span>
                </div>
                <div class="menu-card-action">
                    Ir al Convertidor →
                </div>
            </a>

            <!-- Card Validador -->
            <a href="{{ route('validador') }}" class="menu-card">
                <div class="menu-card-icon validate-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 11L12 14L22 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2>Validar Documento</h2>
                <p>Verifica que tu PDF cumpla con las características requeridas por VUCEM</p>
                <div class="menu-card-features">
                    <span>✓ Tamaño < 3 MB</span>
                    <span>✓ Versión PDF 1.4</span>
                    <span>✓ Escala de grises</span>
                    <span>✓ 300 DPI exactos</span>
                    <span>✓ Sin encriptación</span>
                </div>
                <div class="menu-card-action">
                    Ir al Validador →
                </div>
            </a>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <h3>📋 Requisitos de VUSEM para documentos PDF</h3>
            <div class="requirements-grid">
                <div class="requirement-item">
                    <div class="requirement-icon">📏</div>
                    <div class="requirement-text">
                        <strong>Tamaño máximo</strong>
                        <span>3 MB por archivo</span>
                    </div>
                </div>
                <div class="requirement-item">
                    <div class="requirement-icon">📄</div>
                    <div class="requirement-text">
                        <strong>Versión PDF</strong>
                        <span>1.4 (Acrobat 5.x)</span>
                    </div>
                </div>
                <div class="requirement-item">
                    <div class="requirement-icon">🎨</div>
                    <div class="requirement-text">
                        <strong>Color</strong>
                        <span>Escala de grises</span>
                    </div>
                </div>
                <div class="requirement-item">
                    <div class="requirement-icon">🔓</div>
                    <div class="requirement-text">
                        <strong>Seguridad</strong>
                        <span>Sin contraseña</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
