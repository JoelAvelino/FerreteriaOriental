# Tareas técnicas — Sprint 1

## Estado real
Las funcionalidades de datos y las historias HU01–HU07 están implementadas y tienen evidencia de prueba automatizada o de integración HTTP/MySQL. No se ejecutó inicialización ni se borraron datos durante esta revisión. Los wireframes solicitados siguen pendientes.

| ID | Tarea | Historia relacionada | Estado |
|---|---|---|---|
| T01 | Confirmar stack, alcance y definición de terminado | Todas | Terminado |
| T02 | Diseñar modelo entidad-relación y acordar campos | HU03–HU06 y productos | Terminado |
| T03 | Diseñar wireframe del catálogo de productos en Figma | HU01 | Pendiente |
| T04 | Desarrollar consulta de productos con búsqueda y filtros | HU01 | Terminado |
| T05 | Construir pantalla del catálogo | HU01 | Terminado |
| T06 | Diseñar wireframe de edición de producto en Figma | HU02 | Pendiente |
| T07 | Desarrollar actualización de producto con validaciones | HU02 | Terminado |
| T08 | Construir formulario de edición | HU02 | Terminado |
| T09 | Diseñar wireframe de registro de categoría | HU03 | Pendiente |
| T10 | Crear tabla categoría (id, nombre, descripción) | HU03 | Terminado |
| T11 | Desarrollar registro y listado de categorías | HU03 | Terminado |
| T12 | Construir pantalla de categorías con validaciones | HU03 | Terminado |
| T13 | Diseñar wireframe de registro de marca | HU04 | Pendiente |
| T14 | Crear tabla marca (id, nombre, país de origen) | HU04 | Terminado |
| T15 | Desarrollar registro y listado de marcas | HU04 | Terminado |
| T16 | Construir pantalla de marcas | HU04 | Terminado |
| T17 | Diseñar wireframe de registro de proveedor | HU05 | Pendiente |
| T18 | Crear tabla proveedor (nombre, contacto, teléfono, correo) | HU05 | Terminado |
| T19 | Desarrollar registro y listado de proveedores | HU05 | Terminado |
| T20 | Construir pantalla de proveedores | HU05 | Terminado |
| T21 | Agregar id_marca e id_proveedor como llaves foráneas de producto | HU06 | Terminado |
| T22 | Desarrollar asociación de marca y proveedor con producto | HU06 | Terminado |
| T23 | Agregar listas desplegables de marca y proveedor al formulario de producto | HU06 | Terminado |
| T24 | Confirmar/crear historia para registrar productos si aún no existe | HU07 | Terminado |
| T25 | Integrar funcionalidades y ejecutar pruebas de aceptación | Todas las incluidas | Terminado |
| T26 | Desarrollar registro de productos con los campos documentados y sus validaciones | HU07 | Terminado |

## Evidencia de avance y pruebas
- HU03–HU05: registro y consulta de categorías, marcas y proveedores con validaciones y persistencia PDO implementados y probados.
- HU07: registro de producto implementado con categoría, marca y proveedor existentes; se conservan sus identificadores y el esquema MySQL permanece sin cambios.
- HU06: el formulario y el servidor validan las tres relaciones antes del guardado; las claves foráneas MySQL actúan como protección adicional.
- HU01: el catálogo presenta los campos acordados y las relaciones; la búsqueda opera por nombre/descripción y los filtros por categoría, marca y proveedor.
- HU02: se carga un producto existente, se validan y guardan sus campos y relaciones mediante PDO, y el catálogo refleja los cambios.
- PHP lint: sin errores en los archivos `.php`.
- `php database/test_catalog_modules.php`: 49 comprobaciones OK, incluyendo registro/consulta de categorías, marcas y proveedores, duplicados, búsqueda/filtros, carga y actualización de productos, validaciones y rechazo por FK; datos de prueba en transacción revertida.
- Prueba HTTP/MySQL de extremo a extremo: se registraron fixtures temporales, se creó un producto, se consultó con sus relaciones, se abrió con datos actuales, se actualizó y se encontró mediante búsqueda/filtros; se eliminaron las fixtures al finalizar.
- Prueba HTTP local: las páginas y los recursos CSS/JS respondieron correctamente.
- Revisión final no destructiva: las cuatro rutas respondieron HTTP 200 sin warnings; el formulario cargó un producto real de MySQL; un POST inválido, un nombre duplicado y una FK inexistente mostraron errores sin cambiar el número de filas.
- Prueba de duplicados en navegador: los nombres existentes de categoría, marca y proveedor quedaron bloqueados por validación HTML/JavaScript con mensaje visible; en HTTP el POST duplicado también se rechazó sin modificar filas.
- Pruebas HTTP de lectura contra los datos existentes: el catálogo filtró el producto `Martillo` y mostró categoría `Herramientas`, marca `Truper` y proveedor `Truper Provedor`; el formulario de edición cargó los datos actuales del producto ID 21. No se envió una actualización al registro real para evitar alterar datos de usuario.
- No se ejecutó el flujo HTTP de alta válida en esta revisión final para no crear registros persistentes. La persistencia de altas y la actualización sí están cubiertas por la suite PDO con transacción revertida y por la prueba de extremo a extremo temporal registrada arriba.
- Consulta de solo lectura confirmó las tres claves foráneas exactas y cero productos huérfanos. No se ejecutó `database/init_db.php` ni se reinicializó la base de datos durante la revisión final.
- Validación visual: HTML rechazó campos obligatorios vacíos y aceptó nombre más relaciones seleccionadas en productos; JavaScript actualizó contadores y bloqueó nombres duplicados para categoría, marca y proveedor.
- `php database/validate_db.php`: la base de datos conserva las cuatro tablas y las tres claves foráneas verificadas.
- Las tareas T03, T06, T09, T13 y T17 (wireframes Figma) permanecen pendientes; no se bloquearon las vistas funcionales por ello.

## Secuencia técnica recomendada
1. Confirmar el stack y revisar el repositorio.
2. Acordar campos y modelo de datos.
3. Crear tablas y relaciones base.
4. Implementar registro/listado de categorías, marcas y proveedores.
5. Registrar productos y gestionar sus relaciones.
6. Consultar, filtrar y editar productos.
7. Integrar, probar y preparar la demostración.

La secuencia es orientativa: el equipo puede adaptarla a las dependencias y al alcance aprobado.

## Reglas para actualizar el estado
Usar únicamente estos estados: `Pendiente`, `En progreso`, `En revisión`, `Terminado` y `Bloqueado`. Cambiar el estado solo cuando refleje el avance real.

## Condición para terminar una tarea
La implementación está integrada, cumple sus criterios de aceptación y las pruebas correspondientes se ejecutaron. Si las pruebas no se pudieron ejecutar, documentar el motivo; no marcar la tarea como terminada sin aclararlo.

## Estimaciones originales
El documento inicial señala: HU01 = 2 días, HU02 = 2 días, HU03 = 1 día, HU04 = 1 día, HU05 = 2 días y HU06 = 3 días. Total aritmético original: 11 días. HU07 fue aprobada después y no tiene estimación documentada. Confirmar con el equipo qué representan las estimaciones originales y si incluyen pruebas, integración y revisión.
