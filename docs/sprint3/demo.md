# Sprint 3 – Guía de demostración

## Objetivo
Secuencia para demostrar HU11, HU12 y HU13 ya implementadas y probadas. Realizar la demostración en entorno de pruebas o con registros autorizados.

## HU11 – Actualizar precio
1. Ingresar a «Precios».
2. Buscar un producto por su ID (código visible).
3. Comprobar que se muestra el precio actual.
4. Intentar guardar precio vacío, cero, negativo, no numérico e igual al actual.
5. Confirmar que el sistema rechaza valores inválidos.
6. Capturar un precio válido y guardar.
7. Comprobar confirmación y nuevo precio vigente.
8. Cancelar una modificación de prueba y comprobar que no se alteró el precio.

## HU12 – Historial de precios
1. Ingresar a «Historial de precios».
2. Comprobar que el cambio de HU11 aparece.
3. Verificar fecha/hora, ID y nombre del producto, precio anterior, precio nuevo y área responsable «Administrador».
4. Comprobar el orden descendente.
5. Filtrar por producto.
6. Filtrar por rango de fechas.
7. Buscar sin coincidencias y comprobar el mensaje correspondiente.

## HU13 – Consultar ubicación
1. Ingresar a «Ubicaciones».
2. Comprobar ID (código visible), nombre, categoría, pasillo, estante y stock.
3. Buscar por ID.
4. Buscar por nombre.
5. Filtrar por pasillo.
6. Comprobar «Sin ubicación» para un producto sin ubicación asignada.
7. Buscar sin coincidencias y comprobar el mensaje correspondiente.
8. La demostración cubre solo la consulta; HU13 no permite asignar ni modificar pasillo o estante.

## Verificación final
- Ejecutar pruebas de regresión de Sprints 1 y 2.
- Confirmar que los precios de ventas anteriores no cambiaron.
- Confirmar que no queden registros residuales de pruebas.
- Registrar incidencias y evidencias.
