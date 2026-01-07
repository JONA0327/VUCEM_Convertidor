<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Convertidor VUCEM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #1e40af; }
        .status { 
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .tool-info {
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
        }
        button {
            background: #1e40af;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        button:hover {
            background: #1e3a8a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Diagnóstico del Sistema</h1>
        
        <div id="status">
            <p>Cargando información del sistema...</p>
        </div>

        <button onclick="testGhostscript()">Probar Ghostscript</button>
        <button onclick="testPdfimages()">Probar pdfimages</button>
        <button onclick="checkLogs()">Ver Últimos Logs</button>
        <button onclick="location.href='/'">Volver al Inicio</button>

        <div id="results"></div>
    </div>

    <script>
        // Cargar información al inicio
        window.onload = function() {
            fetch('/debug-tools')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('status').innerHTML = formatToolsInfo(data);
                })
                .catch(e => {
                    document.getElementById('status').innerHTML = 
                        '<div class="status error">Error: ' + e.message + '</div>';
                });
        };

        function formatToolsInfo(data) {
            let html = '<h2>Herramientas del Sistema</h2>';
            
            // Ghostscript
            if (data.tools_info.ghostscript.available) {
                html += '<div class="status success">✅ Ghostscript: Disponible<br>';
                html += '<small>Ruta: ' + data.tools_info.ghostscript.path + '</small></div>';
            } else {
                html += '<div class="status error">❌ Ghostscript: No disponible</div>';
            }

            // pdfimages
            if (data.tools_info.pdfimages.available) {
                html += '<div class="status success">✅ pdfimages: Disponible<br>';
                html += '<small>Ruta: ' + data.tools_info.pdfimages.path + '</small></div>';
            } else {
                html += '<div class="status error">❌ pdfimages: No disponible</div>';
            }

            // OS
            html += '<div class="tool-info"><strong>Sistema Operativo:</strong> ' + 
                    data.tools_info.os.type + ' (' + data.tools_info.os.php_os + ')</div>';

            return html;
        }

        function testGhostscript() {
            document.getElementById('results').innerHTML = '<p>Probando Ghostscript...</p>';
            
            fetch('/test-ghostscript')
                .then(r => r.json())
                .then(data => {
                    let html = '<div class="tool-info"><h3>Resultado de Ghostscript</h3>';
                    if (data.success) {
                        html += '<div class="status success">✅ Ghostscript funciona correctamente</div>';
                        html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    } else {
                        html += '<div class="status error">❌ Error al ejecutar Ghostscript</div>';
                        html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    }
                    html += '</div>';
                    document.getElementById('results').innerHTML = html;
                })
                .catch(e => {
                    document.getElementById('results').innerHTML = 
                        '<div class="status error">Error: ' + e.message + '</div>';
                });
        }

        function testPdfimages() {
            alert('Función en desarrollo');
        }

        function checkLogs() {
            alert('Para ver los logs, revisa: storage/logs/laravel.log');
        }
    </script>
</body>
</html>
