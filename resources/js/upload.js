// Convertidor VUCEM - JavaScript

document.addEventListener('DOMContentLoaded', function () {
    // Get elements
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const btnConvert = document.getElementById('btnConvert');
    const btnSelectFiles = document.getElementById('btnSelectFiles');
    const uploadForm = document.getElementById('uploadForm');

    // Queue management
    let fileQueue = []; // Array of {file, status, progress, error}
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
        handleFiles(files);
    }

    // Handle selected files
    fileInput.addEventListener('change', function (e) {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        const filesArray = Array.from(files);

        // Validate PDFs only
        const invalidFiles = [];
        const validFiles = [];

        filesArray.forEach(file => {
            if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                validFiles.push(file);
            } else {
                invalidFiles.push(file.name);
            }
        });

        if (invalidFiles.length > 0) {
            alert(`❌ Solo se permiten archivos PDF.\n\nArchivos rechazados:\n${invalidFiles.join('\n')}`);
        }

        if (validFiles.length > 0) {
            // Add to queue with pending status
            validFiles.forEach(file => {
                fileQueue.push({
                    id: Date.now() + Math.random(),
                    file: file,
                    status: 'pending', // pending, processing, completed, error
                    progress: 0,
                    error: null,
                    downloadUrl: null
                });
            });

            displayQueue();
            updateConvertButton();
        }

        // Reset file input
        fileInput.value = '';
    }

    function displayQueue() {
        if (fileQueue.length === 0) {
            fileList.classList.remove('active');
            fileList.innerHTML = '';
            return;
        }

        fileList.classList.add('active');

        // Add queue header
        const stats = {
            total: fileQueue.length,
            pending: fileQueue.filter(f => f.status === 'pending').length,
            processing: fileQueue.filter(f => f.status === 'processing').length,
            completed: fileQueue.filter(f => f.status === 'completed').length,
            error: fileQueue.filter(f => f.status === 'error').length
        };

        let html = `
            <div class="queue-header">
                <div class="queue-title">📋 Cola de Conversión</div>
                <div class="queue-stats">
                    <div class="queue-stat">
                        <span>Total:</span>
                        <span class="queue-stat-value">${stats.total}</span>
                    </div>
                    ${stats.pending > 0 ? `<div class="queue-stat"><span>⏳ Pendientes:</span><span class="queue-stat-value">${stats.pending}</span></div>` : ''}
                    ${stats.processing > 0 ? `<div class="queue-stat"><span>🔄 Procesando:</span><span class="queue-stat-value">${stats.processing}</span></div>` : ''}
                    ${stats.completed > 0 ? `<div class="queue-stat"><span>✅ Completados:</span><span class="queue-stat-value">${stats.completed}</span></div>` : ''}
                    ${stats.error > 0 ? `<div class="queue-stat"><span>❌ Errores:</span><span class="queue-stat-value">${stats.error}</span></div>` : ''}
                </div>
            </div>
        `;

        fileQueue.forEach((item, index) => {
            const statusClass = `status-${item.status}`;
            const statusText = {
                'pending': '⏳ Pendiente',
                'processing': '🔄 Procesando',
                'completed': '✅ Completado',
                'error': '❌ Error'
            }[item.status];

            // Verificar si el archivo excede 3 MB
            const maxSize = 3 * 1024 * 1024;
            const exceedsSize = item.outputSize && item.outputSize > maxSize;

            html += `
                <div class="file-item ${exceedsSize ? 'file-warning' : ''}" data-id="${item.id}">
                    <div class="file-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 2V8H20" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="file-info">
                        <div class="file-name">${item.file.name}</div>
                        <div class="file-size">
                            Original: ${formatFileSize(item.file.size)}
                            ${item.outputSize ? ` → Convertido: ${formatFileSize(item.outputSize)}` : ''}
                        </div>
                        ${item.status === 'processing' ? `
                            <div class="file-progress">
                                <div class="file-progress-fill" style="width: ${item.progress}%"></div>
                            </div>
                        ` : ''}
                        ${item.error ? `<div class="file-error">${item.error}</div>` : ''}
                        ${exceedsSize ? `<div class="file-size-warning">⚠️ Excede el límite de 3 MB de VUCEM. Considera dividir el documento.</div>` : ''}
                    </div>
                    <div class="file-status">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="file-actions">
                        ${item.status === 'completed' && item.downloadBlob ? `
                            <button type="button" class="btn-download" onclick="downloadFile('${item.id}')">
                                ⬇️ Descargar
                            </button>
                        ` : ''}
                        ${item.status === 'error' ? `
                            <button type="button" class="btn-retry" onclick="retryFile('${item.id}')">
                                🔄 Reintentar
                            </button>
                        ` : ''}
                        ${item.status === 'pending' ? `
                            <button type="button" class="file-remove" onclick="removeFile('${item.id}')">
                                Eliminar
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        fileList.innerHTML = html;
    }

    window.removeFile = function (id) {
        fileQueue = fileQueue.filter(item => item.id !== parseFloat(id));
        displayQueue();
        updateConvertButton();
    };

    window.retryFile = function (id) {
        const item = fileQueue.find(f => f.id === parseFloat(id));
        if (item) {
            item.status = 'pending';
            item.progress = 0;
            item.error = null;
            displayQueue();
            updateConvertButton();
        }
    };

    window.downloadFile = function (id) {
        const item = fileQueue.find(f => f.id === parseFloat(id));
        if (item && item.downloadBlob) {
            // Create a temporary link and click it
            const url = URL.createObjectURL(item.downloadBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = item.downloadName || item.file.name.replace('.pdf', '_VUCEM.pdf');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    };

    function updateConvertButton() {
        const pendingFiles = fileQueue.filter(f => f.status === 'pending').length;
        if (pendingFiles > 0 && !isProcessing) {
            btnConvert.classList.add('active');
            btnConvert.disabled = false;
            btnConvert.innerHTML = `Convertir ${pendingFiles} archivo${pendingFiles > 1 ? 's' : ''} a formato VUCEM`;
        } else {
            btnConvert.classList.remove('active');
            btnConvert.disabled = true;
            if (isProcessing) {
                btnConvert.innerHTML = 'Procesando...<span class="spinner"></span>';
            } else {
                btnConvert.innerHTML = 'Convertir a formato VUCEM';
            }
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Handle form submission
    uploadForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const pendingFiles = fileQueue.filter(f => f.status === 'pending');
        if (pendingFiles.length === 0) {
            alert('No hay archivos pendientes para convertir.');
            return;
        }

        isProcessing = true;
        updateConvertButton();

        // Process files one by one (queue system)
        for (const item of pendingFiles) {
            await processFile(item);
        }

        isProcessing = false;
        updateConvertButton();

        // Show completion message
        const completed = fileQueue.filter(f => f.status === 'completed').length;
        const errors = fileQueue.filter(f => f.status === 'error').length;

        if (errors === 0) {
            alert(`✅ ¡Conversión completada!\n\n${completed} archivo${completed > 1 ? 's convertidos' : ' convertido'} exitosamente.`);
        } else {
            alert(`⚠️ Conversión finalizada\n\n✅ Exitosos: ${completed}\n❌ Fallidos: ${errors}\n\nPuedes reintentar los archivos con error.`);
        }
    });

    async function processFile(item) {
        try {
            // Update status to processing
            item.status = 'processing';
            item.progress = 0;
            displayQueue();

            // Simulate progress
            const progressInterval = setInterval(() => {
                if (item.progress < 90) {
                    item.progress += 10;
                    displayQueue();
                }
            }, 300);

            // Create FormData
            const formData = new FormData();
            formData.append('file', item.file);

            // Make API call to convert endpoint
            const response = await fetch('/convert', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            });

            clearInterval(progressInterval);
            item.progress = 100;

            if (response.ok) {
                // Get the blob from response
                const blob = await response.blob();
                const fileName = response.headers.get('X-File-Name') || item.file.name.replace('.pdf', '_VUCEM.pdf');
                const sizeWarning = response.headers.get('X-Size-Warning');
                
                item.status = 'completed';
                item.downloadBlob = blob;
                item.downloadName = fileName;
                item.downloadUrl = true; // Flag to show download button
                item.sizeWarning = sizeWarning; // Advertencia de tamaño si existe
                item.outputSize = blob.size; // Tamaño del archivo de salida
                displayQueue();
            } else {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Error en la respuesta del servidor');
            }
        } catch (error) {
            console.error('Error processing file:', error);
            item.status = 'error';
            item.error = error.message || 'Error desconocido';
            item.progress = 0;
            displayQueue();
        }

        // Small delay between files
        await new Promise(resolve => setTimeout(resolve, 500));
    }
});
