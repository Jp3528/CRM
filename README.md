# NexusCRM

CRM empresarial web construido con Laravel. Fase 1: base arquitectónica y de datos.

## Stack

- PHP 8.5 + Laravel 13
- PostgreSQL 17 (nativo, `127.0.0.1:5432`)
- Vite + Tailwind CSS 4 (infraestructura base, sin UI de negocio aún)
- PHPUnit (tests sobre SQLite en memoria)

## Requisitos

- PHP 8.5 con extensiones: `pdo_pgsql`, `pgsql`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `zip`
  (`pdo_sqlite`/`sqlite3` solo para ejecutar los tests en memoria)
- Composer 2, Node 22 + npm 10
- PostgreSQL 17 accesible en `127.0.0.1:5432`

## Instalación

```sh
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

## Configuración PostgreSQL

La app usa el usuario de aplicación `nexuscrm_app` (no el superusuario `postgres`):

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nexuscrm
DB_USERNAME=nexuscrm_app
DB_PASSWORD=[CONFIGURAR LOCALMENTE]
```

Con credenciales administrativas de PostgreSQL, crear una sola vez:

```sql
CREATE DATABASE nexuscrm;
CREATE USER nexuscrm_app WITH PASSWORD '<segura>';
GRANT ALL PRIVILEGES ON DATABASE nexuscrm TO nexuscrm_app;
-- dentro de nexuscrm:
GRANT ALL ON SCHEMA public TO nexuscrm_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO nexuscrm_app;
```

## Migraciones y seeders

```sh
php artisan migrate --seed        # base existente: conserva datos
php artisan migrate:fresh --seed  # SOLO en base nueva/de desarrollo
php artisan migrate:status        # verifica conexión real a PostgreSQL
```

Seeders (idempotentes con `firstOrCreate`/`updateOrCreate`):

- `RoleSeeder`: 7 roles (Superadministrador, Administrador, Gerente comercial, Supervisor, Vendedor, Soporte, Consulta)
- `PermissionSeeder`: permisos base `módulo.accion` (users, companies, contacts, leads, opportunities, tasks × view/create/update/delete)
- `PipelineSeeder`: pipeline `Ventas` con 7 etapas ordenadas (Ganada → `is_won`, Perdida → `is_lost`)
- `DatabaseSeeder`: ejecuta los anteriores + datos demo mínimos (1 equipo, 1 usuario, 3 empresas, contactos y leads)

## Tests

```sh
php artisan test
```

Cubre: arranque de Laravel, existencia de las 20 tablas de Fase 1, roles, pipeline Ventas, factories y relaciones principales.

## Frontend

```sh
npm run build   # compila Vite; sin pantallas de negocio (fuera de Fase 1)
```

## Alcance de Fase 1

Solo base: equipos, RBAC, empresas, contactos, leads, pipelines, oportunidades + historial, actividades, tareas, auditoría, settings y etiquetas polimórficas. Sin login UI, dashboard, Kanban ni módulos comerciales/administrativos/soporte (fases posteriores).
