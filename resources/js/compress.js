// Compresor de PDF - JavaScript

document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const btnSelectFile = document.getElementById('btnSelectFile');
    const compressForm = document.getElementById('compressForm');
    const btnCompress = document.getElementById('btnCompress');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const compressionOptions = document.getElementById('compressionOptions');
    const result = document.getElementById('result');
    const resultContent = document.getElementById('resultContent');

    let selectedFile = null;

    // Open file explorer
    if (btnSelectFile) {
        btnSelectFile.addEventListener('click', function () {
            fileInput.click();
        });
    }

    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    dropZone.addEventListener('drop', function (e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    }, false);

    fileInput.addEventListener('change', function (e) {
        if (this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    function handleFile(file) {
        if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
            alert('❌ Solo se permiten archivos PDF.');
            return;
        }

        selectedFile = file;
        
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        fileName.textContent = file.name;
        fileSize.textContent = `Tamaño: ${sizeMB} MB`;
        
        fileInfo.style.display = 'block';
        compressionOptions.style.display = 'block';
        btnCompress.disabled = false;
        result.style.display = 'none';
    }

    // Handle form submission
    compressForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!selectedFile) {
            alert('❌ Por favor selecciona un archivo PDF');
            return;
        }

        const compressionLevel = document.getElementById('compressionLevel').value;

        try {
            btnCompress.disabled = true;
            btnCompress.textContent = 'Comprimiendo...';

            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('compressionLevel', compressionLevel);

            const response = await fetch('/compress-pdf', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const downloadName = response.headers.get('X-File-Name') || 'compressed.pdf';
                const outputSizeMB = response.headers.get('X-File-Size-MB');
                const inputSizeMB = response.headers.get('X-Input-Size-MB');
                const reductionPercent = response.headers.get('X-Reduction-Percent');
                const level = response.headers.get('X-Compression-Level');

                // Show result
                resultContent.innerHTML = `
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Archivo:</strong> ${downloadName}
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Tamaño original:</strong> ${inputSizeMB} MB
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Tamaño comprimido:</strong> ${outputSizeMB} MB
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Reducción:</strong> ${reductionPercent}%
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 15px;">
                        <strong>Nivel:</strong> ${level}
                    </p>
                `;
                result.style.display = 'block';

                // Auto download
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = downloadName;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                console.log('✅ PDF comprimido:', downloadName);
            } else {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Error en el servidor');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al comprimir el PDF: ' + error.message);
        } finally {
            btnCompress.disabled = false;
            btnCompress.textContent = 'Comprimir PDF';
        }
    });
});
