# FerreteriaOriental

Sistema de información para apoyar el control de inventario y las ventas de una ferretería. El desarrollo se organiza mediante Scrum.

## Estado actual
- Stack confirmado: PHP 8.x + MySQL + XAMPP.
- Módulos HU03, HU04 y HU05 implementados: registro y listado de categorías, marcas y proveedores.
- HU07 aprobada e implementada: registro de productos relacionado con categoría, marca y proveedor.
- HU01 y HU02 implementadas: catálogo con búsqueda/filtros y edición de los campos documentados.
- Formularios con validación en cliente y servidor, persistencia PDO, protección CSRF y rechazo de duplicados.
- Interfaces HTML con estilos en CSS y mejoras de interacción en JavaScript.

## Estructura del proyecto
- `app/Database.php`: encapsula la conexión a MySQL y la validación del esquema.
- `app/Repositories/CatalogRepository.php`: consultas parametrizadas para listar y guardar registros.
- `app/Services/CatalogService.php`: definición de módulos y validaciones de negocio.
- `app/Views/catalog.php`: formularios, mensajes y listados compartidos.
- `assets/css/styles.css`: estilos responsivos de los módulos.
- `assets/js/app.js`: contadores de caracteres y prevención de envíos duplicados.
- `config/database.php`: configuración de conexión para XAMPP.
- `database/schema.sql`: esquema inicial de datos para categorías, marcas, proveedores y productos.
- `database/init_db.php`: crea la base de datos y ejecuta el esquema.
- `database/validate_db.php`: verifica tablas y relaciones foráneas.
- `database/test_catalog_modules.php`: comprobaciones de persistencia y validaciones.
- `index.php`: punto de entrada web para los módulos de administración y productos.

## Documentación
- `AGENTS.md`: reglas generales para el agente.
- `docs/sprint1/requirements.md`: historias de usuario y criterios de aceptación propuestos.
- `docs/sprint1/design.md`: guía de diseño y arquitectura confirmada para la base técnica.
- `docs/sprint1/task.md`: tareas técnicas y el estado real del trabajo.
- `docs/database.md`: modelo de datos aprobado para la base técnica del proyecto.

## Inicialización del entorno
1. Inicia MySQL desde XAMPP.
2. Inicia Apache desde XAMPP.
3. Si aún no existe el esquema, ejecuta:
   `php database/init_db.php`
4. Verifica el esquema:
   `php database/validate_db.php`
5. Ejecuta las comprobaciones funcionales:
   `php database/test_catalog_modules.php`

Abre `http://localhost/FerreteriaOriental/` y selecciona Categorías, Marcas, Proveedores o Productos. El módulo Productos permite registrar, consultar, filtrar y editar productos. Para registrar productos, primero debe existir al menos una categoría, marca y proveedor. La configuración usa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME` y `DB_PASSWORD` si están definidas; por defecto usa la configuración local estándar de XAMPP.
