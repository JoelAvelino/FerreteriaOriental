# Requerimientos — Sprint 2
## Ferretería Oriental

## 1. Objetivo
Ampliar el sistema desarrollado en el Sprint 1 con funciones para eliminar registros de catálogo bajo restricciones, registrar clientes, registrar métodos de pago y registrar ventas.

## 2. Roles involucrados

### Administrador
Gestiona el inventario y los catálogos de la ferretería.

### Vendedor
Atiende al cliente en mostrador y registra las operaciones de venta.

## 3. Historias de usuario

### HU06 — Eliminar productos, proveedores y categorías
- **Rol:** Administrador
- **Quiero:** Eliminar productos, proveedores y categorías.
- **Para:** Llevar un control más limpio del catálogo.
- **Prioridad:** Alta

**Criterios de aceptación**
1. Cada registro del catálogo muestra la acción «Eliminar».
2. Antes de eliminar, el sistema solicita confirmación.
3. Una categoría o proveedor con productos asociados, o un producto con ventas registradas, no puede eliminarse; el sistema indica el motivo.
4. Al confirmar, el registro desaparece del listado y el sistema muestra un mensaje de confirmación.

**Dependencias:** Catálogos del Sprint 1; HU08 (las ventas determinan si un producto puede eliminarse).

### HU07 — Registrar clientes
- **Rol:** Vendedor
- **Quiero:** Registrar clientes.
- **Para:** Asociar las ventas con las personas que realizan las compras.
- **Prioridad:** Alta

**Criterios de aceptación**
1. El formulario solicita nombre, teléfono, correo y dirección; el nombre es obligatorio y solo admite letras Unicode y espacios entre palabras, recortando los espacios al inicio y al final.
2. Se debe proporcionar al menos un dato de contacto: correo electrónico, número telefónico o ambos. Si se proporciona correo o teléfono, se valida su formato; los espacios al inicio y al final se eliminan antes de validar.
3. No se permite registrar un cliente duplicado.
4. El cliente registrado aparece en el listado y queda disponible para seleccionarlo en una venta.

**Dependencias:** Ninguna.

### HU08 — Registrar una venta
- **Rol:** Vendedor
- **Quiero:** Registrar una venta indicando el cliente, el método de pago y los productos vendidos.
- **Para:** Llevar un control de las operaciones.
- **Prioridad:** Alta

**Criterios de aceptación**
1. El vendedor elige el cliente y el método de pago desde listas desplegables.
2. Puede agregar uno o más productos con su cantidad; el sistema calcula subtotales y total.
3. Si la cantidad solicitada supera el stock disponible, el sistema rechaza ese producto e informa el stock existente.
4. Al registrar, el sistema guarda la venta con su detalle, genera el folio y descuenta el stock.
5. No se puede registrar una venta sin productos.

**Dependencias:** HU07, HU09 y los productos con stock del Sprint 1.

### HU09 — Registrar métodos de pago
- **Rol:** Vendedor
- **Quiero:** Registrar métodos de pago.
- **Para:** Identificar cómo fue realizada cada venta.
- **Prioridad:** Media

**Criterios de aceptación**
1. Se captura el nombre (obligatorio) y una descripción.
2. No se permiten métodos de pago duplicados.
3. Los métodos registrados (por ejemplo efectivo, tarjeta o transferencia) aparecen en la lista desplegable de la venta.

**Dependencias:** Ninguna.

## 4. HU10 — Consultar detalle de una venta
- **Rol:** Vendedor
- **Quiero:** Consultar el detalle de una venta.
- **Para:** Conocer qué productos fueron vendidos, sus cantidades y precios.
- **Prioridad:** Alta

**Criterios de aceptación para el programa web**
1. La navegación ofrece acceso al historial de ventas.
2. El historial presenta folio, fecha, cliente y total disponibles, y permite abrir cada detalle.
3. Si no existen ventas, se muestra un mensaje apropiado.
4. Se puede consultar una venta por folio; un folio inválido o inexistente produce un mensaje claro sin romper la página.
5. El detalle presenta cada producto, cantidad, precio unitario histórico y subtotal, además del total de la venta.
6. El detalle presenta el cliente y el método de pago cuando estén disponibles.
7. La consulta es exclusivamente de lectura; no modifica ventas, detalles ni existencias.

Esta entrega cubre únicamente el programa web. Los wireframes y cualquier tarea de Figma quedan fuera de responsabilidad y alcance.

## 5. Dependencias y orden sugerido
1. Revisar el esquema y la implementación existente del Sprint 1.
2. Implementar registro y listado de clientes (HU07).
3. Implementar registro y listado de métodos de pago (HU09).
4. Implementar eliminación segura de productos, proveedores y categorías (HU06), respetando relaciones y ventas.
5. Implementar el registro de ventas y su detalle (HU08), incluyendo validación de existencias, cálculo de totales, folio y descuento de stock.
6. Implementar la consulta de historial y detalle de ventas (HU10) con las tablas existentes, sin modificar el esquema.

Este orden es orientativo y debe validarse con el equipo.

## 6. Reglas generales
- Conservar las funcionalidades existentes del Sprint 1.
- No eliminar datos asociados ni desactivar restricciones de integridad para forzar eliminaciones.
- Validar los datos en el servidor; las validaciones del navegador son complementarias.
- El registro de una venta y el descuento de existencias deben ejecutarse de forma consistente, evitando ventas parciales.
- No ejecutar inicializaciones destructivas ni borrar datos existentes sin autorización.
- Marcar tareas como terminadas solo cuando existan pruebas y evidencia correspondientes.
