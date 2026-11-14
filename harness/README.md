# Harness de desarrollo y mantenimiento

Entrada: [AGENTS.md](../AGENTS.md). Estado único: [feature-list.json](feature-list.json).
Continuidad: [checkpoint](current-task.md). La autorización posterior
[DEC-026](feedback/2026-11-14-DEC-026-projecta.md) amplió el encargo a implementar
Projecta (F-013..F-018), con pruebas aisladas y sin despliegue. El historial
F-001..F-012 se conserva; F-012 sigue blocked por verificación del hosting.
La revisión anterior del harness permanece como evidencia histórica.

DEC-029 autoriza F-019: optimización de navegación/CRUD por flujo, con reducción
objetivo >=50 % en mediana/p95, pruebas aisladas y contraste por uso normal. No
autoriza carga o CRUD de prueba en la MariaDB del usuario ni despliegue. Protocolo
y límites en [rendimiento](context/performance.md).

## Arranque
Desde `C:\Proyectos\kanboard`:

```powershell
git status --short
Get-Content AGENTS.md -Encoding UTF8
Get-Content harness/feature-list.json -Raw -Encoding UTF8
Get-Content harness/current-task.md -Encoding UTF8
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
```

Leer decisiones y evidencia pertinentes. La existencia de archivos no acredita ejecución
ni resuelve un bloqueo. No arrancar PHP para inspeccionar: puede migrar núcleo y plugins.
No volcar `data/config.php`; contiene configuración privada. Los perfiles aislados y
sus precondiciones están en [testing](context/testing.md). El perfil local que usa
MariaDB del usuario nunca es destino de suites destructivas.

## Trabajo autorizado
1. Distinguir inspección, documentación de requisitos, implementación y despliegue.
   Si el usuario comparte ideas sin pedir agregarlas, no crear features, casos ni tareas.
   Si solicita documentarlas, registrar alcance y criterios sin iniciar su implementación.
2. Elegir una feature autorizada con dependencias validated. Verificar la evidencia.
3. Consultar `changes` de [prompt-map.json](prompt-map.json) y sus `specialties`
   por superficie afectada. Los especialistas son prompts aplicados por el ejecutor;
   no son agentes instalados. Declarar consulta o delegación real.
4. Definir cambio mínimo, criterios, riesgos, pruebas y recuperación. Usar la
   [plantilla](templates/change.md) si faltan esos datos, sin duplicar el estado.
5. Ejecutar y observar pruebas pertinentes; revisar diff y seguridad. Guardar comandos,
   destino sintético, resultado, limitaciones y fecha ISO con zona en evidence/.
6. El leader actualiza estado y checkpoint. done queda pendiente de verificación;
   validated exige criterios comprobados. Otra feature requiere cierre, bloqueo real
   o suspensión aprobada de la anterior done. Máximo tres intentos sin progreso.
7. Entregar resultado, archivos, comprobaciones, bloqueos y siguiente acción.

## Especialidades y carga mínima
| Superficie | Prompts | Contexto |
| --- | --- | --- |
| PHP y refactorización | dev, architect | engineering, architecture |
| MySQL/MariaDB | db | database, performance |
| API JSON-RPC | api, security, testing | api-mcp, security |
| MCP y conexión de clientes | mcp, api, security, testing | api-mcp |
| Seguridad y pentesting controlado | security, pentesting | security, pentesting |
| QA, regresión y aceptación | testing, validator | testing |
| UI/UX y accesibilidad | ui-ux | ui-brand |
| Rendimiento | performance, db, testing | performance |
| Entrega/upstream | release, architect | deployment, upstream |

Rutas exactas y disparadores están en prompt-map.json. Una especialidad disponible
no es una feature ni autoriza pruebas externas. Las herramientas ya existentes se
describen con sus efectos en testing; no se añaden servicios al producto.

## Validación del harness
```powershell
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1
```
El primero verifica estructura, estados, DAG, referencias, contratos de prompts y
enrutamiento de especialidades. El segundo prueba aceptaciones/rechazos con fixtures
temporales y recuperación de un candidato inválido. Ninguno inicia Kanboard, prueba
la API del producto, certifica seguridad o demuestra usabilidad.

Los resultados históricos de producto están en evidence/ y cases.json; no se
declaran repetidos en esta revisión. Los escenarios originales mantienen sus resultados
fechados; no son un segundo estado de las features.

## Mantenimiento y recuperación
[Política local](context/harness-maintenance.md): candidato separado, versión previa,
criterios fijos, comparación, comprobación de concurrencia, activación y reversión.
La revisión no exige Drive para operar el producto. Feedback conserva decisiones;
current-task resume el punto de reanudación. No hay servicio autónomo en segundo plano,
CI nueva ni memoria entre sesiones comprobada por escribir estos archivos.

Revisión anterior del harness: [evidencia](evidence/2026-11-14-harness-upgrade.md).
Entrega Projecta: [integración y límites](evidence/2026-11-14-projecta-integracion.md).
