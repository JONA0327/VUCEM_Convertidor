// Combinador de PDFs - JavaScript

document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 Merge.js cargado correctamente');
    
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const btnSelectFiles = document.getElementById('btnSelectFiles');
    const mergeForm = document.getElementById('mergeForm');
    const btnMerge = document.getElementById('btnMerge');
    const fileList = document.getElementById('fileList');
    const mergeOptions = document.getElementById('mergeOptions');
    const result = document.getElementById('result');
    const resultContent = document.getElementById('resultContent');

    console.log('📍 Elementos encontrados:', {
        dropZone: !!dropZone,
        fileInput: !!fileInput,
        btnSelectFiles: !!btnSelectFiles,
        fileList: !!fileList
    });

    let selectedFiles = [];

    // Open file explorer
    if (btnSelectFiles) {
        btnSelectFiles.addEventListener('click', function () {
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
        handleFiles(files);
    }, false);

    fileInput.addEventListener('change', function (e) {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        const filesArray = Array.from(files);
        console.log('📁 Archivos recibidos:', filesArray.length);
        
        const invalidFiles = [];
        const validFiles = [];

        filesArray.forEach(file => {
            if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                validFiles.push(file);
                console.log('✅ PDF válido:', file.name);
            } else {
                invalidFiles.push(file.name);
                console.log('❌ Archivo inválido:', file.name);
            }
        });

        if (invalidFiles.length > 0) {
            alert(`❌ Solo se permiten archivos PDF.\n\nArchivos rechazados:\n${invalidFiles.join('\n')}`);
        }

        if (validFiles.length > 0) {
            selectedFiles = selectedFiles.concat(validFiles);
            console.log('📋 Total de archivos seleccionados:', selectedFiles.length);
            displayFiles();
        }
    }

    function displayFiles() {
        console.log('🎨 Mostrando archivos, total:', selectedFiles.length);
        
        if (selectedFiles.length === 0) {
            fileList.innerHTML = '';
            fileList.classList.remove('active');
            mergeOptions.style.display = 'none';
            btnMerge.disabled = true;
            return;
        }

        let html = '<div style="background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.92) 100%); border-radius: 12px; border: 2px solid #667eea; padding: 20px; box-shadow: 0 4px 20px rgba(102,126,234,0.15);">';
        html += `<h4 style="color: #2d3748; margin-bottom: 15px; font-weight: 700;">📄 Archivos seleccionados (${selectedFiles.length})</h4>`;
        html += `<p style="color: #718096; margin-bottom: 15px; font-size: 13px;">💡 Usa las flechas para reordenar o arrastra los archivos</p>`;
        
        selectedFiles.forEach((file, index) => {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const isFirst = index === 0;
            const isLast = index === selectedFiles.length - 1;
            
            html += `
                <div class="file-item" draggable="true" data-index="${index}" style="display: flex; align-items: center; gap: 12px; padding: 14px; background: white; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; cursor: move; transition: all 0.2s ease;">
                    <div style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white; font-weight: 700; font-size: 16px; box-shadow: 0 2px 6px rgba(102,126,234,0.4);">
                        ${index + 1}
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <button type="button" onclick="moveFileUp(${index})" ${isFirst ? 'disabled' : ''} 
                            style="background: ${isFirst ? '#e2e8f0' : 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)'}; 
                            color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: ${isFirst ? 'not-allowed' : 'pointer'}; 
                            font-size: 14px; font-weight: 700; transition: all 0.2s ease; opacity: ${isFirst ? '0.5' : '1'};"
                            ${!isFirst ? "onmouseover=\"this.style.transform='scale(1.1)'\" onmouseout=\"this.style.transform='scale(1)'\"" : ''}>
                            ▲
                        </button>
                        <button type="button" onclick="moveFileDown(${index})" ${isLast ? 'disabled' : ''} 
                            style="background: ${isLast ? '#e2e8f0' : 'linear-gradient(135deg, #4299e1 0%, #3182ce 100%)'}; 
                            color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: ${isLast ? 'not-allowed' : 'pointer'}; 
                            font-size: 14px; font-weight: 700; transition: all 0.2s ease; opacity: ${isLast ? '0.5' : '1'};"
                            ${!isLast ? "onmouseover=\"this.style.transform='scale(1.1)'\" onmouseout=\"this.style.transform='scale(1)'\"" : ''}>
                            ▼
                        </button>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 2px; color: #718096; padding: 0 8px;">
                        <div style="font-size: 18px;">☰</div>
                        <div style="font-size: 9px; font-weight: 600;">ARRASTRAR</div>
                    </div>
                    
                    <div style="flex: 1;">
                        <p style="color: #2d3748; margin: 0; font-weight: 600; font-size: 14px;">${file.name}</p>
                        <p style="color: #718096; margin: 0; font-size: 12px; margin-top: 2px;">📦 ${sizeMB} MB</p>
                    </div>
                    
                    <button type="button" onclick="removeFile(${index})" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 700; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(255,59,48,0.3);" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 8px rgba(255,59,48,0.4)'" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 6px rgba(255,59,48,0.3)'">
                        🗑️ Eliminar
                    </button>
                </div>
            `;
        });
        
        html += '</div>';
        fileList.innerHTML = html;
        fileList.classList.add('active');
        
        console.log('✅ HTML insertado en fileList');
        console.log('📏 Longitud del HTML:', html.length);
        console.log('🎯 fileList element:', fileList);
        
        // Agregar event listeners para drag & drop de reordenamiento
        initDragAndDrop();
        
        mergeOptions.style.display = 'block';
        btnMerge.disabled = selectedFiles.length < 2;
        result.style.display = 'none';
    }

    let draggedIndex = null;

    function initDragAndDrop() {
        const fileItems = document.querySelectorAll('.file-item');
        
        fileItems.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragenter', handleDragEnter);
            item.addEventListener('dragleave', handleDragLeave);
            item.addEventListener('dragend', handleDragEnd);
        });
    }

    function handleDragStart(e) {
        draggedIndex = parseInt(this.getAttribute('data-index'));
        this.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        this.style.borderColor = '#667eea';
        this.style.background = 'linear-gradient(135deg, #ebf4ff 0%, #e0e7ff 100%)';
        this.style.transform = 'scale(1.02)';
    }

    function handleDragLeave(e) {
        this.style.borderColor = '#e2e8f0';
        this.style.background = 'white';
        this.style.transform = 'scale(1)';
    }

    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        const dropIndex = parseInt(this.getAttribute('data-index'));
        
        if (draggedIndex !== null && draggedIndex !== dropIndex) {
            // Reordenar array
            const draggedFile = selectedFiles[draggedIndex];
            selectedFiles.splice(draggedIndex, 1);
            selectedFiles.splice(dropIndex, 0, draggedFile);
            
            displayFiles();
        }
        
        return false;
    }

    function handleDragEnd(e) {
        this.style.opacity = '1';
        this.style.borderColor = '#e2e8f0';
        this.style.background = 'white';
        this.style.transform = 'scale(1)';
        draggedIndex = null;
    }

    // Exponer función removeFile globalmente
    window.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        displayFiles();
    };

    // Funciones para mover archivos con botones
    window.moveFileUp = function(index) {
        if (index > 0) {
            const temp = selectedFiles[index];
            selectedFiles[index] = selectedFiles[index - 1];
            selectedFiles[index - 1] = temp;
            displayFiles();
        }
    };

    window.moveFileDown = function(index) {
        if (index < selectedFiles.length - 1) {
            const temp = selectedFiles[index];
            selectedFiles[index] = selectedFiles[index + 1];
            selectedFiles[index + 1] = temp;
            displayFiles();
        }
    };

    // Handle form submission
    mergeForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (selectedFiles.length < 2) {
            alert('❌ Selecciona al menos 2 archivos PDF para combinar');
            return;
        }

        if (selectedFiles.length > 50) {
            alert('❌ Máximo 50 archivos permitidos');
            return;
        }

        const outputName = document.getElementById('outputName').value || 'documento_combinado';

        try {
            btnMerge.disabled = true;
            btnMerge.textContent = 'Combinando PDFs...';

            const formData = new FormData();
            selectedFiles.forEach((file, index) => {
                formData.append(`files[${index}]`, file);
            });
            formData.append('outputName', outputName);

            const response = await fetch('/merge-pdfs', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const downloadName = response.headers.get('X-File-Name') || 'merged.pdf';
                const outputSizeMB = response.headers.get('X-File-Size-MB');
                const filesMerged = response.headers.get('X-Files-Merged');

                // Show result
                resultContent.innerHTML = `
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Archivo:</strong> ${downloadName}
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 10px;">
                        <strong>Archivos combinados:</strong> ${filesMerged}
                    </p>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 15px;">
                        <strong>Tamaño final:</strong> ${outputSizeMB} MB
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

                console.log('✅ PDFs combinados:', downloadName);
                
                // Limpiar formulario
                selectedFiles = [];
                displayFiles();
                document.getElementById('outputName').value = '';
            } else {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Error en el servidor');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al combinar los PDFs: ' + error.message);
        } finally {
            btnMerge.disabled = false;
            btnMerge.textContent = 'Combinar PDFs';
        }
    });
});
