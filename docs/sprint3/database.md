# Sprint 3 – Esquema de base de datos

## 1. Alcance y estado de verificación
Este documento describe el esquema de referencia actualizado para HU11, HU12 y HU13. La migración de MariaDB 10.4.32 se ejecutó y verificó antes del desarrollo de HU11; durante la implementación no se volvieron a ejecutar migraciones ni DDL.

El esquema de referencia vive en `database/schema.sql` y se conserva la migración en `database/migrations/20261113_create_price_history_and_product_locations.sql`. `producto.id` es `INT UNSIGNED`, `producto.precio_venta` es `DECIMAL(10,2) NULL`, y `detalle_venta.precio_unitario` conserva el precio histórico de cada venta.

Decisiones confirmadas:
- El ID de producto existente es el código que se muestra y se usa en búsquedas.
- El área responsable de un cambio HU11 es `Administrador`.
- Un producto tiene como máximo una ubicación, formada por pasillo y estante.
- `pasillo` y `estante` son `VARCHAR(50)`, ambos nulos o ambos con contenido.
- El nombre guardado en el historial es el snapshot posterior a cualquier edición que acompañe el cambio de precio.
- `precio_anterior` y `precio_nuevo` admiten `NULL`; HU11 solo permite establecer un precio positivo distinto del actual.

## 2. Modelo de datos

Tablas existentes según `database/schema.sql`:
- Catálogos: `categoria`, `marca`, `proveedor`.
- Inventario y auditoría: `producto`, `historial_precio`.
- Operación comercial: `cliente`, `metodo_pago`, `venta`, `detalle_venta`.

Relaciones:
- `producto.id_categoria` → `categoria.id`.
- `producto.id_marca` → `marca.id`.
- `producto.id_proveedor` → `proveedor.id`.
- `venta.id_cliente` → `cliente.id`.
- `venta.id_metodo_pago` → `metodo_pago.id`.
- `detalle_venta.id_venta` → `venta.id`.
- `detalle_venta.id_producto` → `producto.id`.
- `historial_precio.id_producto` → `producto.id` con `ON DELETE SET NULL`; el historial conserva sus snapshots aunque se elimine el producto.

`historial_precio.codigo_producto` y `nombre_producto` son snapshots independientes del producto actual. HU13 es solo de consulta; no se agrega asignación de ubicaciones. `detalle_venta.precio_unitario` y `detalle_venta.subtotal` no se modifican para implementar HU11/HU12.

## 3. Historial de cambios de precio

