# Despliegue de El SAS Piscícola

Guía paso a paso para publicar la app en un servidor Linux propio (Ubuntu 24.04 LTS), mantenerla actualizada sin perder datos y volver a una copia de seguridad.

> **Hosting compartido gratuito (InfinityFree y similares) no sirve.** La app necesita PostgreSQL, una terminal (SSH), un cron cada minuto y un proceso de cola, y esos planes solo ofrecen MySQL, sin SSH ni cron. Usa un VPS. La guía está probada contra un **VPS KVM 2 de Hostinger** (2 vCPU, 8 GB de RAM, Ubuntu 24.04, acceso root por SSH), que alcanza de sobra; sirve igual en cualquier VPS con Ubuntu 24.04.

En los ejemplos:

- **Dominio:** `finca.ejemplo.com`.
- **IP del servidor:** `203.0.113.10`.
- **Carpeta de la app:** `/var/www/sas-piscicola`.
- **Usuario del sistema que corre PHP:** `www-data`.
- **Usuario con el que entras por SSH (con `sudo`):** `deploy`.

Cámbialos por los tuyos.

**Antes del día del despliegue:**

- **Se despliega solo la rama `main`.** Hoy el código está en `claude/sas-pisicola-project-wo9vac`: fusiónala en `main` (con un pull request en GitHub) antes de empezar.
- **El repositorio es privado.** El servidor lo descarga con una *deploy key* de solo lectura (paso 2).

**Regla para todos los comandos:** `git`, `composer` y `npm` se corren como `deploy`. `php artisan` se corre siempre como `www-data` (`sudo -u www-data php artisan …`), el mismo usuario de PHP, del cron y de la cola. Así los logs y las cachés que crea un comando nunca quedan con un dueño que PHP no puede escribir.

---

## 0. Primer acceso y usuario `deploy`

En hPanel, al crear el VPS:

- Plantilla **«Plain OS → Ubuntu 24.04»**, sin panel. Las plantillas con panel (CloudPanel y similares) instalan su propio Nginx y PHP y chocan con esta guía.
- Agrega tu llave SSH pública (en tu equipo: `ssh-keygen -t ed25519`; la pública es el archivo `.pub`).
- Anota la IP del servidor.

Entra como root y crea el usuario `deploy` con la misma llave:

```bash
ssh root@203.0.113.10

adduser deploy                      # pide una contraseña: es la de sudo, guárdala en tu gestor
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
```

**Comprueba el acceso antes de cerrar nada.** Deja abierta la terminal de root y, en una **segunda terminal**:

```bash
ssh deploy@203.0.113.10     # debe entrar con la llave, sin pedir contraseña de SSH
sudo whoami                 # pide la contraseña de deploy y responde «root»
```

**Solo si las dos cosas funcionan**, desactiva el ingreso de root y las contraseñas por SSH. El archivo se llama `01-…` para que se lea antes que el `50-cloud-init.conf` de la imagen (en SSH gana el primer valor que se lee):

```bash
sudo tee /etc/ssh/sshd_config.d/01-endurecer.conf > /dev/null <<'EOF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
EOF
sudo sshd -t && sudo systemctl reload ssh
```

Vuelve a probar `ssh deploy@203.0.113.10` en una **tercera** terminal antes de cerrar las otras. Si algo falla, la terminal abierta te deja corregirlo. Si quedas por fuera, hPanel tiene una consola en el navegador (VPS → Terminal) que no usa SSH.

> `PermitRootLogin prohibit-password` también sirve si prefieres conservar el ingreso de root con llave; `no` es más estricto.

## 1. Preparar el servidor

Ubuntu 24.04 trae PHP 8.3 y PostgreSQL 16. La app usa **PHP 8.4** y **PostgreSQL 17** (la misma versión que en desarrollo, para que una copia sacada en tu equipo se pueda restaurar en el servidor). Los repositorios se agregan **antes** de instalar:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common ca-certificates curl git unzip acl ufw nginx postgresql-common

# PHP 8.4 (repositorio de Ondřej Surý)
sudo add-apt-repository -y ppa:ondrej/php

# PostgreSQL 17 (repositorio oficial de PostgreSQL; pulsa Enter cuando pregunte)
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh

