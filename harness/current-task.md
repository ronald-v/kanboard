# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-005 validated;
sin feature activa. Rama feat/005. F-004 subida como 302b5abba.

## Resultado
Plugin mínimo RvSoftware 0.1.0 con ruta autenticada, CSS, ACL y esquema nativo
baseline sin DDL. Excepción de gitignore limitada. Pruebas en tests/units/Plugin.
[Evidencia](evidence/2026-11-12-f005-plugin.md), DEC-013.

## Verificaciones y límites
2 tests/10 aserciones, exit 0. HTTP 18005 con plugin y 18006 sin plugin: login,
ruta admin/miembro, CSS y CSP, Dashboard nativo sin plugin, todo correcto.
Harness y diff check al cierre. SQLite sintético; sin ejecución MySQL, producción
ni revisión independiente. Solo Chrome como mecanismo de UI; HTTP no es prueba visual.

## Siguiente paso
Commit dev feature 005 y push; crear feat/006 para integrar diseño aprobado y
flujos nativos de proyectos/tareas/tablero. Servidores sintéticos 18005/18006
activos; al modificar plugin refrescar copia con scripts de entorno aislado.
