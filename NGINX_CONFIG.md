# Configuración de Nginx para VUCEM Convertidor

## Ubicación del archivo de configuración
El archivo suele estar en:
- `/etc/nginx/sites-available/tu-sitio`
- `/etc/nginx/conf.d/tu-sitio.conf`

## Directivas necesarias

Agrega estas líneas dentro del bloque `server { ... }`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /ruta/a/tu/proyecto/public;

    index index.php index.html;

    # AUMENTAR TIMEOUTS PARA PROCESAMIENTO DE PDFs
    client_max_body_size 100M;
    client_body_timeout 600s;
    client_header_timeout 600s;
    send_timeout 600s;
    keepalive_timeout 600s;
    
    # Timeouts para proxy (si usas proxy_pass)
    proxy_connect_timeout 600s;
    proxy_send_timeout 600s;
    proxy_read_timeout 600s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;  # Ajusta la versión de PHP
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # TIMEOUTS PARA PHP-FPM
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
        fastcgi_connect_timeout 600s;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Configuración de PHP-FPM

Edita el archivo de configuración de PHP-FPM (usualmente en `/etc/php/8.2/fpm/pool.d/www.conf`):

```ini
; Timeouts
request_terminate_timeout = 600s

; Límites de memoria
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 600
php_admin_value[max_input_time] = 600
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
```

## Aplicar cambios

```bash
# Verificar configuración de Nginx
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx

# Reiniciar PHP-FPM (ajusta la versión)
sudo systemctl restart php8.2-fpm
```

## Verificación

Para confirmar que los cambios se aplicaron:

```bash
# Ver timeout configurado en PHP
php -i | grep max_execution_time

# Ver configuración de Nginx
nginx -V
```

## Notas importantes

1. **Versión de PHP**: Ajusta `php8.2-fpm` a tu versión instalada (puede ser `php8.1-fpm`, `php8.3-fpm`, etc.)
2. **Socket de PHP-FPM**: Verifica la ruta correcta del socket en tu sistema
3. **Permisos**: Los cambios requieren permisos de root/sudo
4. **Log de errores**: Si sigue fallando, revisa los logs:
   - Nginx: `/var/log/nginx/error.log`
   - PHP-FPM: `/var/log/php8.2-fpm.log`
   - Laravel: `storage/logs/laravel.log`
