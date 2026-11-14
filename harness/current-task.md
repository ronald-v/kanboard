# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-007 validated;
sin feature activa. Rama feat/007 desde d66983e38.

## Resultado
Sesiones nativas únicas con segundos exactos, manual/temporizador/corrección,
auditoría, claves de retry, mutex por usuario/tarea y proyecciones compatibles.
Acumulados históricos opacos preservados, sin sesiones inventadas. Schema plugin 3.
Sin core/vendor ni DB real. DEC-016; [evidencia](evidence/2026-11-12-f007-sesiones.md).

## Verificaciones y límites
16 tests/79 aserciones exit 0; integración HTTP/UI/API y concurrencia en dos
procesos PHP/PDO independientes exit 0; regresión F-006 exit 0. Savepoints,
rollback, FK restrict, precisión y snapshots probados. SQLite ejecutado, MySQL
solo inspección/lint. Sin capturas de Chrome; continuar sin aprobaciones visuales
según DEC-015. No afirmar auditoría WCAG ni revisión independiente.

## Siguiente paso
Commit/push dev feature 007 y crear feat/008. Añadir clasificación/aprobación y
alcance por rol a sesiones, preservar correcciones aprobadas con auditoría.
Servidores sintéticos 18005/18007 plugin y 18006 nativo activos. Producción separada.
