# Sprint 3 – Requisitos de desarrollo

## 1. Objetivo
Implementar HU11, HU12 y HU13 en el proyecto existente Ferretería Oriental, conservando la arquitectura, convenciones y funcionalidades de los Sprints 1 y 2. El alcance es programación y desarrollo; no incluye diseño en Figma.

## 2. Historias de usuario

### HU11 – Actualizar precio de un producto
- **Actor:** Administrador
- **Prioridad:** Alta
- **Precondiciones:** El administrador tiene acceso al sistema y existe al menos un producto registrado.

**Flujo principal**
1. Ingresar al módulo «Precios».
2. Buscar el producto por su ID (código visible) o nombre (UC01).
3. Mostrar el precio actual.
4. Capturar el nuevo precio y presionar «Guardar nuevo precio».
5. Validar el precio, actualizar el producto, registrar el cambio en el historial (UC18) y mostrar confirmación.

**Flujos alternos**
- Precio vacío, no numérico, igual a cero, negativo o igual al precio actual: rechazar según UC19.
- Producto no encontrado: mostrar «No se encontraron productos».
- Cancelar: no modificar el precio.

**Postcondición:** El nuevo precio queda vigente para ventas posteriores y el cambio queda registrado.

**Criterios de aceptación**
- Búsqueda por ID (código visible) o nombre.
- Mostrar el precio vigente antes de modificar.
- Aceptar únicamente precio numérico mayor que cero y diferente al actual.
- Actualizar precio e historial en una misma transacción; si falla el historial, revertir también el precio.
- Cancelar no modifica datos.
- Las ventas posteriores utilizan el precio actualizado; las ventas anteriores conservan su precio histórico.

### HU12 – Consultar historial de cambios de precio
- **Actor:** Administrador
- **Prioridad:** Media
- **Precondición:** Existe al menos un cambio de precio registrado.

**Flujo principal**
1. Ingresar a «Historial de precios».
2. Mostrar cambios del más reciente al más antiguo, con fecha y hora, producto identificado por su ID y nombre, precio anterior, precio nuevo y área responsable.
3. Permitir filtrar por producto o rango de fechas.
4. Actualizar el listado con los resultados del filtro.

**Flujo alterno:** Sin resultados, mostrar «No se encontraron cambios de precio».

**Criterios de aceptación**
- Mostrar fecha y hora, ID y nombre del producto, precio anterior, precio nuevo y área responsable.
- Orden descendente por fecha y hora.
- Filtros por producto y rango de fechas.
- Historial de consulta; no permitir editar o eliminar registros desde esta pantalla.

**Decisión aprobada:** El historial registra el área responsable del cambio. Para modificaciones realizadas desde HU11, el área será «Administrador». No se requiere autenticación individual para cumplir este criterio.

### HU13 – Consultar ubicación de productos
- **Actor:** Almacenista
- **Prioridad:** Media
- **Precondiciones:** El almacenista tiene acceso al sistema y existen productos registrados.

**Flujo principal**
1. Ingresar a «Ubicaciones».
2. Mostrar productos con ID (código visible), nombre, categoría, pasillo, estante y stock.
3. Permitir búsqueda por ID o nombre y filtro opcional por pasillo.
4. Mostrar los productos coincidentes con su pasillo y estante.

**Flujos alternos**
- Producto sin ubicación: mostrar «Sin ubicación».
- Sin resultados: mostrar «No se encontraron productos».

**Criterios de aceptación**
- Listar ID (código visible), nombre, categoría, pasillo, estante y stock.
- Buscar por ID o nombre y filtrar por pasillo.
- Mostrar «Sin ubicación» cuando corresponda.
- Cada producto tendrá una ubicación única compuesta por pasillo y estante.
- La asignación de pasillo y estante es una dependencia funcional pendiente de aprobación. Esta historia solo consulta ubicaciones; no incluye formularios para registrar, editar o eliminar ubicaciones.

## 3. Reglas generales
- Mantener PHP, MySQL/MariaDB, PDO y la arquitectura existente.
- Reutilizar servicios, repositorios, validaciones, protección CSRF, estilos y componentes actuales.
- Validar en servidor toda operación que modifique datos.
- Usar consultas preparadas.
- No afectar funcionalidades de Sprints 1 y 2.
- No alterar datos existentes durante pruebas.
- No implementar login por áreas ni permisos generales como parte de este Sprint, salvo aprobación expresa.
- No crear diseños en Figma.

## 4. Dependencias y decisiones pendientes
1. Confirmar cómo se asignarán pasillo y estante a los productos; no implementar gestión de ubicaciones dentro de HU13 sin aprobación.
2. Inspeccionar si la instancia de base de datos ya tiene una estructura equivalente de ubicación, pasillo y estante.
3. Confirmar el acceso al módulo de consulta para el almacenista sin ampliar el alcance a login individual o permisos generales.
4. HU11 referencia UC01, UC18 y UC19, pero sus definiciones no están en el documento recibido. Consultar documentación adicional antes de asumir su comportamiento.
