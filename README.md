# NexusCRM

CRM empresarial web construido con Laravel. Fase 11: motor interno de automatizaciones (event-driven, sin código arbitrario ni envíos).

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

## Fase 4 — Leads + calificación + conversión

CRUD con `LeadPolicy` (viewAny/view/create/update/delete + `convert` → permiso
`leads.convert`, añadido idempotente en `PermissionSeeder::EXTRA`; Superadministrador
cubierto por el sync total):

- Esquema real sin migraciones nuevas: `first_name, last_name, company_name, email,
  phone, source, status (default new), score (0–100 manual), owner_id,
  estimated_value decimal(15,2) >= 0, notes, converted_at, converted_contact_id,
  converted_company_id`. Sin `converted_opportunity_id` en esquema: la oportunidad
  se traza vía `lead_id` (`$lead->opportunities()`).
- Estados centralizados `Lead::STATUSES = new, contacted, qualified, unqualified,
  converted`; edición manual limitada a `EDITABLE_STATUSES` (converted solo vía flujo).
- Orígenes `Lead::SOURCES = website, referral, campaign, social, email, phone, event, other`
  (factory Fase 1 actualizada desde `web/cold_call` legacy); filtro por source.
- Listado: nombre, empresa declarada, email/teléfono, origen, estado, score (barra visual),
  valor estimado (number_format neutral), responsable, insignia Convertido, creada, acciones.
  Búsqueda backend (`scopeSearch`: nombres, empresa, email, teléfono), filtros combinables
  (estado, origen, responsable, score mín/máx, convertido sí/no), sorting con whitelist
  (`first_name, status, score, estimated_value, created_at`), paginación 15 con query string.
- Crear/editar con `Store/UpdateLeadRequest`; sin unique arbitrario de email;
  convertidos no editables (403 en update, redirect con error en edit).
- Soft delete con confirmación; permitido aun convertido (no arrastra nada).
- Ficha: datos, calificación/valor, tags, oportunidades backend (texto, sin enlaces rotos),
  actividad reciente, tareas relacionadas (título/estado/prioridad/vencimiento/asignado,
  solo lectura), fechas.
- Calificación: botón "Marcar calificado" (`PATCH leads/{lead}/qualify`, solo new/contacted)
  + cambio de estado en edición. Conversión exige `qualified` (decisión documentada:
  garantiza el paso por calificación antes de crear registros definitivos).
- Conversión (`LeadConversionController` + `LeadConversionService::convert`):
  pantalla con empresa nueva/existente, contacto nuevo/existente (bloquea contacto de
  otra empresa; vincula si no tiene), oportunidad opcional (nombre/monto revisable desde
  `estimated_value`/fecha; pipeline Ventas + etapa Prospecto por nombre/posición, sin IDs
  hardcodeados), responsable (default: dueño del lead). Mapeo cuidadoso: `company_name →
  trade_name`; email/teléfono del lead van al Contacto, nunca como email corporativo;
  sin job_title/department/mobile inventados.
- Atómica con `DB::transaction` + `lockForUpdate`: empresa → contacto → oportunidad →
  lead (`converted`, `converted_at`, refs) → actividad `status_change`. Fallos revierten
  todo (verificado: empresa creada se revierte si el contacto es incoherente).
  Reconversión bloqueada en UI (sin botón) y backend (redirect con error, sin duplicados).
- Tags con `SyncsTags`; owner con select de activos. Rutas `leads.*`, `leads.convert`,
  `leads.convert.store`, `leads.qualify`, `leads.tags.detach` bajo `auth`+`active`.
  Sidebar Leads activo; dashboard con accesos a Empresas/Contactos/Leads.

## Fase 5 — Oportunidades + pipeline comercial + Kanban

CRUD con `OpportunityPolicy` (viewAny/view/create/update/delete + `move` →
`opportunities.update`; decisión documentada: sin permisos `move`/`close` separados
para no multiplicar la matriz; Superadministrador intacto). Sin migraciones nuevas.

- Esquema real: `name, description, amount decimal(15,2), currency, probability,
  expected_close_date, actual_close_date, status, loss_reason, owner_id, pipeline_id,
  pipeline_stage_id, company_id, contact_id, lead_id` + SoftDeletes + historial
  (`opportunity_id, from_stage_id nullable, to_stage_id, changed_by, changed_at, notes`).
