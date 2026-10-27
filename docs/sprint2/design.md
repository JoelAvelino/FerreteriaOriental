# Diseño técnico propuesto — Sprint 2
## Ferretería Oriental

## 1. Propósito
Documentar las consideraciones de diseño para HU06–HU09 y señalar las decisiones pendientes antes de modificar el sistema.

## 2. Base técnica existente
El Sprint 1 utiliza PHP, MySQL, PDO, HTML, CSS y JavaScript, dentro del entorno local XAMPP. Antes de programar, inspeccionar el repositorio y reutilizar la conexión PDO, la arquitectura y los estilos existentes. No asumir nombres de tablas, columnas o rutas sin comprobarlos.

## 3. Consideraciones por historia

### HU06 — Eliminaciones
- Mostrar una acción «Eliminar» en los listados correspondientes.
- Solicitar confirmación antes de enviar la eliminación.
- Validar las relaciones en el servidor y respetar las claves foráneas de MySQL.
- No permitir eliminar categorías o proveedores con productos asociados ni productos con ventas registradas; informar el motivo.
- Ejecutar la operación con consultas preparadas y manejar errores de integridad sin exponer detalles internos de la base de datos.
- No usar borrado en cascada para evadir las reglas de aceptación.

### HU07 — Clientes
- Crear un formulario con nombre, teléfono, correo y dirección.
- El nombre es obligatorio; validar formatos de teléfono y correo en el servidor y, como apoyo, en el navegador.
- Definir el criterio exacto de duplicidad con el equipo si no está documentado; no inventar una regla silenciosamente.
- Al guardar, actualizar o volver al listado para que el nuevo cliente esté disponible en el formulario de venta.
- Criterio confirmado: correo o teléfono duplicado; el teléfono se normaliza quitando puntuación y espacios y se conserva un `+` inicial. El nombre no es identificador único.

### HU09 — Métodos de pago
- Crear un formulario con nombre obligatorio y descripción opcional.
- Rechazar métodos duplicados según el criterio de unicidad aprobado.
- Mostrar los métodos registrados en el selector de la venta. No hay un campo de estado/activo definido, por lo que todos los métodos registrados están disponibles.

### HU08 — Ventas
- Permitir seleccionar un cliente, un método de pago y uno o más productos con cantidades.
- Calcular subtotales y total en el servidor; cualquier cálculo mostrado en JavaScript es solo una ayuda visual.
- Verificar que las cantidades sean válidas y que exista stock suficiente.
- Guardar cabecera de venta y líneas de detalle, generar folio y descontar stock dentro de una transacción de base de datos.
- Si falla cualquier paso, revertir la transacción para no dejar una venta parcial ni descontar existencias incorrectamente.
- Impedir ventas sin productos y evitar que solicitudes simultáneas provoquen stock negativo.
- La estructura concreta de tablas, folio, precios históricos y precisión monetaria debe ajustarse al esquema existente y a las decisiones del equipo; no asumir campos no aprobados.

Decisiones confirmadas para esta implementación:
- Folio secuencial único con prefijo `V-`, derivado del identificador autoincremental de venta.
- No vender productos con precio nulo, inválido o igual a cero; cantidades enteras mayores o iguales a 1.
- Rechazar un producto repetido en varias líneas y no permitir stock ni ventas negativos.
- Calcular importes en el servidor y guardar cabecera, detalle, precio histórico y descuento de stock dentro de una transacción atómica.

## 4. Modelo de datos implementado
El esquema desplegado contiene:
- `cliente` y `metodo_pago` como catálogos de selección para ventas.
- `venta` con id autoincremental, folio único, cliente, método de pago, total y fecha.
- `detalle_venta` con venta, producto, cantidad, precio unitario histórico y subtotal.

Las claves foráneas restringen borrados que afectarían el historial. La definición completa está en `database/schema.sql`; las migraciones para bases existentes están en `database/migrations/`.

## 5. HU10 — Consulta de detalle de ventas
El documento de origen menciona consultar el detalle de ventas realizadas y enumera HU10, pero no incluye su historia ni criterios de aceptación. No diseñar ni implementar esa funcionalidad hasta que el equipo confirme su alcance.

## 6. Seguridad y compatibilidad
- Reutilizar PDO y consultas preparadas.
- Validar todas las entradas en el servidor.
- Respetar las claves foráneas y transacciones.
- No imprimir errores SQL o credenciales en la interfaz.
- No reinicializar ni borrar datos existentes durante el desarrollo.
- Mantener la compatibilidad con el código y estilo del Sprint 1.

## 7. Pruebas previstas
- Rechazo de eliminación cuando existan registros relacionados.
- Eliminación confirmada de registros sin dependencias, según las reglas aprobadas.
- Validación de campos obligatorios, formatos y duplicados de clientes y métodos de pago.
- Registro de venta con varios productos y cálculo correcto de importes.
- Rechazo de ventas sin productos o con stock insuficiente.
- Verificación de persistencia de cabecera y detalle, generación de folio y descuento de stock.
- Prueba de rollback cuando falla una operación de la venta.
- Regresión de las funcionalidades del Sprint 1.
