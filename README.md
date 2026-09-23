# NexusCRM

CRM empresarial web construido con Laravel. Fase 3: Empresas + Contactos (primer módulo funcional).

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

## Fase 3 — Empresas y Contactos (primer módulo CRM funcional)

CRUD completo con Policies (`CompanyPolicy`, `ContactPolicy` → permisos
`companies.*` / `contacts.*`, ya existentes en `PermissionSeeder`, sin duplicados;
Superadministrador con acceso total vía RBAC centralizado):

- Listados con búsqueda backend, filtros combinables (estado, industria/país/empresa/departamento,
  responsable), ordenamiento con whitelist (`trade_name/first_name`, `status`, `created_at`),
  paginación de 15 con query string preservado, `with()`/`withCount()` (sin N+1).
- Empresas: `trade_name, legal_name, tax_id (unique DB), email, phone, website, industry,
  company_size, address, city, region, country, postal_code, status (active/inactive),
  owner_id, notes`. Búsqueda: nombre comercial, razón social, email, teléfono, NIT.
- Contactos: `first_name, last_name, email, phone, mobile, job_title, department,
  company_id, owner_id, status, notes`. Búsqueda: nombres, email, teléfonos, cargo +
  nombre de empresa vía relación. Filtros: empresa, estado, responsable, departamento.
- Crear/editar con `Store/UpdateCompanyRequest` y `Store/UpdateContactRequest`;
  `owner_id` con select de usuarios activos (se preserva el existente aunque esté inactivo).
- Soft deletes con confirmación (`confirm()` Alpine-inline); al borrar empresa los contactos se conservan.
- Fichas: datos principales, comercial, contacto/ubicación, responsable, estado, notas,
  tags, contactos asociados (con enlace y creación directa `contacts/create?company_id=`),
  actividad reciente (10 últimas, tipo/título/descripción/usuario/fecha), fechas created/updated.
- Tags polimórficos (`tags`/`taggables` Fase 1): mostrar, asignar existentes, crear por texto
  (coma, sin duplicados por slug), quitar (`DELETE companies|contacts/{id}/tags/{tag}`, requiere update).
- Rutas resource `companies.*` / `contacts.*` (+ `*.tags.detach`) bajo `auth`+`active`;
  placeholders de Fase 2 para empresas/contactos eliminados; leads/oportunidades/tareas/etc. intactos.
- Sidebar: Empresas y Contactos ya funcionales (sin badge "Próx."); resto sigue como placeholder.
- Componentes nuevos: `empty-state`, `status-badge` (reutiliza Fase 2: card, button, badge, flash, breadcrumbs).
- Flash: "Empresa/Contacto creada(o)/actualizada(o)/eliminada(o) correctamente." Errores 403/404/422 estándar.

## Tests

```sh
php artisan test
```

Cubre Fase 1 (arranque, 20 tablas, roles, pipeline Ventas, factories y relaciones)
+ Fase 2 (`tests/Feature/PhaseTwoTest`): login activo/inválido/inactivo, logout,
protegidas redirigen, RBAC, Superadministrador, 403 sin permiso, cambio de contraseña,
perfil requiere auth, inactivo bloqueado, sin registro público, reset renderiza.
+ Fase 3 (`tests/Feature/PhaseThreeTest`, 25 tests): CRUD empresas/contactos, validación,
403 sin permiso (7 operaciones c/u), soft delete (contactos de empresa intactos),
búsqueda (incl. por empresa), filtros combinados, paginación 15, relación
empresa↔contactos, tags asignar/crear/quitar, actividad visible.

## Frontend

```sh
npm run build   # compila Vite (Alpine incluido). Aviso opcional de fontaine ignorable.
```

## Alcance actual (Fase 3)

Empresas + Contactos funcionan completamente. Todavía NO hay: Leads funcional,
Oportunidades, Kanban, tareas completas, productos, cotizaciones, ventas, tickets,
campañas, automatizaciones, reportes, API ni integraciones.
