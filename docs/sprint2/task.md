# Tareas técnicas — Sprint 2
## Ferretería Oriental

## Estados permitidos
Usar únicamente: `Pendiente`, `En progreso`, `En revisión`, `Terminado` y `Bloqueado`.

Una tarea solo debe marcarse `Terminado` cuando esté integrada, cumpla sus criterios de aceptación y existan pruebas ejecutadas. Si una prueba no se pudo ejecutar, documentar el motivo.

## Tareas

| ID | Tarea | Historia relacionada | Estado |
|---|---|---|---|
| S2-T01 | Revisar el código, esquema MySQL y documentación existentes del Sprint 1 | Todas | Terminado |
| S2-T02 | Confirmar el alcance de HU10 y sus criterios de aceptación con el equipo/docente | HU10 | Pendiente |
| S2-T03 | Revisar y acordar el modelo de datos para clientes, métodos de pago, ventas y detalle de venta, si aún no existe | HU07–HU09 | Terminado |
| S2-T04 | Diseñar wireframe de registro/listado de clientes, si se solicita | HU07 | Pendiente |
| S2-T05 | Implementar registro y listado de clientes con validaciones | HU07 | Terminado |
| S2-T06 | Diseñar wireframe de métodos de pago, si se solicita | HU09 | Pendiente |
| S2-T07 | Implementar registro y listado de métodos de pago con validaciones | HU09 | Terminado |
| S2-T08 | Agregar acciones de eliminación y confirmación para productos, proveedores y categorías | HU06 | Terminado |
| S2-T09 | Validar restricciones por productos asociados y ventas registradas al eliminar | HU06 | Terminado |
| S2-T10 | Diseñar interfaz de registro de venta, si se solicita | HU08 | Pendiente |
| S2-T11 | Implementar selección de cliente, método de pago y productos | HU08 | Terminado |
| S2-T12 | Implementar cálculo de subtotales y total, con validaciones del servidor | HU08 | Terminado |
| S2-T13 | Implementar guardado transaccional de venta y detalle, generación de folio y descuento de stock | HU08 | Terminado |
| S2-T14 | Implementar pruebas automatizadas para validaciones, relaciones, ventas, stock y rollback | HU06–HU09 | Terminado |
| S2-T15 | Ejecutar pruebas de integración y regresión de Sprint 1 | Todas las definidas | Terminado |
| S2-T16 | Actualizar documentación y preparar demostración del sprint | Todas las definidas | Terminado |

## Dependencias y notas
- HU08 depende de HU07 (clientes), HU09 (métodos de pago) y los productos con stock del Sprint 1.
- HU06 debe respetar las relaciones existentes y no permitir eliminar productos que ya tengan ventas.
- HU10 aparece mencionada en el documento de desglose como consulta del detalle de ventas, pero sus requisitos no están incluidos. No implementarla hasta confirmar el alcance.
- Los wireframes se indican «si se solicita» porque el documento proporcionado no establece explícitamente que sean entregables de este sprint.
- No se incluyen estimaciones de duración porque el documento de Sprint 2 no las proporciona.
- Las tareas son una propuesta inicial de descomposición técnica y deben validarse con el equipo antes de considerarlas comprometidas.

## Avance HU07
- El módulo se integra con los formularios, el listado y la navegación compartidos; usa PDO, consultas preparadas, validación de servidor, validación HTML complementaria y el token CSRF existente.
- El nombre es obligatorio. Teléfono, correo y dirección son opcionales; teléfono y correo se validan si se proporcionan.
- Criterio de duplicidad confirmado por el equipo: correo o teléfono igual; nombre repetido con datos de contacto distintos sí se permite. Los teléfonos se normalizan al guardar, quitando puntuación y espacios, y conservando `+` solo si está al inicio; el correo se compara según `utf8mb4_unicode_ci`. Validación de servidor e índices únicos protegen las solicitudes concurrentes. Los campos de contacto vacíos se guardan como `NULL` y no se restringen entre sí.
- La migración aditiva creó `cliente` y `metodo_pago` en MariaDB XAMPP (puerto 3307). Tras confirmar que `cliente` no tenía filas, se actualizó su índice único de nombre a índices únicos por correo y teléfono; no se modificaron datos existentes.
- Durante la primera aplicación del DDL, el shell recortó caracteres de algunos identificadores. Se detectó antes de insertar datos; las tablas vacías se corrigieron mediante renombrado de columnas y se verificaron contra el esquema esperado.
- Validación ejecutada: PHP lint sin errores; `test_customer_validation.php` (15 comprobaciones); `test_customer_modules.php` (14); `test_payment_methods.php` (6); `test_catalog_modules.php` (49); `validate_db.php` OK. Un POST con token CSRF inválido fue rechazado y no guardó datos. Las pruebas de persistencia usan transacciones revertidas y se confirmó que no quedaron filas de prueba.
- S2-T05 queda `Terminado`.

