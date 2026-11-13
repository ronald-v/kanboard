# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-006 validated;
sin feature activa. Rama feat/006. Cierre bajo autonomía DEC-015.

## Resultado
Corregido solapamiento: sidebar border-box de 240px dentro del margen de 260px.
Estilos comunes para Nuevo proyecto y modales nativos proyecto/tarea/subtarea.
Se conservan todos los campos, handlers, permisos y CSRF. Sin cambios de núcleo/DB.
[Evidencia](evidence/2026-11-12-f006-flujos.md), DEC-014/015.

## Verificación y límites
4 tests/21 aserciones, HTTP/UI/API, comparación de campos, harness/diff: exit 0.
Revisión estática de CSS responsive/modelo de caja. Chrome falla timeoutMs;
sin captura/render final automático, no atribuir éxito visual. El usuario autoriza
avanzar sin aprobaciones visuales adicionales; no volver a pedirlas.

## Siguiente paso
Commit/push dev feature 006, crear feat/007 y desarrollar sesiones con fuente
única, integridad, reintentos y concurrencia conforme BR-01..10 de DEC-008.
Servidores sintéticos 18005 (plugin)/18006 (nativo) activos. Sin DB externa,
despliegue productivo ni revisión independiente.
