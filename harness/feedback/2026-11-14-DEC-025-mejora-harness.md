# DEC-025: mejora del harness sin ampliar el backlog

Fecha: 2026-11-14T10:05:36-05:00 (America/Bogota).
Feature: ninguna nueva; mantenimiento del harness. F-012 permanece blocked.
Fuente: solicitud de Ronnie del 2026-11-14 de analizar y mejorar el harness con
Agente Harness. Los requisitos compartidos son contexto: pidió expresamente no
agregarlos y comunicarlos después para documentación y desarrollo autorizado.
Clasificación: confirmado por instrucción del usuario. Estado: vigente.

Evidencia: revisión de AGENTS, README, mapa, prompts, contextos y scripts;
[informe](../evidence/2026-11-14-harness-upgrade.md). Solo favicon.png tenía un cambio
local al iniciar. No se leyó ni editó la configuración privada del usuario.

Decisión: mantener estructura e IDs; ampliar guías y enrutamiento por especialidad,
corregir contradicciones histórico/actual, aplicar principios y política local de
mejora con candidato, base, comparación y recuperación. No convertir especialidades
en tareas. No registrar ni implementar los requisitos pendientes, ni retomar F-012.
No cambiar reglas comerciales, criterios o estados del producto. La futura
ejecución de producto sigue dependiendo del alcance que el usuario autorice.

Impacto: prompts api/mcp/performance/pentesting, contextos específicos, contrato de
cambio y controles de catálogo/recuperación. Se mantiene política de seguridad,
QA, licencias y extensión nativa. Consulta de arquitectura, QA y seguridad por el
mismo ejecutor; sin delegación ni revisión independiente.

Siguiente acción: validar candidato y árbol integrado; mantener evidencia de las
comprobaciones reales. En un encargo futuro documentar requisitos solo cuando se
solicite, y desarrollar solo con autorización de implementación.
