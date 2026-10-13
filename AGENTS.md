# Instrucciones para el agente — Ferretería Oriental

## Contexto
Estás trabajando en el sistema de inventario y ventas de **Ferretería Oriental**. El proyecto utiliza Scrum y el alcance inicial corresponde al Sprint 1.

## Antes de programar
1. Lee `README.md` y todos los archivos de `docs/sprint1/`.
2. Revisa el código existente antes de modificarlo. No reemplaces ni reorganices archivos sin necesidad.
3. Identifica la tecnología, arquitectura, convenciones y comandos que ya usa el repositorio.
4. Si la tecnología o una decisión importante no está definida, pregunta antes de asumirla. No cambies de stack por iniciativa propia.
5. Contrasta los requisitos con el código y comunica cualquier contradicción o información faltante.

## Reglas de desarrollo
- Implementa únicamente funcionalidades incluidas en el alcance aprobado.
- Mantén separadas, según la arquitectura existente, la interfaz, la lógica de negocio y la persistencia.
- Usa nombres claros y consistentes en español para el dominio del negocio, salvo que el proyecto existente establezca otra convención.
- Valida los datos en la capa apropiada y muestra mensajes comprensibles.
- No guardes contraseñas, claves, tokens ni credenciales en el código o en archivos versionados.
- No inventes datos reales de proveedores, marcas o productos. Usa datos ficticios solo para pruebas y etiquétalos como tales.
- No afirmes que una tarea está terminada si no se implementó y verificó.
- Evita dependencias nuevas si no son necesarias; explica cualquier dependencia añadida.
- No elimines funcionalidades existentes ni hagas cambios destructivos en la base de datos sin autorización.

## Flujo de trabajo
1. Resume qué archivos revisarás y qué vas a cambiar.
2. Implementa una tarea pequeña y coherente.
3. Ejecuta las pruebas disponibles y revisa los errores.
4. Actualiza `docs/sprint1/task.md` con el estado real de las tareas.
5. Informa los archivos modificados, las pruebas ejecutadas, sus resultados y cualquier pendiente.

## Criterio de calidad
Una historia no está terminada solo porque exista una pantalla. Debe cumplir sus criterios de aceptación, persistir o consultar los datos que correspondan y estar integrada con el proyecto.
