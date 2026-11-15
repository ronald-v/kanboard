# Harness de desarrollo

Herramientas, contexto y seguimiento del desarrollo de Projecta. Las características, tecnologías, requisitos, arranque local y empaquetado están en el [README del proyecto](../README.md).

## Referencias de trabajo

| Necesidad | Fuente |
| --- | --- |
| Reglas y alcance de la colaboración | [AGENTS.md](../AGENTS.md) |
| Estado canónico de funcionalidades | [feature-list.json](feature-list.json) |
| Punto de reanudación | [current-task.md](current-task.md) |
| Selección de roles y contexto por cambio | [prompt-map.json](prompt-map.json) |
| Comandos, perfiles y límites de pruebas | [testing.md](context/testing.md) |
| Arquitectura y persistencia | [architecture.md](context/architecture.md), [database.md](context/database.md) |
| Reglas de tiempo y bolsas | [time-billing.md](context/time-billing.md) |
| Seguridad | [security.md](context/security.md) |
| Preparación de entregas | [deployment.md](context/deployment.md) |
| Mantenimiento y recuperación del harness | [harness-maintenance.md](context/harness-maintenance.md) |
| Decisiones y resultados observados | [feedback/](feedback/), [evidence/](evidence/) |

Antes de editar, revisa `git status --short`, el estado canónico y el checkpoint; después consulta el contexto y las decisiones pertinentes al cambio. Las instrucciones operativas completas están en AGENTS.md.

## Validar cambios del harness

Desde la raíz del repositorio:

```powershell
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1
```

El primer comando comprueba estructura, estados y referencias; el segundo ejecuta fixtures del verificador estático. Ninguno arranca la aplicación ni sustituye sus pruebas funcionales. El harness se excluye del paquete productivo.
