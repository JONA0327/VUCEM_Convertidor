<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validador VUSEM - Verificar Documento</title>

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
            <a href="{{ route('home') }}" class="back-link">← Volver al menú</a>
            <h1>✅ Validador VUSEM</h1>
            <p>Verifica que tu documento PDF cumpla con los requisitos de VUSEM</p>
        </header>

        @if(isset($checks) && isset($fileName))
            <!-- Results Section -->
            <div class="validation-results">
                <div class="result-header {{ $allOk ? 'result-success' : 'result-error' }}">
                    <div class="result-icon">
                        @if($allOk)
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M22 4L12 14.01L9 11.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M15 9L9 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9 9L15 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @endif
                    </div>
                    <div class="result-text">
                        <h2>{{ $allOk ? '¡Documento válido!' : 'Documento no válido' }}</h2>
                        <p>{{ $fileName }}</p>
                    </div>
                </div>

                <div class="checks-list">
                    @foreach($checks as $key => $check)
                        @php
                            $statusClass = $check['ok'] ? 'check-pass' : 'check-fail';
                            $statusIcon = $check['ok'] ? '✓' : '✗';
                            $iconClass = $check['ok'] ? 'pass' : 'fail';
                            
                            // Soporte para estado warning
                            if (isset($check['status']) && $check['status'] === 'warning') {
                                $statusClass = 'check-warning';
                                $statusIcon = '⚠';
                                $iconClass = 'warning';
                            }
                        @endphp
                        <div class="check-item {{ $statusClass }}">
                            <div class="check-status">
                                <span class="status-icon {{ $iconClass }}">{{ $statusIcon }}</span>
                            </div>
                            <div class="check-info">
                                <div class="check-label">{{ $check['label'] }}</div>
                                <div class="check-value">{{ $check['value'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(!$allOk)
                    <div class="recommendation-box">
                        <h4>💡 Recomendación</h4>
                        <p>Tu documento no cumple con todos los requisitos de VUSEM. Puedes usar nuestro <a href="{{ route('convertidor') }}">Convertidor</a> para transformar tu PDF al formato correcto.</p>
                        <a href="{{ route('convertidor') }}" class="btn-convert active">Ir al Convertidor</a>
                    </div>
                @endif

                <div class="validate-another">
                    <a href="{{ route('validador') }}" class="btn-secondary">Validar otro documento</a>
                </div>
            </div>
        @else
            <!-- Upload Form -->
            <div class="upload-card">
                <form action="{{ route('validador.validate') }}" method="POST" enctype="multipart/form-data" id="validateForm">
                    @csrf

                    <!-- Drop Zone -->
                    <div class="drop-zone" id="dropZone">
                        <div class="upload-icon validate-icon-bg">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 11L12 14L22 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>Arrastra y suelta tu archivo PDF aquí</h3>
                        <p>Se verificará que cumpla con los requisitos de VUSEM</p>
                        <input type="file" id="fileInput" name="pdf" accept=".pdf,application/pdf" hidden>
                        <button type="button" class="btn-select" id="btnSelectFile">
                            Seleccionar PDF
                        </button>
                    </div>

                    <!-- Selected File Display -->
                    <div class="selected-file" id="selectedFile" style="display: none;">
                        <div class="file-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M14 2V8H20" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="file-details">
                            <span class="file-name" id="fileName"></span>
                            <span class="file-size" id="fileSize"></span>
                        </div>
                        <button type="button" class="btn-remove" id="btnRemove">✕</button>
                    </div>

                    <!-- Validate Button -->
                    <button type="submit" class="btn-convert" id="btnValidate" disabled>
                        Validar Documento
                    </button>
                </form>
            </div>
        @endif

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <h3>📏 Tamaño</h3>
                <ul>
                    <li>Máximo 3 MB</li>
                    <li>Compresión recomendada</li>
                    <li>Imágenes optimizadas</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>📄 Formato</h3>
                <ul>
                    <li>PDF versión 1.4</li>
                    <li>Compatible Acrobat 5.x</li>
                    <li>Sin características avanzadas</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>🎨 Color</h3>
                <ul>
                    <li>Escala de grises</li>
                    <li>Sin colores RGB/CMYK</li>
                    <li>8 bits de profundidad</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>📐 Resolución</h3>
                <ul>
                    <li>300 DPI requerido</li>
                    <li>Imágenes nítidas</li>
                    <li>Sin pérdida de calidad</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const btnSelectFile = document.getElementById('btnSelectFile');
            const selectedFile = document.getElementById('selectedFile');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const btnRemove = document.getElementById('btnRemove');
            const btnValidate = document.getElementById('btnValidate');

            if (!dropZone) return;

            // Click to select file
            btnSelectFile.addEventListener('click', () => fileInput.click());
            dropZone.addEventListener('click', (e) => {
                if (e.target === dropZone || e.target.tagName === 'H3' || e.target.tagName === 'P') {
                    fileInput.click();
                }
            });

            // Drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
            });

            dropZone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'application/pdf') {
                    fileInput.files = files;
                    showSelectedFile(files[0]);
                }
            });

            // File input change
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    showSelectedFile(fileInput.files[0]);
                }
            });

            // Remove file
            btnRemove.addEventListener('click', () => {
                fileInput.value = '';
                selectedFile.style.display = 'none';
                dropZone.style.display = 'block';
                btnValidate.disabled = true;
                btnValidate.classList.remove('active');
            });

            function showSelectedFile(file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                selectedFile.style.display = 'flex';
                dropZone.style.display = 'none';
                btnValidate.disabled = false;
                btnValidate.classList.add('active');
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }
        });
    </script>
</body>

</html>
