# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-011 validated;
F-012 blocked, sin activa. Rama feat/012. [DEC-021](feedback/2026-11-12-DEC-021-f012-preparacion.md)
y [evidencia local](evidence/2026-11-12-f012-preparacion.md).

Preparación de F-012 implementada: artefacto excluye desarrollo/secretos, manifiesto
con hashes, migración plugin 4→6 preserva historia, recuperación del ZIP con base y
adjuntos sintéticos ejecutada en PHP/HTTP. 33 tests/189 aserciones del plugin;
1272 tests/181101 aserciones nativas con warning conocido y skips; todos los
scripts HTTP integrales pasan. 18 fixtures del harness pasan. Sin deploy ni
capturas nuevas; no afirmar compatibilidad MySQL/hosting.

Pendientes: Ronnie identifica ficha PHP/motor/servidor/staging de destino. Agente ensaya ese motor, reglas HTTP, actualización y recuperación antes
de cerrar F-012. Preparación local se entrega en dev feature 012; no marcar
validated por haber hecho commit. Producción sigue requiriendo autorización separada.
