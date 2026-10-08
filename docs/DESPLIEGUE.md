# Despliegue de El SAS Piscícola

Guía paso a paso para publicar la app en un servidor Linux propio (Ubuntu 24.04 LTS), mantenerla actualizada sin perder datos y volver a una copia de seguridad.

> **Hosting compartido gratuito (InfinityFree y similares) no sirve.** La app necesita PostgreSQL, una terminal (SSH), un cron cada minuto y un proceso de cola, y esos planes solo ofrecen MySQL, sin SSH ni cron. Usa un VPS: con 1 vCPU y 2 GB de RAM alcanza para empezar (por ejemplo Hetzner, DigitalOcean, Vultr, Contabo u Oracle Cloud en su capa gratuita).

En los ejemplos:

- **Dominio:** `finca.ejemplo.com`.
- **Carpeta de la app:** `/var/www/sas-piscicola`.
- **Usuario del sistema que corre PHP:** `www-data`.
- **Usuario con el que entras por SSH (con `sudo`):** `deploy`.

Cámbialos por los tuyos.

---

## 1. Preparar el servidor

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql git unzip curl ufw \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-intl php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath
```

- **PHP 8.4:** si tu Ubuntu no trae `php8.4`, agrega antes el repositorio `ppa:ondrej/php` con `sudo add-apt-repository ppa:ondrej/php`.
- **Extensiones:** `intl` formatea dinero y números según el país de cada finca. `gd` la usan los PDF. `pgsql` conecta con la base.
- **Composer:** sigue las instrucciones de https://getcomposer.org/download/.
- **Node.js 22 LTS:** solo hace falta para compilar los estilos. Instálalo desde https://nodejs.org o con `nvm`.

**PHP para las fotos de especies** (originales de hasta 30 MB): en `/etc/php/8.4/fpm/php.ini` pon `upload_max_filesize = 32M`, `post_max_size = 40M` y `memory_limit = 512M`, y reinicia con `sudo systemctl restart php8.4-fpm`. Con 512 MB se aceptan fotos de hasta 50 megapíxeles; con 128 MB, unos 13.

Cortafuegos: deja abiertos solo SSH y la web. PostgreSQL **no** se abre a internet.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 2. Base de datos con dos roles (sin superusuario)

La app **no** debe conectarse como `postgres`. Usa dos roles:

- **`sas_piscicola_dueno`:** es dueño de las tablas y solo se usa para correr las migraciones.
- **`sas_piscicola_app`:** es el rol con el que se conecta la app. Solo puede leer y escribir datos, nunca cambiar la estructura, y no puede borrar ni modificar la bitácora.

```bash
sudo -u postgres createdb sas_piscicola_v2
```

Después de clonar la app (paso 3), genera el SQL de los roles y córrelo. Las contraseñas se pasan como variables de `psql`, así que no quedan escritas en ningún archivo:

```bash
cd /var/www/sas-piscicola
php artisan piscicola:usuario-base | sed -n '/^-- 1\./,/^-- 4\./p' > /tmp/roles.sql
sudo -u postgres psql -d sas_piscicola_v2 \
    -v clave_dueno='CLAVE-LARGA-DEL-DUENO' -v clave_app='CLAVE-LARGA-DE-LA-APP' \
    -f /tmp/roles.sql
rm /tmp/roles.sql
```

Genera cada clave con `openssl rand -base64 32` y guárdala en tu gestor de contraseñas.

> **Primera instalación:**
>
> - `piscicola:usuario-base` necesita conectarse a la base. Para generar el SQL, ponle una clave a `postgres` con `sudo -u postgres psql -c '\password postgres'` y pon temporalmente ese usuario en el `.env`. Luego cámbialo por `sas_piscicola_app` (paso 4).
> - La parte 4 del SQL, que quita a la app el permiso de cambiar la bitácora, se corre después de las migraciones, porque antes la tabla no existe (paso 5).

## 3. Descargar la app

```bash
sudo mkdir -p /var/www/sas-piscicola && sudo chown deploy:www-data /var/www/sas-piscicola
git clone https://github.com/MarcoXD23/sas-piscicolaV2.git /var/www/sas-piscicola
cd /var/www/sas-piscicola
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## 4. `.env` de producción

