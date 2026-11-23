# Sprint 3 – Plan técnico de tareas

## Objetivo y alcance
Dar seguimiento a HU11 (prioridad alta), HU12 y HU13 (prioridad media), conservando la arquitectura y el comportamiento de Sprints 1 y 2. Marcar una tarea terminada solo cuando su criterio de aceptación se haya implementado y verificado.

## Decisiones confirmadas
- El ID de `producto` será el código visible y criterio de búsqueda.
- El área responsable registrada para HU11 será `Administrador`; no se implementará autenticación.
- Cada producto tendrá como máximo una ubicación formada por pasillo y estante.
- `pasillo` y `estante` son `VARCHAR(50)`, ambos nulos o ambos con contenido; su asignación queda fuera del Sprint 3.
- El historial conserva el nombre posterior a la modificación y permite que precio anterior/nuevo sean `NULL`.
- La instancia MariaDB 10.4.32 se migró y verificó; no se ejecutan migraciones durante HU11.

## Fase 0 – Preparación y análisis

| ID | Tarea | Estado |
|---|---|---|
| S3-T01 | Verificar rama activa y estado de Git, sin alterar cambios preexistentes. | Terminado |
| S3-T02 | Revisar README y documentación de Sprints 1, 2 y 3. | Terminado |
| S3-T03 | Inspeccionar rutas, vistas, servicios, repositorios y validaciones existentes. | Terminado |
| S3-T04 | Comparar el esquema desplegado con `database/schema.sql` y documentar diferencias; requiere autorización para inspección de la instancia. | Terminado |
| S3-T05 | Identificar cómo se guarda y consulta el precio unitario histórico de venta. | Terminado |
| S3-T06 | Confirmar el mecanismo actual de sesión e identificación del usuario. | Terminado |
| S3-T07 | Comprobar si el proyecto ya incluye ubicación, pasillo o estante. | Terminado |
| S3-T08 | Documentar el diseño de aplicación, el modelo de datos propuesto, riesgos y dependencias. | Terminado |

La inspección de la instancia y la comparación con el esquema de referencia se completaron antes de la migración.

## Fase 1 – HU11: Actualizar precio (prioridad alta)

| ID | Tarea | Componentes previstos | Dependencias | Criterio de terminación |
|---|---|---|---|---|
| S3-T09 | Confirmar el diseño de migración contra el esquema desplegado y aprobar su aplicación. | `database/schema.sql`, migración aditiva en `database/migrations/` | S3-T04, S3-T08 | Migración revisada, aplicada y verificada; esquema de referencia actualizado. | Terminado |
| S3-T10 | Añadir búsqueda de productos por ID (código) o nombre. | `index.php`, `CatalogService.php`, `CatalogRepository.php`, vista de precios | S3-T03, S3-T09 | Consulta preparada; encuentra por ID exacto o nombre; resultado vacío comunica «No se encontraron productos». | Terminado |
| S3-T11 | Presentar producto, ID, nombre y precio vigente antes de editar. | Vista de precios | S3-T10 | Muestra precio actual y presenta `NULL` como «Sin precio asignado». | Terminado |
| S3-T12 | Validar el precio solicitado en servidor. | `PriceService.php` | S3-T10 | HU11 acepta solo decimal representable por `DECIMAL(10,2)`, mayor que cero y distinto del actual; rechaza vacío, formato inválido, cero, negativo e igualdad. | Terminado |
| S3-T13 | Actualizar el precio e insertar el registro histórico en una sola transacción. | `CatalogRepository.php`, `PriceService.php` | S3-T09, S3-T12 | Bloquea la fila; actualización e historial se confirman o revierten juntos. | Terminado |
| S3-T14 | Registrar área responsable, ID y nombre del producto al momento del cambio. | Repositorio de catálogo/precios | S3-T09 | Guarda `Administrador`, ID y nombre posterior a la edición; el historial conserva los snapshots. | Terminado |
| S3-T15 | Integrar la auditoría con la edición de productos del catálogo. | `CatalogRepository.php`, `CatalogService.php` | S3-T09, S3-T13 | Los cambios efectivos de precio en el catálogo se registran; transiciones `NULL` se representan fielmente. | Terminado |
| S3-T16 | Implementar cancelación y mensajes de errores/éxito. | Vista de precios, controlador en `index.php` | S3-T11–S3-T15 | Cancelar no envía cambios; errores y éxitos se comunican con los mensajes existentes. | Terminado |
| S3-T17 | Probar validaciones, auditoría, rollback y búsqueda. | `database/test_price_updates.php` | S3-T09–S3-T16 | Suite enfocada pasa; cubre precios inválidos, `NULL`, snapshots y rollback. | Terminado |
| S3-T18 | Verificar precios en ventas nuevas y ventas ya existentes. | Pruebas de ventas/historial | S3-T13, S3-T15 | Venta nueva usa precio vigente y el detalle anterior conserva su precio histórico. | Terminado |

## Fase 2 – HU12: Historial de precios (prioridad media)

