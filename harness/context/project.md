# Proyecto: hechos, capacidades y alcance

## Confirmado
- Organización rvSoftware; uso interno de Ronnie y su equipo de desarrollo.
- Base Kanboard; objetivos: proyectos, tareas, horas trabajadas/facturables y bolsas.
- Problemas reportados: UI compleja y fallo de Dashboard en otra instalación.
- Hostinger permite sitios PHP/HTML; configuración técnica y destino no verificados.
- Activos oficiales entregados; inventario en [marca](ui-brand.md).
- Autorización vigente: crear y validar este harness autónomamente. No implementar
  funcionalidades ni desplegar en esta entrega. Registro en feedback DEC-001.
- No se confirmó portal de clientes, facturación fiscal, impuestos, pagos o
  integración contable. Permanecen fuera del alcance inicial.

## Base y herramientas comprobadas
Ruta `C:\Proyectos\kanboard`, rama `main`, origin `https://github.com/ronald-v/kanboard.git`.
HEAD `60d2caf29edfa58e313b17cadaab76b6d4307742`, coincide con main oficial al inspeccionar.
Dos commits después de v1.2.57; sin tag en HEAD. Git completo, sin cambios iniciales.
Licencia [MIT](../../LICENSE). No había AGENTS.md ni harness. Plugins vacíos salvo
.gitignore; no configuración local ni base de datos encontrada en ubicaciones normales.

| Capacidad del ejecutor | Disponibilidad comprobada | Límite |
| --- | --- | --- |
| PowerShell / Git | Disponible | No implica autorización de remotos o producción |
| PHP CLI / Composer | 8.3.27 / 2.8.12 | Sin GD, sin drivers PDO, sin PHPUnit instalado |
| Node/npm/Python | Detectados en PATH en diagnóstico | No necesarios para el producto ni el harness |
| Docker/make/JSHint/PHP-CS-Fixer | No encontrados en PATH | No declarar ejecutados |
| Navegación web | Documentación oficial consultada | No acceso demostrado a Hostinger o instalación afectada |
| Roles especialistas | Prompts de este harness | Un agente secuencial; no revisión independiente ejecutada |
| Automatización | Verificador PowerShell local | No CI del harness ni orquestación configurada |

## Matriz de reutilización
Nativo significa inspeccionado en código, no validado funcionalmente en este equipo.

| Necesidad | Soporte/evidencia | Limitación | Estrategia | Validación |
| --- | --- | --- | --- | --- |
| Login y sesiones | app/ServiceProvider/AuthenticationProvider.php | Sin política comercial | Nativo | TC-F-012-01, TC-F-008-02 |
| Roles y aislamiento | app/Middleware/ProjectAuthorizationMiddleware.php; app/Core/Security/Role.php | No es multi-tenancy comercial | Nativo + ACL por cliente/bolsa | TC-F-008-02 |
| Proyectos/tablero/tareas | app/Model/ProjectModel.php; app/Model/TaskModel.php | Flujos extensos | Reutilizar y simplificar | TC-F-006-01 |
| Dashboard | app/Controller/DashboardController.php | Fallo externo sin reproducir | Diagnóstico; corrección mínima basada en evidencia | TC-F-002-01 |
| Estimación/real manual | app/Model/SubtaskModel.php; app/Analytic/EstimatedTimeComparisonAnalytic.php | Totales no equivalen a sesiones | Conservar agregados | TC-F-007-01 |
| Sesiones/temporizador | app/Model/SubtaskTimeTrackingModel.php | Concurrencia no validada | Adaptar servicio nativo | TC-F-007-02, TC-F-007-03 |
| Mi tiempo | app/Controller/UserViewController.php | Sin clasificación/aprobación comercial | Reutilizar consultas y ampliar | TC-F-008-01 |
| Reportes/exportación | app/Export/SubtaskExport.php; app/Export/TaskExport.php | Filtro nativo de subtareas por creación de tarea | Reusar estructura; reporte por sesión aparte | TC-F-011-01 |
| Monedas | app/Model/CurrencyModel.php | No resuelve tarifas históricas ni dinero preciso | No asumir facturación nativa | TC-F-011-02 |
| Facturable y aprobación | Búsqueda en modelos/controladores/esquemas sin dominio equivalente | Reglas pendientes | Plugin propio | TC-F-008-01 |
| Clientes/bolsas/consumos | Sin dominio equivalente localizado en código inspeccionado | Reglas pendientes | Plugin propio | TC-F-009-01, TC-F-010-01 |
| Marca/UI | app/Core/Template.php; app/Template/layout.php; app/Template/config/application.php | CSS no resuelve navegación ni seguridad | Configuración, hooks y overrides | TC-F-004-01 |

## Pendientes y fuentes
URL/versión/rol/error del Dashboard; entorno Hostinger; reglas comerciales; versión
propia; diseño UI aprobado. Ninguno impide mantener el harness. Evidencia local:
[línea base](../evidence/2026-11-11-baseline.md). Fuentes externas consultadas durante
el diagnóstico: repositorio oficial y documentación enlazada en los contextos.
Documentación externa puede corresponder a otra revisión: manda la base local.