Edita `/var/www/sas-piscicola/.env`. Las líneas importantes:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://finca.ejemplo.com
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sas_piscicola_v2
DB_USERNAME=sas_piscicola_app
DB_PASSWORD=CLAVE-LARGA-DE-LA-APP

SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD="..."
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="El SAS Piscícola"

RESPALDOS_CARPETA=/var/respaldos/sas-piscicola
RESPALDOS_DIAS=14
```

- **`APP_DEBUG`:** debe quedar en `false`. Con `true`, cada error muestra el SQL y la configuración, y la app deja una alerta crítica en el log.
- **`SESSION_SECURE_COOKIE=true`:** obliga a que la cookie de sesión solo viaje por HTTPS. Es opcional: con HTTPS, directo o a través de un proxy de confianza, la app ya la marca segura sola. Dejarla en `true` es una protección más.
- **`TRUSTED_PROXIES`:** pon las IPs del proxy que está delante de la app. Con Nginx en el mismo servidor no hace falta. Con Cloudflare, pon sus rangos (https://www.cloudflare.com/ips/).
- **Correo:** es obligatorio en producción, porque por ahí llegan los enlaces de "¿Olvidaste tu contraseña?". Si un valor tiene espacios o símbolos, ponlo entre comillas.
- **Asistente:** configura `ASISTENTE_PROVEEDOR` y su clave (`ANTHROPIC_API_KEY` o `GEMINI_API_KEY`), como explica el README. Las claves nunca llegan al navegador.
- **Permisos del archivo:** `chmod 640 .env && sudo chown deploy:www-data .env`.

## 5. Migraciones (con el rol dueño)

Las migraciones cambian la estructura, y eso solo lo puede hacer el rol dueño. Pásale sus datos solo a este comando, sin guardarlos en el `.env`:

```bash
DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' php artisan migrate --force
```

Solo la primera vez, cuando ya existe la tabla de la bitácora, quítale a la app el permiso de cambiarla. Es la parte 4 del SQL del paso 2:

```bash
sudo -u postgres psql -d sas_piscicola_v2 -c 'REVOKE UPDATE, DELETE, TRUNCATE ON public.bitacora FROM sas_piscicola_app;'
sudo -u postgres psql -d sas_piscicola_v2 -c 'GRANT EXECUTE ON FUNCTION public.borrar_bitacora_finca(bigint) TO sas_piscicola_app;'
```

> **Nunca uses `migrate:fresh` ni `migrate:rollback` en producción:** borran datos.

Primer propietario y su finca. El comando pide la contraseña sin mostrarla:

```bash
php artisan piscicola:crear-propietario
```

Acceso al panel de plataforma (opcional, para el dueño del software):

```bash
php artisan piscicola:crear-admin-plataforma
```

## 6. Permisos de carpetas

PHP solo necesita escribir en `storage` y `bootstrap/cache`:

```bash
sudo chown -R deploy:www-data /var/www/sas-piscicola
sudo find /var/www/sas-piscicola -type f -exec chmod 644 {} \;
sudo find /var/www/sas-piscicola -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwX /var/www/sas-piscicola/storage /var/www/sas-piscicola/bootstrap/cache
chmod 640 /var/www/sas-piscicola/.env
```

## 7. Nginx

Crea `/etc/nginx/sites-available/sas-piscicola`. La raíz es `public/`: el resto del proyecto, incluidos el `.env` y `storage`, nunca queda expuesto.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name finca.ejemplo.com;
    root /var/www/sas-piscicola/public;

    index index.php;
    charset utf-8;
    client_max_body_size 40M;   # fotos de especies de hasta 30 MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # El service worker no se guarda en caché, para que las actualizaciones lleguen
    location = /sw.js {
        add_header Cache-Control "no-cache";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Archivos ocultos (.env, .git...), salvo .well-known para el certificado
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/sas-piscicola /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 8. HTTPS con Let's Encrypt

Apunta antes el dominio a la IP del servidor (registro A en tu DNS).

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d finca.ejemplo.com --redirect
```

Certbot agrega el certificado, redirige HTTP a HTTPS y renueva solo; puedes comprobarlo con `sudo certbot renew --dry-run`.

La app agrega por su cuenta HSTS y las cabeceras de seguridad cuando la petición llega por HTTPS.

## 9. Tareas programadas y cola

**Cron:** una sola línea corre todas las tareas programadas de Laravel, que son:

