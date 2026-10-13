# Modelo de datos inicial — Ferretería Oriental

## Estado
Base técnica implementada y validada en el entorno XAMPP local. El esquema queda disponible en `database/schema.sql` y fue cargado correctamente en la base de datos `ferreteria_oriental`.

## Entidades contempladas
- `producto`: información del producto y sus referencias a categoría, marca y proveedor.
- `categoria`: id, nombre y descripción, según la historia HU03.
- `marca`: id, nombre y país de origen, según la historia HU04.
- `proveedor`: id, nombre, contacto, teléfono y correo, según la historia HU05.

## Relaciones implementadas
- Una categoría puede clasificar varios productos.
- Una marca puede asociarse con varios productos.
- Un proveedor puede suministrar varios productos.
- Cada producto referencia una categoría, una marca y un proveedor.

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
- Restricción de eliminación sobre entidades relacionadas para proteger integridad referencial.

## Nota
La base técnica se implementó con la estructura aprobada para Sprint 1 y se validó que existen las tres relaciones foráneas esperadas: producto -> categoria, producto -> marca y producto -> proveedor.

El módulo de registro de productos usa este esquema existente: valida que los identificadores de categoría, marca y proveedor existan antes de insertar, y deja las claves foráneas de MySQL como protección de integridad adicional. No se alteró la estructura de tablas.
