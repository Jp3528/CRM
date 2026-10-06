# Guía de Despliegue y Operación en Producción - NexusCRM

Esta guía detalla los requisitos, pasos y buenas prácticas para desplegar NexusCRM en entornos de producción o demostración empresarial utilizando **Docker** o una instalación nativa sobre Linux/PostgreSQL.

---

## 1. Requisitos del Entorno

- **PHP:** Versión `8.3` u `8.5` con extensiones requeridas:
  - `bcmath` (crítica para precisión en cálculos monetarios).
  - `pdo_pgsql` y `pgsql`.
  - `zip`, `gd`, `fileinfo`, `mbstring`, `openssl`, `pcntl`, `opcache`.
- **Base de Datos:** PostgreSQL 17 (o 16+).
- **Servidor Web:** Nginx sirviendo **únicamente** la carpeta `/public`.
- **Node.js:** Versión 22+ y npm 10+ (para compilación inicial de assets Vite).

---

## 2. Variables de Entorno Críticas (`.env`)

En producción, asegúrese de configurar las siguientes variables:

```ini
APP_NAME=NexusCRM
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.suempresa.com

# Seguridad de Clave de Aplicación (Generar una sola vez y conservar)
APP_KEY=base64:ESTA_CLAVE_SE_GENERA_CON_ARTISAN_KEY_GENERATE

# Base de Datos PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nexuscrm
DB_USERNAME=nexuscrm_app
DB_PASSWORD=Contraseña_Robusta_Generada

# Sesiones y Colas Persistentes
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
CACHE_STORE=database

# Almacenamiento y Correo
FILESYSTEM_DISK=local
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=su_usuario_smtp
MAIL_PASSWORD=su_password_smtp
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@suempresa.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> **¡IMPORTANTE!** `APP_DEBUG` debe estar siempre en `false` en entornos accesibles públicamente. NexusCRM cuenta con vistas personalizadas para errores 403, 404, 419 y 500 que no revelan trazas ni secretos.

---

## 3. Despliegue mediante Docker Compose

NexusCRM incluye una configuración completa y reproducible con contenedores multicapa en `docker-compose.yml`:

```sh
# 1. Preparar archivo de variables
cp .env.example .env
# Configurar contraseñas seguras en .env

# 2. Levantar la infraestructura (PostgreSQL 17, PHP-FPM, Nginx, Worker)
docker compose up -d --build

# 3. Generar la clave de aplicación en el contenedor
docker compose exec app php artisan key:generate

# 4. Ejecutar migraciones incrementales
docker compose exec app php artisan migrate --force

# 5. Opcional: inicializar dataset de demostración
docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoSeeder --force

# 6. Comprobar salud del servicio
curl -I http://localhost:8080/up
# Debe retornar HTTP/1.1 200 OK
```

---

## 4. Tareas en Segundo Plano y Programación (Scheduler)

### A. Worker de Colas
NexusCRM utiliza la tabla `jobs` de PostgreSQL para gestionar notificaciones, auditorías y exportaciones asíncronas. En servidores nativos, configure un demonio con `supervisord`:

```ini
[program:nexuscrm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nexuscrm/artisan queue:work --tries=3 --timeout=90 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/nexuscrm-worker.log
```

### B. Programador de Tareas (Cron)
Para ejecutar los recordatorios diarios de tareas (`crm:remind-tasks`) y limpiezas programadas, agregue la siguiente entrada al crontab del usuario del servidor:

```sh
* * * * * cd /var/www/nexuscrm && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Respaldo y Recuperación ante Desastres (Disaster Recovery)

NexusCRM cuenta con el comando de respaldo integrado `crm:backup`:

```sh
# Ejecutar y verificar respaldo del sistema (base de datos + archivos privados)
php artisan crm:backup --verify
```

### Reglas de Respaldo:
1. Los archivos se generan en `storage/app/backups/nexuscrm_backup_YYYYMMDD_HHMMSS.zip`.
2. Incluyen el `manifest.json` con conteos de registros y la carpeta de documentos privados `documents/`.
3. **Regla de oro:** Jamás ejecute `php artisan migrate:fresh` en un entorno con datos reales o de producción.
