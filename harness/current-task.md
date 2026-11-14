# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-004 validated;
sin feature activa. Rama feat/004 desde feat/003.

## Resultado
Diseño aprobado por usuario (DEC-010) y protocolo manual confirmado (DEC-012).
Mockup, formularios, estados y activos en harness/mockups/f004. Sin cambio de
producto ni DB. [Evidencia final](evidence/2026-11-12-f004-validacion-final.md).

## Verificaciones y límites
26 controles/nueve contrastes, sintaxis JS, harness y diff check: exit 0.
Usuario confirmó Tab/foco, diálogo/Escape, campos avanzados, menú móvil y zoom 200%.
No atribuir revisión visual al agente: integración Chrome falló, sin captura.
Solo Chrome según DEC-011. Servidores estáticos 8004/18004 para revisión local.
F-001: 1240 tests/181101 aserciones, exit 0, un warning; F-002 excluida; F-003 contrato.

## Siguiente paso
Commit dev feature 004 y push; crear feat/005 desde esta entrega. Implementar
plugin RvSoftware mínimo, migración nativa y pruebas sin reglas comerciales
adelantadas. Diseño aprobado se integra en F-006. Sin producción ni MySQL real.
