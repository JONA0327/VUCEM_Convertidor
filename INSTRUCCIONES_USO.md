# 📋 INSTRUCCIONES DE USO - Convertidor VUCEM

## ⚠️ IMPORTANTE: LEE ESTO PRIMERO

### El problema que estabas teniendo:

Estabas **subiendo el PDF ORIGINAL a VUCEM**, no el convertido. Por eso VUCEM te rechazaba el archivo.

### Solución: Usa el archivo CONVERTIDO

1. **Sube tu PDF original** a http://localhost:8000/convertidor
2. **Espera** a que se convierta (aparecerá botón "Descargar")
3. **Descarga** el archivo que termina en `_VUCEM_300DPI.pdf`
4. **Sube a VUCEM** el archivo **_VUCEM_300DPI.pdf**, NO el original

## 🚀 Pasos Detallados

### 1. Iniciar el Servidor

```bash
# Compilar assets (solo la primera vez o después de cambios)
npm run build

# Iniciar servidor
php artisan serve
```

### 2. Abrir la Aplicación

Abre tu navegador en: `http://localhost:8000`

### 3. Ir al Convertidor

Clic en "Convertidor" o ve directamente a: `http://localhost:8000/convertidor`

### 4. Subir PDF Original

- Arrastra tu PDF original a la zona de carga
- O haz clic en "Seleccionar PDFs"
- Puedes subir múltiples archivos

### 5. Convertir

- Haz clic en "Convertir a formato VUCEM"
- Espera a que termine (verás progreso)

### 6. Descargar Archivo Convertido

- Cuando termine, aparecerá botón "⬇️ Descargar"
- El archivo se descargará automáticamente
- Busca el archivo que termina en `_VUCEM_300DPI.pdf`

### 7. Subir a VUCEM

**¡Este es el paso MÁS IMPORTANTE!**

❌ **NO subas** el archivo original (ej: "Anexo 1. Capturas de pantalla CATALOGO DE PRODUCTOS (1).pdf")

✅ **SÍ sube** el archivo convertido (ej: "Anexo 1. Capturas de pantalla CATALOGO DE PRODUCTOS (1)_VUCEM_300DPI.pdf")

## 🔍 Verificar que el Archivo es Correcto

El archivo convertido debe tener:
- ✅ Versión PDF 1.4 (no 1.7)
- ✅ Escala de grises (sin color)
- ✅ Imágenes a exactamente 300 DPI
- ✅ Tamaño menor a 3 MB (si es posible)

## 🛠️ Diagnóstico (Si hay problemas)

Visita: `http://localhost:8000/diagnostico`

Esto te mostrará:
- Si Ghostscript está instalado correctamente
- Si pdfimages está disponible
- Información del sistema

## 📝 Ver Logs (Para debugging)

Los logs están en: `storage/logs/laravel.log`

```bash
# Ver últimas líneas del log
tail -f storage/logs/laravel.log

# En Windows PowerShell:
Get-Content storage\logs\laravel.log -Tail 50 -Wait
```

## ❓ Preguntas Frecuentes

### Q: ¿Por qué VUCEM dice "sin imágenes rasterizadas"?
**A:** Estás subiendo el PDF original, no el convertido. Usa el archivo `_VUCEM_300DPI.pdf`

### Q: ¿Por qué dice "Versión PDF 1.7"?
**A:** Estás subiendo el PDF original. El convertido es versión 1.4.

### Q: ¿Por qué detecta color?
**A:** Estás subiendo el PDF original. El convertido está en escala de grises.

### Q: El archivo convertido es muy grande
**A:** Si el PDF tiene muchas páginas y ya está rasterizado a 300 DPI, puede quedar grande. Considera:
- Dividir el documento en varios PDFs más pequeños
- Reducir el número de páginas por documento

### Q: La conversión tarda mucho
**A:** Es normal. Rasterizar cada página a 300 DPI toma tiempo. Un PDF de 62 páginas puede tardar 2-5 minutos.

## 🎯 Checklist de Uso

- [ ] Compilé los assets con `npm run build`
- [ ] Inicié el servidor con `php artisan serve`
- [ ] Abrí http://localhost:8000
- [ ] Subí mi PDF ORIGINAL
- [ ] Esperé a que se convierta
- [ ] Descargué el archivo `_VUCEM_300DPI.pdf`
- [ ] Subí a VUCEM el archivo **CONVERTIDO** (no el original)
- [ ] VUCEM aceptó mi archivo ✅

## 📞 Soporte

Si después de seguir todos estos pasos VUCEM sigue rechazando el archivo:

1. Ve a `/diagnostico` y verifica que Ghostscript funciona
2. Revisa `storage/logs/laravel.log` para ver errores
3. Verifica que estás subiendo el archivo correcto (el que tiene `_VUCEM_300DPI.pdf` en el nombre)

---

**Versión:** 2.0 - Conversión mejorada con rasterización completa a 300 DPI exactos
