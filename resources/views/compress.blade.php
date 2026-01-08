<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprimir PDF - VUCEM Tools</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/upload.css', 'resources/js/compress.js'])
</head>

<body>
    <div class="animated-bg">
        <div class="particle" style="width: 4px; height: 4px; left: 10%; animation-delay: 0s;"></div>
        <div class="particle" style="width: 6px; height: 6px; left: 30%; animation-delay: 2s;"></div>
        <div class="particle" style="width: 3px; height: 3px; left: 50%; animation-delay: 4s;"></div>
        <div class="particle" style="width: 5px; height: 5px; left: 70%; animation-delay: 6s;"></div>
        <div class="particle" style="width: 4px; height: 4px; left: 90%; animation-delay: 8s;"></div>
    </div>

    <div class="container">
        <header class="header">
            <a href="{{ route('home') }}" class="back-link">← Volver al menú</a>
            <h1>🗜️ Comprimir PDF (300 DPI)</h1>
            <p>Reduce el tamaño de tus PDFs sin perder los 300 DPI requeridos por VUCEM</p>
        </header>

        <div class="upload-card">
            <form id="compressForm" enctype="multipart/form-data">
                @csrf

                <div class="drop-zone" id="dropZone">
                    <div class="upload-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 15L12 3M12 3L16 7M12 3L8 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19L22 17" stroke="white" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3>Arrastra tu archivo PDF aquí</h3>
                    <p>Solo archivos PDF que ya tengan 300 DPI</p>
                    <input type="file" id="fileInput" accept=".pdf,application/pdf" hidden>
                    <button type="button" class="btn-select" id="btnSelectFile">
                        Seleccionar PDF
                    </button>
                </div>

                <div id="fileInfo" style="display: none; margin: 20px 0; padding: 20px; background: rgba(255,255,255,0.95); border-radius: 12px; border: 2px solid #48bb78; box-shadow: 0 2px 8px rgba(72,187,120,0.2);">
                    <h4 style="color: #2d3748; margin-bottom: 10px; font-weight: 600;">📄 Archivo seleccionado:</h4>
                    <p id="fileName" style="color: #2d3748; margin-bottom: 5px; font-weight: 500;"></p>
                    <p id="fileSize" style="color: #4a5568; font-size: 14px;"></p>
                </div>

                <div id="compressionOptions" style="display: none; margin: 20px 0; padding: 25px; background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.92) 100%); border-radius: 16px; border: 2px solid #667eea; box-shadow: 0 4px 20px rgba(102,126,234,0.15);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">⚙️</div>
                        <h4 style="color: #2d3748; margin: 0; font-size: 18px; font-weight: 700;">Nivel de compresión</h4>
                    </div>
                    <select id="compressionLevel" name="compressionLevel" style="width: 100%; padding: 14px 18px; border-radius: 10px; border: 2px solid #667eea; background: white; color: #2d3748; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; outline: none;" onmouseover="this.style.borderColor='#5a67d8'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onmouseout="this.style.borderColor='#667eea'; this.style.boxShadow='none'">
                        <option value="screen">📱 Pantalla (72 DPI) - Máxima compresión</option>
                        <option value="ebook">📖 Ebook (150 DPI) - Compresión alta</option>
                        <option value="printer" selected>🖨️ Impresora (300 DPI) - Mantiene calidad ✓</option>
                        <option value="prepress">⭐ Preimpresión (300 DPI) - Calidad profesional ✓</option>
                    </select>
                    <div style="margin-top: 15px; padding: 14px 16px; background: linear-gradient(135deg, #ebf4ff 0%, #e0e7ff 100%); border-radius: 8px; border: 1px solid #c3dafe; border-left: 4px solid #667eea;">
                        <p style="color: #2c5282; font-size: 13px; margin: 0; line-height: 1.6; font-weight: 500;">
                            💡 <strong style="color: #1e40af;">Recomendación:</strong> Selecciona "Impresora" o "Preimpresión" para mantener 300 DPI requeridos por VUCEM
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-convert" id="btnCompress" disabled>
                    Comprimir PDF
                </button>
            </form>

            <div id="result" style="display: none; margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%); border-radius: 12px; border: 2px solid #48bb78; box-shadow: 0 2px 8px rgba(72,187,120,0.2);">
                <h4 style="color: #2d3748; margin-bottom: 10px; font-weight: 700;">✅ Compresión completada</h4>
                <div id="resultContent"></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>🗜️ Niveles de Compresión</h3>
                <ul>
                    <li><strong>Pantalla:</strong> Máxima compresión (72 DPI)</li>
                    <li><strong>Ebook:</strong> Alta compresión (150 DPI)</li>
                    <li><strong>Impresora:</strong> Mantiene 300 DPI ✓</li>
                    <li><strong>Preimpresión:</strong> Calidad máxima ✓</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>⚡ Características</h3>
                <ul>
                    <li>Sin rasterización completa</li>
                    <li>Mantiene estructura del PDF</li>
                    <li>Compresión de imágenes</li>
                    <li>Optimización de fuentes</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>ℹ️ Importante</h3>
                <ul>
                    <li>Solo para PDFs con 300 DPI</li>
                    <li>Verifica DPI antes de VUCEM</li>
                    <li>Descarga automática</li>
                    <li>Proceso seguro y rápido</li>
                </ul>
            </div>
        </div>
    </div>
</body>

</html>
