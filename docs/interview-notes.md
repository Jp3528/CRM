# Notas Técnicas para Entrevistas Laborales - NexusCRM

Este documento reúne preguntas técnicas frecuentes, decisiones de diseño y respuestas fundamentadas con código real del repositorio para defender el proyecto en entrevistas de desarrollo Backend / Fullstack con Laravel.

---

## 1. Preguntas y Respuestas Técnicas

### P1: ¿Cómo previenes vulnerabilidades de tipo IDOR (Insecure Direct Object References)?
**Respuesta:**  
La autorización en NexusCRM no confía exclusivamente en ocultar botones ni en que el usuario no conozca una URL. Se aplica una doble capa obligatoria:
1. **Policy de Laravel:** Verifica el permiso funcional del usuario (ej. `companies.update`).
2. **Alcance de Datos (`DataScope`):** En cada consulta y endpoint se aplica `DataScope::scopeOwned()` o `DataScope::assertVisibleId()`. Por ejemplo, al intentar ver o descargar un documento privado pasando un ID numérico (`/private-documents/{id}/download`), el controlador verifica que el documento pertenezca a una entidad padre (empresa, oportunidad o ticket) visible según el `owner_id` o `team_id` del usuario autenticado. Si un usuario de un equipo intenta manipular la URL con el ID de otro equipo, el sistema responde con `403 Forbidden` a nivel SQL.

---

### P2: ¿Por qué no utilizaste números de punto flotante (`float`) para los montos comerciales?
**Respuesta:**  
El estándar IEEE-754 de coma flotante introduce imprecisiones binarias acumulativas (`0.1 + 0.2 = 0.30000000000000004`). En sistemas comerciales y de facturación esto genera discrepancias intolerables en totales, subtotales e impuestos.  
En NexusCRM, todo el dinero se maneja como **cadenas de texto decimales con BCMath** a escala fija de 2 decimales (`bcmul`, `bcadd`, `bcsub`, `bcdiv`), encapsulado en servicios como `QuoteCalculator` y `SaleCalculator`. Además, las columnas en PostgreSQL se definen como `numeric(12, 2)` y los modelos Eloquent utilizan el cast `decimal:2`.

---

### P3: ¿Cómo manejas la concurrencia y evitas la doble conversión de leads o duplicación de ventas?
**Respuesta:**  
Las mutaciones críticas se ejecutan dentro de transacciones atómicas `DB::transaction()` con bloqueo pesimista en base de datos mediante `lockForUpdate()`:
```php
$lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
if ($lead->isConverted()) {
    throw ValidationException::withMessages(['lead' => 'Este lead ya fue convertido.']);
}
```
Si dos peticiones llegan en el mismo milisegundo, la primera adquiere el bloqueo de fila en PostgreSQL, realiza la conversión y actualiza el estado; la segunda petición se desbloquea inmediatamente después, detecta que el estado ya es `converted` y aborta de forma limpia sin duplicar empresas ni contactos.

---

### P4: ¿Qué es un "Snapshot Comercial" y por qué es una regla inquebrantable en tu arquitectura?
**Respuesta:**  
Un snapshot comercial es la congelación inmutable de datos en el momento de emisión de un documento legal o comercial. En NexusCRM, `QuoteItem`, `SaleItem` e `InvoiceItem` no se limitan a guardar un `product_id`; copian y congelan el nombre, descripción, precio unitario y tasa impositiva. Si 6 meses después la empresa sube los precios en su catálogo de productos o archiva un ítem, las cotizaciones aceptadas, ventas cerradas y facturas históricas **no sufren ninguna mutación**.

---

### P5: ¿Cómo garantizas que un rollback en base de datos no genere efectos secundarios falsos?
**Respuesta:**  
Utilizamos despachos **after-commit** tanto en el servicio de notificaciones (`InternalNotificationService`) como en los registros de auditoría (`AuditLog`). Si una transacción bancaria o comercial falla a mitad de ejecución y la base de datos ejecuta un rollback, **no se persiste ninguna auditoría de "éxito" ni se envía ninguna notificación por correo o campana**. Solo se notifica lo que realmente quedó confirmado en disco.

---

### P6: ¿Por qué mantienes suites de prueba separadas para SQLite y PostgreSQL?
**Respuesta:**  
SQLite en memoria es excelente para velocidad en desarrollo local (TDD rápido), pero no soporta bloqueos pesimistas reales (`lockForUpdate`), ciertas funciones de agregación multimoneda o comportamientos estrictos de tipos en PostgreSQL. Por eso creamos una configuración específica `phpunit.pgsql.xml` con un contenedor PostgreSQL 17 en GitHub Actions y añadimos un guard de seguridad en `TestCase::ensureTestingEnvironment()` que aborta inmediatamente si los tests intentaran ejecutarse contra una base de datos que no esté explícitamente designada para testing.

---

## 2. Textos Técnicos para CV y Perfil Profesional (LinkedIn / Resumen)

Puedes incorporar los siguientes logros técnicos a tu postulación o CV:

- **Desarrollo de CRM Empresarial en Laravel 13 & PostgreSQL 17:** Diseñé e implementé un sistema modular de gestión comercial con arquitectura orientada a servicios, control de acceso RBAC multinivel y alcance de datos por equipos (`DataScope`), protegiendo la visibilidad y evitando brechas IDOR en toda la plataforma.
- **Integridad Transaccional y Cálculos Financieros de Alta Precisión:** Implementé un motor de cálculo monetario basado en BCMath sin pérdidas de precisión por coma flotante, previniendo doble conversión mediante transacciones con bloqueos pesimistas (`lockForUpdate`) y garantizando inmutabilidad histórica mediante snapshots comerciales.
- **Infraestructura Reproducible y Pipeline de CI/CD:** Diseñé el entorno contenedorizado con Docker Compose (PHP-FPM, PostgreSQL 17, Nginx y workers), automatizando suites de prueba paralelas (SQLite y PostgreSQL), formateo de código con Pint y compilación de assets en GitHub Actions con más de 400 pruebas automatizadas.