- Estados `Opportunity::STATUSES = open, won, lost`; sincronización unidireccional
  etapa→status/probability/cierre (`is_won → won/100/close`, `is_lost → lost/0/close+motivo`,
  normal → open/prob-etapa/null/null). `probability` siempre desde la etapa (sin doble fuente).
- `company_id` requerida en creación/edición; contacto coherente (otra empresa → rechazo;
  sin empresa → vinculación explícita); `lead_id` inmutable una vez asignado.
- `currency` como campo con selección limitada `USD/COP/EUR/MXN` (default USD, igual que
  Fase 4); no existe configuración monetaria funcional en settings, sin multi-moneda.
- Listado: nombre, empresa/contacto, pipeline/etapa, valor, probabilidad, ponderado
  (`amount × probability / 100`, informativo), responsable, cierre previsto, estado.
  Búsqueda (nombre, descripción, empresa, contacto), filtros (estado, pipeline, etapa,
  owner, empresa, monto mín/máx, cierre desde/hasta), whitelist
  (`name, amount, probability, expected_close_date, created_at, status`), paginación 15.
- Crear con `StoreOpportunityRequest` vía `OpportunityStageService::create` (valida
  etapa∈pipeline, exige motivo si Perdida, sincroniza, historial inicial from=null,
  actividad). Editar con `UpdateOpportunityRequest` (sin pipeline/etapa/status: solo vía
  move; documentado en la vista).
- Movimiento (`OpportunityStageController@update`, `PATCH opportunities/{id}/stage`):
  `MoveOpportunityStageRequest` (autoriza `move`), servicio con `lockForUpdate` +
  `DB::transaction`, valida etapa∈pipeline, no-op sin historial si misma etapa, exige
  `loss_reason` en Perdida, reapertura limpia cierre/motivo. Responde JSON (Kanban) o
  redirect con flash. Cada movimiento válido genera historial + actividad `status_change`.
- Ficha: comercial, relaciones (empresa/contacto/lead con enlaces), mover-etapa,
  timeline de historial (from→to/usuario/fecha), actividades, tareas (solo lectura),
  tags con `SyncsTags`.
- Kanban (`opportunities/kanban` antes del resource): selector de pipeline (?pipeline_id,
  default Ventas/is_default), 7 columnas con conteo + suma, tarjetas (nombre/empresa/
  monto/prob/owner/cierre), drag&drop Alpine + HTML5 DnD con `fetch` PATCH + CSRF,
  optimistic UI con reversión y error visible; responsive con scroll horizontal.
- Rutas `opportunities.*`, `opportunities.kanban`, `opportunities.stage.update`,
  `opportunities.tags.detach` bajo `auth`+`active`. Sidebar Oportunidades activo;
  dashboard con acceso directo.

## Fase 6 — Tareas + actividades + calendario comercial

CRUD con `TaskPolicy` y `ActivityPolicy` (patrón de fases previas). Sin migraciones:
el esquema Fase 1 ya traía todo (`taskable`/`subjectable` polimórficos, `scheduled_at`
en activities, SoftDeletes). Permisos `tasks.*` ya existían; `activities.view/create/
update/delete` añadidos idempotentes en `PermissionSeeder::EXTRA`.

- Tareas: `title, description, status (pending/in_progress/completed/cancelled),
  priority (low/medium/high/urgent), due_at, completed_at, assigned_to, created_by
  (= Auth::id(), sin campo en formulario), taskable`. Búsqueda (título/descripción),
  filtros (estado, prioridad, asignado, tipo entidad incl. sin-entidad, presets
  hoy/vencidas/próximas/completadas), whitelist (`title, due_at, priority, status,
  created_at`; prioridad con orden semántico vía CASE portable), paginación 15.
- Invariantes: completar (`pending/in_progress → completed + now`), reabrir
  (`completed/cancelled → pending + null`), cancelar (≠completada, `completed_at`
  siempre null); edición general normaliza igual. `is_overdue` calculado
  (`due_at < now` y no cerrada/cancelada), sin columna redundante.
- Actividades manuales: `call/email/note` + `meeting` (exige `scheduled_at`);
  `status_change` reservado al sistema (rechazado en creación, 403/redirección en
  edición/eliminación). `user_id = Auth::id()`; `completed_at` sincronizado con status.