sudo apt update
sudo apt install -y postgresql-17 \
    php8.4-fpm php8.4-cli php8.4-opcache php8.4-pgsql php8.4-intl php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath

php -v          # PHP 8.4.x
psql --version  # psql (PostgreSQL) 17.x
```

- **Extensiones:** `intl` formatea dinero y números según el país de cada finca. `gd` procesa las fotos y la usan los PDF. `pgsql` conecta con la base. `opcache` acelera PHP en producción.
- **Composer:** sigue las instrucciones de https://getcomposer.org/download/ y mueve `composer.phar` a `/usr/local/bin/composer`. **No** lo instales con `apt install composer`: arrastra PHP 8.3.
- **Node.js 22 LTS:** solo hace falta para compilar los estilos.

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

**PHP para las fotos de especies** (originales de hasta 30 MB): en `/etc/php/8.4/fpm/php.ini` pon `upload_max_filesize = 32M`, `post_max_size = 40M` y `memory_limit = 512M`, y reinicia con `sudo systemctl restart php8.4-fpm`. Con 512 MB se aceptan fotos de hasta 50 megapíxeles; con 128 MB, unos 13.

**Cortafuegos.** Hay dos: `ufw` dentro del servidor y el de Hostinger en hPanel (VPS → Seguridad → Cortafuegos). Los dos deben abrir **los mismos puertos: 22 (SSH), 80 y 443 (web)**. PostgreSQL (5432) **no** se abre a internet: solo escucha dentro del servidor.

```bash
sudo ufw allow OpenSSH        # 22
sudo ufw allow 'Nginx Full'   # 80 y 443
sudo ufw enable
sudo ufw status
```

- En hPanel, si activas el cortafuegos de Hostinger, crea reglas para aceptar TCP 22, 80 y 443 antes de activarlo.
- **Nunca cierres el 22** en ninguno de los dos sin haber comprobado antes que entras con tu llave (paso 0). Un cortafuegos sin el 22 te deja por fuera; solo quedaría la consola de hPanel.

## 2. Descargar la app

**Deploy key de solo lectura.** Como `deploy`, crea una llave solo para GitHub:

```bash
ssh-keygen -t ed25519 -C "deploy@sas-piscicola" -f ~/.ssh/github_sas -N ""
cat ~/.ssh/github_sas.pub
```

1. En GitHub, abre el repositorio → **Settings → Deploy keys → Add deploy key**.
2. Pega el contenido de `github_sas.pub`, ponle un nombre (por ejemplo, «Servidor Hostinger») y deja **sin marcar** «Allow write access». Así el servidor puede descargar el código pero nunca cambiarlo.
3. En el servidor, indica que GitHub use esa llave y prueba la conexión:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github.com
    IdentityFile ~/.ssh/github_sas
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
ssh -T git@github.com     # responde «Hi MarcoXD23/sas-piscicolaV2! You've successfully authenticated...»
```

**Clonar la rama `main`:**

```bash
sudo mkdir -p /var/www/sas-piscicola && sudo chown deploy:www-data /var/www/sas-piscicola
git clone --branch main git@github.com:MarcoXD23/sas-piscicolaV2.git /var/www/sas-piscicola
cd /var/www/sas-piscicola
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate   # único artisan que corre como deploy: .env todavía es suyo
```

## 3. Permisos de carpetas

PHP solo necesita escribir en `storage` y `bootstrap/cache`. Con ACL, lo que cree `www-data` lo puede escribir `deploy` y al revés (por ejemplo, `composer install` regenera archivos de `bootstrap/cache`):

```bash
cd /var/www/sas-piscicola
sudo chown -R deploy:www-data .
sudo find . -type f -not -path './node_modules/*' -exec chmod 644 {} +
sudo find . -type d -not -path './node_modules/*' -exec chmod 755 {} +
sudo setfacl -R  -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
chmod 640 .env
# Que Git no marque como modificados los archivos a los que solo les cambiaron los permisos
git config core.fileMode false
```

## 4. `.env` de producción

Edita `/var/www/sas-piscicola/.env`. Las líneas importantes:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://finca.ejemplo.com
LOG_LEVEL=warning
LOG_STACK=daily
LOG_DAILY_DAYS=14

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sas_piscicola_v2
DB_USERNAME=sas_piscicola_app
DB_PASSWORD=CLAVE-LARGA-DE-LA-APP

SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD="..."
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="El SAS Piscícola"

RESPALDOS_CARPETA=/var/respaldos/sas-piscicola
RESPALDOS_DIAS=14
RESPALDOS_HORA=07:00

SALUD_CORREO_AVISO=tu-correo@ejemplo.com

# Opcional: lagos máximos en el paquete de trabajo de Registro rápido (vacío = 60)
SIN_CONEXION_MAX_LAGOS=
```

- **`APP_DEBUG`:** debe quedar en `false`. Con `true`, cada error muestra el SQL y la configuración, y la app deja una alerta crítica en el log.
- **`SESSION_SECURE_COOKIE=true`:** la cookie de sesión solo viaja por HTTPS. Hasta terminar el paso 8 (HTTPS) no se puede iniciar sesión: es lo esperado.
- **`TRUSTED_PROXIES` vacío:** con Nginx y PHP-FPM en el mismo servidor no hay proxy delante. PHP recibe directamente la IP real del visitante y el HTTPS. Solo se llena si después pones un proxy delante, por ejemplo Cloudflare con la nube naranja: entonces van sus rangos (https://www.cloudflare.com/ips/).
- **`RESPALDOS_HORA=07:00`:** el programador usa la hora UTC de la app. 07:00 UTC son las 02:00 en Colombia (UTC−5), cuando nadie registra. Con el valor por defecto (02:00 UTC) correría a las 21:00, en pleno turno de noche.
- **Correo:** es obligatorio en producción, porque por ahí llegan los enlaces de "¿Olvidaste tu contraseña?" y los avisos de `/salud`. Si un valor tiene espacios o símbolos, ponlo entre comillas. Agrega en el DNS los registros SPF y DKIM que te dé el proveedor de correo, para no caer en spam.
- **Asistente:** configura `ASISTENTE_PROVEEDOR` y su clave (`ANTHROPIC_API_KEY` o `GEMINI_API_KEY`), como explica el README. Las claves nunca llegan al navegador.

## 5. Base de datos con dos roles (sin superusuario)

La app **no** debe conectarse como `postgres`. Usa dos roles:

- **`sas_piscicola_dueno`:** es dueño de las tablas y solo se usa para correr las migraciones.
- **`sas_piscicola_app`:** es el rol con el que se conecta la app. Solo puede leer y escribir datos, nunca cambiar la estructura, y no puede borrar ni modificar la bitácora.

Genera cada clave con `openssl rand -base64 32` y guárdala en tu gestor de contraseñas.

```bash
cd /var/www/sas-piscicola
sudo -u postgres createdb sas_piscicola_v2

# Clave temporal para postgres: piscicola:usuario-base necesita conectarse para generar el SQL
sudo -u postgres psql -c '\password postgres'

# Las credenciales de postgres se pasan solo a este comando: no se escriben en el .env.
# (El espacio al inicio evita que la línea quede en el historial de bash.)
 sudo -u www-data env DB_USERNAME=postgres DB_PASSWORD='CLAVE-TEMPORAL' \
    php artisan piscicola:usuario-base | sed -n '/^-- 1\./,/^-- 4\./p' > /tmp/roles.sql

# Las claves de los roles se pasan como variables de psql: no quedan en ningún archivo
 sudo -u postgres psql -d sas_piscicola_v2 \
    -v clave_dueno='CLAVE-LARGA-DEL-DUENO' -v clave_app='CLAVE-LARGA-DE-LA-APP' \
    -f /tmp/roles.sql
rm /tmp/roles.sql

# Quita la clave temporal: postgres vuelve a entrar solo desde el servidor con sudo
sudo -u postgres psql -c 'ALTER ROLE postgres PASSWORD NULL;'
```

La parte 4 del SQL, que quita a la app el permiso de cambiar la bitácora, se corre después de las migraciones, porque antes la tabla no existe (paso 6).

## 6. Migraciones (con el rol dueño)

Las migraciones cambian la estructura, y eso solo lo puede hacer el rol dueño. Sus datos se pasan solo a este comando, sin guardarlos en el `.env`. En la primera instalación todavía no hay configuración en caché, así que el comando sí usa ese rol (en las actualizaciones hay que limpiarla antes: paso 12).

```bash
 sudo -u www-data env DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' php artisan migrate --force
