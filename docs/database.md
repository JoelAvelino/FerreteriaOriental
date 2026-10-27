# Modelo de datos — Ferretería Oriental

## Estado
Base técnica implementada y validada en MariaDB de XAMPP. El esquema completo de referencia está en `database/schema.sql`; para bases existentes, los cambios de Sprint 2 se aplican mediante migraciones aditivas en `database/migrations/`, no mediante una reinicialización.

## Entidades contempladas
- `producto`: información del producto y sus referencias a categoría, marca y proveedor.
- `categoria`: id, nombre y descripción, según la historia HU03.
- `marca`: id, nombre y país de origen, según la historia HU04.
- `proveedor`: id, nombre, contacto, teléfono y correo, según la historia HU05.
- `cliente`: id, nombre, teléfono, correo, dirección y marcas de tiempo. El nombre es obligatorio; teléfono, correo y dirección son opcionales.
- `metodo_pago`: id, nombre, descripción y marcas de tiempo. El nombre es obligatorio y la descripción es opcional.
- `venta`: id, folio, cliente, método de pago, total y fecha de creación.
- `detalle_venta`: id, venta, producto, cantidad, precio unitario histórico y subtotal.

## Relaciones implementadas
- Una categoría puede clasificar varios productos.
- Una marca puede asociarse con varios productos.
- Un proveedor puede suministrar varios productos.
- Cada producto referencia una categoría, una marca y un proveedor.
- Cada venta referencia un cliente y un método de pago. Cada detalle referencia su venta y un producto.

## Campos de producto definidos para la base técnica
- Identificador.
- Nombre y descripción.
- Precio de compra y precio de venta.
- Stock actual.
- Referencias a categoría, marca y proveedor.
- Fechas de creación y actualización.

## Integridad
- Claves primarias definidas con autoincremental.
- Claves foráneas en `producto` para `categoria`, `marca` y `proveedor`.
- Reglas de unicidad por nombre en categoría, marca y proveedor.
- Índices únicos por correo y teléfono en `cliente` (la regla aprobada considera duplicado cualquiera de esos contactos; el teléfono se almacena normalizado) y por nombre en `metodo_pago`.
- Índice único para el folio y para el par venta/producto. Las relaciones de venta y detalle usan `ON DELETE RESTRICT` para preservar historial.
- Cantidades e importes de venta positivos se validan en servidor y cuentan con restricciones en el esquema.
- Restricción de eliminación sobre entidades relacionadas para proteger integridad referencial.

## Nota
La base técnica se implementó con la estructura aprobada para Sprint 1 y se validó que existen las tres relaciones foráneas esperadas: producto -> categoria, producto -> marca y producto -> proveedor.

El módulo de registro de productos usa este esquema existente: valida que los identificadores de categoría, marca y proveedor existan antes de insertar, y deja las claves foráneas de MySQL como protección de integridad adicional. No se alteró la estructura de tablas.

Las migraciones `20261026_create_client_and_payment_method.sql` y `20261027_customer_contact_uniqueness.sql` agregan clientes y métodos de pago y aplican la regla de duplicidad confirmada. Se comprobó que la tabla de clientes estaba vacía antes de cambiar sus índices. Si se aplica la segunda migración a una base ya poblada, primero hay que comprobar correos y teléfonos duplicados; la migración no borra filas y falla si los índices únicos no pueden crearse.

La migración `20261028_create_sales_tables.sql` agrega ventas y detalle, con folio único, relaciones restrictivas y precio histórico. El folio se forma con `V-` seguido del identificador secuencial de venta rellenado a seis dígitos; un rollback puede dejar saltos en la secuencia. El precio unitario de cada detalle conserva el importe utilizado al registrar la venta.

El esquema desplegado tiene ocho tablas y siete claves foráneas. El registro de venta y el descuento de inventario se ejecutan en una única transacción; la fila del producto se bloquea antes de comprobar existencias y el descuento incluye una condición adicional para impedir stock negativo. HU10 no se implementa en esta fase.