- Relaciones polimórficas con whitelist `RelatedEntity` (`company/contact/lead/
  opportunity`): el frontend envía claves, el backend mapea a modelos; clases
  arbitrarias rechazadas (test). Etiquetas legibles + URL vía accessors
  (`related_label`, `related_url`).
- Creación contextual (`?related=company:3`) desde las 4 fichas, validada backend.
- Timeline unificado (`partials/timeline`) en fichas: actividades + tareas (+ historial
  de etapas en oportunidades), reciente primero.
- Calendario server-rendered sin librerías (decisión: dataset pequeño, bundle intacto):
  vistas mes/semana/día (`?view=&date=`), semanas lunes–domingo, eventos = tareas con
  `due_at` + reuniones con `scheduled_at` (nunca `created_at` como programada),
  distinción visual tarea/reunión, enlaces a fichas, crear tarea/reunión, responsive
  con scroll horizontal. Permiso: `tasks.view` o `activities.view` (cada uno ve lo suyo).
- Timezone: configuración existente (`UTC`), sin cambios; fechas coherentes con `now()`.
- Dashboard moderado: mis tareas de hoy, vencidas, próximos 7 días y próximas reuniones
  (conteos + top 5 con enlaces), sin analítica comercial.

## Fase 7 — Productos + catálogo + cotizaciones

Sin estructuras previas: 3 migraciones incrementales (`product_categories`+`products`,
`quotes`, `quote_items`; tipos verificados `numeric` en PostgreSQL real, sin float).
Permisos `products.*` y `quotes.*` añadidos idempotentes en `PermissionSeeder::EXTRA`;
`ProductPolicy` + `QuotePolicy` con patrón del proyecto. Sin `quotes.send/accept`
separados (transiciones usan `quotes.update`).

- Productos: `sku unique` (manual, normalizado a mayúsculas), `name, description,
  category_id, unit (unit/hour/day/service/license/package), price, cost (interno),
  tax_rate 0–100, status active/inactive, created_by, SoftDeletes`. Categorías como
  tabla simple (sin jerarquía). Búsqueda (sku/nombre/descripción/categoría), filtros
  (estado/categoría/unidad), whitelist (`sku, name, price, status, created_at,
  updated_at`), paginación 15, `withCount(quoteItems)`.
- Costo visible solo para quien puede editar (sin matriz nueva). Inactivos ocultos de
  líneas nuevas pero preservados en histórico (`nullOnDelete`).
- Cotizaciones: `number unique Q-AAAA-NNNNNN` (derivado del ID, seguro en concurrencia),
  `company_id requerida, contact_id?, opportunity_id?, owner_id, status, currency,
  issue_date, valid_until?, subtotal/discount_total/tax_total/total numeric(15,2),
  notes?, terms?, accepted_at?, rejected_at?, SoftDeletes`. Estados
  `draft/sent/accepted/rejected/expired(calculado)`; editable solo draft/sent.
- Líneas: `product_id? (nullOnDelete), position, description, unit, quantity
  numeric(12,3) > 0, unit_price, discount none/percentage(0–100)/fixed(<=base),
  tax_rate 0–100, subtotal/discount/tax/total` calculados. Snapshot obligatorio:
  descripción/precio/impuesto congelados (test: cambiar producto no altera línea).
- `QuoteCalculator` (BCMath, 2 decimales) como fuente única; JS/Alpine solo previsualiza.
  Totales del frontend ignorados (test tampering obligatorio en verde).
- `QuoteService`: create/update/transition transaccionales; coherencia empresa/contacto/
  oportunidad (rechazo cruzado, vinculación explícita si contacto sin empresa);
  prefill de moneda desde oportunidad; sin FX; `valid_until >= issue_date`.
- Transiciones: draft→sent/accepted/rejected, sent→accepted/rejected, terminales
  accepted/rejected (403/edición bloqueada), vencida calculada bloquea envío;
  actividades `status_change` en crear/enviar/aceptar/rechazar (empresa u oportunidad).
- Integraciones: "Nueva cotización" prellenada desde oportunidad (sin líneas ficticias),
  lista en ficha de oportunidad, recientes en empresa/contacto, uso en producto,
  widgets de dashboard (pendientes + próximas a vencer), vista imprimible HTML
  (sin sidebar/topbar/acciones; sin librería PDF).