| Campo | Tipo | Nulabilidad | Propósito |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` autoincremental | No | Identificador del evento. |
| `id_producto` | `INT UNSIGNED` | Sí | FK al producto mientras exista; se hace `NULL` al eliminar el producto. |
| `codigo_producto` | `INT UNSIGNED` | No | Snapshot del ID/código visible cuando ocurrió el cambio. Sin FK para conservar el dato histórico. |
| `nombre_producto` | `VARCHAR(150)` | No | Snapshot del nombre del producto en el momento del cambio. |
| `precio_anterior` | `DECIMAL(10,2)` | Sí | Precio anterior. `NULL` representa que el producto no tenía precio asignado. |
| `precio_nuevo` | `DECIMAL(10,2)` | Sí | Precio nuevo. Nullable para poder auditar fielmente el vaciado del precio desde la edición general existente. HU11 solo permitirá un valor positivo. |
| `area_responsable` | `VARCHAR(50)` | No | Área que realizó el cambio; el servidor escribe `Administrador` para HU11. |
| `created_at` | `TIMESTAMP` | No | Fecha y hora del evento, con valor predeterminado del servidor de base de datos. |

### Claves, restricciones e índices aplicados
- PK en `id`.
- FK `id_producto` → `producto.id`, con `ON DELETE SET NULL` y `ON UPDATE CASCADE`. El historial no se elimina al borrar el producto.
- `idx_historial_precio_producto` en `id_producto`.
- `idx_historial_precio_codigo_fecha` en `(codigo_producto, created_at, id)`.
- `idx_historial_precio_fecha_id` en `(created_at, id)`.
- No se recomienda una FK desde `codigo_producto`: ese snapshot debe sobrevivir a la eliminación del producto.
- No agregar una restricción de positividad general a los importes históricos: la edición de catálogo actual admite `NULL`, cero y negativos, que deben poder registrarse si ese comportamiento se conserva. La regla de HU11 (nuevo precio > 0) debe imponerse en el servicio de aplicación. Si el equipo decide restringir también la edición del catálogo, debe aprobarse y documentarse como cambio de comportamiento.
- No existe unicidad sobre eventos de historial: un producto puede cambiar varias veces y más de un evento puede ocurrir en la misma marca temporal.

El snapshot `codigo_producto` y `nombre_producto` permite que HU12 siga mostrando el producto y código del evento aunque cambie el nombre o se elimine el producto. `id_producto` sirve para relacionar el registro con el producto todavía existente.

### Política para precio actual `NULL`
- HU11 mostrará «Sin precio asignado» como precio vigente.
- Se permitirá establecer un nuevo precio válido mayor que cero.
- `precio_anterior` queda `NULL`; el cambio se considera distinto y se registra en la misma transacción que la actualización.
- En HU12, un importe histórico `NULL` se presenta como «Sin precio asignado», no como cero.
- La edición general de catálogo permite vaciar `precio_venta`; su evento guarda `precio_nuevo = NULL`. La inserción de auditoría ocurre dentro de la transacción que actualiza el producto.

## 4. Ubicación única del producto

La tabla `producto` incluye:

| Campo | Tipo | Nulabilidad | Propósito |
|---|---|---|---|
| `pasillo` | `VARCHAR(50)` | Sí | Pasillo asignado. |
| `estante` | `VARCHAR(50)` | Sí | Estante dentro del pasillo. |

La longitud de 50 caracteres fue confirmada antes de migrar. Existe un índice en `pasillo` para las consultas de HU13.

La restricción `CHECK chk_producto_ubicacion_completa` exige que ambos campos sean nulos o que ambos tengan contenido no vacío.

No se necesita tabla separada ni FK para este alcance, porque la decisión es una ubicación única por producto. La consulta HU13 no autoriza por sí misma una pantalla para asignar o editar pasillo/estante. El mecanismo para poblar esos campos (formularios existentes u otra herramienta aprobada) sigue pendiente.

## 5. Comportamiento de borrado y preservación

- `historial_precio.id_producto` nullable con `ON DELETE SET NULL` permite conservar filas de auditoría y sus snapshots sin bloquear la eliminación de un producto que actualmente no tenga ventas.
- No usar `ON DELETE CASCADE` desde producto al historial: eliminaría evidencia requerida por HU12.
- No cambiar las FK existentes de `detalle_venta` ni las reglas de eliminación de Sprint 2.
- Si se requiere navegar desde el historial al producto actual, hacerlo solo cuando `id_producto` no sea `NULL`; la presentación histórica seguirá usando los snapshots.

## 6. Migración y despliegue

La migración aditiva indicada en la sección 1 ya fue aplicada a la base objetivo y verificada. No se debe volver a ejecutar contra una base ya migrada ni utilizar `database/init_db.php` para actualizar una base existente. En instalaciones nuevas, `database/schema.sql` contiene el esquema actual.

La migración no cambia productos, ventas ni detalles existentes y no asigna ubicaciones.

## 7. Verificación
- HU11 registra precio anterior/nuevo, código/nombre snapshot y área; las 40 comprobaciones de `database/test_price_updates.php` pasan.
- Se verificaron cambios desde HU11 y desde catálogo, ambos sentidos de transición `NULL`, rollback al fallar la auditoría y conservación del precio histórico de venta.
- Las suites de regresión de Sprints 1 y 2 y el validador del esquema pasan.
- HU12 consulta por snapshot, ordena por fecha e ID descendentes, valida filtros de producto y fechas; sus 21 comprobaciones pasan, incluida la conservación del historial tras eliminar un fixture de producto.
- HU13 consulta productos con categoría, ubicación y stock; sus 24 comprobaciones pasan, incluidas búsquedas, filtros combinados, ausencia de ubicación y preservación de datos.
- Los fixtures son ficticios y se revierten en transacciones. MariaDB puede avanzar contadores `AUTO_INCREMENT` aunque la transacción se revierta; no quedan filas de prueba confirmadas.