```

Solo la primera vez, cuando ya existe la tabla de la bitácora, quítale a la app el permiso de cambiarla. Es la parte 4 del SQL del paso 5:

```bash
sudo -u postgres psql -d sas_piscicola_v2 -c 'REVOKE UPDATE, DELETE, TRUNCATE ON public.bitacora FROM sas_piscicola_app;'
sudo -u postgres psql -d sas_piscicola_v2 -c 'GRANT EXECUTE ON FUNCTION public.borrar_bitacora_finca(bigint) TO sas_piscicola_app;'
```

> **Nunca uses `migrate:fresh` ni `migrate:rollback` en producción:** borran datos.

Primer propietario y su finca. El comando pide la contraseña sin mostrarla:

```bash
sudo -u www-data php artisan piscicola:crear-propietario
```

Acceso al panel de plataforma (opcional, para el dueño del software):

```bash
sudo -u www-data php artisan piscicola:crear-admin-plataforma
```

Entra enseguida a `/plataforma` (después del paso 8) y activa la verificación en dos pasos, que es obligatoria: hasta activarla, el panel no deja hacer nada más. Guarda los códigos de recuperación fuera del teléfono.

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

    # Estilos y scripts compilados: llevan huella en el nombre, se guardan un año
    location /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
        try_files $uri =404;
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
        include fastcgi_params;   # en Ubuntu incluye HTTPS: la app sabe sola que llegó por HTTPS
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

Antes, en el DNS del dominio (hPanel → Dominios → DNS si lo compraste en Hostinger):

- Registro **A** del dominio apuntando a la IP del servidor (y de `www`, si lo usarás).
- Registro **AAAA**: bórralo o apúntalo a la IPv6 del servidor. Si queda uno viejo (por ejemplo, el de la página de estacionamiento del dominio), Let's Encrypt lo prueba primero y el certificado falla.
- Espera a que el cambio se vea: `dig +short finca.ejemplo.com` debe responder la IP del servidor.

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d finca.ejemplo.com --redirect
```

Certbot agrega el certificado, redirige HTTP a HTTPS y renueva solo; puedes comprobarlo con `sudo certbot renew --dry-run`.

La app agrega por su cuenta HSTS y las cabeceras de seguridad cuando la petición llega por HTTPS.

## 9. Tareas programadas y cola

**Cron:** una sola línea corre todas las tareas programadas de Laravel, que son:

- Las alertas, cada hora.
- El respaldo, a diario a la hora de `RESPALDOS_HORA`, en UTC (07:00 UTC son las 02:00 en Colombia).
- El latido del programador, cada minuto, y la vigilancia del estado del sistema, cada 5 minutos (ver sección 15).

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
cd /var/www/sas-piscicola
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

Con la configuración en caché, cada cambio al `.env` exige volver a correr `config:cache`. Mientras la caché existe, las variables puestas delante de un comando (como `DB_USERNAME=…`) **no** se usan: por eso los pasos 12 y 13 la limpian antes de migrar o restaurar y la reconstruyen después.

**Prueba final:**

- Abre `https://finca.ejemplo.com` y entra como propietario.
- Pide un enlace de "¿Olvidaste tu contraseña?" y confirma que llega al correo.
- Revisa el archivo de errores del día en `storage/logs/` (`laravel-AAAA-MM-DD.log`).
- `sudo -u www-data php artisan piscicola:estado` muestra todo en «Bien» (el programador, después de un par de minutos con el cron activo).

---

## 11. Copias de seguridad

**Cómo funciona `php artisan piscicola:respaldar`:**

- Saca una copia comprimida con `pg_dump` y la verifica con `pg_restore --list`. Si la copia está dañada, la borra y falla.
- Guarda en `RESPALDOS_CARPETA`, que debe ser una ruta completa **fuera del proyecto**. Si la carpeta queda dentro, el comando se niega.
- Después de cada copia buena, borra solo sus propias copias (`sas_piscicola_*.dump`) con más de `RESPALDOS_DIAS` días; por defecto, 14.
- La contraseña de la base viaja en el entorno del proceso, nunca en la línea de comando ni en los logs.
- Corre a diario a la hora de `RESPALDOS_HORA` (en UTC) si `RESPALDOS_CARPETA` tiene valor.