- Sidebar con secciones CRM/Trabajo/Ventas/Administración; Productos y Cotizaciones
  activos por permiso. Ventas y Facturas añadidos en Fase 8.

## Fase 9 — Tickets + soporte al cliente

Sin estructuras previas: 2 migraciones incrementales (`ticket_categories`+`tickets`,
`ticket_messages`; 27/27 Ran). Permisos `tickets.view/create/update/delete`
idempotentes; `TicketPolicy` con patrón del proyecto. Sin `tickets.assign` separado
(decisión: `tickets.update` cubre asignación y transiciones).

- Correcciones previas incluidas y testeadas: `DemoUserSeeder` dedicado (idempotente,
  con guardia anti-producción en `DatabaseSeeder`); sin textos internos de fase en UI
  (footer, dashboard, placeholders, ficha de lead); botón Convertir solo en qualified
  (backend intacto).
- Tickets: `number unique TKT-AAAA-NNNNNN` (ID, concurrent-safe), `company_id?`
  (nullable para prospectos; sin contacto exige nombre/email del solicitante),
  `contact_id?` (coherente con empresa), `requester_name/email?`, `assigned_to?`
  (activos, preservando inactivo existente), `created_by = Auth::id()`,
  `category_id?`, `subject/description`, `status new/open/pending/resolved/closed`,
  `priority low/medium/high/urgent`, `channel web/email/phone/manual/other`
  (clasificación, sin recepción real), `first_response_at?/resolved_at?/closed_at?/
  last_reply_at?`, SoftDeletes.
- Categorías relacionales simples (`General, Facturación, Comercial,
  Soporte técnico`, idempotentes, sin jerarquía).
- Conversación cronológica con triple distinción visual: respuesta
  (`is_internal=false`), nota interna (`true`) y evento system. Cuerpos escapados
  (XSS verificado en test). Sin borrado de mensajes en UI (histórico).
- `TicketStatusService`: matriz centralizada (new→open/pending, open→pending/
  resolved, pending→open/resolved, resolved→closed/open, closed→open; solo resolved
  cierra), no-op sin evento, `lockForUpdate` + transacción con evento system.
- Primera respuesta (solo reply de usuario) fija `first_response_at` una vez y
  `last_reply_at` siempre; notas y system no mueven relojes; en cerrado solo cabe
  reabrir. Métricas `first_response_seconds`/`resolution_seconds` por accessors.
- Listado con presets (mis/sin-asignar/abiertos/pendientes/urgentes), búsqueda
  (número/asunto/descripción/empresa/contacto/solicitante), filtros combinables,
  whitelist (`number, priority, status, created_at, updated_at, last_reply_at`),
  paginación 15. Ficha helpdesk con tiempos, conversación y formularios duales.
- Integraciones: tickets recientes + "Nuevo ticket" preseleccionado en empresa y
  contacto; dashboard (abiertos/sin-asignar/pendientes + urgentes top 5).
  Sin vínculo a ventas/facturas (categoría Facturación basta) ni tareas automáticas.

## Fase 8 — Ventas + facturación interna (no fiscal)

Sin estructuras previas: 2 migraciones incrementales (`sales`+`sale_items`,
`invoices`+`invoice_items`; tipos verificados `numeric` en PostgreSQL real).
Permisos `sales.*` e `invoices.*` idempotentes; `SalePolicy` + `InvoicePolicy`.

- Ventas: `number unique S-AAAA-NNNNNN` (ID, concurrent-safe), `quote_id? unique
  (1 Quote → 1 Sale)`, `company_id restrict`, `contact_id?/opportunity_id?/owner_id?
  nullOnDelete`, `status draft/confirmed/completed/cancelled`, `currency`, `sale_date`,
  totales `numeric(15,2)`, `notes?`, `completed_at?/cancelled_at?`, SoftDeletes.
  Líneas con snapshot (`sku/description/unit/quantity 12,3/precios/descuentos/impuestos`).
- Conversión Quote→Sale (`SaleCreationService::fromQuote`): solo accepted, con lock,
  copia exacta de totales e ítems del QuoteItem (nunca del catálogo), actividad.
  Doble conversión rechazada (test, sin duplicados).
