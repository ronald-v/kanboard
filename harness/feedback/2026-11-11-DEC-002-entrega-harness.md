# DEC-002: cierre de generación, producto separado

Fecha: 2026-11-11T22:37:09.5298868-05:00.
Feature de referencia: F-001; entrega administrativa del harness bajo DEC-001.
Evidencia: [validación](../evidence/2026-11-11-harness-validation.md).

Decisión: entregar coherencia estática comprobada y 18 fixtures ejecutados.
Conservar F-001 blocked por herramientas, F-002 planned con reproducción blocked
y funcionalidades futuras planned. Ninguna feature funcional pasa a done/validated
por crear documentación.

Impacto: iniciar sesiones desde AGENTS.md y README; verificador ejecutable sin
PHP/DB. No hay orquestación de agentes ni CI instalada. Escenarios con verificación
parcial no se declaran validated. No se cambió núcleo, dependencias o producción.

Siguiente acción: alcance concreto para preparar F-001; habilitar entorno aislado
y ejecutar suite/arranque antes de cambios funcionales. Reglas comerciales y diseño
conservan decisiones pendientes; no se infieren de autonomía.
