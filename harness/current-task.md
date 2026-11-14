# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-003 validated;
sin feature activa. Rama feat/003, encadenada desde feat/002.

## Resultado y archivos
Contrato BR-01..10 aceptado por usuario y registrado en DEC-008. Capacidades
nativas contrastadas; tarifas/importes, compartir bolsas y automatismos excluidos.
Cambios solo en documentos, caso y estado.
Evidencia: [F-003](evidence/2026-11-12-f003-contrato.md).

## Verificaciones y límites
Test-Harness.ps1 y diff check al cierre. Entregable documental, sin migraciones.
F-001: suite 1240 tests/181101 aserciones, exit 0, 1 warning; HTTP sintético.
F-002: incidente externo excluido por usuario, sin atribuir corrección técnica.
No MySQL configurado, despliegue productivo separado y sin revisión independiente.

## Siguiente paso
Crear feat/004. Preparar navegación/formularios y mockup con activos oficiales;
comprobar accesibilidad y obtener aprobación concreta antes de implementar.
F-005 requiere F-004 validated. Conservar contrato y permisos en futuras features.