- Venta manual básica con `SaleCalculator` (delega en `QuoteCalculator`, misma
  matemática BCMath); totales del frontend ignorados. Edición: draft manual completa;
  con cotización solo notas (historia intacta); no-draft 403.
- Transiciones: draft→confirmed/cancelled, confirmed→completed/cancelled, terminales;
  cancelar bloqueado con factura vigente. Actividades `status_change`.
- Facturas INTERNAS (aviso visible "no fiscal" en índice/ficha/impresión): `number
  unique INV-AAAA-NNNNNN`, `sale_id? unique (1 Sale → 1 Invoice)`, snapshots de
  empresa (`company_name/tax_id/address`) y contacto, `due_date?`, `paid_at?/
  cancelled_at?`, SoftDeletes. Sin `paid_amount` (decisión: sin ledger; paid es marca).
- `InvoiceCreationService::fromSale`: solo confirmed/completed, con lock, copia exacta
  de SaleItems + totales, doble facturación rechazada, todo transaccional.
- Estados factura: draft→sent/paid/cancelled, sent→paid/cancelled, paid terminal;
  `paid_at` manual (registro interno, sin pago real); overdue calculado
  (`due_date < today`, no paid/cancelled), sin scheduler.
- Sin creación manual de facturas (solo desde venta) ni edición comercial (ficha +
  estados + impresión). Vista imprimible HTML para venta y factura.
- Integraciones: "Crear venta" en cotización aceptada (+enlace si existe), "Generar
  factura" en venta confirmada/completada, listas en oportunidad/empresa/contacto,
  uso en producto (quotes/ventas/facturas), dashboard (recientes + pendientes + vencidas).

## Fase 9.5 — DataScope: permiso + alcance de datos

Segunda capa de autorización sin reemplazar RBAC, sin multi-tenancy y sin
jerarquías inventadas (Gerente/Supervisor = mismo `team_id`).

- `app/Support/DataScope.php` centraliza todo (sin `if admin/team/owner` por Policy):
  niveles `global` (Superadministrador, Administrador), `team` (Gerente comercial,
  Supervisor: propio + mismo equipo no nulo), `own` (Vendedor, Soporte, Consulta),
  `legacy` (sin rol de alcance → comportamiento histórico global, compatibilidad).
  Memoización por objeto (`WeakMap`), todo en SQL (WHERE/IN, sin filtrado PHP).
- Equipo null no agrupa: dos usuarios sin equipo no acceden entre sí (test).
- Soporte en tickets: propios + sin asignar (cola); nunca los de otro agente.
  Equipo: equipo + cola. Vendedor/consulta: asignados o creados por él.
- Consulta puro: solo lectura vía `Gate::before` (deniega create/update/delete/
  move/convert aunque existan permisos por error).
- Productos: catálogo global (solo permiso).
- `scopeVisibleTo` en 9 modelos + `visibleTo()` en índices, Kanban (columnas y
  totales), dashboard (todos los widgets) y eager loads de fichas (incl. conteos).
- Policies: permiso funcional + alcance (contacto exige además empresa visible).
- Enlaces belongsTo ocultos (nombre incluido) si el destino está fuera de alcance;
  creación contextual (tareas/actividades) valida visibilidad (403, sin oráculos).
- Conversiones (lead/quote/sale) verifican alcance del origen (403, sin parciales).
- Filtros de responsable limitados por alcance (`filterableUsers`, con preservación
  del valor existente en edición).
- Migración `000025`: índices `users.team_id`, `tasks.created_by`,
  `tickets.created_by` (WHERE frecuentes del scope).

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
+ Fase 4 (`tests/Feature/PhaseFourTest`, 26 tests): CRUD leads, validación, 403,
soft delete, búsqueda, filtros (estado/origen/responsable/score/convertido),
sorting por score, paginación 15, tags, calificación, conversión a Company+Contact
(nueva o existente), oportunidad opcional en Ventas/Prospecto con amount revisable,
trazabilidad converted_*, reconversión bloqueada, conversión exige qualified,
403 sin leads.convert, rollback total ante fallo, actividad de conversión.

