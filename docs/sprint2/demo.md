# Guía de demostración — Sprint 2

## Alcance y entorno

Esta guía cubre HU06, HU07, HU08, HU09 y HU10 en la aplicación PHP/PDO de Ferretería Oriental. Está preparada para la instancia MariaDB de XAMPP configurada en el puerto `3307` y la base `ferreteria_oriental`.

La guía usa únicamente datos ficticios. No se debe ejecutar `database/init_db.php` para preparar la demostración. Antes de empezar, confirma que Apache y MariaDB de XAMPP estén activos y que `php database/validate_db.php` termine con `Estado: OK`.

Usa una etiqueta de ejecución única para que los registros sean identificables. Ejemplo:

```text
DEMO-S2-20261026-2200
```

En cada ejecución, reemplaza la etiqueta en todos los campos por una nueva. Los ejemplos de nombres, correo, teléfono, productos, precios y cantidades son datos ficticios de demostración.

## 1. Registrar un cliente y mostrar duplicados — HU07

1. Abre `http://127.0.0.1/FerreteriaOriental/?modulo=clientes`.
2. Registra el siguiente cliente, sustituyendo la etiqueta:
   - Nombre: `DEMO-S2-20261026-2200 Cliente`
   - Teléfono: `+1 (202) 555-0147`
   - Correo: `demo-DEMO-S2-20261026-2200@example.test`
   - Dirección: `Dirección ficticia de demostración`
3. Comprueba el mensaje de éxito y que el cliente aparece en el listado.
4. Intenta guardar otro cliente con un nombre distinto y el mismo correo. Debe rechazarse con un mensaje de correo duplicado.
5. Intenta guardar otro cliente con un correo distinto y el mismo teléfono, aunque uses una presentación equivalente, por ejemplo `+12025550147`. Debe rechazarse porque el teléfono se normaliza al quitar espacios y puntuación, conservando el prefijo `+`.
6. El nombre no es la clave de duplicidad: nombres repetidos con correo y teléfono distintos se permiten.

## 2. Registrar un método de pago y mostrar duplicados — HU09

1. Abre `http://127.0.0.1/FerreteriaOriental/?modulo=metodos_pago`.
2. Registra:
   - Nombre: `DEMO-S2-20261026-2200 Pago ficticio`
   - Descripción: `Método ficticio para demostración`
3. Comprueba que aparece en el listado.
4. Intenta registrar el mismo nombre de nuevo. Debe rechazarse como duplicado.

## 3. Crear productos ficticios para la venta

Usa la navegación de la aplicación para crear registros temporales relacionados. No reutilices nombres de marcas, proveedores ni productos reales.

1. En Categorías, registra `DEMO-S2-20261026-2200 Categoría`.
2. En Marcas, registra `DEMO-S2-20261026-2200 Marca` y un país de origen ficticio, por ejemplo `Demostración`.
3. En Proveedores, registra `DEMO-S2-20261026-2200 Proveedor`, un contacto ficticio, un teléfono ficticio y un correo `@example.test`.
4. En Productos, registra estos dos productos ficticios usando la categoría, marca y proveedor creados:

| Nombre | Precio de venta | Stock inicial |
|---|---:|---:|
| `DEMO-S2-20261026-2200 Producto A` | 12.50 | 5 |
| `DEMO-S2-20261026-2200 Producto B` | 3.25 | 4 |

5. Anota los nombres exactos usados y comprueba que los productos aparecen en el catálogo. Las cantidades y precios son exclusivamente de prueba.

## 4. Registrar una venta con varios productos

1. Abre `http://127.0.0.1/FerreteriaOriental/?modulo=ventas`.
2. Selecciona el cliente y el método de pago de demostración.
3. Agrega el Producto A con cantidad `2` y el Producto B con cantidad `1`.
4. Comprueba la vista previa de los subtotales: `25.00` y `3.25`; el total estimado debe ser `28.25`.
5. Registra la venta y anota el folio mostrado (formato `V-` seguido de una secuencia numérica).
6. En el catálogo, verifica que el stock del Producto A pasó de `5` a `3` y el del Producto B de `4` a `3`.
7. El cálculo definitivo se realiza en el servidor; el precio unitario aplicado queda guardado en cada detalle.

## 5. Consultar el historial y detalle de venta — HU10