**Preparar la carpeta:**

```bash
sudo mkdir -p /var/respaldos/sas-piscicola
sudo chown www-data:www-data /var/respaldos/sas-piscicola
sudo chmod 700 /var/respaldos/sas-piscicola
sudo -u www-data php /var/www/sas-piscicola/artisan piscicola:respaldar   # primera copia, a mano
```

**Archivos (fotos y lista de fincas eliminadas):** `piscicola:respaldar` copia solo la base. Las fotos (`storage/app/private/fotos`) y la lista de fincas eliminadas (`storage/app/private/fincas-eliminadas.json`) viven fuera de la base y se copian aparte, una hora después del respaldo de la base. Se deja fuera `exportaciones`: son paquetes temporales que vencen en 24 horas. En el cron de `www-data` (`sudo crontab -u www-data -e`):

```cron
0 8 * * * tar --exclude='private/exportaciones' -czf /var/respaldos/sas-piscicola/archivos_$(date +\%Y\%m\%d).tar.gz -C /var/www/sas-piscicola/storage/app private && find /var/respaldos/sas-piscicola -name 'archivos_*.tar.gz' -mtime +14 -delete
```

Para restaurar: descomprime el `.tar.gz` de la misma fecha que la copia de la base en `storage/app/` (la base guarda las rutas; los archivos deben coincidir).

**Fincas eliminadas y copias:** si restauras una copia anterior al borrado de una finca, esa finca reaparece. `piscicola:eliminar-fincas` (en el cron diario) la vuelve a borrar porque la lista de fincas eliminadas vive fuera de la base, en `storage/app/private/fincas-eliminadas.json`: no la borres, y restáurala junto con las fotos.

**Copia fuera del servidor.** Una copia que solo está en el mismo servidor se pierde junto con él. **Los respaldos y las instantáneas de Hostinger no cuentan como copia externa:** viven en la misma empresa y la misma cuenta. Sincroniza la carpeta todos los días a otro proveedor con `rclone`, por ejemplo Backblaze B2 (10 GB gratis) o Google Drive:

```bash
sudo apt install -y rclone
sudo rclone config
```

- **Backblaze B2:** crea un bucket **privado** y una *application key* limitada a ese bucket. En `rclone config`: `n` (nuevo), nombre `b2`, tipo `b2`, y pega el *keyID* y la *applicationKey*.
- **Google Drive:** en `rclone config`, tipo `drive`. El servidor no tiene navegador: cuando lo pida, corre `rclone authorize "drive"` en tu equipo y pega el resultado.
- **Cifrado (recomendado):** las copias tienen todos los datos de las fincas. Crea encima un remoto de tipo `crypt` (por ejemplo `b2-cifrado`, apuntando a `b2:mi-bucket/sas-piscicola`) y guarda sus contraseñas en tu gestor: sin ellas no se pueden leer las copias.

En el cron de root (`sudo crontab -e`), después de las dos copias del día:

```cron
30 8 * * * rclone sync /var/respaldos/sas-piscicola b2-cifrado: --log-file /var/log/rclone-respaldos.log --log-level NOTICE
```

`rclone sync` deja afuera lo mismo que hay en el servidor (las copias de los últimos 14 días). Revisa de vez en cuando `/var/log/rclone-respaldos.log`.

**Revisa una vez al mes que las copias sirven:** restaura la última en una base de prueba, como explica el paso 13.

**En tu equipo con Windows:**

- `RESPALDOS_CARPETA=C:\respaldos`.
- Si `pg_dump` no está en el PATH: `PG_BIN='C:\Program Files\PostgreSQL\17\bin'`, con comillas simples.
- Para programarlo, usa el Programador de tareas de Windows con `php C:\wamp64\www\sas-piscicola\artisan piscicola:respaldar`.

**Nunca** subas una copia (`*.dump`) ni el `.env` a git; el `.gitignore` ya los excluye.

## 12. Actualizar sin perder datos

El código nuevo debe estar fusionado en `main` antes de actualizar.

