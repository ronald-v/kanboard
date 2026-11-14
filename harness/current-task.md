# Checkpoint

Feature de referencia: F-001. Estado canónico: [feature-list.json](feature-list.json).
Sin feature activa del producto. F-001 bloqueada en preparación/ejecución del entorno;
su inspección ya tiene evidencia. F-002 sigue reportada, pendiente de reproducción.

## Último resultado
Harness creado bajo DEC-001 y entregado según DEC-002: validación estática exitosa
y 18 fixtures del validador sin fallos. Evidencia en
[validación](evidence/2026-11-11-harness-validation.md). No funcionalidades implementadas.

## Archivos afectados
AGENTS.md y harness/ únicamente: prompts, contextos, JSON, casos, scripts y evidencias.
No cambios de código PHP, assets del producto, dependencia, configuración o datos.

## Comprobaciones
Línea base en evidence/2026-11-11-baseline.md; Composer válido, plataforma falla por
GD, drivers PDO vacíos, PHPUnit ausente. Las suites de aplicación no se ejecutaron.
Test-Harness.ps1: exit 0; 12 features, 23 casos, 9 prompts y 10 escenarios.
Test-HarnessScenarios.ps1: exit 0; 18 fixtures. Ambos scripts sin errores de parser.
Estos resultados no equivalen a pruebas funcionales ni revisión independiente.

## Bloqueos y próximo paso
Para F-001: alcance de preparación del entorno, GD, driver de prueba y dependencias
de desarrollo antes de iniciar la aplicación con datos sintéticos. No instalar
automáticamente como efecto de generar el harness. F-002 necesita URL/versión/rol,
pasos y error de la instalación afectada. Reglas comerciales pendientes en time-billing.
Tras entregar este harness, proponer F-001; no empezar features del producto sin
alcance suficiente. No repetir comandos fallidos hasta cambiar su condición.