1. Abre `http://127.0.0.1/FerreteriaOriental/?modulo=historial_ventas`.
2. Comprueba que el historial muestra folio, fecha, cliente y total; selecciona **Consultar detalle** para la venta ficticia creada en el paso anterior.
3. Verifica los productos, cantidades, precios unitarios históricos, subtotales, total y método de pago. Los importes deben coincidir con los guardados al registrar la venta.
4. Si necesitas demostrar que el precio es histórico, modifica temporalmente solo el precio de uno de los productos ficticios y vuelve a consultar el folio: el detalle debe conservar el precio registrado en la venta. Restaura después el precio del producto de demostración; no cambies el stock.
5. Prueba `?modulo=historial_ventas&folio=folio-invalido` y `?modulo=historial_ventas&folio=V-999999999999`. La página debe explicar respectivamente que el formato no es válido y que no existe una venta con ese folio.
6. En una base de demostración sin ventas, el historial debe indicar que aún no hay ventas registradas.

Las operaciones de historial y detalle son exclusivamente de lectura; la verificación no debe cambiar filas de ventas, detalles ni existencias.

## 6. Rechazar una venta sin stock suficiente

1. En el formulario de ventas, selecciona el mismo cliente, método y Producto A.
2. Solicita cantidad `99`, que supera las `3` unidades disponibles.
3. Envía el formulario. La venta debe rechazarse y el mensaje debe indicar el stock disponible.
4. Comprueba que el stock permanece en `3` y que no se genera una nueva venta/folio.

## 7. Demostrar restricciones de eliminación — HU06

En las siguientes pruebas confirma la acción del navegador para que la solicitud llegue al servidor. Las operaciones deben terminar rechazadas por las relaciones de integridad; no se elimina ningún registro vinculado.

1. En Categorías, intenta eliminar `DEMO-S2-20261026-2200 Categoría`. Debe indicar que tiene productos asociados.
2. En Proveedores, intenta eliminar `DEMO-S2-20261026-2200 Proveedor`. Debe indicar que tiene productos asociados.
3. En Productos, intenta eliminar el Producto A. Como ya aparece en el detalle de la venta, debe indicar que no se puede eliminar un producto con ventas asociadas.
4. Confirma que los tres registros siguen apareciendo. No elimines registros reales para esta demostración.

## 8. Verificar regresión del Sprint 1

1. Abre el catálogo de Productos y busca el nombre del Producto A de demostración.
2. Filtra por la categoría ficticia y confirma que se muestran los productos correspondientes.
3. Abre la edición del Producto A. Cambia únicamente la descripción a `Edición temporal de demostración`, guarda y confirma que se refleja en el catálogo.
4. Vuelve a editar el Producto A y restaura la descripción anterior (vacía si así se creó). No cambies stock, precio, categoría, marca ni proveedor después de registrar la venta.
5. Confirma que la búsqueda, los filtros y la edición siguen disponibles para el catálogo.

## Limpieza manual opcional

La preparación y las pruebas automatizadas no requieren limpiar registros reales. Esta limpieza es solo para los registros ficticios creados manualmente con esta guía; **no se ejecuta automáticamente**. No la ejecutes hasta terminar la demostración y revisar todos los resultados previos.

1. En phpMyAdmin conectado a `127.0.0.1:3307`, reemplaza `@tag` por la etiqueta exacta de esta ejecución. Reemplaza el ejemplo del correo si se usó otro.
2. Ejecuta las consultas de previsualización y confirma que cada identificador corresponde exclusivamente a los registros de demostración. Si falta un ID, aparece más de un registro inesperado o hay una relación no prevista, detente y no ejecutes la transacción.
3. Comprueba que los productos ficticios no tengan ventas realizadas por otros clientes; que la categoría, el proveedor y la marca no estén asociados con otros productos; y que el método de pago no se use en ventas de otros clientes. Si alguna consulta muestra filas fuera de esta demostración, no continúes.
4. Ejecuta el bloque transaccional en una sola sesión. Revisa las filas afectadas antes de confirmar. Si cualquier sentencia falla o el resultado no coincide con los registros de demostración, ejecuta `ROLLBACK`; no intentes forzar la eliminación ni desactives claves foráneas.
5. Ejecuta `COMMIT` solo cuando el preflight fue correcto y todos los registros afectados sean los previstos. La actualización de stock suma únicamente las cantidades detalladas en las ventas del cliente ficticio, antes de borrar esos detalles y ventas.

Previsualización (solo lectura):