```bash
cd /var/www/sas-piscicola
sudo -u www-data php artisan down --retry=60          # 1. modo mantenimiento
sudo -u www-data php artisan piscicola:respaldar      # 2. copia ANTES de tocar nada
git pull origin main                                  # 3. código nuevo
composer install --no-dev --optimize-autoloader       # 4. dependencias
npm ci && npm run build                               # 5. estilos
sudo -u www-data php artisan config:clear             # 6. sin caché: así la línea siguiente usa el rol dueño
 sudo -u www-data env DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' php artisan migrate --force   # 7. migraciones
sudo -u www-data php artisan optimize:clear           # 8. cachés de nuevo, con el .env (rol de la app)
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo systemctl restart php8.4-fpm sas-piscicola-cola  # 9. recargar PHP y la cola
sudo -u www-data php artisan up                       # 10. abrir de nuevo
```

- **No te saltes el paso 8:** sin él, el servidor queda sin caché (más lento) y, si alguien corrió `config:cache` con las variables del dueño, la app quedaría conectada con el rol dueño.
- **Si falla una migración:** no la deshagas con `rollback`. Deja la app en mantenimiento, reconstruye la caché (paso 8) y restaura la copia del paso 2, como explica el paso 13.
- **Si el código nuevo tiene un error:** vuelve a la versión anterior con `git checkout <commit-anterior>` y repite los pasos 4 a 10.
- **Si la actualización trae una tabla nueva:** los permisos de la app se aplican solos (`ALTER DEFAULT PRIVILEGES`), siempre que la migración la corra el rol dueño.

## 13. Restaurar desde una copia

`php artisan piscicola:restaurar` **nunca escribe sobre la base que usa la app**. Restaura en una base nueva o vacía. Tú la revisas y luego cambias la app a esa base. La base anterior queda intacta por si hace falta volver.

```bash
cd /var/www/sas-piscicola
sudo -u www-data php artisan down

# 1. Base nueva, del rol dueño
sudo -u postgres createdb -O sas_piscicola_dueno sas_piscicola_v2_restaurada

# 2. Restaurar la copia con el rol dueño (sin caché, para que se use ese rol)
sudo -u www-data php artisan config:clear
 sudo -u www-data env DB_USERNAME=sas_piscicola_dueno DB_PASSWORD='CLAVE-LARGA-DEL-DUENO' \
    php artisan piscicola:restaurar /var/respaldos/sas-piscicola/sas_piscicola_20261007_070000.dump \
    --base=sas_piscicola_v2_restaurada

# 3. Permisos de la app en la base restaurada (el mismo SQL del paso 5, sin el bloque "1. Roles")
sudo -u www-data php artisan piscicola:usuario-base | sed -n '/^-- 2\./,/borrar_bitacora_finca/p' | sed 's/sas_piscicola_v2/sas_piscicola_v2_restaurada/g' > /tmp/permisos.sql
sudo -u postgres psql -d sas_piscicola_v2_restaurada -f /tmp/permisos.sql && rm /tmp/permisos.sql

# 4. Revisar los datos (por ejemplo, contar fincas y lagos)
sudo -u postgres psql -d sas_piscicola_v2_restaurada -c 'SELECT count(*) FROM tenants; SELECT count(*) FROM lagos;'

# 5. Cambiar la app a la base restaurada y reconstruir las cachés con el .env (rol de la app)
#    En el .env: DB_DATABASE=sas_piscicola_v2_restaurada
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo systemctl restart php8.4-fpm sas-piscicola-cola
sudo -u www-data php artisan up
```

- **Archivos:** restaura también el `archivos_….tar.gz` de la misma fecha (paso 11).
- **Si el destino ya existe:** `piscicola:restaurar` solo lo acepta si está vacío. Si no existe, intenta crearlo, y para eso el rol necesita permiso `CREATEDB`. Por eso, en el paso 1 se crea antes como `postgres`.
- **Si la restauración falla a medias:** no queda nada aplicado. Va en una sola transacción y se detiene en el primer error. Reconstruye la caché (paso 5 del bloque) antes de volver a abrir la app.
- **Restaurar en tu equipo para revisar una copia:** `php artisan piscicola:restaurar C:\respaldos\sas_piscicola_....dump --base=revision_copia`. Las dos versiones de PostgreSQL deben ser la misma (17); por eso el servidor usa la 17.