| ID | Tarea | Componentes previstos | Dependencias | Criterio de terminación | Estado |
|---|---|---|---|---|---|
| S3-T19 | Consultar historial en orden descendente de fecha/hora. | `PriceHistoryRepository.php` | S3-T09, S3-T14 | Consulta preparada ordena por fecha descendente y usa ID descendente como desempate estable. | Terminado |
| S3-T20 | Validar filtros por producto y rango de fechas. | `PriceHistoryService.php` | S3-T19 | El filtro acepta ID/código o nombre; valida fechas y rechaza rango invertido. | Terminado |
| S3-T21 | Mostrar campos históricos de solo lectura. | `index.php`, `price_history.php` | S3-T19, S3-T20 | Muestra fecha/hora, ID/código, snapshot de nombre, precios y área responsable; representa valores `NULL`. | Terminado |
| S3-T22 | Implementar estados vacíos y errores del historial. | Vista y controlador del historial | S3-T21 | Sin coincidencias muestra «No se encontraron cambios de precio»; errores de consulta tienen mensaje claro. | Terminado |
| S3-T23 | Mantener el historial como consulta inmutable desde la interfaz. | Ruta GET y vista | S3-T21 | El módulo no ofrece edición ni eliminación ni ejecuta escrituras. | Terminado |
| S3-T24 | Probar orden, filtros, valores nulos y solo lectura. | `database/test_price_history.php` | S3-T19–S3-T23 | Prueba transaccional cubre orden, filtros, snapshots tras borrar, nulos, fechas inválidas, estado vacío y rollback. | Terminado |

## Fase 3 – HU13: Consulta de ubicaciones (prioridad media)

| ID | Tarea | Componentes previstos | Dependencias | Criterio de terminación | Estado |
|---|---|---|---|---|---|
| S3-T25 | Confirmar longitudes, formato y regla para ubicación ausente; aprobar la migración. | Esquema de producto y migración | S3-T04, S3-T08 | Pasillo y estante son `VARCHAR(50)` y ambos quedan informados juntos o ausentes. | Terminado |
| S3-T26 | Añadir columnas opcionales de pasillo y estante al producto. | `database/schema.sql`, migración aditiva | S3-T25 | Migración aditiva aplicada; productos existentes permanecen sin ubicación. | Terminado |
| S3-T27 | Mantener la asignación de ubicaciones fuera del Sprint 3. | Alcance de HU13 | S3-T25 | HU13 solo consulta y no ofrece alta, edición o eliminación de ubicaciones. | Terminado |
| S3-T28 | Consultar productos con ID, nombre, categoría, pasillo, estante y stock. | `CatalogRepository.php`, `CatalogService.php` | S3-T26 | Listado incluye los campos requeridos y no excluye productos sin ubicación. | Terminado |
| S3-T29 | Implementar búsqueda por ID o nombre y filtro por pasillo. | Repositorio/servicio y vista de ubicaciones | S3-T28 | ID exacto o nombre parcial se combinan con el filtro exacto de pasillo mediante consultas parametrizadas. | Terminado |
| S3-T30 | Mostrar ubicaciones incompletas o ausentes sin inventar datos. | `locations.php` | S3-T28 | Pasillo y estante ausentes o vacíos se muestran como «Sin ubicación». | Terminado |
| S3-T31 | Implementar la pantalla de consulta de ubicaciones. | `index.php`, navegación y `locations.php` | S3-T28–S3-T30 | Vista accesible desde el menú y sin acciones de escritura de ubicación. | Terminado |
| S3-T32 | Probar búsqueda, filtro, producto sin ubicación y estados vacíos. | `database/test_locations.php` | S3-T26–S3-T31 | Pruebas transaccionales cubren búsqueda, filtros, valores ausentes, seguridad y preservación de datos. | Terminado |

## Fase 4 – Regresión, integración y cierre

| ID | Tarea | Dependencias | Criterio de terminación | Estado |
|---|---|---|---|---|
| S3-T33 | Ejecutar pruebas de HU11, HU12 y HU13. | S3-T17, S3-T24, S3-T32 | Todas las pruebas enfocadas pasan y sus resultados quedan documentados. | Terminado |
| S3-T34 | Ejecutar suites de regresión de Sprints 1 y 2. | S3-T17 | Catálogo, clientes, métodos de pago, ventas, historial y eliminaciones conservan su comportamiento esperado. | Terminado |
| S3-T35 | Ejecutar lint PHP y comprobaciones JavaScript aplicables. | S3-T17 | No quedan errores introducidos por los cambios. | Terminado |
| S3-T36 | Verificar migración, integridad y ausencia de datos de prueba residuales. | S3-T09, S3-T17 | Verificación autorizada de la instancia y de datos se documenta; no se limpia información ajena a las pruebas. | Terminado |
| S3-T37 | Actualizar documentación de cierre de Sprint 3. | S3-T33–S3-T36 | README y documentación describen solo comportamiento implementado y pruebas ejecutadas. | Terminado |

Las pruebas de persistencia usan fixtures en una transacción revertida. MariaDB puede avanzar los contadores `AUTO_INCREMENT` aunque las filas se reviertan; esto no deja registros de prueba confirmados.

## Dependencias y decisiones aún necesarias
- Consultar las definiciones de UC01, UC18 y UC19 si el equipo dispone de ellas.
- No se implementa login: el área responsable de los cambios se registra como `Administrador`, decisión ya confirmada.

## Criterios generales de terminado
- La historia cumple sus criterios de aceptación y se integra con la arquitectura existente.
- Toda escritura se valida en servidor y usa consultas preparadas.
- Cambios acoplados de datos se ejecutan en transacciones y se revierten ante errores.
- Migraciones son aditivas, están aprobadas y verificadas contra la instancia real.
- Se ejecutan pruebas enfocadas, regresión y análisis estático pertinentes.
- No quedan fixtures ni alteraciones involuntarias de datos existentes.
- Ninguna tarea se considera terminada antes de implementar y verificar su criterio.
