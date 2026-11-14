# Revisión y mejora del harness

Fecha: 2026-11-14T10:05:36-05:00 (America/Bogota). Alcance: harness e instrucciones; sin producto,
migraciones, conexión DB, pruebas de aplicación o despliegue.
Base Git: 6ae82f4563382811b7eb7a0193a3d0d87752c521, rama feat/012.
Decisión: [DEC-025](../feedback/2026-11-14-DEC-025-mejora-harness.md).

## Diagnóstico por consulta del mismo ejecutor
| Gravedad | Evidencia | Cambio |
| --- | --- | --- |
| Alta | Sin contratos específicos de API/MCP, pentesting ni medición de latencia | Guías, roles y enrutamiento verificable |
| Alta | README y contextos dicen que faltan plugin/entorno o exclusiones ya implementados | Separación explícita de diagnóstico histórico y situación actual |
| Media | ui-ux exige aprobación adicional pese a DEC-015 | Respeta autonomía vigente dentro de su alcance |
| Media | Sin ciclo local explícito de candidato/comparación/recuperación | Política, copia previa, hash y fixture de rechazo/restauración |

Resolución entre arquitectura y QA: API/MCP y rendimiento requieren guías completas,
pero agregarlas como features violaría el alcance. Se conservan changes y estado;
specialties añade solo capacidades sin status/features/casos. El validador rechaza
mezclar estado en ese catálogo. Seguridad requiere verificar permisos por objeto
y el destino de pruebas; se documentan controles sin lanzar ataques ni tocar DB.
No se atribuye independencia a estas consultas.

## Núcleo del complemento
Origen autenticado y pertenencia de carpetas/archivos comprobados en Drive.
Release fijada 1.0.1 (bootstrap local 1.0.2). Índice SHA256
`4f71c0e7029f1ec0aef2095d4ac09c89d8563cd523249dfc96b0637550121ed2`;
ZIP `eede5549e45e623a603b948082cd289b50d422b978f8db90a83d645dc50abf19`.
102 archivos verificados y extraídos sin ejecutar contenido remoto. Leídos núcleo,
política, contratos, persistencia, catálogo, reglas y procedimientos pertinentes.
El loader original falló en Windows porque comparaba rutas con separadores nativos
contra rutas POSIX del índice. Copia temporal local con única adaptación as_posix();
se conservaron hashes y todos los controles. Un bloqueo de permisos de extracción
se resolvió con escalación; no se modificó ni promovió el complemento/origen remoto.

## Base, candidato y recuperación
Base y candidato separados en `.runtime/harness-review-20261114/before` y
`candidate` (solo material local de revisión, no entregable productivo).
Se preserva el formato útil existente en vez de duplicar cada rol en cuatro archivos;
memoria se mantiene en feedback y checkpoint. No hay skills/agentes instalados nuevos.

Invariantes SHA256 al inicio:
- feature-list.json: `5d179a8e1cdec79be67bfafaf60e9e711b16d8380b4812b6dcd26eeccee85bd2`.
- cases.json: `208c3ffec08877d751f0338fe8827b3ec55314e350ee4eb31af4d804ba734738`.
- favicon.png ajeno: `ac4cb175082275c1562a45b08c961245a67c46299d53cede09359aa9e50940e6`.

## Resultados de esta sesión
Validación estática base: exit 0, 12 features/23 casos/9 prompts/10 escenarios.
Baseline: powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1
retornó exit 0: 18 fixtures, 0 fallos.

Candidato: powershell -NoProfile -File
.runtime/harness-review-20261114/candidate/harness/scripts/Test-Harness.ps1
-RootPath .runtime/harness-review-20261114/candidate retornó exit 0:
12 features, 23 casos, 13 prompts, 10 escenarios.
Las referencias al producto fueron marcadores inertes verificados contra rutas
existentes; no se copió ni ejecutó código del producto en el candidato.

Candidato: powershell -NoProfile -File
.runtime/harness-review-20261114/candidate/harness/scripts/Test-HarnessScenarios.ps1
-RootPath .runtime/harness-review-20261114/candidate retornó exit 0:
26 fixtures, 0 fallos. Conserva los 18 anteriores; añade siete casos de catálogo
(especialidad ausente, rol desconocido, duplicado, contexto roto, estado indebido,
disparador vacío y prompt no registrado) y rechazo/restauración de un candidato.
La recuperación restauró bytes y SHA256 originales y volvió a pasar el validador.

Comparación: 9 -> 13 prompts y 18 -> 26 fixtures; no se retiró ningún control
anterior. Esto demuestra los controles estáticos nuevos, no productividad futura.
Integración en el árbol real: 32 archivos del harness/instrucciones promovidos tras comprobar hashes base y lectura posterior. Test-Harness.ps1 retornó exit 0 con rutas reales: 12 features, 23 casos, 13 prompts, 10 escenarios. git diff --check retornó exit 0. SHA256 de feature-list, cases y favicon coincide con inicio. No cambios de producto, configuración privada, DB, remotos ni despliegue.

## Límites y continuidad
Las pruebas del harness son estáticas/fixtures. No acreditan mejoras de velocidad,
MFA configurado, API funcional, MCP instalado ni QA/pentesting del producto.
Recuperación entre sesiones y comportamiento de agentes futuros no se han probado.
F-001..F-011 y bloqueo F-012 conservan su evidencia histórica; no se repitieron sus
suites. Los diez requisitos pendientes no se incorporan a features/casos/tareas.
