// Extractor de Imágenes VUCEM - JavaScript

document.addEventListener('DOMContentLoaded', function () {
    // Get elements
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const btnConvert = document.getElementById('btnConvert');
    const btnSelectFiles = document.getElementById('btnSelectFiles');
    const extractForm = document.getElementById('extractForm');

    // File management
    let selectedFile = null;
    let isProcessing = false;

    // Button to open file explorer
    if (btnSelectFiles) {
        btnSelectFiles.addEventListener('click', function () {
            fileInput.click();
        });
    }

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        dropZone.classList.add('drag-over');
    }

    function unhighlight(e) {
        dropZone.classList.remove('drag-over');
    }

    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    }

    // Handle selected files
    fileInput.addEventListener('change', function (e) {
        if (this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    function handleFile(file) {
        // Validate PDF only
        if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
            alert('❌ Solo se permiten archivos PDF.');
            return;
        }

        selectedFile = file;
        displayFile();
        
        // Habilitar el botón
        if (btnConvert) {
            btnConvert.disabled = false;
            console.log('Botón habilitado');
        }

        // Reset file input
        fileInput.value = '';
    }

    function displayFile() {
        if (!selectedFile) {
            fileList.classList.remove('active');
            fileList.innerHTML = '';
            return;
        }

        fileList.classList.add('active');

        let html = `
            <div class="file-item" data-id="current">
                <div class="file-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2V8H20" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="file-info">
                    <div class="file-name">${selectedFile.name}</div>
                    <div class="file-size">${formatFileSize(selectedFile.size)}</div>
                </div>
                <div class="file-actions">
                    <button type="button" class="file-remove" onclick="removeCurrentFile()">
                        Eliminar
                    </button>
                </div>
            </div>
        `;

        fileList.innerHTML = html;
    }

    // Remove file function
    window.removeCurrentFile = function() {
        selectedFile = null;
        fileList.innerHTML = '';
        fileList.classList.remove('active');
        btnConvert.disabled = true;
    };

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    // Submit form
    extractForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!selectedFile || isProcessing) return;

        isProcessing = true;
        btnConvert.disabled = true;
        btnConvert.textContent = '⏳ Extrayendo...';

        try {
            const formData = new FormData();
            formData.append('pdf', selectedFile);

            const response = await fetch('/extract-images', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (response.ok) {
                // Verificar si es un ZIP
                const contentType = response.headers.get('Content-Type');
                
                if (contentType && contentType.includes('application/zip')) {
                    // Descargar ZIP
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    
                    // Obtener nombre del archivo desde headers
                    const contentDisposition = response.headers.get('Content-Disposition');
                    let filename = 'imagenes_extraidas.zip';
                    if (contentDisposition) {
                        const filenameMatch = contentDisposition.match(/filename="?(.+)"?/);
                        if (filenameMatch) {
                            filename = filenameMatch[1];
                        }
                    }
                    
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    // Obtener información del ZIP
                    const zipSizeMb = response.headers.get('X-File-Size-MB') || 'N/A';
                    const imagesCount = response.headers.get('X-Images-Count') || 'N/A';

                    // Show success message
                    showNotification('success', `✅ Imágenes extraídas exitosamente\nArchivo ZIP: ${zipSizeMb} MB • ${imagesCount} imágenes`);
                    
                    // Reset form
                    selectedFile = null;
                    displayFile();
                    
                } else {
                    // Respuesta JSON (error)
                    const data = await response.json();
                    throw new Error(data.error || 'Error desconocido');
                }
            } else {
                const data = await response.json();
                throw new Error(data.error || 'Error al extraer imágenes');
            }

        } catch (error) {
            console.error('Error:', error);
            showNotification('error', '❌ ' + error.message);
        } finally {
            isProcessing = false;
            btnConvert.disabled = selectedFile === null;
            btnConvert.textContent = 'Extraer Imágenes a ZIP';
        }
    });

    function showNotification(type, message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: ${type === 'success' ? '#10b981' : '#ef4444'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
});
