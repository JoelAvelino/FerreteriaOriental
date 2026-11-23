# Sprint 3 – Diseño técnico de desarrollo

## 1. Propósito, alcance y decisiones confirmadas
Este documento describe la implementación de HU11, HU12 y HU13 en la arquitectura existente de Ferretería Oriental. La migración de MariaDB fue aplicada y verificada antes del desarrollo; no se ejecutó DDL durante estas historias.

Decisiones del equipo:
- El ID de `producto` será el código visible y criterio de búsqueda.
- Para cambios de precio, el área responsable es `Administrador`. No se agregará autenticación o gestión de usuarios.
- Cada producto tendrá una única ubicación formada por pasillo y estante.
- El historial conserva snapshots de código y nombre, y admite precios anterior/nuevo `NULL`.

## 2. Arquitectura actual y estrategia de integración
La aplicación usa `index.php` para enrutar módulos y solicitudes, servicios para validación/reglas, repositorios PDO para persistencia, y vistas PHP compartidas con CSS/JavaScript. Los puntos de referencia son `app/Services/CatalogService.php`, `app/Repositories/CatalogRepository.php`, `app/Services/SalesService.php`, `app/Repositories/SalesRepository.php` y las vistas bajo `app/Views/`.

- Reutilizar navegación modular, consultas preparadas, validación de servidor, protección CSRF, sesiones para mensajes flash, escape de salida, alertas y tablas existentes.
- Integrar el registro de cambios de precio con la actualización de producto ya existente. No dejar una ruta que pueda cambiar `precio_venta` sin generar auditoría.
- Mantener desacoplado el precio histórico de venta: no modificar `detalle_venta.precio_unitario` ni recalcular ventas anteriores.
- Añadir servicios/repositorios separados solo si simplifican la lógica. Como opción preferida, mantener el flujo de auditoría de precios en una unidad coherente (servicio/repositorio de precios o métodos acotados existentes) y reutilizarla desde HU11 y desde la edición del catálogo.
- HU13 es de consulta: no agrega alta, edición o eliminación, ni puebla pasillo/estante. Cualquier mecanismo futuro para asignar ubicaciones queda fuera de este alcance.

## 3. HU11 – Actualizar precio (prioridad alta)

### Flujo
1. Añadir a la navegación «Precios» y su ruta de módulo.
2. Aceptar búsqueda por ID exacto (código) o por nombre; consultas parametrizadas.
3. Mostrar ID, nombre y precio vigente. Si el valor es `NULL`, presentar «Sin precio asignado».
4. Capturar y validar el nuevo precio en servidor.
5. Leer/bloquear el producto vigente, comparar el precio, actualizar `producto.precio_venta` e insertar el registro histórico dentro de una transacción.
6. Confirmar al usuario solo tras completar ambas escrituras; ante fallo, rollback.
7. Cancelar vuelve a la consulta sin enviar una actualización.

### Validación y auditoría
- HU11 admite únicamente un decimal con precisión compatible con `DECIMAL(10,2)`, estrictamente mayor que cero y distinto del precio vigente.
- Se rechazan vacío, formato no numérico, cero, negativo, exceso de precisión/rango y valor igual al actual.
- Si el precio vigente es `NULL`, se permite asignar un precio válido y se conserva `NULL` como precio anterior en el historial.
- El área `Administrador` la fija el servidor; no se recibe como un valor editable del formulario.
- La actualización del producto y la inserción de auditoría son atómicas. La lectura del valor actual y su modificación deben protegerse contra actualizaciones concurrentes (por ejemplo, bloqueo de fila dentro de la transacción).

### Edición del catálogo
La edición existente de productos también modifica `precio_venta`, y hoy permite valores vacíos y decimales negativos o cero. Debe usar la misma ruta transaccional de auditoría. Para preservar el comportamiento actual del catálogo, el historial debe poder reflejar valores `NULL`, cero o negativos que provengan de esa ruta; la validación estricta de HU11 se mantiene específica de su formulario. Si el equipo desea prohibir esos valores también en edición, debe aprobarse como un cambio de comportamiento explícito.

Los cambios de otros campos del producto no generan eventos de precio. Si el precio no cambió, no se inserta una fila histórica.

## 4. HU12 – Historial de precios (prioridad media)