- Las alertas, cada hora.
- El respaldo, a diario a las 2:00, hora del servidor.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/sas-piscicola && php artisan schedule:run >> /dev/null 2>&1
```

**Cola:** el trabajador de la cola prepara las exportaciones de datos de las fincas (sin él, quedan «en preparación»). Crea `/etc/systemd/system/sas-piscicola-cola.service`:

```ini
[Unit]
Description=Cola de El SAS Piscícola
After=network.target postgresql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/sas-piscicola
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now sas-piscicola-cola
```

> **Regla para trabajos nuevos en cola:** deben llevar su `tenant_id` y fijar la finca al empezar. El filtro por finca falla cerrado (ver `AGENTS.md`).

## 10. Optimizar

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Con la configuración en caché, cada cambio al `.env` exige volver a correr `php artisan config:cache`.

**Prueba final:**

- Abre `https://finca.ejemplo.com` y entra como propietario.
- Pide un enlace de "¿Olvidaste tu contraseña?" y confirma que llega al correo.
- Revisa `storage/logs/laravel.log`.

---

## 11. Copias de seguridad

**Cómo funciona `php artisan piscicola:respaldar`:**

- Saca una copia comprimida con `pg_dump` y la verifica con `pg_restore --list`. Si la copia está dañada, la borra y falla.
- Guarda en `RESPALDOS_CARPETA`, que debe ser una ruta completa **fuera del proyecto**. Si la carpeta queda dentro, el comando se niega.
- Después de cada copia buena, borra solo sus propias copias (`sas_piscicola_*.dump`) con más de `RESPALDOS_DIAS` días; por defecto, 14.
- La contraseña de la base viaja en el entorno del proceso, nunca en la línea de comando ni en los logs.
- Corre a diario a las 2:00 si `RESPALDOS_CARPETA` tiene valor. Para cambiar la hora, usa `RESPALDOS_HORA`.

**Preparar la carpeta:**

```bash
sudo mkdir -p /var/respaldos/sas-piscicola
sudo chown www-data:www-data /var/respaldos/sas-piscicola
sudo chmod 700 /var/respaldos/sas-piscicola
sudo -u www-data php /var/www/sas-piscicola/artisan piscicola:respaldar   # primera copia, a mano
```

**Copia fuera del servidor:** una copia que solo está en el mismo servidor se pierde junto con él. Sincroniza la carpeta a otro lugar todos los días, por ejemplo con `rclone` hacia un almacenamiento externo, o con `rsync` hacia otro equipo:

```cron
30 2 * * * rsync -a --delete /var/respaldos/sas-piscicola/ respaldo@otro-servidor:/respaldos/sas-piscicola/
```

**Revisa una vez al mes que las copias sirven:** restaura la última en una base de prueba, como explica el paso 13.

**En tu equipo con Windows:**

- `RESPALDOS_CARPETA=C:\respaldos`.
- Si `pg_dump` no está en el PATH: `PG_BIN='C:\Program Files\PostgreSQL\17\bin'`, con comillas simples.
- Para programarlo, usa el Programador de tareas de Windows con `php C:\wamp64\www\sas-piscicola\artisan piscicola:respaldar`.

**Fotos de especies:** `piscicola:respaldar` copia solo la base. Las fotos viven en `storage/app/private/fotos` y se respaldan aparte, el mismo día y al mismo lugar externo, por ejemplo:

```cron
0 3 * * * tar -czf /var/respaldos/sas-piscicola/fotos_$(date +\%Y\%m\%d).tar.gz -C /var/www/sas-piscicola/storage/app/private fotos && find /var/respaldos/sas-piscicola -name 'fotos_*.tar.gz' -mtime +14 -delete
```

Para restaurar: descomprime el `.tar.gz` en `storage/app/private/` de la base restaurada (la base guarda las rutas; los archivos deben coincidir con la copia de la misma fecha).

**Fincas eliminadas y copias:** si restauras una copia anterior al borrado de una finca, esa finca reaparece. `piscicola:eliminar-fincas` (en el cron diario) la vuelve a borrar porque la lista de fincas eliminadas vive fuera de la base, en `storage/app/private/fincas-eliminadas.json`: no la borres y respáldala junto con las fotos.

**Nunca** subas una copia (`*.dump`) ni el `.env` a git; el `.gitignore` ya los excluye.