```sql
SET @tag = 'DEMO-S2-20261026-2200';
SET @correo_demo = CONCAT('demo-', @tag, '@example.test');
SET @cliente_demo = (SELECT id FROM cliente WHERE correo = @correo_demo);
SET @pago_demo = (SELECT id FROM metodo_pago WHERE nombre = CONCAT(@tag, ' Pago ficticio'));
SET @categoria_demo = (SELECT id FROM categoria WHERE nombre = CONCAT(@tag, ' Categoría'));
SET @marca_demo = (SELECT id FROM marca WHERE nombre = CONCAT(@tag, ' Marca'));
SET @proveedor_demo = (SELECT id FROM proveedor WHERE nombre = CONCAT(@tag, ' Proveedor'));
SET @producto_a_demo = (SELECT id FROM producto WHERE nombre = CONCAT(@tag, ' Producto A'));
SET @producto_b_demo = (SELECT id FROM producto WHERE nombre = CONCAT(@tag, ' Producto B'));

SELECT @cliente_demo AS cliente_id, @pago_demo AS metodo_pago_id,
       @categoria_demo AS categoria_id, @marca_demo AS marca_id,
       @proveedor_demo AS proveedor_id,
       @producto_a_demo AS producto_a_id, @producto_b_demo AS producto_b_id;

SELECT v.id, v.folio, v.id_cliente, v.id_metodo_pago,
       d.id_producto, d.cantidad
FROM venta v
LEFT JOIN detalle_venta d ON d.id_venta = v.id
WHERE v.id_cliente = @cliente_demo;

SELECT v.folio, p.id, p.nombre
FROM producto p
JOIN detalle_venta d ON d.id_producto = p.id
JOIN venta v ON v.id = d.id_venta
WHERE p.id IN (@producto_a_demo, @producto_b_demo)
  AND v.id_cliente <> @cliente_demo;

SELECT v.folio, d.id_producto, p.nombre
FROM venta v
JOIN detalle_venta d ON d.id_venta = v.id
JOIN producto p ON p.id = d.id_producto
WHERE v.id_cliente = @cliente_demo
  AND d.id_producto NOT IN (@producto_a_demo, @producto_b_demo);

SELECT id, nombre FROM producto
WHERE (id_categoria = @categoria_demo OR id_proveedor = @proveedor_demo OR id_marca = @marca_demo)
  AND id NOT IN (@producto_a_demo, @producto_b_demo);

SELECT v.folio, v.id_cliente, v.id_metodo_pago
FROM venta v
WHERE v.id_metodo_pago = @pago_demo
  AND v.id_cliente <> @cliente_demo;
```

Si todos los IDs corresponden a esta ejecución y las tres últimas consultas no muestran relaciones externas a la demo, el siguiente bloque puede usarse para limpiar. Si se hizo más de una venta con el cliente demo, restaura y elimina todas sus ventas de demostración.

```sql
START TRANSACTION;

UPDATE producto p
JOIN (
    SELECT d.id_producto, SUM(d.cantidad) AS cantidad
    FROM detalle_venta d
    JOIN venta v ON v.id = d.id_venta
    WHERE v.id_cliente = @cliente_demo
      AND d.id_producto IN (@producto_a_demo, @producto_b_demo)
    GROUP BY d.id_producto
) demo_ventas ON demo_ventas.id_producto = p.id
SET p.stock = p.stock + demo_ventas.cantidad;

DELETE d
FROM detalle_venta d
JOIN venta v ON v.id = d.id_venta
WHERE v.id_cliente = @cliente_demo;

DELETE FROM venta WHERE id_cliente = @cliente_demo;
DELETE FROM producto WHERE id IN (@producto_a_demo, @producto_b_demo);
DELETE FROM categoria WHERE id = @categoria_demo;
DELETE FROM proveedor WHERE id = @proveedor_demo;
DELETE FROM marca WHERE id = @marca_demo;
DELETE FROM cliente WHERE id = @cliente_demo;
DELETE FROM metodo_pago WHERE id = @pago_demo;

-- Revisa las filas afectadas. Confirma solo si son las de esta etiqueta:
-- COMMIT;
-- Si hay cualquier discrepancia, ejecuta ROLLBACK; en lugar de confirmar.
```

## Verificación de la guía

Esta guía se contrastó con la navegación, formularios, mensajes, reglas de integridad y pruebas de aceptación vigentes. La demostración manual y la limpieza no se ejecutaron al preparar esta documentación; las pruebas automatizadas usan datos ficticios dentro de transacciones revertidas.