- El módulo navegable «Historial de precios» es de solo lectura y reutiliza los componentes visuales existentes.
- `PriceHistoryRepository` consulta los snapshots sin unirlos con `producto`, de modo que siguen disponibles después de eliminar el producto.
- Los resultados se ordenan por `created_at DESC, id DESC`.
- Los filtros GET permiten código/ID o coincidencia parcial de nombre y rango inclusivo de fechas (hasta `23:59:59` del día final); el servidor valida fechas y rechaza rangos invertidos.
- Las consultas usan PDO preparado y escapan los comodines de nombre.
- La pantalla muestra fecha/hora, código, snapshot del nombre, precios y responsable; los valores nulos se explican con mensajes específicos.
- El estado sin coincidencias muestra «No se encontraron cambios de precio». No existen operaciones de escritura, edición o eliminación.

Los snapshots del ID y nombre permiten mostrar el contexto histórico incluso si el producto cambia de nombre o se elimina. El enlace a un producto aún existente puede mantenerse mediante una FK nullable.

## 5. HU13 – Consulta de ubicaciones (prioridad media)

- El módulo «Ubicaciones» está disponible desde la navegación general y es de solo lectura.
- `CatalogRepository::searchProductsForLocations` consulta `producto.id`, `nombre`, `pasillo`, `estante`, `stock` y `categoria.nombre` mediante la relación existente `producto.id_categoria`.
- `CatalogService::validateLocationFilters` valida la búsqueda por ID/nombre y el pasillo; los filtros se pueden combinar.
- La búsqueda admite ID exacto y coincidencia parcial de nombre; los comodines se escapan y los valores se pasan mediante parámetros PDO.
- El listado general incluye productos sin ubicación. Si pasillo o estante están ausentes o vacíos, la vista muestra «Sin ubicación».
- Sin coincidencias, la vista muestra «No se encontraron productos».
- La pantalla no ofrece alta, edición ni eliminación de ubicaciones y no asigna valores automáticamente.

## 6. Esquema de base de datos
Consultar `docs/sprint3/database.md` para las definiciones y restricciones aplicadas. La tabla `producto` ya contiene `pasillo` y `estante` (`VARCHAR(50) NULL`) y el índice de pasillo; la relación de categoría es `producto.id_categoria` → `categoria.id`. No se ejecutó DDL ni se modificó el esquema durante HU13.

## 7. Archivos y componentes previstos
La implementación modificó:
- `index.php`: navegación y despacho GET de HU12 y HU13, además de las rutas HU11 existentes.
- `app/Services/CatalogService.php` y `app/Repositories/CatalogRepository.php`: navegación, auditoría HU11 y consulta/validación HU13.
- `app/Repositories/PriceHistoryRepository.php` y `app/Services/PriceHistoryService.php`: consulta y validación de filtros HU12.
- `app/Views/prices.php`, `app/Views/price_history.php` y `app/Views/locations.php`: pantallas de HU11, HU12 y HU13.
- `assets/css/styles.css` y/o `assets/js/app.js` solo si los estilos o interacciones existentes no bastan. La validación definitiva permanece en PHP.
- `database/test_price_updates.php`, `database/test_price_history.php` y `database/test_locations.php`: pruebas enfocadas de HU11–HU13.
- `README.md` y documentación de Sprint 3 para registrar el estado verificado.

No se prevén cambios en el flujo de escritura de ventas. `SalesService` debe seguir leyendo el precio vigente para ventas nuevas y `detalle_venta` debe conservar el precio guardado para ventas pasadas.

## 8. Pruebas y seguridad
- Probar validaciones de HU11, cambio desde precio `NULL`, igualdad, precisión, búsqueda y rollback al fallar la inserción de auditoría.
- Probar que la edición de catálogo registre cualquier cambio de precio y que los cambios en otros campos no generen historial.
- Probar uso del nuevo precio en ventas futuras y conservación de los detalles anteriores.
- HU12: `database/test_price_history.php` verifica consulta completa, orden estable, filtros por producto/fecha combinados, validación de fechas, snapshots tras borrar el producto, valores nulos, estado vacío y ausencia de escrituras persistentes.
- HU13: `database/test_locations.php` verifica listado, búsquedas, filtro combinado por pasillo, ubicaciones ausentes, seguridad y preservación de datos.
- Ejecutar suites de regresión de Sprints 1 y 2, lint PHP y comprobaciones JavaScript aplicables.
- Usar fixtures identificables y reversibles; no alterar datos reales.

## 9. Fuera de alcance y dependencias
- No implementar login, permisos generales ni módulo de gestión de usuarios.
- No implementar movimientos de inventario ni administración completa de almacén.
- No añadir CRUD de ubicaciones sin aprobación.
- No cambiar el comportamiento de ventas históricas.
- UC01, UC18 y UC19 se deben consultar si el equipo tiene definiciones adicionales.
- HU11, HU12 y HU13 están implementadas; la asignación de ubicaciones permanece fuera de alcance.