## 12. Actualizar sin perder datos

```bash
cd /var/www/sas-piscicola
php artisan down --retry=60                       # 1. modo mantenimiento
sudo -u www-data php artisan piscicola:respaldar  # 2. copia ANTES de tocar nada
git pull                                          # 3. código nuevo
composer install --no-dev --optimize-autoloader   # 4. dependencias
npm ci && npm run build                           # 5. estilos
DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' php artisan migrate --force   # 6. migraciones
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
sudo systemctl restart php8.4-fpm sas-piscicola-cola   # 7. recargar PHP y la cola
php artisan up                                    # 8. abrir de nuevo
```

- **Si falla una migración:** no la deshagas con `rollback`. Deja la app en mantenimiento y restaura la copia del paso 2, como explica el paso 13.
- **Si el código nuevo tiene un error:** vuelve a la versión anterior con `git checkout <commit-anterior>` y repite los pasos 4 a 8.
- **Si la actualización trae una tabla nueva:** los permisos de la app se aplican solos (`ALTER DEFAULT PRIVILEGES`), siempre que la migración la corra el rol dueño.

## 13. Restaurar desde una copia

`php artisan piscicola:restaurar` **nunca escribe sobre la base que usa la app**. Restaura en una base nueva o vacía. Tú la revisas y luego cambias la app a esa base. La base anterior queda intacta por si hace falta volver.

```bash
cd /var/www/sas-piscicola
php artisan down

# 1. Base nueva, del rol dueño
sudo -u postgres createdb -O sas_piscicola_dueno sas_piscicola_v2_restaurada

# 2. Restaurar la copia con el rol dueño
DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' \
    php artisan piscicola:restaurar /var/respaldos/sas-piscicola/sas_piscicola_20261007_020000.dump \
    --base=sas_piscicola_v2_restaurada

# 3. Permisos de la app en la base restaurada (el mismo SQL del paso 2, sin el bloque "1. Roles")
php artisan piscicola:usuario-base | sed -n '/^-- 2\./,/bitacora FROM/p' | sed 's/sas_piscicola_v2/sas_piscicola_v2_restaurada/g' > /tmp/permisos.sql
sudo -u postgres psql -d sas_piscicola_v2_restaurada -f /tmp/permisos.sql && rm /tmp/permisos.sql

# 4. Revisar los datos (por ejemplo, contar fincas y lagos)
sudo -u postgres psql -d sas_piscicola_v2_restaurada -c 'SELECT count(*) FROM tenants; SELECT count(*) FROM lagos;'

# 5. Cambiar la app a la base restaurada
#    En el .env: DB_DATABASE=sas_piscicola_v2_restaurada
php artisan config:cache
php artisan up
```

- **Si el destino ya existe:** `piscicola:restaurar` solo lo acepta si está vacío. Si no existe, intenta crearlo, y para eso el rol necesita permiso `CREATEDB`. Por eso, en el paso 1 se crea antes como `postgres`.
- **Si la restauración falla a medias:** no queda nada aplicado. Va en una sola transacción y se detiene en el primer error.
- **Restaurar en tu equipo para revisar una copia:** `php artisan piscicola:restaurar C:\respaldos\sas_piscicola_....dump --base=revision_copia`.

La prueba automática `RespaldoTest` hace este mismo recorrido de verdad:

- Llena la base de pruebas con datos de dos fincas, la respalda y la restaura en otra base.
- Comprueba que cada tabla tenga las mismas filas y el mismo contenido, comparando una huella md5 por tabla.
- Comprueba que la bitácora siga protegida.

## 14. Lista de chequeo antes de abrir a las fincas

- [ ] `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con `https://`.
- [ ] La app se conecta como `sas_piscicola_app`, no como `postgres`.
- [ ] HTTPS activo; en el navegador, la cookie de sesión aparece como `Secure`.
- [ ] El correo funciona (probado con "¿Olvidaste tu contraseña?").
- [ ] El cron (`schedule:run`) y la cola (`sas-piscicola-cola`) están activos.
- [ ] La primera copia está hecha y probada con `piscicola:restaurar`, y existe una copia fuera del servidor.
- [ ] El cortafuegos deja abiertos solo SSH, 80 y 443, y PostgreSQL no está expuesto.
- [ ] Revisaste `storage/logs/laravel.log` sin errores.
