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

                <!-- Split Options -->
                <div class="split-options" style="margin: 20px 0; padding: 25px; background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.92) 100%); border-radius: 16px; border: 2px solid #667eea; box-shadow: 0 4px 20px rgba(102,126,234,0.15);">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 18px; padding: 18px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 12px; cursor: pointer; transition: all 0.3s ease; border: 2px solid #e2e8f0;" onclick="document.getElementById('splitEnabled').click()" onmouseover="this.style.borderColor='#667eea'; this.style.background='linear-gradient(135deg, #ebf4ff 0%, #e0e7ff 100%)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%)'">
                        <input type="checkbox" id="splitEnabled" name="splitEnabled" style="width: 24px; height: 24px; cursor: pointer; accent-color: #667eea;" onclick="event.stopPropagation();">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">📂</span>
                                <label for="splitEnabled" style="color: #2d3748; font-size: 17px; font-weight: 700; cursor: pointer; user-select: none; margin: 0;">
                                    Dividir PDF en partes
                                </label>
                            </div>
                            <p style="color: #4a5568; font-size: 13px; margin: 5px 0 0 32px; font-weight: 500;">Divide el documento en múltiples archivos más pequeños</p>
                        </div>
                    </div>
                    <div id="splitControls" style="display: none; padding: 20px; background: rgba(255,255,255,0.95); border-radius: 12px; margin-top: 15px; border-left: 4px solid #667eea; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <label for="numberOfParts" style="color: #2d3748; font-size: 15px; font-weight: 600; display: block; margin-bottom: 12px;">
                            📊 Número de partes:
                        </label>
                        <select id="numberOfParts" name="numberOfParts" style="padding: 12px 18px; border-radius: 10px; border: 2px solid #667eea; background: white; color: #2d3748; font-size: 15px; font-weight: 500; cursor: pointer; min-width: 180px; transition: all 0.3s ease; outline: none;" onmouseover="this.style.borderColor='#5a67d8'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onmouseout="this.style.borderColor='#667eea'; this.style.boxShadow='none'">
                            <option value="2">✂️ 2 partes</option>
                            <option value="3">✂️ 3 partes</option>
                            <option value="4">✂️ 4 partes</option>
                            <option value="5">✂️ 5 partes</option>
                            <option value="6">✂️ 6 partes</option>
                            <option value="7">✂️ 7 partes</option>
                            <option value="8">✂️ 8 partes</option>
                        </select>
                        <div style="margin-top: 15px; padding: 14px 16px; background: linear-gradient(135deg, #ebf4ff 0%, #e0e7ff 100%); border-radius: 8px; border: 1px solid #c3dafe;">
                            <p style="color: #2c5282; font-size: 13px; margin: 0; line-height: 1.6; font-weight: 500;">
                                💡 <strong style="color: #1e40af;">Ejemplo:</strong> Si divides en 2 partes, se descargarán como:<br>
                                <code style="background: rgba(102,126,234,0.15); padding: 3px 8px; border-radius: 4px; font-size: 12px; color: #4c51bf; font-weight: 600; border: 1px solid rgba(102,126,234,0.3);">nombre_parte1.pdf</code> y 
                                <code style="background: rgba(102,126,234,0.15); padding: 3px 8px; border-radius: 4px; font-size: 12px; color: #4c51bf; font-weight: 600; border: 1px solid rgba(102,126,234,0.3);">nombre_parte2.pdf</code>
                            </p>
                        </div>
                    </div>
                </div>

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