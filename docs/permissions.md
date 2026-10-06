# Matriz de Control de Acceso y Permisos (RBAC + DataScope)

NexusCRM implementa un modelo de autorización en dos capas:
1. **Permiso Funcional:** Define *qué acción* puede ejecutar el usuario (ej. crear empresa, exportar reporte).
2. **Alcance de Datos (`DataScope`):** Define *sobre qué registros* tiene visibilidad el usuario (sus propios registros, los de su equipo comercial o todo el sistema).

---

## 1. Los 7 Roles Reservados del Sistema

| Rol | Alcance de Datos (`DataScope`) | Descripción y Política Administrativa |
| :--- | :--- | :--- |
| **Superadministrador** | **Global** | Acceso total al sistema. Capacidad exclusiva para editar la matriz de permisos y roles. **Protegido:** el sistema impide eliminar, desactivar o revocar privilegios al último Superadministrador activo. |
| **Administrador** | **Global** | Gestión operativa de usuarios, equipos y catálogos. No puede modificar cuentas de Superadministrador ni autoasignarse privilegios superiores. |
| **Gerente comercial** | **Equipo** | Gestión de los registros pertenecientes a los miembros de su mismo equipo (`team_id`). Sin acceso a otros equipos ni administración global de la matriz. |
| **Supervisor** | **Equipo** | Supervisión y seguimiento de actividades y oportunidades de su mismo equipo comercial. |
| **Vendedor** | **Propio** | Visualización y gestión exclusiva de su cartera asignada (`owner_id === user->id`). |
| **Soporte** | **Propio / Cola** | Gestión de tickets asignados o cola de soporte autorizada. |
| **Consulta** | **Propio** | **Solo lectura estricta:** Incluso si un formulario envía peticiones de escritura o se le asignara un permiso de modificación por error, el Gate bloquea todas las mutaciones (`POST`, `PUT`, `PATCH`, `DELETE`). |

---

## 2. Reglas Críticas de Seguridad y Aislamiento

### A. Aislamiento Estricto de Usuarios sin Equipo (`team_id === null`)
Dos usuarios que no tengan equipo asignado (`team_id = null`) **jamás se consideran miembros del mismo grupo**. El alcance de un vendedor o usuario con equipo nulo queda estrictamente restringido a su propio `user_id`.

### B. Protección del Último Superadministrador
Centralizado en `App\Services\Users\UserProtectionService`:
- Si se intenta desactivar, eliminar mediante soft-delete o remover el rol `Superadministrador` a un usuario, el servicio evalúa dentro de una transacción con `lockForUpdate()` si existen otros superadministradores activos.
- Si es el último, la operación aborta con error de validación `422/403`, evitando que el sistema quede sin administración.

### C. Prevención de Escalamiento de Privilegios
- Un usuario con rol `Administrador` no puede crear nuevos usuarios con rol `Superadministrador`, ni modificar los datos o roles de un Superadministrador existente.
- Toda asignación de accesos pasa por `UserPolicy::assignAccess()`.

---

## 3. Matriz de Permisos por Módulo

| Módulo | Permisos Funcionales Disponibles | Alcance Típico |
| :--- | :--- | :--- |
| **Empresas (`companies`)** | `view`, `create`, `update`, `delete`, `export` | Filtrado por `owner_id` vía `DataScope` |
| **Contactos (`contacts`)** | `view`, `create`, `update`, `delete`, `export` | Filtrado por `owner_id` o empresa asociada |
| **Leads (`leads`)** | `view`, `create`, `update`, `delete`, `convert`, `export` | Filtrado por `owner_id` |
| **Oportunidades (`opportunities`)** | `view`, `create`, `update`, `delete`, `move_stage`, `export` | Filtrado por `owner_id` |
| **Cotizaciones (`quotes`)** | `view`, `create`, `update`, `delete`, `accept`, `reject`, `export`, `pdf` | Filtrado por `owner_id` |
| **Ventas (`sales`)** | `view`, `create`, `update`, `cancel`, `complete`, `export`, `pdf` | Filtrado por `owner_id` |
| **Facturas Internas (`invoices`)** | `view`, `create`, `update`, `mark_paid`, `cancel`, `export`, `pdf` | Filtrado por `owner_id` |
| **Tickets (`tickets`)** | `view`, `create`, `update`, `reply`, `resolve`, `close`, `export` | Filtrado por `assigned_to` o `created_by` |
| **Automatizaciones (`automations`)** | `view`, `create`, `update`, `delete`, `execute` | Administración global |
| **Reportes y Forecast (`reports`)** | `view_pipeline`, `view_sales`, `view_forecast`, `view_dashboard` | Agregados SQL restringidos por `DataScope` |
| **Importaciones (`data_imports`)** | `view`, `create`, `execute`, `download` | Scope propio por `created_by` |
| **Auditoría (`audit`)** | `view` | Exclusivo `Superadministrador` y `Administrador` |
| **Usuarios y Equipos (`admin`)** | `users.view/create/update/delete`, `teams.view/create/update/delete` | Roles globales |

---

## 4. Verificación Dinámica contra IDOR
En las acciones que aceptan identificadores externos (ej. adjuntos privados, descarga de exportación, selector de propietarios):
- El sistema no confía en que el usuario "no conozca el enlace".
- Cada petición revalida en base de datos que el ID del recurso y su entidad padre pertenezcan al conjunto de IDs autorizados por `DataScope::ownerIds($user)`.
