# Mantenimiento del harness y continuidad

Autorización: revisión solicitada el 2026-11-14, registrada en DEC-025.
Aplica a archivos del harness e instrucciones. El ejecutor de producto desarrolla
solo features que el usuario autorice; el generador de harness no desarrolla producto.
Las ideas compartidas sin autorización de registro no se convierten en backlog.

## Ciclo local
1. Leer estado, Git y decisiones. Registrar síntoma, evidencia y gravedad de la brecha.
2. Conservar base versionada o copia local bajo .runtime/; no copiar secretos.
   Calcular hashes de los destinos y fijar aceptación/invariantes antes de editar.
3. Construir candidato separado. Mantener IDs, estados, reglas y evidencias de producto;
   conservar la estructura útil. No duplicar expertos en cuatro documentos vacíos
   ni añadir un segundo inventario para ajustarse a un generador genérico.
4. Revisar como architect, testing y security por consulta o delegación real.
   Registrar conflictos y su resolución. No afirmar revisión independiente sin ella.
5. Ejecutar Test-Harness y Test-HarnessScenarios contra candidato; comparar con base.
   Casos fijos, negativos/equivalentes/regresión; no bajar controles para aprobar.
6. Releer destinos y comparar hashes; con cambios concurrentes conservar candidato
   e integrar por archivo. Promover solo tras pasar; verificar lectura posterior.
   No afirmar transacción atómica del árbol ni lock si no existe tal mecanismo.
7. Para recuperación restaurar únicamente archivos propios desde base comparada,
   comprobando que no cambiaron después; retirar solo archivos nuevos de esta revisión
   si permanecen idénticos al candidato. Nunca reset/clean ni restauración global.
8. Registrar decisión inmutable, evidencia y checkpoint. Máximo tres correcciones
   consecutivas de una causa sin progreso; repetir solo con cambio de condiciones.

## Contratos reutilizables
Consulta/delegación: objetivo, fuente, modalidad, contexto mínimo, archivos permitidos,
herramientas disponibles, restricciones, aceptación, dependencias y parada.
Una sola persona/agente integra estado compartido. Un prompt no instala un agente.
Tool nueva: propósito, runtime/dependencias, entradas y rutas validadas, salida,
efectos/permisos, timeout/reintentos, verificación y estado declarado/configurado/probado.
Pruebas sintéticas éxito/fallo antes de usarla sobre recursos reales.

Memoria local: decisiones en feedback con ID/fecha/fuente, hecho/inferencia/propuesta,
impacto y siguiente paso; historia inmutable, correcciones por nueva decisión.
feature-list es el único estado del producto; checkpoint resume. No importar chat
completo, subir código o memoria a Drive ni prometer actividad de fondo.
Una nueva sesión debe leer y cotejar archivos; continuidad entre sesiones no se
acredita por una simulación en la misma sesión.