+ Fase 5 (`tests/Feature/PhaseFiveTest`, 25 tests): CRUD oportunidades, 403 (9 ops),
validación, lead origen inmutable, coherencia empresa/contacto, soft delete sin arrastre,
búsqueda (nombre/empresa/contacto/descripción), filtros (estado/pipeline/etapa/owner/
empresa/monto/cierre), sorting amount, paginación 15, tags, movimiento con historial+
sincronización+actividad, no-op sin historial, etapa ajena rechazada, Ganada/Perdida
(requiere motivo) con sincronización, reaperturas, atomicidad ante fallo, endpoint JSON,
Kanban (columnas/totales/tarjetas), compatibilidad con conversión Fase 4.

+ Fase 6 (`tests/Feature/PhaseSixTest`, 30 tests): CRUD tareas/actividades, 403
(10/7 ops), validación, rechazo de morph arbitrario, preselección contextual,
complete/reopen/cancel con invariantes, overdue, búsqueda/filtros/sorting/paginación,
tipo sistema rechazado en creación y protegido en edición/eliminación, reunión exige
`scheduled_at`, labels polimórficos ×4 entidades, timeline en ficha, calendario
(permiso/eventos/semana/día/alcance), widgets de dashboard.

+ Fase 7 (`tests/Feature/PhaseSevenTest`, 25 tests): CRUD productos/cotizaciones,
403 (7/11 ops), SKU unique/normalizado, costo solo editores, soft delete con histórico,
búsqueda/filtros/sorting/paginación, producto inactivo rechazado, tampering de totales
ignorado, snapshot inmutable, descuentos %/fijo, cantidad decimal, inmutabilidad accepted,
coherencia empresa/contacto/oportunidad, prefill desde oportunidad, transiciones con
timestamps, vencida calculada, integraciones y uso de producto.

+ Fase 8 (`tests/Feature/PhaseEightTest`, 22 tests): CRUD ventas/facturas, 403
(11/7 ops), recálculo manual, validación, notas-solo con origen, soft delete,
búsqueda/filtros/sorting/paginación, transiciones con timestamps, conversión
Quote→Sale exacta, doble conversión/facturación rechazadas, snapshots en 3 niveles,
transiciones de factura, vencida calculada, integraciones y dashboard.

+ Fase 9 (`tests/Feature/PhaseNineTest`, 23 tests): correcciones UX verificadas,
CRUD tickets, 403 (10 ops), validación + solicitante + coherencia, conversación
(reply/nota/relojes/cierre/XSS), transiciones completas + inválidas + no-op,
métricas deterministas, integraciones y dashboard.

+ Fase 9 (`tests/Feature/PhaseNineTest`, 23 tests) + auditoría posterior.
+ Fase 9.5 (`tests/Feature/DataScopeTest`, 18 tests): legado global, IDOR ×10
módulos, escrituras, matriz vendedor/supervisor/admin, null-team, Kanban, dashboard,
conversiones con alcance, fugas en relaciones, matriz soporte, consulta read-only,
actividades, tareas, creación contextual, filtros de responsable.

## Frontend

```sh
npm run build   # compila Vite (Alpine incluido). Aviso opcional de fontaine ignorable.
```

## Fase 10 — Campañas + comunicaciones (simuladas)

Sin proveedor externo: NO se envían emails/SMS/WhatsApp reales. No hay SMTP,
SendGrid, Mailgun, SES, Twilio ni WhatsApp API. `email/sms/whatsapp` son solo
clasificación. No existen métricas reales de delivery/open/click.

- Esquema (migración `000026`): `campaigns` (money `numeric(15,2)`, SoftDeletes),
  `campaign_members` (unique `campaign_id+member_type+member_id`, sin FK destructivas
  a contactos/leads), `message_templates` (SoftDeletes), `communications`
  (`metadata jsonb`, SoftDeletes, `nullOnDelete` hacia históricos).
- Campaign: tipos `email/sms/whatsapp/phone/event/other`; estados
  `draft/scheduled/active/paused/completed/cancelled` con transiciones simples
  (`TRANSITIONS`, sin workflow engine); editable solo en
  `draft/scheduled/active/paused`. Búsqueda (nombre/descripción), filtros
  (status/type/owner/date range), sorting whitelist, paginación 15, conteo de
  miembros con alcance (`scoped_members_count`). Actividades `status_change`
  solo en creada/activada/completada.
