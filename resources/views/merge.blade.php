<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Combinar PDFs - VUCEM Tools</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/upload.css', 'resources/js/merge.js'])
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
            <h1>📑 Combinar PDFs (300 DPI)</h1>
            <p>Une múltiples PDFs en uno solo sin perder los 300 DPI requeridos por VUCEM</p>
        </header>

        <div class="upload-card">
            <form id="mergeForm" enctype="multipart/form-data">
                @csrf

                <div class="drop-zone" id="dropZone">
                    <div class="upload-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 15L12 3M12 3L16 7M12 3L8 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19L22 17" stroke="white" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3>Arrastra y suelta tus archivos PDF aquí</h3>
                    <p>Selecciona 2 o más PDFs para combinar</p>
                    <input type="file" id="fileInput" multiple accept=".pdf,application/pdf" hidden>
                    <button type="button" class="btn-select" id="btnSelectFiles">
                        Seleccionar PDFs
                    </button>
                </div>

                <div class="file-list" id="fileList" style="margin: 20px 0;"></div>

                <div id="mergeOptions" style="display: none; margin: 20px 0; padding: 25px; background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.92) 100%); border-radius: 16px; border: 2px solid #667eea; box-shadow: 0 4px 20px rgba(102,126,234,0.15);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 8px rgba(102,126,234,0.3);">📝</div>
                        <h4 style="color: #2d3748; margin: 0; font-size: 18px; font-weight: 700;">Nombre del archivo combinado</h4>
                    </div>
                    <input type="text" id="outputName" name="outputName" placeholder="documento_combinado" style="width: 100%; padding: 14px 18px; border-radius: 10px; border: 2px solid #667eea; background: white; color: #2d3748; font-size: 15px; font-weight: 500; outline: none; transition: all 0.3s ease; box-sizing: border-box;" onfocus="this.style.borderColor='#5a67d8'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#667eea'; this.style.boxShadow='none'">
                    <div style="margin-top: 12px; padding: 12px 14px; background: linear-gradient(135deg, #ebf4ff 0%, #e0e7ff 100%); border-radius: 8px; border: 1px solid #c3dafe; border-left: 4px solid #667eea;">
                        <p style="color: #2c5282; font-size: 13px; margin: 0; line-height: 1.5; font-weight: 500;">
                            💡 <strong style="color: #1e40af;">Nota:</strong> Se agregará automáticamente "_combinado.pdf" al final del nombre
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-convert" id="btnMerge" disabled>
                    Combinar PDFs
                </button>
            </form>

            <div id="result" style="display: none; margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%); border-radius: 12px; border: 2px solid #48bb78; box-shadow: 0 2px 8px rgba(72,187,120,0.2);">
                <h4 style="color: #2d3748; margin-bottom: 10px; font-weight: 700;">✅ PDFs combinados exitosamente</h4>
                <div id="resultContent"></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>📑 Combinar PDFs</h3>
                <ul>
                    <li>Múltiples archivos PDF</li>
                    <li>Orden personalizable (arrastrar)</li>
                    <li>Mantiene 300 DPI originales</li>
                    <li>Sin rasterización</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>⚡ Proceso Rápido</h3>
                <ul>
                    <li>Sin conversión de imágenes</li>
                    <li>Mantiene estructura PDF</li>
                    <li>Preserva texto editable</li>
                    <li>Descarga automática</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>ℹ️ Recomendaciones</h3>
                <ul>
                    <li>Usa PDFs con 300 DPI</li>
                    <li>Verifica tamaño final</li>
                    <li>Ordena antes de combinar</li>
                    <li>Máximo 50 archivos</li>
                </ul>
            </div>
        </div>
    </div>
</body>

</html>