La prueba automática `RespaldoTest` hace este mismo recorrido de verdad:

- Llena la base de pruebas con datos de dos fincas, la respalda y la restaura en otra base.
- Comprueba que cada tabla tenga las mismas filas y el mismo contenido, comparando una huella md5 por tabla.
- Comprueba que la bitácora siga protegida.

## 14. Lista de chequeo antes de abrir a las fincas

- [ ] Se desplegó la rama `main` con una deploy key de **solo lectura**.
- [ ] Se entra por SSH como `deploy` con llave; root y las contraseñas por SSH están desactivados.
- [ ] El cortafuegos de Hostinger y `ufw` abren solo 22, 80 y 443, y PostgreSQL no está expuesto.
- [ ] `php -v` muestra 8.4 y `psql --version` muestra 17.
- [ ] `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con `https://`.
- [ ] La app se conecta como `sas_piscicola_app`, no como `postgres`, y `postgres` no tiene clave.
- [ ] HTTPS activo; en el navegador, la cookie de sesión aparece como `Secure`.
- [ ] El correo funciona (probado con "¿Olvidaste tu contraseña?") y el dominio tiene SPF y DKIM.
- [ ] El cron (`schedule:run`) y la cola (`sas-piscicola-cola`) están activos.
- [ ] La primera copia está hecha y probada con `piscicola:restaurar`, y `rclone` ya dejó una copia fuera de Hostinger.
- [ ] Revisaste el archivo de errores del día en `storage/logs/` sin errores.
- [ ] Hiciste en un teléfono real la prueba en modo avión de Registro rápido (README, «Sin conexión» → «Prueba a mano en un teléfono real»).
- [ ] Un servicio externo vigila `/salud` y `SALUD_CORREO_AVISO` (o un administrador de plataforma) recibe los avisos (ver sección 15).
- [ ] La verificación en dos pasos del panel de plataforma está activa.

## 15. Estado del sistema y monitoreo

**`/salud`** es pública y responde solo `ok` (200) o `falla` (503). No dice qué falló ni muestra datos de la app o de las fincas. Comprueba tres cosas:

- La base de datos responde.
- Se puede escribir en `storage`.
- El programador (cron) corrió en la última hora.

Tiene un límite de 60 consultas por minuto por IP.

**Vigilarla desde afuera (gratis).** Usa UptimeRobot o Better Stack, que tienen plan gratuito:

1. Crea un monitor de tipo **Keyword** (palabra clave) con la URL `https://finca.ejemplo.com/salud`.
2. Pon como palabra esperada `ok` y el intervalo en **5 minutos**.
3. Agrega tu correo (o el aviso a Telegram o la app del teléfono) como contacto de alerta.

Así te enteras aunque el servidor entero esté caído, cuando la app no puede avisar por sí misma.

**Aviso por correo desde la app.** `php artisan piscicola:vigilar` corre cada 5 minutos con el cron:

- Si algo empieza a fallar, envía **un** correo y no lo repite mientras siga fallando.
- Si algo distinto falla, envía otro.
- Cuando todo se recupera, envía un último correo avisando la recuperación.
- Lo recibe `SALUD_CORREO_AVISO`. Si está vacía, lo reciben los administradores de plataforma.
- Necesita el correo configurado (`MAIL_*`).

**Detalle para quien administra el servidor:**

```bash
sudo -u www-data php artisan piscicola:estado
```

Muestra:

- Cada comprobación.
- La versión de PostgreSQL.
- El último ciclo del programador.
- Los trabajos en cola y los fallidos.
- El espacio libre en disco.
- La última copia de seguridad en `RESPALDOS_CARPETA`.

Termina con código 1 si algo falla.

**Archivos de errores:** con `LOG_STACK=daily` se escribe un archivo por día, `storage/logs/laravel-AAAA-MM-DD.log`. Se borran los de más de `LOG_DAILY_DAYS` días (14 por defecto).

> **Opcional, no instalado:** un servicio de reporte de errores como Sentry muestra cada excepción con su contexto. Se agrega solo si se aprueba aparte.