- Miembros: claves seguras `contact|lead` vía `CampaignMemberType` (nunca clases
  PHP); estados `pending/sent/delivered/responded/converted/unsubscribed/failed`
  (en esta fase el flujo usa `pending→sent` simulado, más `unsubscribed` manual;
  sin `delivered` falsificado). Alta individual con `DataScope::assertVisibleId`,
  duplicados rechazados, baja bloqueada si hay comunicaciones. Listado y conteos
  siempre con alcance (`scopeCampaignMembers`); fuera de alcance se oculta.
- Audience builder básico: contactos (status/owner/company/tag) y leads
  (status/source/score mínimo/owner/tag), con preview/count scoped y alta masiva
  que revalida cada ID en backend (máximo 500 por operación).
- Plantillas (`templates.view/create/update/delete`, namespace corto documentado):
  `channel email/sms/whatsapp`, estados `draft/active/archived`, variables
  whitelist `first_name/last_name/full_name/company_name/email` con `strtr()`
  (sin Blade/eval/HTML); cuerpos siempre escapados (`{{ }}`, texto plano);
  preview con datos de ejemplo seguros.
- Comunicaciones (`communications.*`): `channel`, `direction` (solo `outbound`
  en esta fase), estados `draft/queued/simulated_sent/failed/cancelled`
  (sin `delivered/opened/clicked` reales). Compose con objetivo Contact o Lead
  (exactamente uno), campaña/plantilla opcionales, todo validado contra
  DataScope. "Registrar envío simulado" crea `simulated_sent + sent_at=now()`
  y pasa el miembro a `sent` en `DB::transaction`. Masivo por campaña (máximo
  500, chunks de 100 en una transacción razonable, sin queues).
- DataScope: campaña/plantilla por `owner_id` (`scopeOwned`); comunicación por
  owner + objetivo visible (`scopeCommunications`/`canAccessCommunication`);
  miembros por campaña visible + objetivo visible. `filterableUsers()` en todos
  los selectores. Query strings (`?owner_id`, `?contact_id`, `?campaign_id`…)
  no amplían acceso (se combinan con `visibleTo`).
- Integraciones: contacto/lead muestran campañas y comunicaciones recientes
  (scoped) + "Nueva comunicación"; dashboard con campañas activas y simuladas
  recientes (sin analítica); sidebar MARKETING (Campañas/Plantillas/
  Comunicaciones) por permiso.
- Tests: `tests/Feature/PhaseTenTest` (30 tests): CRUD, validación, transiciones,
  search/filters/sorting/paginación, scope, miembros (duplicado/alcance/morph/
  baja/counts), audience (filtros/tags/score/bulk/scope), plantillas (CRUD/
  scope/variables/XSS), comunicaciones (crear/simular/transacción/alcance/
  XSS/edición), masivos (elegibles/unsubscribed/límite 500), IDOR ×5, query
  string, Consulta read-only, supervisor mismo equipo.

## Alcance actual (Fase 11)

RBAC + DataScope activos también en automatizaciones. Sin scheduled triggers,
colas, webhooks, API, reportes, IA ni proveedores reales. Fase 12 no iniciada.

## Fase 11 — Motor de automatizaciones internas

Trigger → conditions (AND) → actions → AutomationRun. Deliberadamente limitado:
sin scripting, eval, Blade sobre input, SQL/clases/métodos arbitrarios, HTTP,
webhooks, email/SMS/WhatsApp reales, IA, ramas, loops, delays ni cron.

- Esquema (migración `000027`): `automations` (name, description, status,
  trigger_type, conditions/actions `jsonb`, owner/created_by `nullOnDelete`,
  last_run_at, SoftDeletes; índice compuesto `(status, trigger_type)`) y
  `automation_runs` (automation `nullOnDelete`, event_uuid `uuid`, trigger,
  subject, status, triggered_by, context/result `jsonb`, error_message,
  started/finished; unique `(automation_id, event_uuid)` anti-duplicados).
- Estados: `draft` (configurable, no ejecuta), `active` (escucha), `paused`
  (conserva, no ejecuta). Active no se edita (pause → edit → activate, backend);
  el estado no viaja en update normal (`prohibited`); delete exige pausada y es
  soft (historial conservado). Activar revalida TODA la config y exige owner
  activo.
