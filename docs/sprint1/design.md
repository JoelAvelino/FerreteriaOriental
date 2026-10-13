# Diseño — Sprint 1

## Propósito
Guiar el diseño de la solución y dejar preparada la base técnica para la siguiente fase del Sprint 1.

## Stack confirmado para esta implementación
- Lenguaje: PHP 8.x.
- Base de datos: MySQL en XAMPP.
- Tipo de aplicación: proyecto web local con arquitectura simple por capas.
- Estructura de carpetas: configuración, capa de acceso a datos y scripts de inicialización.
- Forma de ejecutar: PHP CLI para inicialización y validación del esquema.

## Decisiones cerradas para la base técnica
- Se confirma PHP + MySQL + XAMPP como stack inicial del proyecto.
- La solución separa configuración, persistencia (`CatalogRepository`), validación/reglas (`CatalogService`) y presentación (`index.php`, HTML en `app/Views/catalog.php`, CSS en `assets/css/styles.css` y JavaScript en `assets/js/app.js`).
- HU03, HU04 y HU05 cuentan con formularios de alta y listados integrados con MySQL.
- HU07 está aprobada y dispone de formulario de registro integrado con las referencias de categoría, marca y proveedor.
- HU01 dispone de catálogo con búsqueda por nombre/descripción y filtros por categoría, marca y proveedor.
- HU02 dispone de carga de datos actuales, validación y actualización PDO conservando las relaciones.
- Permanecen fuera de esta entrega las demás funcionalidades no especificadas, como ventas y compras.

## Orientación funcional
- **Catálogo de productos:** tabla/listado, búsqueda y filtros acordados.
- **Edición de producto:** formulario con valores actuales y validaciones.
- **Categorías:** formulario de registro y listado.
- **Marcas:** formulario con nombre y país de origen, más listado.
- **Proveedores:** formulario con datos de contacto y listado.
- **Producto:** los formularios deberán permitir seleccionar categoría, marca y proveedor cuando esas relaciones estén definidas y disponibles.

## Orientación de arquitectura
Respetar la arquitectura que ya exista en el repositorio. Si el repositorio está vacío, proponer una separación clara entre:
- Presentación: pantallas, formularios y mensajes.
- Lógica de negocio: reglas y validaciones.
- Persistencia: consultas y operaciones con la base de datos.

No crear capas o patrones innecesarios si el tamaño y la tecnología del proyecto no los justifican.

## Wireframes
Las tareas originales solicitan wireframes en Figma para catálogo, edición de producto, categorías, marcas y proveedores. Antes de programar, el equipo debe acordar si esos wireframes son una condición de aceptación o un entregable de diseño. Los wireframes no sustituyen las pantallas funcionales del programa.

## Usabilidad
- Etiquetas claras en español.
- Mensajes de éxito y error comprensibles.
- Validación de campos obligatorios.
- Evitar pérdida innecesaria de datos introducidos por el usuario.
- Estados visibles para listas vacías y errores de carga.

## Seguridad y datos
- Usar consultas parametrizadas o el mecanismo seguro equivalente de la tecnología elegida.
- No incluir credenciales en el repositorio.
- Validar los datos también en la lógica de negocio, no solo en la interfaz.
