# NexusCRM — Sistema Integral de Gestión Comercial y Clientes

[![PHP Version](https://img.shields.io/badge/PHP-8.3%20%7C%208.5-777BB4?logo=php)](https://www.php.net/)
[![Laravel Framework](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel)](https://laravel.com/)
[![Database](https://img.shields.io/badge/PostgreSQL-17-336791?logo=postgresql)](https://www.postgresql.org/)
[![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?logo=tailwindcss)](https://tailwindcss.com/)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-v3-8BC0D0?logo=alpinedotjs)](https://alpinejs.dev/)
[![Tests](https://img.shields.io/badge/Tests-414%20Passing-brightgreen?logo=githubactions)](#pruebas-y-calidad-de-código)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**NexusCRM** es una plataforma web de grado empresarial desarrollada para orquestar la cartera comercial, la prospección de ventas, la emisión de documentos comerciales y el soporte técnico postventa. 

Construido como proyecto insignia de portafolio técnico, destaca por su **rigor en integridad transaccional**, **cálculos financieros exactos mediante BCMath**, **autorización multicapa en SQL (RBAC + DataScope)** y una **infraestructura reproducible con Docker y CI/CD en GitHub Actions**.

---

## 🏛️ Módulos del Sistema

```
NexusCRM
├── 💼 Comercial
│   ├── Empresas & Contactos (CRUD, asignación de responsables, historial y tags)
│   └── Leads & Prospección (Calificación y conversión atómica transaccional con locks)
├── 📈 Ventas
│   ├── Oportunidades & Pipeline (Embudo configurable con sincronización de probabilidades)
│   ├── Tablero Kanban Comercial (Accesible y adaptable para móviles con selector de etapa)
│   ├── Cotizaciones (Cálculos exactos con BCMath y snapshots congelados de productos)
│   ├── Ventas Formalizadas (Generación protegida desde cotizaciones aceptadas)
│   └── Facturas Internas (Control administrativo de cobro y registro de estados de pago)
├── ⚙️ Operación & Soporte
│   ├── Tickets de Soporte (Mesa de ayuda con canales, prioridades e historial de respuestas)
│   ├── Tareas & Actividades (Seguimiento comercial, llamadas, reuniones y vencimientos)
│   └── Calendario Comercial (Vista mensual integrada de compromisos y actividades)
├── 📣 Marketing & Automatización
│   ├── Campañas Multicanal (Email, SMS y eventos con métricas de costo y retorno)
│   ├── Plantillas de Mensajes (Interpolación segura de variables por destinatario)
│   └── Motor de Automatizaciones (Disparadores de eventos, condiciones y acciones automáticas)
├── 📊 Informes & Diagnóstico
│   ├── Dashboard Ejecutivo (KPIs en tiempo real, pipeline abierto y métricas comerciales)
│   ├── Forecast de Ventas (Proyección ponderada multimoneda segregada en USD, PEN y EUR)
│   ├── Reportes Detallados (Filtrado multidimensional y exportación en CSV, XLSX y PDF)
│   └── Diagnóstico de Calidad de Datos (Detección preventiva de inconsistencias de cartera)
└── 🛡️ Seguridad & Administración
    ├── Matriz RBAC & Permisos (7 roles canónicos con control jerárquico de privilegios)
    ├── Equipos Comerciales (Aislamiento de alcance de datos por pertenencia de equipo)
    ├── Trazabilidad & Auditoría (Registro inmutable de cambios con sanitización de secretos)
    ├── Centro de Notificaciones (Avisos internos de tareas, tickets e importaciones)
    ├── Adjuntos Privados (Almacenamiento protegido fuera de la raíz web con anti-IDOR)
    └── Búsqueda Global Autorizada (Acceso instantáneo con respeto estricto de visibilidad)
```

---

## 💎 Decisiones Técnicas y Arquitectura Destacada

1. **Precisión Numérica con BCMath:**  
   Todos los cálculos comerciales (subtotales, descuentos, impuestos y totales) utilizan aritmética de cadenas decimales con BCMath (`bcmul`, `bcadd`, `bcsub`, `bcdiv`) a escala fija de 2 decimales, evitando por completo las pérdidas de precisión de la coma flotante IEEE-754 (`0.1 + 0.2 !== 0.3`).
2. **Inmutabilidad por Snapshots Comerciales:**  
   Al guardar una cotización, venta o factura, los nombres, descripciones, precios y tasas impositivas se congelan en la base de datos (`QuoteItem`, `SaleItem`, `InvoiceItem`). Actualizaciones futuras del catálogo de productos **jamás mutan documentos legales o comerciales emitidos**.
3. **Prevención de Concurrencia y Doble Conversión:**  
   Las transiciones de leads a empresas y oportunidades se ejecutan dentro de bloques `DB::transaction()` con bloqueo pesimista `lockForUpdate()`, garantizando que peticiones concurrentes o dobles clics no dupliquen registros.
4. **Doble Capa de Autorización (RBAC + DataScope):**  
   Además de verificar permisos de habilidad (Policy), cada consulta en Eloquent aplica `DataScope`, asegurando que vendedores solo vean sus registros, supervisores solo su equipo (`team_id`), y que usuarios sin equipo (`team_id = null`) jamás compartan alcance entre sí. El rol `Consulta` tiene restricción de solo lectura a nivel Gate para todas las peticiones de mutación.
5. **Efectos Secundarios After-Commit:**  
   Las notificaciones internas y los registros de auditoría (`AuditLog`) se despachan únicamente después de confirmada la transacción (`afterCommit`). Un rollback de base de datos **nunca** genera auditorías de éxito falso ni notificaciones erróneas.
6. **Almacenamiento y Streaming Seguro:**  
   Los documentos privados se guardan en un disco privado fuera de la raíz pública (`storage/app/private/documents/`) con validación de tipo MIME, prevención de path traversal y streaming autorizado contra IDOR. Las exportaciones en CSV/XLSX se transmiten en lotes para evitar saturación de memoria RAM.

---

## 🚀 Instalación y Puesta en Marcha

### Opción A: Entorno Contenedorizado con Docker (Recomendado)

El repositorio incluye soporte nativo y reproducible con **Docker Compose** (PHP-FPM 8.3 con extensiones, Nginx, PostgreSQL 17 y Worker):

```sh
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/NexusCRM.git
cd NexusCRM

# 2. Configurar variables de entorno
cp .env.example .env

# 3. Levantar contenedores
docker compose up -d --build

# 4. Generar clave de aplicación y ejecutar migraciones
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force

# 5. Cargar dataset de demostración
docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoSeeder --force

# 6. Comprobar salud del servicio
curl -I http://localhost:8080/up
```

La aplicación estará disponible inmediatamente en `http://localhost:8080`.

---

### Opción B: Instalación Nativa Local

#### Requisitos
- **PHP:** `8.3` u `8.5` con extensiones `bcmath`, `pdo_pgsql`, `zip`, `gd`, `fileinfo`, `mbstring`.
- **Composer:** 2.x
- **Node.js:** 22+ y npm 10+
- **PostgreSQL:** 17 instalado localmente (`127.0.0.1:5432`)

```sh
# 1. Instalar dependencias
composer install
npm install

# 2. Compilar assets para producción
npm run build

# 3. Variables de entorno y clave
cp .env.example .env
php artisan key:generate

# 4. Configurar base de datos en .env y migrar
php artisan migrate

# 5. Cargar datos de demostración
php artisan db:seed --class=Database\\Seeders\\DemoSeeder

# 6. Iniciar servidor local
php artisan serve
```

---

## 👥 Cuentas de Acceso de Demostración

El comando `php artisan db:seed --class=Database\\Seeders\\DemoSeeder` (o `php artisan crm:demo-reset`) genera las siguientes cuentas listas para evaluar el sistema:

| Rol | Correo Electrónico | Contraseña | Alcance de Datos (`DataScope`) |
| :--- | :--- | :--- | :--- |
| **Superadministrador** | `superadmin@demo.test` | `password` | **Global** (Control total del sistema y auditoría) |
| **Administrador** | `admin@demo.test` | `password` | **Global** (Gestión de usuarios y catálogos) |
| **Supervisor Corp.** | `supervisor.corp@demo.test` | `password` | **Equipo** (Equipo Ventas Corporativas) |
| **Vendedor Corp.** | `marcos.diaz@demo.test` | `password` | **Propio** (Cartera de cuentas grandes) |
| **Supervisor Pyme** | `supervisor.pyme@demo.test` | `password` | **Equipo** (Equipo Ventas Pyme) |
| **Vendedora Pyme** | `valeria.ruiz@demo.test` | `password` | **Propio** (Cartera comercial Pyme) |
| **Soporte Técnico** | `david.soporte@demo.test` | `password` | **Propio** (Mesa de ayuda y tickets) |
| **Auditor Consulta** | `auditor.consulta@demo.test` | `password` | **Solo Lectura** (Mutaciones bloqueadas 403) |

---

## 🛠️ Comandos Artisan de Soporte y Operación

NexusCRM incluye herramientas de línea de comandos para mantenimiento y automatización:

```sh
# Recordatorios automáticos de tareas comerciales próximas y vencidas
php artisan crm:remind-tasks

# Reinicio seguro de datos demo (Protegido: aborta en producción)
php artisan crm:demo-reset

# Generación y verificación de respaldo empaquetado (BD + archivos privados)
php artisan crm:backup --verify
```

---

## 🧪 Pruebas y Calidad de Código

NexusCRM cuenta con una suite completa de **más de 414 pruebas automatizadas** que validan la lógica de negocio, concurrencia, transacciones y control de acceso:

```sh
# Ejecutar suite de pruebas unitarias y de integración sobre SQLite en memoria
php artisan test

# Ejecutar suite de pruebas sobre base de datos PostgreSQL aislada
php artisan test --configuration=phpunit.pgsql.xml

# Comprobación de estándares de código (Laravel Pint)
./vendor/bin/pint --test
```

El pipeline de **GitHub Actions** (`.github/workflows/ci.yml`) verifica en cada pull request:
1. Comprobación de formato de código con Laravel Pint.
2. Compilación de assets con Vite.
3. Suite completa de pruebas en SQLite.
4. Servicio PostgreSQL 17 dedicado para pruebas de concurrencia y restricciones relacionales.

---

## 📚 Documentación Técnica Detallada

Para una inmersión profunda en la arquitectura y las decisiones de diseño del proyecto, consulte los documentos en el directorio `docs/`:

- [Arquitectura del Sistema y Flujo de Petición](docs/architecture.md)
- [Matriz de Permisos y Control de Acceso (RBAC + DataScope)](docs/permissions.md)
- [Modelo de Datos y Ciclo de Vida Comercial](docs/data-model.md)
- [Guía de Despliegue en Producción y Operaciones](docs/deployment.md)
- [Guion de Demostración y Evaluación Técnica (5-7 min)](docs/demo-walkthrough.md)
- [Notas Técnicas para Entrevistas y CV](docs/interview-notes.md)

---

## 📌 Alcance de la Versión 1.0.0
- **Organización con equipos:** Representa una empresa estructurada con múltiples equipos comerciales y carteras separadas (sin multi-tenancy SaaS innecesario).
- **Facturación administrativa interna:** Diseñada para seguimiento y cobranza comercial interna (sin integración con proveedores fiscales como SUNAT o DIAN).
- **Comunicaciones y campañas:** Modelado y registro de interacciones simuladas (sin pasarelas de pago de terceros ni cobro por envío SMS/WhatsApp).
- **Pronóstico matemático:** Forecast de ventas basado en probabilidades reales y ponderación matemática por divisa (sin predicciones ficticias de IA).

---

## 📄 Licencia

Este proyecto está bajo la licencia [MIT](LICENSE).
