# Guion de Demostración y Evaluación Técnica (5 a 7 Minutos) - NexusCRM

Este recorrido está diseñado para permitir a evaluadores técnicos, reclutadores o líderes de equipo experimentar y auditar el funcionamiento real de **NexusCRM** y sus reglas de negocio en menos de 7 minutos.

---

## Preparación Inicial

Si se encuentra en un entorno de desarrollo o pruebas limpio:
```sh
php artisan crm:demo-reset
```
*Este comando inicializa de forma segura el dataset de demostración con dos equipos, clientes, leads, oportunidades, ventas, facturas y tickets.*

---

## Minuto 1: Inicio de Sesión y Comparación de Alcance (DataScope)

1. **Ingreso como Vendedor de Equipo Corporativo:**
   - **Usuario:** `marcos.diaz@demo.test`
   - **Contraseña:** `password`
   - **Observación:** El dashboard muestra únicamente sus oportunidades y tareas. En el menú de empresas solo figuran las asignadas a él (*Soluciones Tecnológicas Andinas SAC* y *Distribuidora Internacional Pacífico SA*).
2. **Cambio de Sesión a Auditor (Rol Consulta - Solo Lectura):**
   - **Usuario:** `auditor.consulta@demo.test`
   - **Contraseña:** `password`
   - **Observación:** La interfaz oculta botones de creación o edición. Si el usuario intenta forzar una petición `POST /companies` o `POST /leads`, el backend responde inmediatamente con `403 Forbidden`.
3. **Ingreso como Superadministrador:**
   - **Usuario:** `superadmin@demo.test`
   - **Contraseña:** `password`
   - **Observación:** Acceso global a todas las empresas de ambos equipos, pista de auditoría completa, gestión de usuarios y matriz de permisos.

---

## Minuto 2 a 3: Prospección, Calificación y Conversión Atómica de Lead

1. Diríjase a **Comercial > Leads**.
2. Abra el lead en estado **Calificado** (*Martín Beltrán Flores* de *Cadena Hotelera del Valle SAC*).
3. Haga clic en **Convertir Lead**:
   - Observe el modal interactivo que permite crear una nueva empresa o vincular una existente.
   - Deje seleccionada la creación de empresa, contacto y oportunidad.
   - Confirme la conversión.
4. **Verificación de Integridad:**
   - En una sola transacción protegida por `lockForUpdate()`, el lead pasa a `converted`.
   - Se crea la Empresa y el Contacto vinculados sin duplicados.
   - Si se intentara una doble sumisión simultánea, el sistema rechaza la repetición protegiendo la base de datos.

---

## Minuto 4: Embudo Comercial y Tablero Kanban Táctil

1. Diríjase a **Comercial > Oportunidades**.
2. Alterne entre la vista de **Listado** y el tablero **Kanban**:
   - En móviles o mediante teclado, observe el selector de etapa accesible en cada tarjeta (`<select>`), que permite mover oportunidades sin depender de arrastrar con ratón.
   - Mueva una oportunidad a la etapa *Negociación*.
   - El sistema actualiza automáticamente la probabilidad y recalcula el pronóstico comercial (*Forecast*).

---

## Minuto 5: Ciclo Comercial Completo (Cotización -> Venta -> Factura)

1. Diríjase a **Ventas > Cotizaciones**.
2. Abra la cotización `COT-DEMO-001` (Aceptada):
   - Observe los cálculos decimales exactos realizados en backend mediante **BCMath** (subtotal, impuestos y total exacto sin decimales flotantes).
   - Observe cómo los precios unitarios quedaron congelados (*snapshots*) independientemente del catálogo general.
3. Diríjase a **Ventas > Ventas**:
   - Visualice la venta formalizada `VNT-DEMO-001` originada desde la cotización aceptada.
4. Diríjase a **Ventas > Facturas**:
   - Visualice la factura interna `FAC-DEMO-001` en estado **Pagada**, con datos fiscales y registro administrativo de cobranza interna.

---

## Minuto 6: Mesa de Soporte al Cliente y Adjuntos Privados

1. Diríjase a **Operación > Tickets de Soporte**.
2. Abra el ticket `TCK-DEMO-001` (*Error en sincronización de inventario nocturno*):
   - Observe el hilo de conversación entre cliente y el agente de soporte.
   - Observe el componente de **Documentos Privados**: los archivos se almacenan en el disco privado protegido y solo pueden descargarse con permisos validados en tiempo real contra IDOR.

---

## Minuto 7: Pista de Auditoría y Reportes Multimoneda

1. Diríjase a **Administración > Auditoría**:
   - Revise el registro inmutable de acciones (`AuditLog`).
   - Abra el detalle de un registro para ver las diferencias estructuradas (*old values* vs *new values*).
   - Confirme que no se filtran contraseñas, secretos ni tokens.
2. Diríjase a **Informes > Forecast**:
   - Verifique que los totales se presentan segregados por moneda (**USD**, **PEN**, **EUR**) sin mezclar importes en sumas incorrectas.
3. Ejecute una exportación en **CSV**, **XLSX** o **PDF**:
   - La exportación se procesa en streaming seguro respetando exactamente los filtros aplicados en pantalla y el alcance de datos del usuario.
