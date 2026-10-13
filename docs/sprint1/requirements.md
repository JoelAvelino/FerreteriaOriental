# Requisitos — Sprint 1

## Objetivo propuesto
Construir una primera versión funcional del módulo de administración del inventario de Ferretería Oriental, con productos y sus datos relacionados. El Product Owner y el equipo deben confirmar este objetivo y el alcance según la capacidad del sprint.

## Historias de usuario de origen

### HU01 — Consultar productos
**Historia:** Como administrador, quiero consultar los productos registrados, para conocer su información y disponibilidad.

**Prioridad original:** Alta.

**Criterios de aceptación propuestos**
- Se muestra una lista de productos existentes.
- Cada fila muestra los campos de producto acordados con el equipo, incluido el stock si forma parte del modelo.
- La búsqueda devuelve productos coincidentes por nombre o descripción.
- Los filtros por categoría, marca y proveedor restringen los resultados correctamente, conforme a las relaciones definidas en el modelo de datos.
- El listado muestra nombre, descripción, precios definidos, stock y los nombres relacionados de categoría, marca y proveedor.
- Si no hay productos o coincidencias, se muestra un estado vacío claro.
- Los errores de consulta se comunican sin cerrar inesperadamente la aplicación.

### HU02 — Modificar producto
**Historia:** Como administrador, quiero modificar la información de un producto, para mantener sus datos actualizados.

**Prioridad original:** Alta.

**Criterios de aceptación propuestos**
- El administrador puede seleccionar un producto existente y abrir su formulario de edición.
- Los campos se cargan con los valores actuales.
- Los datos obligatorios y formatos se validan antes de guardar.
- Las relaciones con categoría, marca y proveedor existentes se conservan o se actualizan con identificadores válidos.
- Al guardar correctamente, los cambios persisten y se reflejan en la consulta.
- Si el producto no existe o los datos no son válidos, se informa el problema y se conservan los datos ingresados cuando sea posible.

### HU03 — Registrar categorías
**Historia:** Como administrador, quiero registrar categorías, para clasificar los productos de manera organizada.

**Prioridad original:** Media.

**Criterios de aceptación propuestos**
- Se puede registrar una categoría con nombre y descripción.
- La categoría queda almacenada y aparece en el listado.
- Se validan los campos obligatorios.
- Se define y aplica una regla para evitar categorías duplicadas según el criterio acordado por el equipo.

### HU04 — Registrar marcas
**Historia:** Como administrador, quiero registrar marcas, incluyendo su país de origen, para identificar el fabricante de cada producto.

**Prioridad original:** Media.

**Criterios de aceptación propuestos**
- Se puede registrar una marca con nombre y país de origen.
- La marca queda almacenada y aparece en el listado.
- Se validan los campos obligatorios.
- Se define y aplica una regla para evitar duplicados según el criterio acordado.

### HU05 — Registrar proveedores
**Historia:** Como administrador, quiero registrar proveedores con sus datos de contacto, para mantener un control de quién suministra los productos.

**Prioridad original:** Alta.

**Criterios de aceptación propuestos**
- Se puede registrar un proveedor con los campos definidos por el equipo.
- El proveedor queda almacenado y aparece en el listado.
- Se validan campos obligatorios y formatos de correo/teléfono cuando correspondan.
- Se informa si ocurre un error al guardar.

**Campos mencionados en las tareas originales:** nombre, contacto, teléfono y correo. La tarea técnica también menciona una tabla proveedor.

### HU06 — Relacionar productos con marcas y proveedores
**Historia:** Como administrador, quiero relacionar cada producto con su marca y proveedor, para tener organizada la información del inventario.

**Prioridad original:** Alta.

**Criterios de aceptación propuestos**
- El formulario de producto permite seleccionar una marca y un proveedor existentes.
- Las asociaciones se guardan usando identificadores válidos.
- Al consultar o editar un producto, se muestran sus asociaciones.
- No se puede guardar una referencia a una marca o proveedor inexistente.

### HU07 — Registrar productos
**Historia aprobada:** Como administrador, quiero registrar productos con los datos definidos para el inventario y sus relaciones, para mantener productos disponibles para consulta y administración.

**Campos respaldados por el modelo de datos:** nombre, descripción, precio de compra, precio de venta, stock actual, categoría, marca y proveedor. El nombre y las tres referencias son obligatorios según el esquema. Descripción y precios admiten valor vacío; stock vacío usa el valor predeterminado `0` del esquema.

**Criterios de aceptación**
- Se puede registrar un producto con nombre y seleccionar una categoría, marca y proveedor existentes.
- Se pueden capturar descripción, precios y stock según los tipos ya definidos por el esquema MySQL.
- El servidor valida los datos y comprueba que las referencias seleccionadas existan antes de guardar.
- La base de datos conserva las claves foráneas como última protección de integridad referencial.
- Tras guardar, se informa el resultado; errores de validación o de integridad se comunican sin perder los datos enviados.

La incorporación de HU07 al Sprint 1 fue autorizada por el Product Owner antes de implementar el formulario. Los criterios de consulta, búsqueda y edición de productos pertenecen a HU01/HU02 y no forman parte de HU07.

## Fuera de alcance confirmado por ahora
No se debe asumir que este sprint incluye ventas, caja, impresión de tickets, compras, movimientos de almacén, reportes o cálculo de ganancias. Incorporarlos requiere confirmación explícita del Product Owner.

## Nota sobre los criterios
Los criterios anteriores son propuestas iniciales derivadas de las historias. Deben ser revisados y aprobados por el equipo antes de considerarlos requisitos definitivos.
