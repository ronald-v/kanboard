# Seguridad y permisos

## Contenci?n y revisi?n F-020

F-020 a?ade una frontera HTTP independiente del bootstrap para web, JSON-RPC,
MCP y OAuth: 500/503 gen?ricos, referencia y diagn?stico privado depurado. Mantener
el registro fuera del directorio p?blico y DEBUG desactivado en producci?n.
Los flujos binarios liberan el b?fer de recuperaci?n para no acumular adjuntos.
Las cabeceras de proxy requieren redes confiables y los adjuntos son private/no-store.
No modificar estas garant?as para depurar un fallo contra datos reales.
[Revisi?n, pruebas y pendientes](../evidence/2026-11-14-f020-security.md).
El diagn?stico hist?rico siguiente se conserva; no reemplaza la revisi?n local F-020.

## Política vigente Projecta
F-014 exige contraseña y TOTP para todos, recuperación de un solo uso, límites
atómicos y sesiones acotadas. F-017/F-018 usan credenciales individuales/OAuth
revocables con scopes y permisos por objeto. [Operación y recuperación](../../plugins/RvSoftware/PROJECTA.md).
[Pruebas controladas y límites](../evidence/2026-11-14-projecta-integracion.md)
reemplazan el diagnóstico inicial de ausencia de pruebas dinámicas; no constituyen
una auditoría exhaustiva ni certificación de seguridad del hosting.

## Base inspeccionada
AuthenticationProvider configura autenticación y ACL de aplicación/proyecto/API.
Role define app-admin/app-manager/app-user y project-manager/member/viewer.
AuthenticationMiddleware valida sesión; ApplicationAuthorizationMiddleware y
ProjectAuthorizationMiddleware verifican permisos. El proyecto real de una tarea
prevalece sobre un project_id enviado por URL. BaseController::getUser limita
consulta de otros usuarios a administradores. No se ejecutó auditoría dinámica.

SubtaskStatusController usa comprobación CSRF para cambio de estado y timer;
API tiene autorización propia en app/Api/Authorization. Usar mecanismos existentes
según entrada; no asumir que todo método nuevo queda protegido automáticamente.
Response conserva CSP; plantillas deben escapar contenido. No reemplazar autenticación.

## Capacidades nativas para evaluar, no reemplazar por suposición
Existen app/Auth/TotpAuth.php y tests/units/Auth/TotpAuthTest.php; config.default.php
declara TOTP_ISSUER y umbrales BRUTEFORCE_CAPTCHA/LOCKDOWN/DURATION. Esto acredita
presencia de implementación, no una política activa ni protección suficiente en
el entorno. Antes de extender autenticación revisar cobertura, sesiones, recuperación,
alcance en API y pruebas reales. Una base abierta permite inspección; no demuestra
por sí sola que el login sea vulnerable. Guía dinámica en pentesting.md.

## Revisión por feature
- Autorización por objeto real y acción, incluido acceso directo, API y exportación.
- Reglas separadas para horas ajenas, tarifas, bolsas compartidas, correcciones y cierre.
- Validación de duración/fechas/IDs y pertenencia; SQL parametrizado; escape XSS.
- CSRF y métodos de modificación; no degradar CSP para aplicar branding.
- Archivos subidos: autorización, tipo/tamaño y descarga controlada.
- Exportación: datos mínimos, filtros por permisos y neutralización de fórmulas CSV
  cuando corresponda. Verificar sin exponer información comercial en errores.
- Auditoría y preservación de historia, incluida eliminación de usuario/subtarea.

## Datos y HTTP
Usar sintéticos; nunca copiar producción sin autorización específica. No guardar
credenciales, tokens, cookies, dumps ni logs completos en evidencia. Revisar solo
fragmentos depurados. No habilitar DEBUG público para reproducir Dashboard.
data/.htaccess y data/web.config existen; verificar servidor efectivo y denegación
HTTP de configuración, base, backups, Git y harness. .gitignore no protege HTTP.
Preservar acceso legítimo a adjuntos mediante controladores. No desplegar el checkout.

Hallazgo estático no equivale a vulnerabilidad reproducida: registrar alcance,
precondiciones, evidencia y mitigación proporcional. Pruebas en entorno autorizado.