## Avance HU09
- Implementados registro y listado de métodos de pago en el módulo compartido. El nombre es obligatorio y único; la descripción es opcional.
- Se agregó la tabla `metodo_pago` con índice único por nombre. La comparación respeta la colación `utf8mb4_unicode_ci`.
- Las pruebas de validación, persistencia, duplicado en aplicación y restricción única de base de datos pasaron; los datos de prueba se revirtieron.
- S2-T07 queda `Terminado`.

## Avance HU08
- Formulario integrado a la navegación con selectores de cliente, método de pago y líneas de productos. JavaScript agrega y quita filas y presenta una estimación; el servidor valida y calcula importes en centavos.
- Reglas confirmadas: folio secuencial `V-` más identificador; precio estrictamente mayor que cero; cantidades enteras desde 1; producto no repetido; no permitir existencias ni ventas negativas. La cantidad y el precio se leen bajo bloqueo de filas durante la transacción.
- Cabecera, detalles con precio histórico, folio y descuentos de stock se guardan en una única transacción. Cualquier error revierte todos los cambios. Las claves foráneas impiden eliminar clientes, métodos o productos asociados al historial.
- `test_sales.php`: 24 comprobaciones, incluyendo cálculo multiproducto, precio histórico, stock insuficiente, cero/sin precio y rollback inducido luego de una escritura parcial.
- S2-T11, S2-T12 y S2-T13 quedan `Terminado`.

## Avance HU06
- Las tablas de categorías y proveedores y el catálogo de productos muestran acciones de eliminación con confirmación; las operaciones POST conservan CSRF.
- Se respetan las claves foráneas existentes y la relación `detalle_venta -> producto`; los errores de integridad se traducen a mensajes comprensibles.
- `test_catalog_deletions.php`: 8 comprobaciones; bloquea categorías/proveedores con productos y productos con ventas, y permite eliminar registros sin relaciones.
- S2-T08 y S2-T09 quedan `Terminado`.

## Pruebas Sprint 2 y regresión
- `test_customer_validation.php`: 15; `test_customer_modules.php`: 14; `test_payment_methods.php`: 6; `test_sales.php`: 24; `test_catalog_deletions.php`: 8.
- `test_catalog_modules.php`: 49 comprobaciones de regresión; `validate_db.php` confirma ocho tablas y siete claves foráneas.
- Lint de todos los archivos PHP y `node --check` de JavaScript terminaron sin errores. Las rutas de catálogos, clientes, métodos de pago, productos y ventas respondieron HTTP 200. POST de venta y eliminación con CSRF inválido fueron rechazados.
- Las pruebas de integración crean fixtures ficticios dentro de transacciones y revierten los cambios; se comprobó que no quedaron filas de prueba.
- La guía `docs/sprint2/demo.md` se contrastó con las pantallas, nombres de campos, mensajes, relaciones y pruebas actuales. Incluye datos ficticios, recorrido de las seis historias/módulos solicitados, previsualización y limpieza manual transaccional acotada; la limpieza no se ejecutó.

## Pendientes
- HU10 y la consulta del detalle de ventas continúan fuera del alcance confirmado.
- Las tareas de wireframes siguen pendientes porque no se han definido como condición de aceptación o entregable de este sprint.
