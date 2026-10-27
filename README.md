# FerreteriaOriental

Sistema de información para apoyar el control de inventario y las ventas de una ferretería. El desarrollo se organiza mediante Scrum.

## Estado actual
- Stack confirmado: PHP 8.x + MySQL + XAMPP.
- Módulos HU03, HU04 y HU05 implementados: registro y listado de categorías, marcas y proveedores.
- HU07 de Sprint 1 aprobada e implementada: registro de productos relacionado con categoría, marca y proveedor.
- HU01 y HU02 implementadas: catálogo con búsqueda/filtros y edición de los campos documentados.
- HU06–HU09 de Sprint 2 implementadas: eliminaciones seguras, clientes, métodos de pago y ventas con descuento transaccional de existencias.
- HU10 y la consulta de detalle de ventas continúan pendientes de confirmación de alcance.
- Formularios con validación en cliente y servidor, persistencia PDO, protección CSRF y rechazo de duplicados.
- Interfaces HTML con estilos en CSS y mejoras de interacción en JavaScript.

## Estructura del proyecto
- `app/Database.php`: encapsula la conexión a MySQL y la validación del esquema.
- `app/Repositories/CatalogRepository.php`: consultas parametrizadas para listar y guardar registros.
- `app/Services/CatalogService.php`: definición de módulos y validaciones de negocio.
- `app/Views/catalog.php`: formularios, mensajes y listados compartidos.
- `app/Repositories/SalesRepository.php` y `app/Services/SalesService.php`: persistencia transaccional de ventas e inventario.
- `app/Views/sales.php`: formulario de ventas con selección de cliente, método y productos.
- `assets/css/styles.css`: estilos responsivos de los módulos.
- `assets/js/app.js`: contadores de caracteres y prevención de envíos duplicados.
- `assets/js/sales.js`: líneas dinámicas y vista previa de importes recalculados en el servidor.
- `config/database.php`: configuración de conexión para XAMPP.
- `database/schema.sql`: esquema de referencia para catálogos, clientes, métodos de pago, ventas y detalle.
- `database/migrations/`: migraciones aditivas para actualizar bases existentes sin reinicializarlas.
- `database/init_db.php`: crea la base de datos y ejecuta el esquema.
- `database/validate_db.php`: verifica tablas y relaciones foráneas.
- `database/test_catalog_modules.php`: comprobaciones de persistencia y validaciones.
- `index.php`: punto de entrada web para los módulos de administración, productos y ventas.

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
   Para una base existente de Sprint 1, aplica en orden las migraciones necesarias de `database/migrations/`; no uses el inicializador para actualizarla. Antes de aplicar `20261027_customer_contact_uniqueness.sql` a una base con clientes, revisa posibles correos o teléfonos duplicados; la migración no borra filas.
4. Verifica el esquema:
   `php database/validate_db.php`
5. Ejecuta las comprobaciones funcionales:
   `php database/test_catalog_modules.php`
6. Ejecuta las pruebas de Sprint 2:
   `php database/test_customer_validation.php`
   `php database/test_customer_modules.php`
   `php database/test_payment_methods.php`
   `php database/test_sales.php`
   `php database/test_catalog_deletions.php`

Abre `http://localhost/FerreteriaOriental/` y selecciona Categorías, Marcas, Proveedores, Productos, Clientes, Métodos de pago o Ventas. El módulo Productos permite registrar, consultar, filtrar, editar y eliminar productos. Para registrar productos, primero debe existir al menos una categoría, marca y proveedor. La configuración usa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME` y `DB_PASSWORD` si están definidas; el puerto local predeterminado de este proyecto es 3307.

## Documentación del Sprint 2

Los requerimientos, el diseño técnico y las tareas del segundo sprint se documentan en `docs/sprint2/`.

- `docs/sprint2/requirements.md`: historias de usuario, prioridades y criterios de aceptación del Sprint 2.
- `docs/sprint2/design.md`: consideraciones técnicas para las nuevas funcionalidades y sus dependencias.
- `docs/sprint2/task.md`: desglose de tareas técnicas y seguimiento de su estado.
- `docs/sprint2/demo.md`: guía paso a paso para demostrar y limpiar manualmente datos ficticios del Sprint 2.

### Estado de funcionalidades

- **HU06:** eliminación de productos, proveedores y categorías.
- **HU07:** registro de clientes.
- **HU08:** registro de ventas, incluyendo cliente, método de pago y productos vendidos.
- **HU09:** registro de métodos de pago.

Las funcionalidades reutilizan la arquitectura y las relaciones existentes; las pruebas y el estado del Sprint 2 se documentan en `docs/sprint2/task.md`.

**Nota:** la consulta del detalle de ventas también aparece mencionada en el documento de desglose del Sprint 2, pero su historia de usuario y sus criterios de aceptación deben confirmarse con el equipo antes de implementarla.