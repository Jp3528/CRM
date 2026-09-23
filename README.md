# NexusCRM

CRM empresarial web construido con Laravel. Fase 2: autenticación + RBAC operativo + estructura visual base.

## Stack

- PHP 8.5 + Laravel 13
- PostgreSQL 17 (nativo, `127.0.0.1:5432`)
- Blade + Tailwind CSS 4 + Alpine.js + Vite
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
- `DatabaseSeeder`: ejecuta los anteriores + datos demo mínimos (1 equipo, 1 usuario, 3 empresas, contactos y leads).
  El usuario demo `demo@nexuscrm.local` recibe el rol Superadministrador (idempotente, sin duplicados).
  Contraseña demo solo para desarrollo local: `password` (no reutilizar en producción).

## Fase 2 — Acceso y navegación base

Autenticación web (sin registro público):

- `GET/POST /login`, `POST /logout` (regeneración/invalidación de sesión, throttle, remember opcional).
- Recuperación lista a nivel arquitectura: `forgot-password`, `reset-password/{token}` (mailer `log` por defecto).
- Usuarios con `status != active` no pueden iniciar sesión; `EnsureActiveUser` los expulsa en la siguiente petición autenticada.

RBAC operativo (tablas existentes, sin paquetes):

- `User::hasRole/hasAnyRole/hasPermission/hasAnyPermission/isSuperAdmin/isActive/primaryRole`.
- Middleware `auth`, `active`, `role`, `permission` (alias en `bootstrap/app.php`).
- `Gate::before` en `AppServiceProvider`: cualquier habilidad que coincida con un permiso se autoriza vía RBAC;
  Superadministrador pasa siempre de forma centralizada (sin `if` dispersos).
- Ejemplo backend real: `GET /admin/users` exige `permission:users.view`.

Layout empresarial (`layouts/app`, `layouts/guest`, sidebar, topbar, breadcrumbs, menú usuario, responsive con Alpine):

- Sidebar CRM (Dashboard, Empresas, Contactos, Leads, Oportunidades, Tareas, Actividades)
  + Administración (Usuarios, Equipos, Roles y permisos, Configuración).
- Módulos futuros como placeholders controlados (`coming-soon`); sin CRUD falsos.
- Visibilidad por permisos en sidebar + autorización real en backend.
- Dashboard mínimo: saludo, rol, equipo, fecha/hora, estado y accesos permitidos.
- Perfil: ver nombre/email/equipo/roles; cambiar nombre/email validado y contraseña (actual + nueva + confirmación, hashing Laravel).
- Componentes Blade: button, input, label, input-error, card, badge, flash (success/error/warning/info), breadcrumbs, modal base.
- Errores coherentes `403`/`404` sin stack traces.

## Tests

```sh
php artisan test
```

Cubre Fase 1 (arranque, 20 tablas, roles, pipeline Ventas, factories y relaciones)
+ Fase 2 (`tests/Feature/PhaseTwoTest`): login activo/inválido/inactivo, logout,
protegidas redirigen, RBAC, Superadministrador, 403 sin permiso, cambio de contraseña,
perfil requiere auth, inactivo bloqueado, sin registro público, reset renderiza.

## Frontend

```sh
npm run build   # compila Vite (Alpine incluido). Aviso opcional de fontaine ignorable.
```

## Alcance actual (Fase 2)

Base de acceso y navegación. Todavía NO hay: CRUD de empresas/contactos, leads,
oportunidades, Kanban, tareas funcionales, productos, cotizaciones, ventas, tickets,
campañas, reportes, API ni integraciones.