- Permisos `automations.view/create/update/delete/execute` (idempotentes;
  Superadministrador sincronizado). `AutomationPolicy` = permiso + DataScope
  (`owner_id`). `execute` cubre activate/pause/dry-run y está en
  `WRITE_ABILITIES` (Consulta sigue read-only aunque reciba permisos por error).
- Catálogo central `AutomationCatalog` (whitelists): 12 triggers
  (`lead.created`, `lead.status_changed`, `contact.created`,
  `opportunity.stage_changed/won/lost`, `task.completed`,
  `ticket/quote/sale/invoice/campaign.status_changed`), subjects
  (`lead/opportunity/task/ticket/quote/sale/invoice/campaign/contact`, nunca
  clases PHP), condition fields por trigger (columnas reales + `previous_*`
  controlados), 11 operadores con compatibilidad por tipo, 4 acciones
  (`create_task`, `create_activity`, `assign_owner`, `add_to_campaign`).
- Detección: un `AutomationObserver` central (9 modelos, sin duplicar hooks en
  controllers/servicios); previos capturados en `updating`, dispatch en
  `created/updated` solo ante cambio real (no-op no emite; sin deleted/restored).
  Contexto limitado (sin snapshots ni PII duplicada).
- After-commit: `AutomationTriggerDispatcher` difiere con `DB::afterCommit()`
  (síncrono si no hay transacción; modo `::$sync` explícito para tests con
  RefreshDatabase). El negocio confirma primero; un fallo de automatización
  nunca lo revierte (run `failed`, error seguro sin stack trace en UI).
- Condiciones: lista AND, máx. 10; `ConditionEvaluator` con tipos seguros
  (decimales vía `bccomp`, `contains` solo strings, sin comparaciones ambiguas).
- Acciones: máx. 5 en orden, `AutomationActionExecutor` con match cerrado.
  `create_task` (title/description con variables whitelist, priority,
  due_in_days 0–365, assigned_to subject_owner/automation_owner/triggering_user/
  fixed_user, relación vía `RelatedEntity` si aplica); `create_activity`
  (tipos `note/call/email`, nunca `status_change`); `assign_owner` (solo modelos
  con `owner_id`, destino automation_owner/triggering_user/fixed_user, vía
  `can('update')` sin saltar Policy); `add_to_campaign` (solo contact/lead,
  campaña visible/editable, sin duplicados, sin envíos). Variables
  `subject_name/subject_id/owner_name` con `strtr()`.
- Transacción por run: las acciones de una automatización son atómicas (fallo →
  rollback propio + `failed`); cada automatización aislada en try/catch (una
  defectuosa no bloquea otras). `last_run_at` en cada run terminal.
- Revalidación al ejecutar (nunca autoridad histórica): owner existe+activo,
  `canViewModel(owner, subject)`, permiso funcional por acción, fixed_user y
  campaign contra el alcance actual. Skips: `owner_missing/inactive`,
  `scope_denied`, `permission_denied`, `conditions_not_met`, `subject_unavailable`.
- Dedup por unique `(automation_id, event_uuid)` (segundo delivery se ignora);
  loop guard con `correlation_id`/`depth` (máx. 5) + anti-reentrada por cadena
  (dormidos en Fase 11 porque ninguna acción produce triggers, testeados igual).
- CRUD `automations.*` + `activate/pause/dry-run` y detalle de run; builder sin
  JSON crudo (Alpine: trigger→campos→operadores, acciones con parámetros);
  backend valida todo (`AutomationDefinitionValidator`, usado en store/update/
  activate). Dry run requiere `execute`, valida subject visible (403 si no),
  no escribe nada ni crea runs.
- Historial paginado en la ficha + detalle de run (status/trigger/subject/
  duración/resultado/error seguro). Dashboard: activas + fallos recientes
  (scoped). Sidebar AUTOMATIZACIÓN por `automations.view`. Empty states y
  breadcrumbs incluidos.
- Tests: `tests/Feature/PhaseElevenTest` (43 tests): CRUD/validación/transiciones,
  triggers reales vía servicios, decimales exactos, atomicidad, aislamiento
  múltiple, dedup, loop guard, dry run, 4 acciones, operadores, límites,
  seguridad de payloads, XSS, IDOR, escalamiento denegado, Consulta/sin-rol/
  null-team, rechecks (permiso/owner/scope), smoke de 100 automations y detalle.
